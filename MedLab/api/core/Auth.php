<?php
require_once __DIR__ . '/Configuration.php';
require_once __DIR__ . '/Response.php';
class Auth {
    static function token(): string {
        return $_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(32)));
    }
    static function validarToken($token): bool {
        return is_string($token) && hash_equals(self::token(), $token);
    }
    static function proteger(bool $api = false): void {
        header('Cache-Control: no-store');
        if (empty($_SESSION['usuario']) || time() - ($_SESSION['actividad'] ?? 0) > 1800) {
            unset($_SESSION['usuario']);
            if ($api) Response::error('Tu sesión terminó. Inicia sesión nuevamente.', 401, 401);
            header('Location: login.php'); exit;
        }
        $_SESSION['actividad'] = time();
        if ($api && $_SERVER['REQUEST_METHOD'] !== 'GET' && !self::validarToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            Response::error('Solicitud no válida. Recarga la página.', 403, 403);
        }
    }
}
