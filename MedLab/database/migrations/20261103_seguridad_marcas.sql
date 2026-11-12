-- Migración para instalaciones existentes. Ejecutar como administrador de MySQL.
-- Haz una copia de seguridad y detén las escrituras de la aplicación antes.
-- No vuelvas a importar MedLabDB.sql sobre una base con datos.
-- Es repetible: no elimina datos ni vuelve a conceder permisos de administrador.
-- DDL en MySQL hace COMMIT implícito: una transacción no revierte esta migración.

USE MedLabDB;

CREATE TABLE IF NOT EXISTS marcas (
    id_marca INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(150),
    activo BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS intentos_login (
    clave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    intentos INT UNSIGNED NOT NULL DEFAULT 0,
    ventana_inicio DATETIME NOT NULL,
    bloqueado_hasta DATETIME NULL,
    actualizado_en DATETIME NOT NULL,
    INDEX idx_intentos_login_actualizado (actualizado_en)
) ENGINE = InnoDB;

-- Rutina temporal, exclusiva de esta migración; se elimina al finalizar.
DROP PROCEDURE IF EXISTS medlab_migrar_20261103;
DELIMITER $$
CREATE PROCEDURE medlab_migrar_20261103()
BEGIN
    DECLARE v_bloqueado_nuevo BOOLEAN DEFAULT FALSE;

    -- Compatibilidad con el contador antiguo: inicio_ventana guardaba epoch.
    -- Se conserva el contador y su columna original. Las fechas nuevas son UTC.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'intentos_login'
          AND COLUMN_NAME = 'ventana_inicio'
    ) THEN
        ALTER TABLE intentos_login ADD COLUMN ventana_inicio DATETIME NULL;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'intentos_login'
          AND COLUMN_NAME = 'bloqueado_hasta'
    ) THEN
        ALTER TABLE intentos_login ADD COLUMN bloqueado_hasta DATETIME NULL;
        SET v_bloqueado_nuevo = TRUE;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'intentos_login'
          AND COLUMN_NAME = 'actualizado_en'
    ) THEN
        ALTER TABLE intentos_login ADD COLUMN actualizado_en DATETIME NULL;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'intentos_login'
          AND COLUMN_NAME = 'inicio_ventana'
    ) THEN
        UPDATE intentos_login
            SET ventana_inicio = CASE
                -- Evita overflow DATETIME y timestamps futuros corruptos.
                WHEN inicio_ventana BETWEEN 0 AND LEAST(253402299899, UNIX_TIMESTAMP() + 300)
                THEN DATE_ADD('1970-01-01 00:00:00', INTERVAL inicio_ventana SECOND)
                ELSE UTC_TIMESTAMP()
            END
            WHERE ventana_inicio IS NULL;
        -- Los INSERT del código nuevo omiten la columna legada.
        ALTER TABLE intentos_login
            MODIFY COLUMN inicio_ventana BIGINT UNSIGNED NOT NULL DEFAULT 0;
    ELSE
        UPDATE intentos_login SET ventana_inicio = UTC_TIMESTAMP()
            WHERE ventana_inicio IS NULL;
    END IF;

    UPDATE intentos_login SET actualizado_en = ventana_inicio
        WHERE actualizado_en IS NULL;
    IF v_bloqueado_nuevo THEN
        UPDATE intentos_login
            SET bloqueado_hasta = DATE_ADD(ventana_inicio, INTERVAL 15 MINUTE)
            WHERE intentos >= 5
              AND DATE_ADD(ventana_inicio, INTERVAL 15 MINUTE) > UTC_TIMESTAMP();
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'intentos_login'
          AND COLUMN_NAME = 'ventana_inicio' AND IS_NULLABLE = 'YES'
    ) THEN
        ALTER TABLE intentos_login MODIFY COLUMN ventana_inicio DATETIME NOT NULL;
    END IF;
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'intentos_login'
          AND COLUMN_NAME = 'actualizado_en' AND IS_NULLABLE = 'YES'
    ) THEN
        ALTER TABLE intentos_login MODIFY COLUMN actualizado_en DATETIME NOT NULL;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'intentos_login'
          AND INDEX_NAME = 'idx_intentos_login_actualizado'
    ) THEN
        ALTER TABLE intentos_login
            ADD INDEX idx_intentos_login_actualizado (actualizado_en);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles'
          AND COLUMN_NAME = 'es_administrador'
    ) THEN
        ALTER TABLE roles
            ADD COLUMN es_administrador BOOLEAN NOT NULL DEFAULT FALSE;
        -- Compatibilidad inicial: solo el rol preexistente con este nombre.
        -- Una segunda ejecución no modifica esta decisión de permisos.
        UPDATE roles SET es_administrador = TRUE WHERE nombre = 'Administrador';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
          AND COLUMN_NAME = 'version_sesion'
    ) THEN
        ALTER TABLE usuarios
            ADD COLUMN version_sesion INT UNSIGNED NOT NULL DEFAULT 1;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos'
          AND COLUMN_NAME = 'id_marca'
    ) THEN
        ALTER TABLE equipos ADD COLUMN id_marca INT NULL;
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos'
          AND COLUMN_NAME = 'marca'
    ) THEN
        INSERT INTO marcas (nombre)
            SELECT DISTINCT TRIM(marca) FROM equipos
            WHERE marca IS NOT NULL AND TRIM(marca) <> ''
            ON DUPLICATE KEY UPDATE id_marca = marcas.id_marca;

        UPDATE equipos AS e
            INNER JOIN marcas AS m ON m.nombre = TRIM(e.marca)
            SET e.id_marca = m.id_marca
            WHERE e.id_marca IS NULL AND e.marca IS NOT NULL
              AND TRIM(e.marca) <> '';
        -- equipos.marca se conserva como respaldo del texto original.
        -- Los futuros módulos deben escribir únicamente equipos.id_marca.
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'equipos'
          AND CONSTRAINT_NAME = 'fk_equipo_marca'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE equipos
            ADD CONSTRAINT fk_equipo_marca FOREIGN KEY (id_marca)
            REFERENCES marcas(id_marca) ON DELETE RESTRICT ON UPDATE RESTRICT;
    END IF;
END$$
DELIMITER ;
CALL medlab_migrar_20261103();
DROP PROCEDURE medlab_migrar_20261103;

-- Comprobaciones de estructura: deben aparecer ambas columnas y ambas tablas.
SHOW COLUMNS FROM roles LIKE 'es_administrador';
SHOW COLUMNS FROM usuarios LIKE 'version_sesion';
SHOW CREATE TABLE marcas;
SHOW CREATE TABLE intentos_login;
SHOW CREATE TABLE equipos;

-- Debe devolver 0; equipos sin marca pueden mantener id_marca NULL.
SELECT COUNT(*) AS marcas_huerfanas FROM equipos AS e
    LEFT JOIN marcas AS m ON m.id_marca = e.id_marca
    WHERE e.id_marca IS NOT NULL AND m.id_marca IS NULL;

-- Revisar qué roles tienen el privilegio administrativo antes de abrir el sitio.
SELECT id_rol, nombre, es_administrador FROM roles ORDER BY id_rol;

-- Limpieza periódica con tarea administrativa (p. ej. diaria). No ejecutar
-- si se cambia la ventana de bloqueo sin ajustar también este margen:
-- DELETE FROM intentos_login
-- WHERE actualizado_en < NOW() - INTERVAL 1 DAY
--   AND (bloqueado_hasta IS NULL OR bloqueado_hasta < NOW());
