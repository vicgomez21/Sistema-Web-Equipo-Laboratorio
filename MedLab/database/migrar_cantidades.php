<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../api/core/Connection.php';
require_once __DIR__.'/migrations/FlujoMigration.php';
$options=getopt('',['apply','map:','backup-dir:','maintenance']);
try{
    $mapping=[];
    if(isset($options['map']))$mapping=json_decode(file_get_contents($options['map']),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($mapping))throw new RuntimeException('El mapa de estados debe ser un objeto JSON ID:CODIGO.');
    $db=new Connection();
    if(!isset($options['apply'])){
        $states=$db->query('SELECT id_estado_prestamo,nombre FROM estados_prestamo ORDER BY id_estado_prestamo')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['modo'=>'solo lectura','estados_para_mapear'=>$states,'instrucciones'=>'Revisar mapa, respaldo y mantenimiento antes de usar --apply --maintenance --map=archivo.json --backup-dir=carpeta_privada'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
        exit;
    }
    if(!isset($options['maintenance'],$options['backup-dir']))throw new RuntimeException('Para aplicar se requiere --maintenance y --backup-dir fuera del directorio público.');
    echo json_encode(FlujoMigration::apply($db,$mapping,$options['backup-dir']),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'No se completó la migración: '.$e->getMessage().PHP_EOL);exit(1);}
