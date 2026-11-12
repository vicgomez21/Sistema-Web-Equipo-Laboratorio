<?php
require_once __DIR__ . '/../core/Auth.php';
Auth::proteger(true, false);
require_once __DIR__ . '/../core/Connection.php';
try {
    $db = new Connection(); $user = (int)$_SESSION['usuario']['id_usuario'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $q = $db->prepare('SELECT id_notificacion,id_prestamo,evento,mensaje,fecha_creacion,fecha_lectura FROM notificaciones WHERE id_usuario=? ORDER BY id_notificacion DESC LIMIT 200');
        $q->execute([$user]); Response::success('Notificaciones obtenidas correctamente.', $q->fetchAll(), 1);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405, 405);
    $raw = $_POST['id_notificacion'] ?? null;
    if ((!is_string($raw) && !is_int($raw)) || !preg_match('/^[1-9][0-9]*$/D',(string)$raw) || filter_var($raw,FILTER_VALIDATE_INT)===false) Response::error('Notificación no válida.',422,422);
    $db->beginTransaction();
    $q=$db->prepare('SELECT id_notificacion FROM notificaciones WHERE id_notificacion=? AND id_usuario=? FOR UPDATE');$q->execute([$raw,$user]);
    if(!$q->fetchColumn()){ $db->rollBack();Response::error('La notificación no existe.',404,404); }
    $q=$db->prepare('UPDATE notificaciones SET fecha_lectura=COALESCE(fecha_lectura,CURRENT_TIMESTAMP) WHERE id_notificacion=? AND id_usuario=?');$q->execute([$raw,$user]);
    $db->commit();Response::success('Notificación marcada como leída.',[],1);
} catch(Throwable $e) {
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    error_log('[MedLab] Notificaciones: fallo interno.');Response::error('No se pudo completar la operación.',500,500);
}
