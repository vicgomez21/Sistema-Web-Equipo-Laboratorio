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
    id_encargado_entrega INT NOT NULL,
    fecha_prestamo DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_limite DATETIME NOT NULL,
    id_estado_prestamo INT NOT NULL,
    observaciones TEXT,

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
    id_condicion_entrega INT NOT NULL,
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

    CONSTRAINT uq_equipo_pendiente
        UNIQUE (equipo_pendiente),

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
