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
$identificador = is_string($_POST['identificador'] ?? null) ? trim($_POST['identificador']) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
if ($identificador === '' || strlen($identificador) > 150 || $password === '' || strlen($password) > 1024) {
    $_SESSION['login_error'] = 'Completa tu correo o código y contraseña.';
} else {
    require_once __DIR__ . '/../core/Connection.php';
    require_once __DIR__ . '/../models/M_Auth.php';
    require_once __DIR__ . '/../core/LoginLimiter.php';
    try {
        $db = new Connection();
        // Correo y código de una misma cuenta comparten el contador.
        $accountQuery = $db->prepare('SELECT id_usuario FROM usuarios WHERE correo = :correo OR codigo_usuario = :codigo LIMIT 1');
        $accountQuery->execute(['correo' => $identificador, 'codigo' => $identificador]);
        $accountId = $accountQuery->fetchColumn();
        $accountKey = $accountId === false ? 'unknown:' . mb_strtolower($identificador) : 'user:' . $accountId;
        $limiter = new LoginLimiter();
        $retry = $limiter->reserve($accountKey, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($retry > 0) {
            $_SESSION['login_error'] = 'Demasiados intentos de acceso. Espera unos minutos antes de intentar nuevamente.';
            http_response_code(429);
            header('Retry-After: ' . $retry);
            header('Content-Type: text/html; charset=utf-8');
            exit('<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso temporalmente limitado | MedLab</title><body><main><h1>Espera antes de volver a intentar</h1><p>Se alcanzó el límite temporal de intentos de acceso.</p><p><a href="../../login.php">Volver a iniciar sesión</a></p></main></body></html>');
        }
        $usuario = (new M_Auth($db))->AUTENTICAR($identificador, $password);
        if ($usuario) {
            $limiter->success($accountKey);
            session_regenerate_id(true);
            $_SESSION['usuario'] = $usuario; $_SESSION['actividad'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: ../../index.php'); exit;
        }
        $_SESSION['login_error'] = 'Credenciales incorrectas o cuenta inactiva.';
    } catch (Throwable $e) {
        error_log('[MedLab] Login: ' . $e->getMessage());
        $_SESSION['login_error'] = 'No pudimos iniciar sesión. Intenta nuevamente.';
        http_response_code(503);
        header('Retry-After: 60');
        exit('No pudimos iniciar sesión. Intenta nuevamente en unos momentos.');
    }
}
header('Location: ../../login.php'); exit;
