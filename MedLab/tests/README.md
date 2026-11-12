# Pruebas de seguridad

`passwords.php` es independiente de MySQL:

```powershell
C:\xampp\php\php.exe tests/passwords.php
```

Las demás suites deben usar una instancia MySQL/MariaDB desechable en loopback, separada del datadir real. Nunca reutilices el directorio `C:\xampp\mysql\data` para ellas.

1. Arranca una instancia temporal en `127.0.0.1:13316`, con datadir propio y cuenta de pruebas.
2. En una copia temporal de `database/MedLabDB.sql`, sustituye el nombre de base por `MedLabSecurityTest_20261103` y ejecútala en esa instancia. La prueba HTTP espera root sin contraseña solo en esta instancia local aislada; no cambies la configuración del servidor real para satisfacer esa condición.
3. Configura un Apache temporal en `127.0.0.1:18081`, con DocumentRoot apuntando al proyecto, PHP, mod_rewrite y AllowOverride All. Define en el proceso/virtualhost `DATABASE_URL=127.0.0.1:13316`, `DATABASE_NAME=MedLabSecurityTest_20261103`, `DATABASE_USER=root`, `DATABASE_PSSW=` y `APP_ENV=local`. Verifica que las variables vacías se mantengan y no caigan al `.env` del proyecto.
4. Ejecuta la suite HTTP:

   ```powershell
   $env:MEDLAB_SECURITY_TEST='1'
   C:\xampp\php\php.exe tests/security_http.php
   Remove-Item Env:MEDLAB_SECURITY_TEST
   ```

5. Para `throttle.php`, define las variables anteriores en la terminal. Puedes usar una cuenta temporal con permisos CRUD sobre la base de pruebas. La suite exige nombre `MedLabSecurityTest_*`, verifica la base conectada y crea/limpia únicamente sus propios fixtures y contadores. Ejecuta `C:\xampp\php\php.exe tests/throttle.php`.
6. Detén únicamente los procesos temporales que creaste y elimina su datadir después de verificar su ruta absoluta.

Para validar la migración, prueba además una base temporal con el esquema anterior: marcas como texto en equipos y/o `intentos_login(clave, intentos, inicio_ventana BIGINT UNSIGNED NOT NULL)`. Aplica dos veces una copia de la migración que apunte a esa base, verifica datos preservados y ejecuta la suite de contadores contra ella.

Resultados de esta revisión: 46 comprobaciones HTTP correctas; pruebas de contraseñas y contadores correctas; doce procesos de una cuenta admitieron exactamente cinco reservas y dieciséis procesos tras veintidós reservas de IP admitieron exactamente ocho más. También pasaron migración repetida y restricciones de claves foráneas. No se usó la contraseña de ningún usuario de la instalación real.
