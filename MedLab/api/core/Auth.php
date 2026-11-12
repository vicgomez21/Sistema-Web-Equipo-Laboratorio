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
    static function esAdministrador(): bool {
        return strtolower(trim($_SESSION['usuario']['rol'] ?? '')) === 'administrador';
    }
    private static function sinSesion(bool $api): void {
        $_SESSION = [];
        if ($api) Response::error('Tu sesión terminó. Inicia sesión nuevamente.', 401, 401);
        header('Location: login.php');
        exit;
    }
    static function proteger(bool $api = false, bool $administrador = false): void {
        header('Cache-Control: no-store');
        if (empty($_SESSION['usuario']['id_usuario']) || time() - ($_SESSION['actividad'] ?? 0) > 1800) {
            self::sinSesion($api);
        }
        // Revalidar cuenta y rol: una sesión antigua no conserva permisos revocados.
        require_once __DIR__ . '/Connection.php';
        try {
            $db = new Connection();
            $stmt = $db->prepare('SELECT u.id_usuario, u.nombres, u.apellidos, u.activo, r.nombre AS rol FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = :id_usuario');
            $stmt->execute(['id_usuario' => $_SESSION['usuario']['id_usuario']]);
            $usuario = $stmt->fetch();
        } catch (Throwable $e) {
            error_log('[MedLab] Sesión: ' . $e->getMessage());
            if ($api) Response::error('No pudimos verificar tu sesión. Intenta nuevamente.', 500, 500);
            http_response_code(503);
            exit('No pudimos verificar tu sesión. Intenta nuevamente.');
        }
        if (!$usuario || !$usuario['activo']) self::sinSesion($api);
        unset($usuario['activo']);
        $_SESSION['usuario'] = $usuario;
        if ($administrador && !self::esAdministrador()) {
            if ($api) Response::error('No tienes permiso para realizar esta acción.', 403, 403);
            http_response_code(403);
            exit('No tienes permiso para acceder a esta página.');
        }
        if ($api && $_SERVER['REQUEST_METHOD'] !== 'GET' && !self::validarToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            Response::error('Solicitud no válida. Recarga la página.', 403, 403);
        }
        $_SESSION['actividad'] = time();
    }
}
