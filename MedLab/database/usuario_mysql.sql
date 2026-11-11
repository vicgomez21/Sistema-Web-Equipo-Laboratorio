-- Ejecutar como administrador de MySQL, DESPUÉS del esquema y las migraciones.
-- Sustituye el marcador por una contraseña NUEVA, aleatoria y exclusiva.
-- Guarda la misma contraseña en api/core/.env (DATABASE_PSSW).
-- No reutilices la contraseña que estuvo incluida en versiones anteriores.
-- El usuario de la aplicación no ejecuta migraciones ni administra permisos.
-- usr_medlab es exclusivo de este proyecto. No uses aquí una cuenta compartida
-- con otras aplicaciones: sus permisos se revocan antes de conceder los nuevos.
-- Este archivo modifica también una cuenta existente: rota la clave y elimina
-- todos sus privilegios anteriores antes de conceder exclusivamente los necesarios.

USE MedLabDB;
SET @medlab_clave = 'REEMPLAZAR_POR_UN_SECRETO_ALEATORIO_NUEVO';

-- Si se importa sin editar, se detiene sin crear una contraseña conocida.
-- SQL SECURITY INVOKER exige permisos del DBA incluso si una ejecución
-- interrumpida deja esta rutina temporal pendiente de eliminación.
DROP PROCEDURE IF EXISTS medlab_configurar_usuario;
DELIMITER $$
CREATE PROCEDURE medlab_configurar_usuario()
SQL SECURITY INVOKER
BEGIN
    IF @medlab_clave IS NULL OR CHAR_LENGTH(@medlab_clave) < 20
       OR @medlab_clave LIKE 'REEMPLAZAR_%' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Sustituye el marcador por una clave aleatoria nueva de al menos 20 caracteres';
    END IF;

    SET @medlab_sql = CONCAT(
        'CREATE USER IF NOT EXISTS ''usr_medlab''@''localhost'' IDENTIFIED BY ',
        QUOTE(@medlab_clave));
    PREPARE medlab_stmt FROM @medlab_sql;
    EXECUTE medlab_stmt;
    DEALLOCATE PREPARE medlab_stmt;

    SET @medlab_sql = CONCAT(
        'ALTER USER ''usr_medlab''@''localhost'' IDENTIFIED BY ', QUOTE(@medlab_clave));
    PREPARE medlab_stmt FROM @medlab_sql;
    EXECUTE medlab_stmt;
    DEALLOCATE PREPARE medlab_stmt;

    REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'usr_medlab'@'localhost';
    GRANT SELECT, INSERT, UPDATE, DELETE ON MedLabDB.*
        TO 'usr_medlab'@'localhost';
END$$
DELIMITER ;
CALL medlab_configurar_usuario();
DROP PROCEDURE medlab_configurar_usuario;
SET @medlab_clave = NULL, @medlab_sql = NULL;

-- Debe listar SELECT, INSERT, UPDATE, DELETE con IS_GRANTABLE=NO.
-- Evita SHOW GRANTS, que en algunas versiones revela el hash de autenticación.
SELECT PRIVILEGE_TYPE, IS_GRANTABLE FROM information_schema.SCHEMA_PRIVILEGES
    WHERE GRANTEE = CONCAT(QUOTE('usr_medlab'), '@', QUOTE('localhost'))
      AND TABLE_SCHEMA = DATABASE();
-- Como privilegio global únicamente puede aparecer USAGE (o ninguna fila).
SELECT PRIVILEGE_TYPE, IS_GRANTABLE FROM information_schema.USER_PRIVILEGES
    WHERE GRANTEE = CONCAT(QUOTE('usr_medlab'), '@', QUOTE('localhost'));
