ALTER TABLE estados_prestamo ADD COLUMN codigo VARCHAR(24) NULL UNIQUE;
ALTER TABLE equipos
 ADD COLUMN cantidad_total INT NOT NULL DEFAULT 1,
 ADD COLUMN cantidad_fuera_servicio INT NOT NULL DEFAULT 0,
 ADD CONSTRAINT chk_equipo_cantidades CHECK (cantidad_total >= 0 AND cantidad_fuera_servicio >= 0 AND cantidad_fuera_servicio <= cantidad_total);
ALTER TABLE prestamos
 MODIFY id_encargado_entrega INT NULL,
 MODIFY fecha_prestamo DATETIME NULL DEFAULT NULL,
 MODIFY fecha_limite DATETIME NULL,
 ADD COLUMN fecha_solicitud DATETIME NULL DEFAULT NULL,
 ADD COLUMN fecha_devolucion_solicitada DATE NULL,
 ADD COLUMN id_encargado_aprobacion INT NULL,
 ADD COLUMN fecha_aprobacion DATETIME NULL,
 ADD COLUMN fecha_decision DATETIME NULL,
 ADD COLUMN motivo_rechazo VARCHAR(500) NULL,
 ADD COLUMN version_reglas TINYINT NOT NULL DEFAULT 1,
 ADD COLUMN fecha_reserva_hasta DATETIME GENERATED ALWAYS AS (DATE_ADD(fecha_aprobacion, INTERVAL 14 DAY)) STORED,
 ADD CONSTRAINT fk_prestamo_aprobacion FOREIGN KEY (id_encargado_aprobacion) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
 ADD CONSTRAINT chk_prestamo_entrega CHECK (version_reglas = 1 OR ((fecha_prestamo IS NULL AND id_encargado_entrega IS NULL AND fecha_limite IS NULL) OR (fecha_prestamo IS NOT NULL AND id_encargado_entrega IS NOT NULL AND fecha_limite IS NOT NULL AND fecha_aprobacion IS NOT NULL AND fecha_prestamo >= fecha_aprobacion AND fecha_prestamo < fecha_reserva_hasta))),
 ADD CONSTRAINT chk_prestamo_mes CHECK (version_reglas = 1 OR fecha_prestamo IS NULL OR (fecha_limite >= fecha_prestamo AND fecha_limite <= DATE_ADD(fecha_prestamo, INTERVAL 1 MONTH))),
 ADD CONSTRAINT chk_prestamo_aprobacion CHECK (version_reglas = 1 OR ((fecha_aprobacion IS NULL AND id_encargado_aprobacion IS NULL) OR (fecha_aprobacion IS NOT NULL AND id_encargado_aprobacion IS NOT NULL AND fecha_aprobacion >= fecha_solicitud))),
 ADD CONSTRAINT chk_prestamo_version CHECK (version_reglas IN (1,2)),
 ADD CONSTRAINT chk_prestamo_solicitud CHECK (version_reglas=1 OR fecha_solicitud IS NOT NULL),
 ADD INDEX idx_reserva_vencimiento (fecha_reserva_hasta);
ALTER TABLE detalle_prestamo
 DROP INDEX uq_equipo_pendiente,
 MODIFY id_condicion_entrega INT NULL,
 ADD COLUMN cantidad INT NOT NULL DEFAULT 1,
 ADD COLUMN cantidad_devuelta INT NOT NULL DEFAULT 0,
 ADD CONSTRAINT chk_detalle_cantidades CHECK (cantidad > 0 AND cantidad_devuelta >= 0 AND cantidad_devuelta <= cantidad),
 ADD INDEX idx_detalle_equipo (id_equipo);
UPDATE detalle_prestamo SET cantidad_devuelta = CASE WHEN fecha_devolucion IS NULL THEN 0 ELSE 1 END;
ALTER TABLE detalle_prestamo ADD CONSTRAINT chk_detalle_cierre CHECK ((fecha_devolucion IS NULL AND cantidad_devuelta < cantidad) OR (fecha_devolucion IS NOT NULL AND cantidad_devuelta = cantidad));
