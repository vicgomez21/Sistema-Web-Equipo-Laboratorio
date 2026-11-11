<?php
if (!isset($tipoCatalogo) || !in_array($tipoCatalogo, ['categorias', 'estados', 'condiciones'], true)) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
Auth::administrador(true);
Request::metodos(['GET', 'POST', 'PUT', 'DELETE']);
require_once __DIR__ . '/../core/Connection.php';
require_once __DIR__ . '/../models/M_CatalogosEquipo.php';
require_once __DIR__ . '/../core/Response.php';

$connection = new Connection();
$response = new Response();
$method = $_SERVER['REQUEST_METHOD'];
$cuerpo = $method === 'GET' ? [] : Request::form();
$modelo = M_CatalogosEquipo::{$tipoCatalogo}($connection, $response);

try {
    switch ($method) {
        case 'GET':
            $modelo->SELECT_CATALOGO();
            break;
        case 'POST':
            $modelo->INSERT_CATALOGO($cuerpo);
            break;
        case 'PUT':
            $modelo->UPDATE_CATALOGO($cuerpo);
            break;
        case 'DELETE':
            $modelo->DELETE_CATALOGO($cuerpo[$modelo->campoId()] ?? null);
            break;
    }
} catch (Throwable $error) {
    error_log('[MedLab] Catálogo ' . $tipoCatalogo . ' ' . $method . ': ' . $error->getMessage());
    $response->error('No se pudo completar la operación del catálogo.', 2500, 500);
}
