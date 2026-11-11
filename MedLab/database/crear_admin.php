<?php
// Ejecutar únicamente desde la terminal; no expone un registro público.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../api/core/Connection.php';
require_once __DIR__ . '/../api/core/Password.php';
fwrite(STDOUT, "Nombres: "); $nombre = trim((string)fgets(STDIN));
fwrite(STDOUT, "Apellidos: "); $apellidos = trim((string)fgets(STDIN));
fwrite(STDOUT, "Correo: "); $correo = trim((string)fgets(STDIN));
fwrite(STDOUT, "Código: "); $codigo = trim((string)fgets(STDIN));
fwrite(STDOUT, "Contraseña (15 caracteres mínimo, 72 bytes máximo; visible en esta terminal): ");
$password = rtrim((string)fgets(STDIN), "\r\n");
if ($nombre === '' || mb_strlen($nombre) > 60 || $apellidos === '' || mb_strlen($apellidos) > 60
    || !filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150
    || $codigo === '' || mb_strlen($codigo) > 30 || Password::validarNueva($password) !== null) {
    fwrite(STDERR, "Datos no válidos. No se creó la cuenta.\n");
    exit(1);
}
$db = new Connection();
try {
    $hash = Password::crear($password);
    unset($password);
    $db->beginTransaction();
    // Mismo orden de bloqueos que los CRUD: roles administradores y luego sus usuarios.
    $db->query('SELECT id_rol FROM roles WHERE es_administrador = 1 ORDER BY id_rol FOR UPDATE')->fetchAll();
    $db->query('SELECT u.id_usuario, u.version_sesion FROM usuarios u
        INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.activo = 1 AND r.es_administrador = 1
        ORDER BY u.id_usuario FOR UPDATE')->fetchAll();
    $db->exec("INSERT INTO roles (nombre, descripcion, es_administrador)
        VALUES ('Administrador', 'Administración del sistema', 1)
        ON DUPLICATE KEY UPDATE es_administrador = 1");
    $id = $db->query("SELECT id_rol FROM roles WHERE nombre = 'Administrador'")->fetchColumn();
    $cruce = $db->prepare('SELECT id_usuario FROM usuarios WHERE correo = :codigo OR codigo_usuario = :correo LIMIT 1');
    $cruce->execute(['codigo' => $codigo, 'correo' => $correo]);
    if ($cruce->fetchColumn() !== false) {
        throw new RuntimeException('El código o correo coincide con otro identificador.');
    }
    $stmt = $db->prepare('INSERT INTO usuarios (codigo_usuario,nombres,apellidos,correo,password_hash,id_rol) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$codigo,$nombre,$apellidos,$correo,$hash,$id]);
    $db->commit(); fwrite(STDOUT, "Cuenta creada. Ya puedes iniciar sesión.\n");
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[MedLab] crear_admin: ' . $e->getMessage());
    fwrite(STDERR, "No se creó la cuenta. Revisa la migración, la conexión y los datos repetidos.\n");
    exit(1);
}
