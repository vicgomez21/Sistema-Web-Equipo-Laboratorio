<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';

Request::metodos(['POST']);
$datos = Request::form();
if (!Auth::validarToken($datos['csrf'] ?? null)) {
    http_response_code(403);
    exit('Solicitud no válida. Regresa al login y recarga la página.');
}
if (($datos['accion'] ?? '') === 'salir') {
    Auth::cerrar();
    header('Location: ../../login.php');
    exit;
}

$identificador = trim($datos['identificador'] ?? '');
$password = $datos['password'] ?? '';
if ($identificador === '' || mb_strlen($identificador, 'UTF-8') > 150
    || $password === '' || strlen($password) > 72 || strpos($password, "\0") !== false) {
    $_SESSION['login_error'] = 'Credenciales incorrectas o cuenta inactiva.';
} else {
    require_once __DIR__ . '/../core/Connection.php';
    require_once __DIR__ . '/../core/LoginThrottle.php';
    require_once __DIR__ . '/../models/M_Auth.php';
    try {
        $db = new Connection();
        $limite = new LoginThrottle($db);
        // Cabeceras de proxy no verificadas pueden ser falsificadas: solo REMOTE_ADDR.
        if (!$limite->reservar($identificador, $_SERVER['REMOTE_ADDR'] ?? '')) {
            $_SESSION['login_error'] = 'Demasiados intentos. Espera 15 minutos e intenta nuevamente.';
        } else {
            $usuario = (new M_Auth($db))->AUTENTICAR($identificador, $password);
            if ($usuario !== null) {
                Auth::iniciar($usuario);
                header('Location: ../../index.php');
                exit;
            }
            $_SESSION['login_error'] = 'Credenciales incorrectas o cuenta inactiva.';
        }
    } catch (Throwable $e) {
        error_log('[MedLab] Login: ' . $e->getMessage());
        $_SESSION['login_error'] = 'No pudimos iniciar sesión. Intenta nuevamente.';
    }
}
header('Location: ../../login.php');
exit;
