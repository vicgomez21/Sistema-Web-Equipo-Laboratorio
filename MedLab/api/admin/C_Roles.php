<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
Auth::administrador(true);
Request::metodos(['GET', 'POST', 'PUT', 'DELETE']);
require_once(__DIR__ . "/../core/Connection.php");
require_once(__DIR__ . "/../models/M_Roles.php");
require_once(__DIR__ . "/../core/Response.php");

$conection = new Connection();
$response = new Response();
$method = $_SERVER['REQUEST_METHOD'];

// PHP solo llena $_POST con el método POST; en PUT y DELETE hay que leer el cuerpo a mano
$cuerpo = $method === 'GET' ? [] : Request::form();

switch ($method) {
    case 'GET':
        try {
            $modelo = new M_Roles($conection, $response);
            $modelo->SELECT_ROLES();
        } catch (\Throwable $th) {
            error_log("[MedLab] C_Roles GET: " . $th->getMessage());
            $response->error("Error al obtener los roles", 2101, 500);
        }
        break;
    case 'POST':
        try {
            $modelo = new M_Roles($conection, $response);
            $modelo->INSERT_ROL($cuerpo);
        } catch (\Throwable $th) {
            error_log("[MedLab] C_Roles POST: " . $th->getMessage());
            $response->error("Error al agregar el rol", 2102, 500);
        }
        break;
    case 'PUT':
        try {
            $modelo = new M_Roles($conection, $response);
            $modelo->UPDATE_ROL($cuerpo);
        } catch (\Throwable $th) {
            error_log("[MedLab] C_Roles PUT: " . $th->getMessage());
            $response->error("Error al actualizar el rol", 2103, 500);
        }
        break;
    case 'DELETE':
        try {
            $modelo = new M_Roles($conection, $response);
            $modelo->DELETE_ROL($cuerpo['id_rol'] ?? null);
        } catch (\Throwable $th) {
            error_log("[MedLab] C_Roles DELETE: " . $th->getMessage());
            $response->error("Error al eliminar el rol", 2104, 500);
        }
        break;
    default:
        $response->error("Método no permitido", 2105, 405);
        break;
}
