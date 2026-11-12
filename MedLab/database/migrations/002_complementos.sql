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
