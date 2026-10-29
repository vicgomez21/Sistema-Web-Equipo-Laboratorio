<?php
// Ejecutar únicamente desde la terminal; no expone un registro público.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../api/core/Connection.php';
fwrite(STDOUT, "Nombre: "); $nombre = trim(fgets(STDIN));
fwrite(STDOUT, "Correo: "); $correo = trim(fgets(STDIN));
fwrite(STDOUT, "Código: "); $codigo = trim(fgets(STDIN));
fwrite(STDOUT, "Contraseña (mínimo 12 caracteres; visible en esta terminal): "); $password = rtrim(fgets(STDIN), "\r\n");
if (!$nombre || mb_strlen($nombre) > 60 || !filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150 || !$codigo || mb_strlen($codigo) > 30 || strlen($password) < 12) exit("Datos no válidos. No se creó la cuenta.\n");
$db = new Connection();
try {
    $db->beginTransaction();
    $db->exec("INSERT IGNORE INTO roles (nombre, descripcion) VALUES ('Administrador', 'Administración del sistema')");
    $id = $db->query("SELECT id_rol FROM roles WHERE nombre = 'Administrador'")->fetchColumn();
    $stmt = $db->prepare('INSERT INTO usuarios (codigo_usuario,nombres,apellidos,correo,password_hash,id_rol) VALUES (?,?, ?,?,?,?)');
    $stmt->execute([$codigo,$nombre,'',$correo,password_hash($password,PASSWORD_DEFAULT),$id]);
    $db->commit(); fwrite(STDOUT, "Cuenta creada. Ya puedes iniciar sesión.\n");
} catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); fwrite(STDERR, "No se creó la cuenta. Revisa la conexión y que el correo/código no exista.\n"); exit(1); }
