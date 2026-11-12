# Guion para explicar y defender MedLab

## Exposición de aproximadamente tres minutos

“MedLab es un sistema de gestión de equipos de laboratorio desarrollado con PHP y MySQL. Hasta ahora tenemos autenticación y los módulos de usuarios, roles, categorías, estados, condiciones y equipos. Los préstamos tienen diseño de base de datos, pero su pantalla funcional todavía está pendiente.

Antes de ampliar los módulos revisamos la seguridad. El hallazgo principal fue que tener una pantalla de login no basta: las API de administración también deben exigir sesión y permisos. Ahora cada solicitud comprueba quién está conectado y si puede administrar el recurso. Una persona sin sesión no puede consultar ni modificar usuarios, aunque escriba directamente la URL de la API.

Separamos autenticación de autorización. Autenticación significa comprobar la identidad; autorización significa decidir qué puede hacer esa identidad. El privilegio administrativo se guarda como un atributo del rol en la base de datos. Ponerle el nombre ‘Administrador’ a otro rol no le concede permisos.

Para las contraseñas usamos hash desde PHP. Preferimos Argon2id y tenemos bcrypt como alternativa. Cada hash recibe una sal aleatoria automática, de modo que dos contraseñas iguales no producen la misma cadena almacenada. MySQL guarda el hash completo, nunca la contraseña original. Al iniciar sesión usamos password_verify; no desciframos nada. Las contraseñas nuevas tienen una política de longitud y los hashes antiguos se actualizan después de una autenticación válida.

Para dificultar la fuerza bruta limitamos las verificaciones a cinco por cuenta y treinta por dirección IP durante quince minutos. Los contadores viven en MySQL y se reservan dentro de transacciones, así que cambiar de navegador o mandar varias solicitudes al mismo tiempo no reinicia el límite.

También protegemos las operaciones contra CSRF mediante un token aleatorio ligado a la sesión. Una página externa no debe poder usar la sesión del navegador para cambiar nuestros datos. En las sesiones renovamos el identificador al entrar, usamos cookies protegidas, aplicamos caducidad y destruimos la sesión al salir. Si se cambia una contraseña, un rol o el estado de una cuenta, se invalida su sesión anterior.

Contra inyección SQL usamos consultas preparadas: los datos del usuario se envían separados de la instrucción SQL. Contra XSS escapamos los textos al mostrarlos y añadimos una política del navegador que restringe la ejecución de scripts. Validamos también los datos en el servidor; las restricciones del formulario son una ayuda, pero pueden omitirse.

En el inventario respetamos las dependencias. Primero se crean categoría, estado y condición en sus propios CRUD; luego Equipo solo permite seleccionar registros existentes. Las claves foráneas evitan referencias inexistentes y bloquean borrar catálogos utilizados. La marca se captura dentro del CRUD de equipos: el servidor reutiliza la existente o crea la relación nueva dentro de la misma transacción.

Finalmente, comprobamos estas medidas con peticiones HTTP y pruebas concurrentes en una base temporal. La defensa es por capas: cada control reduce un riesgo específico. Antes de publicar todavía hay trabajo operativo, especialmente actualizar el PHP instalado, configurar HTTPS y mantener monitoreo y respaldos.”

## Preguntas que pueden hacerte

**¿Por qué hacer el hash en el código y no en la base de datos?**

Porque PHP ofrece funciones específicas y mantenidas para contraseñas: generan sal, aplican el coste y permiten verificar y actualizar el hash. Así MySQL recibe únicamente el hash, y la política se mantiene en un solo lugar para altas, cambios y creación del administrador.

**¿Dónde está la sal? ¿Debe ser secreta?**

Dentro de la cadena que devuelve `password_hash()`. No necesita ser secreta: su finalidad es hacer distinto cada hash. El secreto que el usuario debe proteger es su contraseña.

**¿Por qué no usar SHA-256?**

SHA-256 es útil para otros fines, pero es demasiado rápido como defensa directa de contraseñas. Argon2id y bcrypt están diseñados para encarecer cada intento de adivinación. En los contadores de login sí usamos SHA-256 para obtener claves de longitud fija; son usos diferentes.

**¿Qué diferencia hay entre hash y cifrado?**

El cifrado permite recuperar el original con una clave. El hash de contraseña se usa para verificar una candidata, sin recuperar la contraseña almacenada.

**¿Por qué token CSRF si ya hay cookie de sesión?**

El navegador adjunta automáticamente su cookie. El token demuestra además que la solicitud lleva un valor que nuestra aplicación entregó a esa sesión. SameSite ayuda, pero usamos ambas medidas.

**¿Por qué validar en PHP si HTML ya tiene required y maxlength?**

Porque se puede llamar directamente a la API sin usar el formulario. El servidor es quien debe decidir si acepta la operación.

**¿Cómo demostramos que no basta con cambiar el ID para modificar otra cuenta?**

Una cuenta sin privilegio administrativo recibe 403 al llamar la API, aunque conozca un ID válido. Además se comprueba en cada solicitud que su permiso y su sesión siguen vigentes. En los futuros préstamos habrá que agregar reglas de propiedad para cada recurso.

**¿Las pruebas garantizan seguridad total?**

No. Demuestran el comportamiento de los controles probados. Hay que mantener el servidor actualizado, usar HTTPS, vigilar eventos y volver a evaluar cada módulo nuevo. La instalación local de PHP 8.0.30 todavía requiere actualización antes de publicarse.

**¿Por qué usar una tabla de marcas?**

Para evitar repetir nombres libremente y centralizar su gestión. Varios equipos pueden referenciar la misma marca; la clave foránea mantiene esa relación consistente.

**¿Por qué crear primero categoría, estado y condición?**

Porque Equipo depende de esas tres claves foráneas. Preparar primero los catálogos permite escoger valores controlados, evita variaciones escritas a mano y garantiza que cada equipo nazca con relaciones válidas. Marca es la excepción de interfaz: se escribe en Equipo y el servidor la normaliza en su tabla.

## Qué mostrar en el código

1. `api/admin/C_Usuarios.php`: las comprobaciones de sesión y administrador aparecen antes de las operaciones de negocio.
2. `api/core/Auth.php`: CSRF, revalidación del usuario y versión de sesión.
3. `api/core/Password.php`: política, algoritmo y llamadas a las funciones de PHP.
4. `api/core/LoginThrottle.php`: reserva transaccional de intentos.
5. `api/models/M_Usuarios.php`: parámetros preparados, validación y protección de cuentas administrativas.
6. `database/migrations/20261103_seguridad_marcas.sql`: cambios aditivos y relación equipo–marca.
7. `api/models/M_CatalogosEquipo.php` y `api/admin/C_CatalogoEquipo.php`: CRUD compartido, validación e integridad de categorías, estados y condiciones.
8. `api/models/M_Equipos.php` y `api/admin/C_Equipos.php`: selección de relaciones existentes y alta transaccional de la marca.
9. `tests/security_http.php` y `tests/throttle.php`: evidencia reproducible, con base de pruebas aislada.

Explica el propósito de cada control antes de leer líneas. No muestres `.env`, respaldos con datos personales, contraseñas ni hashes reales durante la exposición.
