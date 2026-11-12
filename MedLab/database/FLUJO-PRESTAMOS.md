# Inventario por cantidades y préstamos — adaptación mínima

Se conservan las nueve tablas originales y sus claves. Se añade únicamente `notificaciones` y una vista de lectura `disponibilidad_equipos`. No se sustituye el modelo por un sistema nuevo.

## Significado de los datos

- `equipos` identifica un tipo/lote homogéneo de instrumento. `cantidad_total` cuenta unidades y `cantidad_fuera_servicio` excluye unidades dañadas o no utilizables. Código de inventario, categoría, marca, modelo, ubicación y relaciones existentes se conservan. `numero_serie` sigue siendo opcional y solo describe una unidad individual: no poner un número de serie como si identificara varias unidades. No se fusionan registros históricos automáticamente.
- `prestamos` representa tanto solicitud como préstamo. Nuevas fechas de solicitud, aprobación y decisión y su responsable complementan las columnas existentes. `fecha_prestamo` conserva su significado de entrega efectiva y permanece NULL mientras no se entregue. `fecha_limite` es la fecha real de devolución comprometida; `fecha_devolucion_solicitada` guarda la fecha que eligió el solicitante para validarla al entregar.
- `detalle_prestamo` mantiene un renglón por instrumento y préstamo: ahora incluye `cantidad` y `cantidad_devuelta`. No se permite cantidad cero, devolución superior a lo entregado ni cierre sin devolución completa. Los campos de recepción existentes describen el cierre del renglón; un historial detallado de múltiples devoluciones parciales requeriría un registro adicional, que no se añade sin definir ese proceso.
- `estados_prestamo` conserva ID y nombre y añade `codigo` estable: PENDIENTE, APROBADO, RECHAZADO, ENTREGADO, DEVUELTO, RESERVA_VENCIDA, CANCELADO. El código identifica la regla; el nombre sirve para mostrarla.
- `notificaciones` permite avisos internos, responsable, fecha de lectura y un evento único por usuario/préstamo. No envía correo ni WhatsApp. La pantalla y los productores de estos avisos aún no están implementados.

## Reglas confirmadas

1. Estudiantes y personal solicitan cantidades. Una solicitud PENDIENTE no aparta existencias.
2. Aprobar aparta la cantidad solicitada. `fecha_reserva_hasta` se calcula automáticamente como aprobación + 14 días. El plazo es exacto: al alcanzar ese instante la reserva ya está vencida.
3. Solo se entrega una reserva vigente. Al entregar se registran responsable, condición y `fecha_prestamo`; no se descuentan otra vez las cantidades ya apartadas.
4. La fecha límite no puede ser anterior a la entrega ni superar `DATE_ADD(fecha_prestamo, INTERVAL 1 MONTH)`. Se usa un mes calendario, ajustado al último día del mes cuando corresponde, conservando la hora de entrega: 31 enero -> 28/29 febrero.
5. Una reserva vencida sin entregar deja de ocupar disponibilidad aun antes de actualizar su estado almacenado. Un préstamo entregado sigue ocupado aunque supere su vencimiento; solo devolver libera esas unidades.
6. Zona horaria de las conexiones de la aplicación: Guatemala, UTC-06:00. Las fechas DATETIME se interpretan en esa zona.

`disponibilidad_equipos` calcula total - fuera de servicio - reservas vigentes - cantidades prestadas no devueltas. No mantiene contadores duplicados. En instrumentos inactivos devuelve disponibilidad cero. Se conserva la columna generada antigua `equipo_pendiente`, pero se retira su índice UNIQUE porque impedía prestar distintas cantidades del mismo instrumento a varias personas; no debe usarse para decidir disponibilidad.

## Contrato obligatorio del futuro módulo de préstamos

La vista NO impide por sí sola aprobar más unidades de las existentes. Antes de publicar acciones de préstamo:

