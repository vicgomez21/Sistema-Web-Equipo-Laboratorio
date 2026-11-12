<?php
require_once __DIR__ . '/Response.php';

class Request
{
    public static function metodos(array $permitidos): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $permitidos, true)) {
            header('Allow: ' . implode(', ', $permitidos));
            Response::error('Método no permitido.', 405, 405);
        }
    }

    public static function form(): array
    {
        $tipo = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        if ($tipo !== 'application/x-www-form-urlencoded') {
            Response::error('Formato de solicitud no admitido.', 415, 415);
        }
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
            Response::error('La solicitud supera el tamaño permitido.', 413, 413);
        }
        $cuerpo = file_get_contents('php://input', false, null, 0, 16385);
        if ($cuerpo === false || strlen($cuerpo) > 16384) {
            Response::error('La solicitud supera el tamaño permitido.', 413, 413);
        }
        $datos = [];
        foreach ($cuerpo === '' ? [] : explode('&', $cuerpo) as $campo) {
            $partes = explode('=', $campo, 2);
            $clave = urldecode($partes[0]);
            $valor = urldecode($partes[1] ?? '');
            if (!preg_match('/\A[a-z][a-z0-9_]*\z/D', $clave) ||
                array_key_exists($clave, $datos) || !mb_check_encoding($valor, 'UTF-8') ||
                strpos($valor, "\0") !== false || count($datos) >= 32) {
                Response::error('Campos de solicitud no válidos.', 422, 422);
            }
            $datos[$clave] = $valor;
        }
        return $datos;
    }
}
