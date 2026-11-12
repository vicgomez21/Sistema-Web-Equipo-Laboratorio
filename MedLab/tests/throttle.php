<?php
/** Integración destructiva SOLO para una BD desechable MedLabSecurityTest_*. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$basePruebas = getenv('DATABASE_NAME');
if (!is_string($basePruebas) || !preg_match('/^MedLabSecurityTest_[A-Za-z0-9_]+$/D', $basePruebas)) {
    fwrite(STDERR, "Usa una BD desechable y DATABASE_NAME=MedLabSecurityTest_<identificador>.\n");
    exit(1);
}
require_once __DIR__ . '/../api/core/Connection.php';
require_once __DIR__ . '/../api/core/LoginThrottle.php';
require_once __DIR__ . '/../api/models/M_Auth.php';
if (DATABASE !== $basePruebas) {
    fwrite(STDERR, "La configuración no apunta a la BD desechable solicitada.\n");
    exit(1);
}
$db = new Connection();
if (strcasecmp((string)$db->query('SELECT DATABASE()')->fetchColumn(), $basePruebas) !== 0) {
    fwrite(STDERR, "La conexión no corresponde a la BD desechable.\n");
    exit(1);
}

if (($argv[1] ?? '') === '--worker') {
    try {
        fwrite(STDOUT, (new LoginThrottle($db))->reservar($argv[2], $argv[3]) ? '1' : '0');
        exit;
    } catch (Throwable $e) {
        fwrite(STDERR, "Fallo en la reserva concurrente.\n");
        exit(1);
    }
}

function verificar(bool $condicion, string $mensaje): void
{
    if (!$condicion) throw new RuntimeException($mensaje);
}

function claveIp(string $ip): string
{
    return hash('sha256', 'ip:' . bin2hex(inet_pton($ip)));
}

function claveInexistente(string $identificador): string
{
    return hash('sha256', 'cuenta:identificador:' . mb_strtolower(trim($identificador), 'UTF-8'));
}

/** Los argumentos de proc_open se pasan como array, sin construir comandos de shell. */
function reservasParalelas(array $peticiones): array
{
    $procesos = [];
    try {
        foreach ($peticiones as [$identificador, $ip]) {
            $pipes = [];
            $proceso = proc_open([PHP_BINARY, __FILE__, '--worker', $identificador, $ip],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
            if (!is_resource($proceso)) throw new RuntimeException('No se pudo crear un proceso de prueba.');
            fclose($pipes[0]);
            $procesos[] = [$proceso, $pipes];
        }
        $resultados = [];
        foreach ($procesos as $indice => [$proceso, $pipes]) {
            $salida = stream_get_contents($pipes[1]);
            $errores = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $estado = proc_close($proceso);
            unset($procesos[$indice]);
            if ($estado !== 0 || $errores !== '' || ($salida !== '0' && $salida !== '1')) {
                throw new RuntimeException('Una reserva concurrente falló: ' . trim($errores));
            }
            $resultados[] = $salida === '1';
        }
        return $resultados;
    } finally {
        foreach ($procesos as [$proceso, $pipes]) {
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            if (is_resource($proceso)) { proc_terminate($proceso); proc_close($proceso); }
        }
    }
}

$token = bin2hex(random_bytes(6));
$codigo = 'SEC' . $token;
$correo = 's' . $token . '@e.invalid';
$claves = [];
$idRol = null;
$idUsuario = null;
$idAmbiguo = null;
$fallo = null;
try {
    $rol = $db->prepare('INSERT INTO roles (nombre, descripcion, es_administrador) VALUES (?, ?, 0)');
    $rol->execute(['Prueba ' . $token, 'Fixture temporal de seguridad']);
    $idRol = (int)$db->lastInsertId();
    $insertar = $db->prepare('INSERT INTO usuarios (codigo_usuario, nombres, apellidos, correo, password_hash, id_rol)
        VALUES (?, ?, ?, ?, ?, ?)');
    $password = 'Una contraseña de pruebas 2026';
    $insertar->execute([$codigo, 'Prueba', 'Temporal', $correo, Password::crear($password), $idRol]);
    $idUsuario = (int)$db->lastInsertId();
    $claveCuenta = hash('sha256', 'cuenta:usuario:' . $idUsuario);
    $claves[] = $claveCuenta;

    $autenticacion = new M_Auth($db);
    $usuario = $autenticacion->AUTENTICAR($codigo, $password);
    verificar($usuario !== null && (int)$usuario['id_usuario'] === $idUsuario, 'Debe autenticar la cuenta fixture.');
    verificar(!isset($usuario['password_hash']) && isset($usuario['version_sesion']), 'La sesión no expone el hash.');
    verificar($autenticacion->AUTENTICAR($codigo, 'Incorrecta') === null, 'Debe rechazar credenciales incorrectas.');
    verificar($autenticacion->AUTENTICAR("' OR 1=1 -- ", $password) === null,
        'Un identificador con sintaxis SQL no debe autenticar.');
    $cambiar = $db->prepare('UPDATE usuarios SET activo = 0 WHERE id_usuario = ?');
    $cambiar->execute([$idUsuario]);
    verificar($autenticacion->AUTENTICAR($codigo, $password) === null, 'Una cuenta inactiva no autentica.');
    $cambiar = $db->prepare('UPDATE usuarios SET activo = 1, password_hash = ? WHERE id_usuario = ?');
    $legado = password_hash('antigua12', PASSWORD_BCRYPT, ['cost' => 10]);
    $cambiar->execute([$legado, $idUsuario]);
    verificar($autenticacion->AUTENTICAR($codigo, 'antigua12') !== null, 'Debe aceptar y migrar un hash legado válido.');
    $leerHash = $db->prepare('SELECT password_hash, version_sesion FROM usuarios WHERE id_usuario = ?');
    $leerHash->execute([$idUsuario]);
    $migrado = $leerHash->fetch();
    verificar($migrado['password_hash'] !== $legado && !Password::necesitaActualizar($migrado['password_hash'])
        && (int)$migrado['version_sesion'] === (int)$usuario['version_sesion'],
        'El login debe rehashear sin alterar la versión de sesión.');

    // Simula una colisión heredada entre código de una persona y correo de otra.
    $insertar->execute([$correo, 'Ambigua', 'Temporal', 'otro' . $token . '@e.invalid', Password::crear($password), $idRol]);
    $idAmbiguo = (int)$db->lastInsertId();
    verificar($autenticacion->AUTENTICAR($correo, $password) === null,
        'Un identificador ambiguo debe rechazarse aunque una contraseña coincida.');
    $borrarAmbiguo = $db->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
    $borrarAmbiguo->execute([$idAmbiguo]);
    $idAmbiguo = null;

    $throttle = new LoginThrottle($db);
    $alias = [$codigo, $correo, strtoupper($correo), strtolower($codigo), $correo, $codigo];
    foreach ($alias as $indice => $identificador) {
        $ip = '192.0.2.' . ($indice + 1);
        $claves[] = claveIp($ip);
        verificar($throttle->reservar($identificador, $ip) === ($indice < 5),
            'Código/correo/mayúsculas deben compartir el límite de cinco intentos.');
    }
    $caducar = $db->prepare('UPDATE intentos_login SET ventana_inicio = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 16 MINUTE)
        WHERE clave = ?');
    $caducar->execute([$claveCuenta]);
    verificar($throttle->reservar($codigo, '192.0.2.1'), 'Una ventana vencida debe permitir otro intento.');

    $ip = '198.51.100.10';
    $claves[] = claveIp($ip);
    for ($i = 0; $i < 30; $i++) {
        $identificador = 'ausente_' . $token . '_' . $i;
        $claves[] = claveInexistente($identificador);
        verificar($throttle->reservar($identificador, $ip), 'Los primeros treinta intentos IP deben reservarse.');
    }
    $antes = (int)$db->query('SELECT COUNT(*) FROM intentos_login')->fetchColumn();
    for ($i = 30; $i < 45; $i++) {
        verificar(!$throttle->reservar('ausente_' . $token . '_' . $i, $ip), 'La IP debe quedar bloqueada.');
    }
    verificar((int)$db->query('SELECT COUNT(*) FROM intentos_login')->fetchColumn() === $antes,
        'Una IP bloqueada no debe crear filas para identificadores inventados.');

    $ipV6 = ['2001:db8:0:0:0:0:0:1', '2001:db8::1'];
    $claves[] = claveIp($ipV6[0]);
    for ($i = 0; $i < 31; $i++) {
        $identificador = 'ipv6_' . $token . '_' . $i;
        $claves[] = claveInexistente($identificador);
        verificar($throttle->reservar($identificador, $ipV6[$i % 2]) === ($i < 30),
            'Representaciones equivalentes de IPv6 deben compartir límite.');
    }

    $borrar = $db->prepare('DELETE FROM intentos_login WHERE clave = ?');
    $borrar->execute([$claveCuenta]);
    $peticiones = [];
    for ($i = 1; $i <= 12; $i++) {
        $ip = '203.0.113.' . $i;
        $claves[] = claveIp($ip);
        $peticiones[] = [$i % 2 ? $codigo : $correo, $ip];
    }
    verificar(count(array_filter(reservasParalelas($peticiones))) === 5,
        'Doce procesos simultáneos deben permitir exactamente cinco verificaciones por cuenta.');

    $ip = '198.51.100.20';
    $claves[] = claveIp($ip);
    for ($i = 0; $i < 22; $i++) {
        $identificador = 'previo_' . $token . '_' . $i;
        $claves[] = claveInexistente($identificador);
        verificar($throttle->reservar($identificador, $ip), 'La precarga IP debe permitirse.');
    }
    $peticiones = [];
    for ($i = 0; $i < 16; $i++) {
        $identificador = 'paralelo_' . $token . '_' . $i;
        $claves[] = claveInexistente($identificador);
        $peticiones[] = [$identificador, $ip];
    }
    verificar(count(array_filter(reservasParalelas($peticiones))) === 8,
        'El límite IP debe mantenerse en treinta aun con dieciséis procesos simultáneos.');
} catch (Throwable $e) {
    $fallo = $e;
} finally {
    if ($db->inTransaction()) $db->rollBack();
    if ($claves !== []) {
        $claves = array_values(array_unique($claves));
        $borrar = $db->prepare('DELETE FROM intentos_login WHERE clave IN (' . implode(',', array_fill(0, count($claves), '?')) . ')');
        $borrar->execute($claves);
    }
    if ($idAmbiguo !== null) {
        $borrar = $db->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
        $borrar->execute([$idAmbiguo]);
    }
    if ($idUsuario !== null) {
        $borrar = $db->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
        $borrar->execute([$idUsuario]);
    }
    if ($idRol !== null) {
        $borrar = $db->prepare('DELETE FROM roles WHERE id_rol = ?');
        $borrar->execute([$idRol]);
    }
}
if ($fallo !== null) {
    fwrite(STDERR, 'FAIL: ' . $fallo->getMessage() . "\n");
    exit(1);
}
fwrite(STDOUT, "PASS: login/SQLi/inactivo/ambiguo/rehash, alias, caducidad, IP/IPv6, crecimiento y concurrencia.\n");
