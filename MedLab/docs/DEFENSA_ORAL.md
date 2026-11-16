# Guion para explicar y defender MedLab

Actualizado al código revisado el 12 de noviembre de 2026. Es una guía de estudio: quien presente debe entender y poder demostrar cada afirmación en su instalación.

## Exposición de aproximadamente tres minutos

“MedLab gestiona instrumentos de laboratorio con PHP nativo, MySQL/MariaDB y JavaScript. Tiene autenticación, usuarios y roles, inventario por cantidades, catálogos y un flujo de préstamos: solicitud, aprobación o rechazo, entrega, cancelación y devolución parcial o completa.

Autenticación es comprobar quién entra; autorización es decidir qué puede hacer. Las páginas y las API verifican sesión y permisos. El administrador gestiona cuentas, catálogos, instrumentos y movimientos; el usuario regular consulta existencias y sus propios préstamos. Conocer una URL o un ID no concede permiso. En esta versión, el nombre del rol Administrador identifica ese privilegio y está protegido contra cambios que lo eliminen. El servidor impide dejar el sistema sin un administrador activo.

Las contraseñas se procesan en PHP con password_hash y se verifican con password_verify. La sal aleatoria viene incluida en el hash; no almacenamos la contraseña original ni la desciframos. La política de longitud aún debe unificarse entre creación CLI del administrador y alta de usuarios. No afirmamos que exista actualización automática de hashes, porque el flujo actual no la ejecuta.

Para limitar intentos, cinco verificaciones por cuenta y treinta por IP se controlan en ventanas de quince minutos. Correo y código de una cuenta comparten contador. Los contadores están en archivos fuera del directorio público y usan bloqueo compartido entre procesos. Por eso abrir otra sesión no reinicia el límite. Un fallo del almacenamiento impide autenticar sin ese control.

Las escrituras llevan un token CSRF aleatorio de sesión. Usamos cookies protegidas, renovamos el identificador al entrar y destruimos sesión y cookie al salir. Una sesión vence tras treinta minutos de inactividad. En cada petición se vuelve a consultar si la cuenta existe, está activa y conserva su rol. En esta versión, cambiar una contraseña no garantiza revocar otras sesiones: es un punto distinto que debe verificarse o implementarse.

En el inventario, las relaciones con categoría, estado y condición se validan en servidor. La disponibilidad no es una cifra enviada por el navegador: se calcula con el total, las unidades fuera de servicio, las reservas vigentes y lo entregado pendiente de devolución. Aprobar reserva cantidades durante catorce días; entregar requiere una reserva vigente. El plazo de devolución no supera un mes calendario desde la entrega. La devolución parcial libera solo lo recibido utilizable y conserva observaciones de la recepción.

Las operaciones de stock usan transacciones y bloqueos en un orden común para evitar sobreasignación. Las consultas preparadas separan datos de SQL; el escape de salida evita interpretar texto como HTML. Las pruebas funcionales deben usar bases desechables y los resultados deben corresponder a la versión que mostramos. Ninguna revisión equivale a seguridad total: faltan las comprobaciones del despliegue real, actualizaciones, HTTPS, monitoreo y respaldos probados.”

## Preguntas que pueden hacerte

**¿Dónde está la sal y debe ser secreta?**

Está dentro de la cadena generada por password_hash. No es una contraseña ni una clave secreta. Su aleatoriedad hace que contraseñas iguales puedan tener hashes distintos. SHA-256 se usa para claves de los contadores, no como sustituto del hash de contraseña.

**¿Qué diferencia hay entre hash y cifrado?**

El cifrado permite recuperar el original con una clave; el hash de contraseña permite verificar una candidata sin recuperar la contraseña almacenada.

**¿Por qué validar en PHP si HTML ya usa required y maxlength?**

Porque la API puede recibir peticiones sin pasar por el formulario. Las restricciones HTML ayudan al usuario; el servidor decide qué acepta.

**¿Por qué usar un token CSRF si ya hay cookie?**

La cookie se adjunta automáticamente. El token aporta un valor entregado por la aplicación a esa sesión. SameSite ayuda como defensa adicional.

**¿Qué impide modificar el préstamo de otra persona cambiando el ID?**

El servidor toma la identidad de la sesión y comprueba propiedad en las operaciones de usuario regular. Las acciones administrativas además exigen permiso administrativo. La lista de préstamos del usuario regular se limita a su propia cuenta.

**¿Cómo se evitan dos aprobaciones que consuman las mismas existencias?**

Se bloquean primero los equipos por ID y después la solicitud y sus detalles. La disponibilidad se vuelve a leer bajo esos bloqueos, dentro de la transacción. No basta con haber mostrado stock disponible antes de pulsar Aprobar.

**¿Una reserva vencida libera un equipo ya entregado?**

No. Solo las reservas sin entrega liberan su cantidad al vencer. Lo entregado sigue ocupado hasta registrarse su devolución, incluso si está atrasado.

**¿Cómo se conserva una devolución parcial?**

Cada detalle acumula cantidad_devuelta. La recepción queda descrita también en el historial de observaciones del préstamo; el préstamo solo pasa a Devuelto cuando no quedan unidades pendientes.

**¿Por qué crear primero categoría, estado y condición?**

Los equipos dependen de esas claves foráneas y el servidor comprueba que existan. El inventario activo recibe la marca como texto; no debe confundirse con implementaciones anteriores de una relación de marcas que siguen presentes en otros archivos.

**¿Las pruebas garantizan seguridad completa?**

No. Demuestran los controles ensayados sobre la versión probada. El entorno de ejecución, sus permisos, la configuración de Apache/HTTPS y los dispositivos de los usuarios también requieren validación.

## Qué mostrar en el código

1. `api/admin/C_Usuarios.php` y `C_Prestamos.php`: protección antes de las operaciones de negocio.
2. `api/core/Auth.php` y `AdminGuard.php`: sesión, rol, CSRF y protección del último administrador.
3. `api/models/M_Auth.php`, `M_Usuarios.php` y `database/crear_admin.php`: hash y verificación realmente utilizados.
4. `api/core/LoginLimiter.php`: reserva de intentos, bloqueo y ventana de caducidad.
5. `api/models/M_Inventario.php`: validación de catálogos, cantidades y stock ocupado.
6. `api/models/M_Prestamos.php`: propiedad, estados, transacciones y recepción parcial.
7. `database/FLUJO-PRESTAMOS.md`: reglas y precauciones para una migración existente.

Conservar los resultados de prueba con su versión y fecha. Las suites antiguas que permanecen en `tests/` pueden corresponder a otra revisión y no deben presentarse como evidencia de funciones que no utiliza el flujo actual. Explica el propósito antes de leer líneas; no muestres configuración privada, respaldos personales, contraseñas ni hashes reales.