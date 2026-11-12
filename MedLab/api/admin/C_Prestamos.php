<?php
require_once __DIR__ . '/../core/Auth.php';
Auth::proteger(true, false);
require_once __DIR__ . '/../core/Connection.php';
require_once __DIR__ . '/../models/M_Prestamos.php';
try {
    $model = new M_Prestamos(new Connection());
    $user = (int)$_SESSION['usuario']['id_usuario'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') Response::success('Préstamos obtenidos correctamente.', $model->listar($user, Auth::esAdministrador()), 1);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Método no permitido.', 405, 405);
    $action = $_POST['accion'] ?? '';
    if (!is_string($action)) throw new RuntimeException('Acción no válida.', 400);
    $result = $model->ejecutar($action, $_POST, $user, Auth::esAdministrador());
    Response::success('Operación de préstamo registrada correctamente.', $result, 1);
} catch (PDOException $e) {
    $number = (int)($e->errorInfo[1] ?? 0);
    if (in_array($number, [1205,1213], true)) Response::error('La operación coincidió con otro cambio. Intenta nuevamente.',409,409);
    if ($number === 1452) Response::error('Una referencia ya no existe. Recarga la página.',422,422);
    if ($number === 1062) Response::error('La operación ya existe o repite datos únicos.',409,409);
    error_log('[MedLab] Préstamos: fallo de base de datos.');
    Response::error('No se pudo completar la operación. Intenta nuevamente.',500,500);
} catch (RuntimeException $e) {
    $status = $e->getCode();
    if (!in_array($status, [400,403,404,409,422], true)) { error_log('[MedLab] Préstamos: fallo interno.'); Response::error('No se pudo completar la operación.', 500, 500); }
    Response::error($e->getMessage(), $status, $status);
} catch (Throwable $e) {
    error_log('[MedLab] Préstamos: fallo interno.');
    Response::error('No se pudo completar la operación. Intenta nuevamente.', 500, 500);
}
