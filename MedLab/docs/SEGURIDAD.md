# Seguridad de MedLab

Revisión e implementación: 3 de noviembre de 2026. Alcance: login, sesiones, usuarios, roles, catálogos de inventario, equipos y diagnóstico. Los préstamos aún no tienen un módulo funcional.

## Hallazgos y cambios

| Riesgo | Dónde está la defensa | Cómo funciona |
| --- | --- | --- |
| Consultar o modificar usuarios sin autenticarse | `api/admin/C_Usuarios.php`, `C_Roles.php`, `C_Conexion.php`; `api/core/Auth.php` | Cada controlador comprueba sesión y privilegio administrativo antes de ejecutar consultas de negocio. Las páginas administrativas también lo comprueban. Anónimo recibe 401 en API y redirección al login en páginas; usuario sin permiso recibe 403. |
| Escalada de privilegios | `Auth::administrador()`, `roles.es_administrador`, `M_Usuarios.php`, `M_Roles.php` | El permiso es un atributo de BD, separado del nombre del rol. Crear o renombrar un rol no concede administración. El CRUD no permite escribir ese atributo. Las mutaciones de usuarios bloquean filas y revalidan al actor para impedir perder administradores con solicitudes concurrentes. |
| CSRF: otra página intenta operar usando la sesión del navegador | `Auth::token()/validarToken()/proteger()`, `C_Auth.php`, `includes/layout_inicio.php`, `js/lib/app.js` | Token aleatorio de 32 bytes, ligado a sesión y comparado con `hash_equals`. Formularios de login/logout lo envían en el cuerpo; las escrituras de API lo envían en `X-CSRF-Token`. Cookie SameSite=Strict como defensa adicional. |
| Fuerza bruta y reutilización masiva de credenciales | `api/core/LoginThrottle.php`, `intentos_login` | Máximo cinco verificaciones por cuenta y treinta por IP en una ventana de quince minutos. Correo, código y variantes que MySQL reconoce como la misma cuenta comparten contador. Las reservas ocurren antes del hash y son atómicas con transacciones y bloqueo de filas. Cambiar de cookie no reinicia el límite. Una IP bloqueada no crea nuevas claves de cuenta. |
| Robo de hashes de contraseñas | `api/core/Password.php`, `M_Usuarios.php`, `M_Auth.php`, `database/crear_admin.php` | Argon2id con 19 MiB, dos iteraciones y un hilo cuando está disponible; alternativa bcrypt con coste 12. PHP genera una sal aleatoria por hash. Verificación con `password_verify()` y actualización automática del algoritmo/coste después de autenticar. El listado y la sesión nunca reciben el hash. |
| Fijación, reutilización o sesiones abandonadas | `Configuration.php`, `Auth.php` | Cookies HttpOnly, SameSite=Strict y Secure cuando hay HTTPS; modo estricto y solo cookies. Cambia el ID al iniciar sesión, destruye sesión y cookie al salir. Caduca tras treinta minutos de inactividad u ocho horas totales. |
| Cuenta revocada que conserva una sesión | `usuarios.version_sesion`, `M_Usuarios::UPDATE_USUARIO()`, `Auth::proteger()` | Cambiar contraseña, rol o estado incrementa la versión. Cada solicitud vuelve a consultar usuario, estado, versión y privilegios; una sesión antigua deja de servir. Eliminar la cuenta también revoca el acceso. |
| Inyección SQL | `api/models/*.php`, `api/core/Connection.php` | Se conservan y extienden consultas preparadas PDO, con parámetros y preparaciones nativas (`ATTR_EMULATE_PREPARES=false`). Los nombres de columnas que se construyen en el servidor son constantes del código, no entradas del usuario. |
| XSS almacenado o reflejado | `js/lib/app.js`, `usuarios.js`, `roles.js`, vistas PHP, `Configuration.php` | Se conservan `escapeHtml`, `textContent` y `htmlspecialchars` al mostrar texto. Se añade CSP que limita scripts al propio sitio y bloquea objetos, cambios de URL base y marcos. Guardar texto no equivale a autorizar ejecutarlo como HTML. |
| Clickjacking | `Configuration.php` | `X-Frame-Options: DENY` y CSP `frame-ancestors 'none'` impiden incrustar las páginas en un marco. |
| Peticiones manipuladas | `api/core/Request.php`, validaciones en modelos, `.htaccess` | Solo métodos previstos y formularios URL-encoded. Cuerpo máximo de 16 KiB, hasta 32 campos escalares, nombres de campos simples, sin duplicados, UTF-8 válido y sin NUL. Valida IDs, longitudes, correo y estado en servidor. Apache limita también el cuerpo antes de PHP. |
| Archivos sensibles y mensajes técnicos públicos | `.htaccess`, archivos `.htaccess` de carpetas internas, `Configuration.php`, controladores | Se bloquea acceso HTTP a SQL, `.env`, modelos, core, includes, pruebas y documentación, y el listado de directorios. Errores técnicos van al log; respuesta pública genérica. El diagnóstico solo está disponible para administradores. |
| Cuenta MySQL con privilegios excesivos | `database/usuario_mysql.sql`, `.env.example` | Usuario exclusivo de MedLab con SELECT, INSERT, UPDATE y DELETE. Migraciones y permisos se ejecutan con cuenta DBA aparte. Se elimina la contraseña fija del instalador; hay un marcador obligatorio que debe sustituirse. |

