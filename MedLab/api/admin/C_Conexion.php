<?php
require_once(__DIR__ . "/../core/Connection.php");
require_once(__DIR__ . "/../models/M_Conexion.php");
require_once(__DIR__ . "/../core/Response.php");

$conection = new Connection();
$response = new Response();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $modelo = new M_Conexion($conection, $response);
            $modelo->VERIFICAR_CONEXION();
        } catch (\Throwable $th) {
            error_log("[MedLab] C_Conexion: " . $th->getMessage());
            $response->error("Error al verificar la conexión", 2001, 400);
        }
        break;
    default:
        $response->error("Método no permitido", 2005, 405);
        break;
}
