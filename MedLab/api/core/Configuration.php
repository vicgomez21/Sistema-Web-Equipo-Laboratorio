<?php
require_once __DIR__ . '/Env.php';
Env::load(__DIR__ . '/.env');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Guatemala');

$conexionHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    if ($conexionHttps) header('Strict-Transport-Security: max-age=31536000');
    // No confiar en cabeceras Forwarded enviadas por el cliente. TLS debe terminar aquí.
    if (Env::get('APP_ENV', 'local') === 'production' && !$conexionHttps) {
        http_response_code(400);
        exit('Este servicio requiere HTTPS.');
    }
}

set_exception_handler(function (Throwable $error): void {
    error_log('[MedLab] Error no controlado: ' . $error->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "No se pudo completar la operación. Revisa el registro del servidor.\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['code' => 500, 'message' => 'No se pudo completar la operación.']));
});

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '1800');
    session_name('MEDLABSESSID');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $conexionHttps,
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

define('APP_NAME', Env::get('APP_NAME', 'MedLab'));
$partesServidor = explode(':', Env::get('DATABASE_URL', 'localhost'), 2);
define('HOST_DB', $partesServidor[0]);
define('PORT_DB', $partesServidor[1] ?? '3306');
define('USER_DB', Env::get('DATABASE_USER', 'usr_medlab'));
define('PASSWORD_DB', Env::get('DATABASE_PSSW', ''));
define('DATABASE', Env::get('DATABASE_NAME', 'MedLabDB'));
define('CHARSET', Env::get('DATABASE_CHARSET', 'utf8mb4'));
