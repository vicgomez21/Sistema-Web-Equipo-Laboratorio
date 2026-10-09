<?php
require_once(__DIR__ . "/Env.php");
Env::load(__DIR__ . "/.env");

#   ERRORES: nunca se muestran al usuario final, solo se guardan en el log de PHP
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

#   SEGURIDAD Y SESIONES (preparado para el login de una etapa futura)
$conexionHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.cookie_secure', $conexionHttps ? '1' : '0'); // en XAMPP local se usa http
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

#   DEFINICIONES DEL ENTORNO
define("APP_NAME", Env::get('APP_NAME', 'MedLab'));

// DATABASE_URL acepta "servidor" o "servidor:puerto" (ej. localhost:3310)
$partesServidor = explode(':', Env::get('DATABASE_URL', 'localhost'), 2);
define("HOST_DB", $partesServidor[0]);
define("PORT_DB", $partesServidor[1] ?? '3306');

define("USER_DB", Env::get('DATABASE_USER', 'root'));
define("PASSWORD_DB", Env::get('DATABASE_PSSW', ''));
define("DATABASE", Env::get('DATABASE_NAME', 'MedLabDB'));
define("CHARSET", Env::get('DATABASE_CHARSET', 'utf8mb4'));
