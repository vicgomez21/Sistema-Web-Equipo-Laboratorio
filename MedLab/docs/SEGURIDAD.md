# Seguridad de MedLab

Documento iniciado el 3 de noviembre de 2026. Revisión documental: 12 de noviembre de 2026. Este texto describe los controles conectados al código actual, no certifica el despliegue ni la ausencia de vulnerabilidades.

## Controles activos

| Control | Implementación actual |
| --- | --- |
| Autenticación y permisos | `Auth::proteger()` exige sesión y reconsulta cuenta, actividad y rol. Usuarios, Roles y diagnóstico requieren administración; Inventario y Préstamos permiten acceso regular según sus reglas. API sin sesión responde 401; sin permiso, 403. |
| Rol administrativo | `Auth::esAdministrador()` reconoce el nombre de rol Administrador. `AdminGuard.php` protege al último administrador activo y el nombre del rol del sistema mediante transacciones y bloqueos. No usa actualmente `roles.es_administrador` como fuente de autorización. |
| CSRF | Token aleatorio de sesión, comparación `hash_equals`, formulario para login/logout y encabezado `X-CSRF-Token` en las escrituras de API. La cookie SameSite es una capa adicional. |
| Intentos de login | `C_Auth.php` usa `LoginLimiter.php`: cinco intentos por cuenta y treinta por IP en quince minutos. Correo y código se resuelven al mismo ID. Los contadores compartidos están fuera del directorio público, con `flock`, ventana fija, tamaño acotado y reemplazo del estado mediante archivo temporal. Un acceso correcto reinicia el contador de cuenta, pero no el de IP. El bloqueo devuelve 429 y `Retry-After`; un fallo del almacenamiento impide autenticar. |
| Contraseñas | El alta/cambio de usuarios y la creación CLI del administrador usan `password_hash(..., PASSWORD_DEFAULT)`; `M_Auth.php` verifica con `password_verify()`. No se entrega el hash en el listado ni en la identidad de sesión. El flujo activo no realiza rehash automático. |
| Sesión | Cookie HttpOnly, SameSite Strict y Secure bajo HTTPS; modo estricto y solo cookies. El ID se regenera al entrar y la sesión/cookie se destruyen al salir. `Auth.php` aplica treinta minutos de inactividad. No implementa actualmente el límite absoluto de ocho horas descrito en versiones anteriores de este documento. |
| Revocación | Una cuenta eliminada o desactivada pierde acceso; el rol se vuelve a comprobar en cada petición protegida. No se comprueba actualmente `version_sesion`, por lo que no debe afirmarse que cambiar la contraseña revoca por sí solo todas las sesiones anteriores. |
| SQL y salida HTML | Consultas preparadas PDO, parámetros y preparaciones nativas. `escapeHtml`, `textContent` y `htmlspecialchars` al mostrar texto; las validaciones de PHP son la autoridad y no se confía en restricciones del formulario. |
| Cabeceras y archivos internos | `Configuration.php` configura CSP, `nosniff`, protección de marcos, referrer y políticas de permisos. `.htaccess` deniega archivos internos, SQL, configuración, documentación y herramientas CLI. Su aplicación depende de Apache y debe verificarse en el servidor real. |
| Inventario y préstamos | `M_Inventario.php` y `M_Prestamos.php` validan cantidades, relaciones, pertenencia y transiciones. Las operaciones de stock usan transacciones y bloqueos. Las cantidades disponibles y el solicitante no se aceptan como autoridad desde el navegador. |

## Contraseñas: hash y sal

La contraseña se procesa en PHP y MySQL recibe el hash. La cadena almacenada incluye algoritmo, parámetros, sal y resultado; no se necesita una columna de sal ni funciones MD5/SHA para verificar contraseñas. `password_verify()` comprueba una candidata sin descifrar el hash.

La política todavía difiere entre rutas: `M_Usuarios.php` exige al menos ocho caracteres para altas/cambios y `database/crear_admin.php` exige al menos doce. Debe unificarse antes de describir una política única. `api/core/Password.php` conserva una implementación adicional, pero su existencia y sus pruebas no demuestran que esté conectada al flujo actual de usuarios/login. Lo mismo aplica a `LoginThrottle.php` frente al limitador de archivo actualmente utilizado.

## Flujo de préstamos

El módulo permite solicitar, aprobar/rechazar, entregar, cancelar reservas y registrar devoluciones parciales o completas. La aprobación reserva cantidades durante catorce días. La entrega debe ocurrir con reserva vigente y fija una devolución dentro del mes calendario permitido. Los préstamos entregados siguen ocupando unidades hasta su recepción, aunque estén atrasados.

Las notificaciones internas y sus marcas de lectura se muestran en Préstamos. Las observaciones de cada recepción se conservan en el historial del préstamo. Para reglas del esquema y migración, consultar `database/FLUJO-PRESTAMOS.md`.

## Pruebas y evidencia

`tests/README.md` conserva las instrucciones y los resultados de una revisión anterior. Esos resultados no certifican automáticamente la versión actual: algunas suites y migraciones se escribieron para otra revisión de seguridad y deben contrastarse con el esquema y las rutas vigentes antes de ejecutarse.

Las pruebas que escriben datos deben ejecutarse en una instancia desechable y separada. No reutilizar el directorio de datos real, no publicar cuentas ficticias y no mostrar `.env`, contraseñas ni hashes reales durante una demostración. Las pruebas HTTP tampoco certifican accesibilidad visual, móvil o con lector de pantalla.

## Despliegue

En producción usar una versión de PHP soportada, HTTPS y `APP_ENV=production`, que rechaza HTTP. La versión instalada y los puertos son propiedades del entorno: deben comprobarse, no inferirse de una revisión histórica. La aplicación no confía en cabeceras proxy del cliente; un proxy de confianza requiere configuración explícita. El limitador actual presupone una sola instalación con almacenamiento compartido entre sus procesos; varios servidores requieren un mecanismo compartido adecuado.

Usar una cuenta de base de datos exclusiva con los permisos necesarios, guardar configuración/respaldo/logs fuera del acceso público, reproducir restricciones `.htaccess` si se cambia de servidor y probar restauración de respaldos. Los límites no eliminan ataques distribuidos ni sustituyen monitoreo y actualización del sistema.

## Registro histórico

La revisión inicial documentó una migración de seguridad/marcas, una cuenta local exclusiva y comprobaciones en una instalación concreta. Se conservan las migraciones y suites correspondientes en el repositorio. Este registro histórico no prueba el estado actual de la base, sus credenciales, el puerto ni que todos aquellos controles continúen conectados; este documento no publica valores privados ni los vuelve a verificar.

## Referencias

- [PHP: password_hash](https://www.php.net/manual/en/function.password-hash.php)
- [PHP: password_verify](https://www.php.net/manual/en/function.password-verify.php)
- [PHP: versiones soportadas](https://www.php.net/supported-versions.php)
- [OWASP: Password Storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
- [OWASP: Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP: Authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)