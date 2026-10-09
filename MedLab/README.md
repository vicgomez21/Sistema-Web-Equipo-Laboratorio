# MedLab - Sistema de Gestión de Equipos de Laboratorio

PHP nativo + MySQL (XAMPP). Etapa 2: menú hamburguesa y CRUD de **Roles** y **Usuarios**.

## Instalación en XAMPP
1. Copia la carpeta `MedLab` dentro de `C:\xampp\htdocs\`.
2. En el Panel de XAMPP pulsa **Start** en **Apache** y **MySQL**.
3. En `http://localhost/phpmyadmin` (pestaña **Importar**) importa `database/MedLabDB.sql`.
4. En la pestaña **SQL** ejecuta `database/usuario_mysql.sql` (crea el usuario `usr_proyecto` del `.env`).
5. Abre **http://localhost/MedLab/**

## Configuración (`api/core/.env`)
```
DATABASE_URL=localhost:3310     # servidor:puerto (cambia a 3306 si tu MySQL usa el puerto por defecto)
DATABASE_USER=usr_proyecto
DATABASE_PSSW=...
DATABASE_NAME=MedLabDB
DATABASE_CHARSET=utf8mb4
```
Para usar `root` sin contraseña: `DATABASE_USER=root` y `DATABASE_PSSW=` (vacío).

## Uso
1. Menú ☰ → **Roles** → crea al menos un rol (ej. Administrador, Encargado, Estudiante).
2. Menú ☰ → **Usuarios** → crea usuarios asignándoles un rol.

## Estructura
```
MedLab/
├── index.php, roles.php, usuarios.php     páginas
├── includes/                              encabezado/pie compartidos (menú hamburguesa)
├── api/
│   ├── admin/   C_Roles, C_Usuarios, C_Conexion      controladores (GET/POST/PUT/DELETE)
│   ├── models/  M_Roles, M_Usuarios, M_Conexion      consultas SQL con PDO preparado
│   └── core/    .env, Env, Configuration, Connection, Response
├── css/styles.css
├── js/lib/      app.js (común), inicio.js, roles.js, usuarios.js
├── database/    MedLabDB.sql, usuario_mysql.sql
└── assets/
```

## Agregar un módulo nuevo
1. Crear `C_Modulo.php` y `M_Modulo.php` copiando el patrón de Roles.
2. Crear `modulo.php` y `js/lib/modulo.js` copiando `roles.php` / `roles.js`.
3. En `includes/layout_inicio.php` cambiar `'archivo' => null` por `'modulo.php'`.
