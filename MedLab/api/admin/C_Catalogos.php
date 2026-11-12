<?php
require_once __DIR__.'/../core/Auth.php';
$method=$_SERVER['REQUEST_METHOD'];Auth::proteger(true,$method!=='GET');
require_once __DIR__.'/../models/M_Inventario.php';require_once __DIR__.'/../core/InventoryResponse.php';
try{
    $tipo=$_GET['tipo']??'';if(!is_string($tipo))throw new RuntimeException('Catálogo no válido.',404);
    $model=new M_Inventario(new Connection());parse_str(file_get_contents('php://input'),$body);
    switch($method){
        case 'GET':Response::success('Catálogo actualizado.',$model->catalogo($tipo,Auth::esAdministrador()),1);
        case 'POST':Response::success('Registro creado.',$model->guardarCatalogo($tipo,$_POST,false),1);
        case 'PUT':Response::success('Registro actualizado.',$model->guardarCatalogo($tipo,$body,true),1);
        case 'DELETE':Response::success('Registro eliminado.',$model->eliminarCatalogo($tipo,$body),1);
        default:Response::error('Método no permitido.',405,405);
    }
}catch(Throwable $e){inventoryError($e);}