Las consultas preparadas, parte del escape HTML, el hash básico, el token del formulario de login y la regeneración de sesión ya existían. La falla principal era que las páginas y API de administración no exigían la protección existente. Esta revisión conecta esos controles y añade los descritos arriba.

## Contraseñas: hash y sal desde PHP

La contraseña entra por HTTPS en producción y PHP calcula el hash antes de enviarlo a MySQL. La columna `usuarios.password_hash VARCHAR(255)` guarda una cadena que contiene algoritmo, parámetros, sal y resultado. No se necesita una columna `salt`, un trigger, `MD5`, `SHA1` ni `SHA2` en SQL.

La sal es aleatoria y distinta para cada operación; no es una clave secreta. Dos usuarios con la misma contraseña obtienen hashes distintos. El hash no se descifra: `password_verify()` comprueba la contraseña candidata usando la información de la cadena almacenada. Un atacante con los hashes todavía puede intentar adivinarlos; el algoritmo costoso encarece ese ataque.

Altas y cambios exigen al menos quince caracteres y hasta 72 bytes. El límite de bytes evita truncamiento en la alternativa bcrypt; un carácter Unicode puede ocupar varios bytes. No se recortan espacios de la contraseña. Las contraseñas antiguas cortas pueden iniciar sesión y actualizar su hash; esto no significa que ya cumplan la nueva longitud. Contraseñas antiguas de más de 72 bytes necesitan restablecimiento administrativo.

