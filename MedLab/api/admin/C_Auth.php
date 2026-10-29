<?php
require_once __DIR__ . '/../core/Auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!Auth::validarToken($_POST['csrf'] ?? null)) { http_response_code(403); exit('Solicitud no válida. Regresa al login y recarga la página.'); }
if (($_POST['accion'] ?? '') === 'salir') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy(); header('Location: ../../login.php'); exit;
}
$identificador = trim((string)($_POST['identificador'] ?? ''));
$password = (string)($_POST['password'] ?? '');
if ($identificador === '' || strlen($identificador) > 150 || $password === '' || strlen($password) > 1024) {
    $_SESSION['login_error'] = 'Completa tu correo o código y contraseña.';
} else {
    require_once __DIR__ . '/../core/Connection.php';
    require_once __DIR__ . '/../models/M_Auth.php';
    try {
        $usuario = (new M_Auth(new Connection()))->AUTENTICAR($identificador, $password);
        if ($usuario) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = $usuario; $_SESSION['actividad'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: ../../index.php'); exit;
        }
        $_SESSION['login_error'] = 'Credenciales incorrectas o cuenta inactiva.';
    } catch (Throwable $e) {
        error_log('[MedLab] Login: ' . $e->getMessage());
        $_SESSION['login_error'] = 'No pudimos iniciar sesión. Intenta nuevamente.';
    }
}
header('Location: ../../login.php'); exit;
