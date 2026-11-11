# MedLab — Gestión de equipos de laboratorio

PHP nativo + MySQL/MariaDB. Implementado: login, sesiones y CRUD administrativo de usuarios, roles, categorías, estados de equipo, condiciones de equipo y equipos. El inventario se configura en ese orden; la marca se captura dentro de Equipo. Los préstamos todavía están pendientes.

## Instalación nueva en XAMPP

1. Copia MedLab dentro de `C:\xampp\htdocs\` y enciende Apache y MySQL.
2. Importa `database/MedLabDB.sql` con una cuenta administradora de MySQL. Este archivo es para una base nueva; no lo importes sobre datos existentes.
3. Copia `api/core/.env.example` a `api/core/.env`. Ajusta puerto, base, usuario exclusivo y contraseña.
4. En `database/usuario_mysql.sql`, sustituye el marcador por una contraseña aleatoria única y ejecuta el SQL como DBA. Debe coincidir con el `.env`. El usuario de aplicación tiene únicamente SELECT, INSERT, UPDATE y DELETE.
5. Crea el primer administrador desde terminal con una cuenta DBA configurada temporalmente para ese comando:

   ```powershell
   $env:DATABASE_USER = 'root'
   $env:DATABASE_PSSW = Read-Host 'Contraseña DBA local' -MaskInput
   C:\xampp\php\php.exe database/crear_admin.php
   Remove-Item Env:DATABASE_USER,Env:DATABASE_PSSW
   ```

   Ejecuta el aprovisionamiento administrativo como tarea DBA separada del servicio web. Las variables del proceso prevalecen sobre `.env` y no cambian el archivo. El script pide la contraseña del nuevo usuario y avisa que será visible en terminal. Evita grabar la sesión.
6. Abre [MedLab local](http://localhost/MedLab/) e inicia sesión. Solo administradores acceden a los CRUD, usuarios, roles y diagnóstico.

## Instalación existente

Respalda la base y detén escrituras durante el cambio. Ejecuta `database/migrations/20261103_seguridad_marcas.sql` como DBA antes de usar el código actualizado. Conserva marcas anteriores, agrega versiones de sesión y actualiza el formato anterior de los contadores de login. Es repetible; las operaciones DDL hacen commits implícitos.

Usa un usuario MySQL exclusivo de MedLab. No revoques permisos ni cambies la contraseña de una cuenta compartida con otras aplicaciones sin coordinarlas. El script de permisos afecta únicamente `usr_medlab`; no modifica cuentas anteriores de otros proyectos.

## Configuración

```dotenv
APP_NAME=MedLab
APP_ENV=local
DATABASE_URL=localhost:3310
DATABASE_USER=usr_medlab
DATABASE_PSSW=UNA_CLAVE_ALEATORIA_EXCLUSIVA
DATABASE_NAME=MedLabDB
DATABASE_CHARSET=utf8mb4
```

Ajusta el puerto a tu instalación. En producción usa HTTPS y `APP_ENV=production`: las solicitudes HTTP se rechazan. No se confía automáticamente en cabeceras de proxy. Apache necesita `mod_rewrite`, `AllowOverride All` y aplicar los `.htaccess` para impedir acceso a configuración, SQL y carpetas internas. Otro servidor necesita restricciones equivalentes.

## Estructura

- `index.php`, `login.php`, `usuarios.php`, `roles.php`: acceso y administración.
- `categorias.php`, `estados_equipo.php`, `condiciones_equipo.php`, `equipos.php`: flujo ordenado del inventario.
- `api/admin/`: controladores públicos con autenticación, permisos, CSRF y métodos permitidos.
- `api/core/`: entorno, conexión, sesión, contraseñas, límites y validación HTTP; sin acceso web directo.
- `api/models/`: consultas preparadas y reglas de negocio; sin acceso web directo.
- `database/`: esquema, migración, permisos y creación CLI del administrador; sin acceso web directo.
- `js/lib/`: interfaz y solicitudes con token CSRF.
- `tests/`: pruebas de contraseñas e integración aislada; sin acceso web directo.
- `docs/SEGURIDAD.md`: explicación de medidas, verificación y pendientes.
- `docs/DEFENSA_ORAL.md`: exposición y respuestas a preguntas.

## Comprobaciones

```powershell
C:\xampp\php\php.exe tests/passwords.php
```

Las suites `tests/security_http.php` y `tests/throttle.php` requieren una BD desechable; sus controles impiden usar la base normal del proyecto. Consulta `docs/SEGURIDAD.md` para conocer puertos y variables de pruebas. No guardes contraseñas ni respaldos en archivos públicos o en el repositorio.

## Agregar un módulo

Cada página debe llamar `Auth::proteger()`; cada API debe exigir autenticación y los permisos adecuados, métodos explícitos y CSRF para escrituras. Valida la entrada con `Request::form()` y en el modelo, usa PDO preparado y escapa la salida. Para recursos propios de cada usuario, comprueba también la propiedad: el login por sí solo no autoriza cualquier registro.

## Flujo para registrar equipos

1. Crea las categorías.
2. Crea los estados de equipo.
3. Crea las condiciones de equipo.
4. Abre Equipos y selecciona los tres catálogos anteriores.
5. Escribe la marca; si no existe, el servidor la crea y la relaciona dentro de la misma transacción.

Una categoría inactiva no puede asignarse y una clasificación utilizada no puede eliminarse. No hay una pantalla CRUD independiente para marcas.

## Pendiente antes de publicar

Actualizar el PHP instalado (8.0.30, sin soporte de seguridad) y los demás componentes de XAMPP, habilitar HTTPS, restringir MySQL/phpMyAdmin y preparar monitoreo y respaldos con restauración probada. Los controles incorporados no garantizan inmunidad frente a ataques.
