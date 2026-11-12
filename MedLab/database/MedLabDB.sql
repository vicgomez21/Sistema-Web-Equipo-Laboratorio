CREATE DATABASE IF NOT EXISTS MedLabDB
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE MedLabDB;

CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(150)
) ENGINE = InnoDB;

CREATE TABLE categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE,
    descripcion VARCHAR(150),
    activo BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE = InnoDB;

CREATE TABLE estados_equipo (
    id_estado INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(150)
) ENGINE = InnoDB;

CREATE TABLE condiciones_equipo (
    id_condicion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(150)
) ENGINE = InnoDB;

CREATE TABLE estados_prestamo (
    codigo VARCHAR(24) NOT NULL UNIQUE,
    id_estado_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(150)
) ENGINE = InnoDB;

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    codigo_usuario VARCHAR(30) NOT NULL UNIQUE,
    nombres VARCHAR(60) NOT NULL,
    apellidos VARCHAR(60) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(20),
    id_rol INT NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuario_rol
        FOREIGN KEY (id_rol)
        REFERENCES roles(id_rol)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE equipos (
    id_equipo INT AUTO_INCREMENT PRIMARY KEY,
    codigo_inventario VARCHAR(30) NOT NULL UNIQUE,
    cantidad_total INT NOT NULL DEFAULT 1,
    cantidad_fuera_servicio INT NOT NULL DEFAULT 0,
    CONSTRAINT chk_equipo_cantidades CHECK (cantidad_total >= 0 AND cantidad_fuera_servicio >= 0 AND cantidad_fuera_servicio <= cantidad_total),
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    marca VARCHAR(50),
    modelo VARCHAR(50),
    numero_serie VARCHAR(80) UNIQUE,
    id_categoria INT NOT NULL,
    id_estado INT NOT NULL,
    id_condicion INT NOT NULL,
    ubicacion VARCHAR(100),
    fecha_adquisicion DATE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_equipo_categoria
        FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_equipo_estado
        FOREIGN KEY (id_estado)
        REFERENCES estados_equipo(id_estado)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_equipo_condicion
        FOREIGN KEY (id_condicion)
        REFERENCES condiciones_equipo(id_condicion)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE prestamos (
    id_prestamo INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_encargado_entrega INT NULL,
    fecha_prestamo DATETIME NULL DEFAULT NULL,
    fecha_limite DATETIME NULL,
    id_estado_prestamo INT NOT NULL,
    observaciones TEXT,
    fecha_solicitud DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_devolucion_solicitada DATE NULL,
    id_encargado_aprobacion INT NULL,
    fecha_aprobacion DATETIME NULL,
    fecha_decision DATETIME NULL,
    motivo_rechazo VARCHAR(500) NULL,
    version_reglas TINYINT NOT NULL DEFAULT 2,
    fecha_reserva_hasta DATETIME GENERATED ALWAYS AS (DATE_ADD(fecha_aprobacion, INTERVAL 14 DAY)) STORED,
    CONSTRAINT fk_prestamo_aprobacion FOREIGN KEY (id_encargado_aprobacion) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_prestamo_entrega CHECK (version_reglas = 1 OR ((fecha_prestamo IS NULL AND id_encargado_entrega IS NULL AND fecha_limite IS NULL) OR (fecha_prestamo IS NOT NULL AND id_encargado_entrega IS NOT NULL AND fecha_limite IS NOT NULL AND fecha_aprobacion IS NOT NULL AND fecha_prestamo >= fecha_aprobacion AND fecha_prestamo < fecha_reserva_hasta))),
    CONSTRAINT chk_prestamo_mes CHECK (version_reglas = 1 OR fecha_prestamo IS NULL OR (fecha_limite >= fecha_prestamo AND fecha_limite <= DATE_ADD(fecha_prestamo, INTERVAL 1 MONTH))),
    CONSTRAINT chk_prestamo_aprobacion CHECK (version_reglas = 1 OR ((fecha_aprobacion IS NULL AND id_encargado_aprobacion IS NULL) OR (fecha_aprobacion IS NOT NULL AND id_encargado_aprobacion IS NOT NULL AND fecha_aprobacion >= fecha_solicitud))),
    CONSTRAINT chk_prestamo_version CHECK (version_reglas IN (1,2)),
    CONSTRAINT chk_prestamo_solicitud CHECK (version_reglas=1 OR fecha_solicitud IS NOT NULL),
    INDEX idx_reserva_vencimiento (fecha_reserva_hasta),

    CONSTRAINT fk_prestamo_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_prestamo_encargado_entrega
        FOREIGN KEY (id_encargado_entrega)
        REFERENCES usuarios(id_usuario)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_prestamo_estado
        FOREIGN KEY (id_estado_prestamo)
        REFERENCES estados_prestamo(id_estado_prestamo)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT chk_prestamo_fechas
        CHECK (fecha_limite >= fecha_prestamo),

    INDEX idx_prestamo_fecha_limite (fecha_limite)
) ENGINE = InnoDB;

CREATE TABLE detalle_prestamo (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT NOT NULL,
    id_equipo INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    cantidad_devuelta INT NOT NULL DEFAULT 0,
    CONSTRAINT chk_detalle_cantidades CHECK (cantidad > 0 AND cantidad_devuelta >= 0 AND cantidad_devuelta <= cantidad),
    CONSTRAINT chk_detalle_cierre CHECK ((fecha_devolucion IS NULL AND cantidad_devuelta < cantidad) OR (fecha_devolucion IS NOT NULL AND cantidad_devuelta = cantidad)),
    INDEX idx_detalle_equipo (id_equipo),
    id_condicion_entrega INT NULL,
    observaciones_entrega TEXT,
    fecha_devolucion DATETIME NULL,
    id_encargado_recepcion INT NULL,
    id_condicion_recepcion INT NULL,
    observaciones_devolucion TEXT,

    equipo_pendiente INT GENERATED ALWAYS AS (
        CASE
            WHEN fecha_devolucion IS NULL THEN id_equipo
            ELSE NULL
        END
    ) STORED,

    CONSTRAINT fk_detalle_prestamo
        FOREIGN KEY (id_prestamo)
        REFERENCES prestamos(id_prestamo)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_detalle_equipo
        FOREIGN KEY (id_equipo)
        REFERENCES equipos(id_equipo)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_detalle_condicion_entrega
        FOREIGN KEY (id_condicion_entrega)
        REFERENCES condiciones_equipo(id_condicion)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_detalle_encargado_recepcion
        FOREIGN KEY (id_encargado_recepcion)
        REFERENCES usuarios(id_usuario)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_detalle_condicion_recepcion
        FOREIGN KEY (id_condicion_recepcion)
        REFERENCES condiciones_equipo(id_condicion)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT uq_prestamo_equipo
        UNIQUE (id_prestamo, id_equipo),

    CONSTRAINT chk_devolucion_completa
        CHECK (
            (
                fecha_devolucion IS NULL
                AND id_encargado_recepcion IS NULL
                AND id_condicion_recepcion IS NULL
                AND observaciones_devolucion IS NULL
            )
            OR
            (
                fecha_devolucion IS NOT NULL
                AND id_encargado_recepcion IS NOT NULL
                AND id_condicion_recepcion IS NOT NULL
            )
        )
) ENGINE = InnoDB;

INSERT INTO estados_prestamo(codigo,nombre) VALUES ('PENDIENTE','Pendiente');
INSERT INTO estados_prestamo(codigo,nombre) VALUES ('APROBADO','Aprobado');
INSERT INTO estados_prestamo(codigo,nombre) VALUES ('RECHAZADO','Rechazado');
INSERT INTO estados_prestamo(codigo,nombre) VALUES ('ENTREGADO','Entregado');
INSERT INTO estados_prestamo(codigo,nombre) VALUES ('DEVUELTO','Devuelto');
INSERT INTO estados_prestamo(codigo,nombre) VALUES ('RESERVA_VENCIDA','Reserva vencida');
INSERT INTO estados_prestamo(codigo,nombre) VALUES ('CANCELADO','Cancelado');

CREATE TABLE notificaciones (
 id_notificacion BIGINT AUTO_INCREMENT PRIMARY KEY,
 id_usuario INT NOT NULL,
 id_prestamo INT NOT NULL,
 evento VARCHAR(40) NOT NULL,
 mensaje VARCHAR(500) NOT NULL,
 fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 fecha_lectura DATETIME NULL,
 CONSTRAINT fk_notificacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_notificacion_prestamo FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE RESTRICT ON UPDATE RESTRICT,
 UNIQUE KEY uq_notificacion_evento (id_usuario, id_prestamo, evento),
 INDEX idx_notificacion_pendiente (id_usuario, fecha_lectura)
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW disponibilidad_equipos AS
SELECT e.id_equipo, e.cantidad_total, e.cantidad_fuera_servicio,
 COALESCE(SUM(CASE WHEN s.codigo='APROBADO' AND p.fecha_prestamo IS NULL AND p.fecha_reserva_hasta > CURRENT_TIMESTAMP THEN d.cantidad ELSE 0 END),0) AS cantidad_reservada,
 COALESCE(SUM(CASE WHEN p.fecha_prestamo IS NOT NULL AND d.fecha_devolucion IS NULL THEN d.cantidad-d.cantidad_devuelta ELSE 0 END),0) AS cantidad_prestada,
 CASE WHEN e.activo=0 THEN 0 ELSE e.cantidad_total-e.cantidad_fuera_servicio
  -COALESCE(SUM(CASE WHEN s.codigo='APROBADO' AND p.fecha_prestamo IS NULL AND p.fecha_reserva_hasta > CURRENT_TIMESTAMP THEN d.cantidad ELSE 0 END),0)
  -COALESCE(SUM(CASE WHEN p.fecha_prestamo IS NOT NULL AND d.fecha_devolucion IS NULL THEN d.cantidad-d.cantidad_devuelta ELSE 0 END),0) END AS cantidad_disponible
FROM equipos e LEFT JOIN detalle_prestamo d ON d.id_equipo=e.id_equipo
LEFT JOIN prestamos p ON p.id_prestamo=d.id_prestamo
LEFT JOIN estados_prestamo s ON s.id_estado_prestamo=p.id_estado_prestamo
GROUP BY e.id_equipo,e.cantidad_total,e.cantidad_fuera_servicio,e.activo;