Fuentes: [PHP: password_hash](https://www.php.net/manual/en/function.password-hash.php), [PHP: password_verify](https://www.php.net/manual/en/function.password-verify.php), [OWASP: Password Storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html), [OWASP: Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html), [OWASP: Authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html).

## Marcas y migración

`database/MedLabDB.sql` es únicamente para instalaciones nuevas. Define `marcas(id_marca, nombre, descripcion, activo)` y `equipos.id_marca` con clave foránea RESTRICT. El nombre único evita duplicados según la comparación de MySQL. La marca es opcional mientras no se conozca; no se inventan fabricantes.

Para instalaciones existentes se usa `database/migrations/20261103_seguridad_marcas.sql`. Crea lo que falta, migra marcas de texto a relaciones y conserva la columna de texto antigua cuando existe. Añade el permiso administrativo, la versión de sesión y los contadores de login. También contempla el formato previo de contadores basado en `inicio_ventana` UNIX. Puede repetirse sin volver a otorgar permisos de administrador revocados. El SQL DDL hace commits implícitos: siempre respaldar antes.

No existe un CRUD independiente de marcas. El CRUD de equipos recibe el nombre de la marca y resuelve su `id_marca` dentro de la misma transacción: reutiliza el registro existente o crea uno nuevo.

Categoría, estado y condición sí tienen CRUD propios y se crean antes que el equipo. `M_Equipos.php` ya no acepta sus nombres libres: recibe identificadores, comprueba que existan y exige que la categoría esté activa. Las claves foráneas impiden borrar clasificaciones utilizadas; además, una categoría con equipos asociados no se puede desactivar. Así se evita que una escritura directa a la API fabrique relaciones inexistentes o deje equipos sin clasificación válida.

## Verificación

- Sintaxis de todos los PHP y configuración Apache.
- `tests/passwords.php`: sal distinta, contraseña correcta/incorrecta, límites, Unicode, NUL y rehash.
- `tests/security_http.php`: 46 comprobaciones por HTTP real en Apache temporal: anonimato, permisos, CSRF, cabeceras, archivos internos, entradas inválidas, SQLi como texto, mass assignment, contraseñas, revocación y logout. Requiere `MEDLAB_SECURITY_TEST=1`, Apache loopback 18081 y BD desechable `MedLabSecurityTest_20261103` en MySQL 13316. Jamás apunta al `.env` real.
- `tests/throttle.php`: integración con BD desechable `MedLabSecurityTest_*`; límites por cuenta/IP, alias, caducidad, IPv6, cuenta inactiva, ambigüedad y rehash. Doce procesos compitiendo por la misma cuenta admitieron exactamente cinco; dieciséis procesos tras veintidós reservas IP admitieron exactamente ocho más.
- Migración: esquema nuevo, esquema con marcas de texto y formato anterior de contadores; segunda ejecución, preservación de datos y restricciones FK.

Ejecutar la prueba independiente: `C:\xampp\php\php.exe tests/passwords.php`. Las pruebas de integración requieren instancia desechable, nunca una copia de producción abierta a usuarios. Los directorios de pruebas y su configuración temporal están bloqueados por Apache; `tests/.runtime/` no se versiona.

## Antes de publicar

El equipo usa PHP 8.0.30, una rama sin soporte de seguridad. Actualizar PHP a una rama soportada y actualizar Apache/MySQL antes de exponerlos a Internet. Consultar [versiones soportadas de PHP](https://www.php.net/supported-versions.php).

Configurar HTTPS y `APP_ENV=production`; ese modo rechaza HTTP. En local HTTP, Secure no se puede activar y el tráfico no va cifrado. Si TLS termina en un proxy, configurar explícitamente su relación de confianza; el código no confía en `X-Forwarded-Proto` o `X-Forwarded-For` del cliente. Usar un host dedicado y mantener `.env`, respaldos y logs fuera del acceso público; para otros servidores deben reproducirse las restricciones de `.htaccess`. Limitar MySQL a red privada/loopback, cerrar acceso externo a phpMyAdmin y configurar `ServerTokens Prod` en Apache.

El límite de login reduce fuerza bruta; no elimina ataques distribuidos ni DDoS. Contar éxitos también puede limitar a usuarios legítimos detrás de una misma IP; medir y ajustar según el uso. Programar limpieza diaria de contadores vencidos según el SQL de migración. Falta incorporar MFA y recuperación de cuenta si van a formar parte del alcance, así como auditoría de acciones, monitoreo, respaldos con restauración probada y pruebas sobre los futuros módulos. Nunca se debe presentar esta revisión como garantía de que ningún ataque tendrá éxito.

## Resultado en la instalación local

Se respaldó `MedLabDB` fuera de `htdocs` y se aplicó la migración en MySQL local, puerto 3310. La base ya tenía la tabla de marcas y su relación con equipos; se conservaron. Se añadió la versión de sesión y se adaptaron los contadores del formato anterior, sin eliminar usuarios. Permanecen once tablas y el usuario existente.

Se creó `usr_medlab@localhost`, exclusivo del proyecto, con contraseña aleatoria de 32 bytes guardada en `.env` como 64 caracteres hexadecimales. Solo tiene SELECT, INSERT, UPDATE y DELETE, sin permiso para conceder privilegios. Se corrigió el puerto del `.env`, que apuntaba a 3311. La cuenta anterior tenía permisos sobre otra aplicación y no se modificó; la rotación de cualquier credencial antigua compartida debe coordinarse con ese proyecto.

Comprobación final de la instalación real, sin iniciar sesión como el usuario existente ni cambiar su contraseña: conexión PHP/MySQL correcta, login HTTP 200, API de usuarios sin sesión HTTP 401 y acceso HTTP a `.env` denegado con 403. Las pruebas que modifican registros se hicieron exclusivamente en bases temporales.
