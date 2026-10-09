<?php
require_once(__DIR__ . "/../core/Connection.php");
require_once(__DIR__ . "/../models/M_Usuarios.php");
require_once(__DIR__ . "/../core/Response.php");

$conection = new Connection();
$response = new Response();
$method = $_SERVER['REQUEST_METHOD'];

// PHP solo llena $_POST con el método POST; en PUT y DELETE hay que leer el cuerpo a mano
parse_str(file_get_contents("php://input"), $cuerpo);

switch ($method) {
    case 'GET':
        try {
            $modelo = new M_Usuarios($conection, $response);
            $modelo->SELECT_USUARIOS();
        } catch (\Throwable $th) {
            error_log("[MedLab] C_Usuarios GET: " . $th->getMessage());
            $response->error("Error al obtener los usuarios", 2201, 400);
        }
        break;
    case 'POST':
        try {
            $modelo = new M_Usuarios($conection, $response);
            $modelo->INSERT_USUARIO($_POST);
        } catch (\Throwable $th) {
            error_log("[MedLab] C_Usuarios POST: " . $th->getMessage());
            $response->error("Error al agregar el usuario", 2202, 400);
        }
        break;
    case 'PUT':
        try {
            $modelo = new M_Usuarios($conection, $response);
            $modelo->UPDATE_USUARIO($cuerpo);
        } catch (\Throwable $th) {
            error_log("[MedLab] C_Usuarios PUT: " . $th->getMessage());
            $response->error("Error al actualizar el usuario", 2203, 400);
        }
        break;
    case 'DELETE':
        try {
            $modelo = new M_Usuarios($conection, $response);
            $modelo->DELETE_USUARIO($cuerpo['id_usuario'] ?? null);
        } catch (\Throwable $th) {
            error_log("[MedLab] C_Usuarios DELETE: " . $th->getMessage());
            $response->error("Error al eliminar el usuario", 2204, 400);
        }
        break;
    default:
        $response->error("Método no permitido", 2205, 405);
        break;
}
