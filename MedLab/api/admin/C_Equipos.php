<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
Auth::administrador(true);
Request::metodos(['GET', 'POST', 'PUT', 'DELETE']);
require_once __DIR__ . '/../core/Connection.php';
require_once __DIR__ . '/../models/M_Equipos.php';
require_once __DIR__ . '/../core/Response.php';

$connection = new Connection();
$response = new Response();
$method = $_SERVER['REQUEST_METHOD'];
$cuerpo = $method === 'GET' ? [] : Request::form();
$modelo = new M_Equipos($connection, $response);

try {
    switch ($method) {
        case 'GET':
            $modelo->SELECT_EQUIPOS();
            break;
        case 'POST':
            $modelo->INSERT_EQUIPO($cuerpo);
            break;
        case 'PUT':
            $modelo->UPDATE_EQUIPO($cuerpo);
            break;
        case 'DELETE':
            $modelo->DELETE_EQUIPO($cuerpo['id_equipo'] ?? null);
            break;
    }
} catch (Throwable $error) {
    error_log('[MedLab] C_Equipos ' . $method . ': ' . $error->getMessage());
    $response->error('No se pudo completar la operación con el equipo.', 2400, 500);
}
