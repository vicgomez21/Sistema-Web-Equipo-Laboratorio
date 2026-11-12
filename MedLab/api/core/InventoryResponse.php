<?php
function inventoryError(Throwable $e):void {
    if($e instanceof PDOException){$mysql=(int)($e->errorInfo[1]??0);if($mysql===1062)Response::error('Ya existe un registro con ese código, nombre o número de serie.',409,409);if($mysql===1451)Response::error('No se puede eliminar: existen instrumentos o movimientos asociados.',409,409);if($mysql===1452)Response::error('La categoría, estado o condición seleccionada ya no existe.',422,422);if(in_array($mysql,[1205,1213],true))Response::error('Otro usuario está modificando estas existencias. Intenta nuevamente.',409,409);}
    if($e instanceof RuntimeException&&in_array($e->getCode(),[400,403,404,409,422],true))Response::error($e->getMessage(),$e->getCode(),$e->getCode());
    error_log('[MedLab] Inventario: '.$e->getMessage());Response::error('No pudimos completar la operación de inventario. Intenta nuevamente.',500,500);
}
