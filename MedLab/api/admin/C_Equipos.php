<?php
require_once __DIR__.'/../core/Auth.php';
$method=$_SERVER['REQUEST_METHOD'];
Auth::proteger(true,$method!=='GET');
require_once __DIR__.'/../models/M_Inventario.php';require_once __DIR__.'/../core/InventoryResponse.php';
try{
    $model=new M_Inventario(new Connection());parse_str(file_get_contents('php://input'),$body);
    switch($method){
        case 'GET':Response::success('Inventario actualizado.',$model->equipos(Auth::esAdministrador()),1);
        case 'POST':Response::success('Instrumento registrado.',$model->guardarEquipo($_POST,false),1);
        case 'PUT':Response::success('Instrumento actualizado.',$model->guardarEquipo($body,true),1);
        case 'DELETE':Response::success('Instrumento eliminado.',$model->eliminarEquipo($body),1);
        default:Response::error('Método no permitido.',405,405);
    }
}catch(Throwable $e){inventoryError($e);}
