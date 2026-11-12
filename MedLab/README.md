# MedLab — gestión de equipos de laboratorio

Aplicación PHP nativo, MySQL/MariaDB y JavaScript. Conserva páginas, modelos, controladores, CSS y JavaScript separados.

## Abrir en este equipo

1. Iniciar Apache y MySQL en XAMPP.
2. Abrir `http://localhost/Sistema-Web-Equipo-Laboratorio/MedLab/login.php`.
3. Iniciar sesión con una cuenta existente. No hay contraseña predeterminada.

La instalación actual usa MySQL en el puerto 3311. `api/core/.env` contiene la configuración privada; no debe publicarse. Para otra instalación, copiar `.env.example` y configurar servidor, puerto y credenciales reales.

## Funciones

- Usuarios y roles: gestión restringida al rol Administrador.
- Inventario: categorías, estados, condiciones, instrumentos y cantidades totales/fuera de servicio; búsqueda y disponibilidad real.
- Solicitudes: estudiantes y personal solicitan uno o varios instrumentos y eligen fecha de devolución.
- Administración: aprobar/rechazar solicitudes, entregar instrumentos, recibir devoluciones parciales o completas y cancelar reservas.
- Reservas: aprobar aparta cantidades durante catorce días. Vencer sin entrega libera disponibilidad automáticamente al consultarla. Lo entregado permanece ocupado hasta su devolución.
- Devolución: máximo un mes calendario desde la entrega, con ajuste de fin de mes; zona horaria Guatemala. Las unidades devueltas dañadas quedan fuera de servicio. Las observaciones de cada recepción se conservan en el historial del préstamo.
- Notificaciones internas: nuevas solicitudes para administradores y respuestas/movimientos para solicitantes, con marcado de lectura.
- Reportes básicos: existencias, solicitudes por estado y devoluciones atrasadas. Historial consultable en Préstamos.

## Primer uso del inventario

Un administrador abre «Categorías», «Estados de equipo» y «Condiciones de equipo» para registrar los catálogos reales del laboratorio. Después abre «Equipos» y registra instrumentos. Un registro agrupa unidades del mismo tipo, marca y modelo; número de serie se usa únicamente cuando el registro representa una unidad. No se añaden datos de demostración a la base real.

«Fuera de servicio» determina cuántas unidades no se pueden solicitar. Estado y condición describen el lote. No se puede reducir el stock utilizable por debajo de lo reservado/prestado ni desactivar instrumentos ocupados. Los instrumentos con historial se desactivan en lugar de eliminarse.

Un usuario regular consulta Inventario y Préstamos. Puede cancelar sus solicitudes pendientes o reservas vigentes; solo un administrador aprueba, rechaza, entrega y registra devoluciones.

## Base de datos

En esta instalación ya se aplicó la migración de cantidades. No volver a importar `database/MedLabDB.sql` encima de datos existentes.

- Instalación nueva: importar el esquema en una base vacía y configurar `.env`.
- Base anterior: seguir `database/FLUJO-PRESTAMOS.md`; el migrador tiene revisión previa, respaldo y mapa de estados. Por defecto solo lee, no aplica.
- Primera cuenta, si no existe ninguna: desde MedLab ejecutar `C:\xampp\php\php.exe database\crear_admin.php` y completar sus datos.

Los códigos de estados de préstamo son reglas del sistema, no un catálogo editable: PENDIENTE, APROBADO, RECHAZADO, ENTREGADO, DEVUELTO, RESERVA_VENCIDA y CANCELADO.

## Seguridad y comprobaciones

Sesiones, autorización en servidor, CSRF en escrituras, consultas preparadas, límite de intentos y protección del último administrador activo. Aprobaciones y cambios de stock usan transacciones y bloqueos; no se confía en cantidades disponibles enviadas por el navegador.

El flujo se verifica con bases ficticias separadas, incluyendo aprobaciones simultáneas. No se exponen cuentas de prueba en la base real. La comprobación visual automática puede depender de la disponibilidad y permisos del navegador; las pruebas HTTP no certifican por sí solas accesibilidad WCAG ni apariencia en todos los dispositivos.