- Exigir sesión/permiso y CSRF, identificar solicitante desde sesión y validar fecha elegida/cantidades en servidor.
- En aprobación, iniciar transacción, bloquear la solicitud y sus equipos con SELECT FOR UPDATE en orden de ID, comprobar estado PENDIENTE y volver a leer disponibilidad actual bajo bloqueo. Rechazar si no alcanza; aprobar una sola vez. Todas las rutas que cambien inventario, reservas o devoluciones deben seguir el mismo orden de bloqueo.
- Usar lectura actual y consistente con esos bloqueos; no una fotografía previa de disponibilidad bajo REPEATABLE READ. Una comprobación anterior a la transacción no es suficiente.
- Entrega: bloquear solicitud/equipos, comprobar APROBADO y reserva vigente, validar la fecha elegida respecto de la entrega real y registrar todos los campos en la misma transacción.
- Devolución: bloquear los mismos registros, validar cantidad pendiente y actualizar recepción/condición y cantidad devuelta. Las unidades dañadas pasan a fuera de servicio sin volver a quedar disponibles.
- Cancelación/vencimiento: solo libera reservas sin entregar; nunca libera un préstamo entregado. Al consultar se puede mostrar el estado efectivo de vencimiento y persistirlo con una tarea periódica o proceso de mantenimiento; todavía no se instala ningún programador.
- Registrar notificaciones en la transacción que cambia el estado, usando evento estable y la restricción única para reintentos.

No hay aún controladores ni pantallas de inventario/préstamos. Esta entrega adapta almacenamiento y reglas estructurales, no habilita esas operaciones en la interfaz.

## Instalación nueva

Importar `MedLabDB.sql` únicamente en una base nueva. Incluye los códigos de estado y la tabla/vista nuevas. Nunca reimportarlo encima de una base con datos para actualizarla.

## Instalación existente

El migrador solo admite el esquema original de nueve tablas, sin vistas/triggers/columnas generadas adicionales. Si hay extensiones, debe revisarse específicamente su respaldo antes de migrar. No se ejecuta automáticamente desde páginas o API.

1. Con la conexión real configurada, ejecutar desde MedLab `C:\xampp\php\php.exe database\migrar_cantidades.php`. Por defecto solo lista estados actuales: no cambia datos.
2. Preparar un archivo JSON privado que asigne cada ID existente a un código de estado, sin duplicados. Ejemplo ilustrativo: `{"1":"ENTREGADO","2":"DEVUELTO"}`. Los IDs reales deben salir del paso anterior: no copiar el ejemplo a ciegas. Si históricos no son realmente entregados/devueltos o el mapeo provoca conflicto de nombres, se detiene antes del DDL.
3. Detener escrituras/poner la aplicación en mantenimiento y elegir una carpeta privada de respaldo fuera de htdocs. `--maintenance` declara que el operador ya hizo esto; no deshabilita la web por sí solo.
4. Aplicar explícitamente: `C:\xampp\php\php.exe database\migrar_cantidades.php --apply --maintenance --map=RUTA_PRIVADA\estados.json --backup-dir=RUTA_PRIVADA\respaldos`.

Se genera primero respaldo SQL de tablas base, luego registro de fases, luego cambios. Se conservan IDs, relaciones, datos y fechas reales. Cada registro antiguo de equipo representa una unidad, por eso se inicia en 1; cada detalle antiguo ya devuelto queda cantidad=1/devuelta=1. Los préstamos históricos quedan `version_reglas=1` y no reciben fechas ficticias de solicitud/aprobación ni vencimientos nuevos. Las operaciones nuevas usan versión 2; nunca aceptar `version_reglas` enviado por el cliente.

El DDL de MariaDB hace commits implícitos: el proceso completo no es atómico. Si falla después de empezar, detenerse, revisar el registro y restaurar/verificar el respaldo en una base aparte antes de planificar la recuperación. Se rechaza repetir una migración completa o parcial a ciegas. El respaldo es específico del esquema original, no un exportador universal de vistas, procedimientos o triggers.

Referencias: [DATE_ADD](https://mariadb.com/docs/server/reference/sql-functions/date-time-functions/date_add), [commits implícitos](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).
