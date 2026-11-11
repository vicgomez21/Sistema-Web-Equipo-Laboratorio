<?php
require_once __DIR__ . '/Configuration.php';
require_once __DIR__ . '/Response.php';

class Auth
{
    public static function token(): string
    {
        return $_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(32)));
    }

    public static function validarToken($token): bool
    {
        return is_string($token) && hash_equals(self::token(), $token);
    }

    public static function iniciar(array $usuario): void
    {
        session_regenerate_id(true);
        $_SESSION = ['usuario' => $usuario, 'actividad' => time(), 'inicio' => time(),
            'csrf' => bin2hex(random_bytes(32))];
    }

    public static function cerrar(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000,
                'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'],
                'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?? 'Strict']);
        }
        session_destroy();
    }

    private static function rechazar(bool $api): void
    {
        self::cerrar();
        if ($api) Response::error('Tu sesión terminó. Inicia sesión nuevamente.', 401, 401);
        header('Location: login.php');
        exit;
    }

    public static function proteger(bool $api = false): void
    {
        // Inactividad: 30 minutos. Duración absoluta: 8 horas.
        if (empty($_SESSION['usuario']['id_usuario']) ||
            time() - ($_SESSION['actividad'] ?? 0) > 1800 ||
            time() - ($_SESSION['inicio'] ?? 0) > 28800) {
            self::rechazar($api);
        }
        require_once __DIR__ . '/Connection.php';
        // Comprobar privilegios y revocación en la BD en CADA solicitud.
        $db = new Connection();
        $stmt = $db->prepare('SELECT u.id_usuario, u.nombres, u.apellidos, u.id_rol,
            u.activo, u.version_sesion, r.nombre AS rol, r.es_administrador
            FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
            WHERE u.id_usuario = :id');
        $stmt->execute(['id' => $_SESSION['usuario']['id_usuario']]);
        $usuario = $stmt->fetch();
        if (!$usuario || !(int)$usuario['activo'] ||
            (int)$usuario['version_sesion'] !== (int)($_SESSION['usuario']['version_sesion'] ?? 0)) {
            self::rechazar($api);
        }
        unset($usuario['activo']);
        $_SESSION['usuario'] = $usuario;
        $_SESSION['actividad'] = time();
        if ($api && !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD', 'OPTIONS'], true) &&
            !self::validarToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            Response::error('Solicitud no válida. Recarga la página.', 403, 403);
        }
    }

    public static function esAdministrador(): bool
    {
        return (int)($_SESSION['usuario']['es_administrador'] ?? 0) === 1;
    }

    public static function administrador(bool $api = false): void
    {
        self::proteger($api);
        if (!self::esAdministrador()) {
            if ($api) Response::error('No tienes permiso para administrar este recurso.', 403, 403);
            http_response_code(403);
            exit('No tienes permiso para acceder a esta página.');
        }
    }
}
