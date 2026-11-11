<?php
/** Integración destructiva SOLO en la instancia desechable dedicada (MySQL 13316 / Apache 18081). */
if (PHP_SAPI !== 'cli' || getenv('MEDLAB_SECURITY_TEST') !== '1') {
    fwrite(STDERR, "Esta prueba requiere CLI y MEDLAB_SECURITY_TEST=1.\n");
    exit(1);
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:18081', '/');
$url = parse_url($base);
if (!$url || ($url['scheme'] ?? '') !== 'http' || ($url['host'] ?? '') !== '127.0.0.1'
    || (int)($url['port'] ?? 0) !== 18081) {
    fwrite(STDERR, "Solo se admite Apache de pruebas en http://127.0.0.1:18081.\n");
    exit(1);
}
require_once __DIR__ . '/../api/core/Password.php';

final class SecurityHttpClient
{
    private $curl;
    private string $base;
    public function __construct(string $base)
    {
        $this->base = $base;
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10]);
    }
    public function request(string $path, string $method = 'GET', ?string $body = null, array $headers = []): array
    {
        $responseHeaders = [];
        curl_setopt_array($this->curl, [CURLOPT_URL => $this->base . $path, CURLOPT_POSTFIELDS => $body,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                if (strpos($line, ':') !== false) {
                    [$key, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($key))] = trim($value);
                }
                return strlen($line);
            }]);
        $result = curl_exec($this->curl);
        if ($result === false) throw new RuntimeException('HTTP: ' . curl_error($this->curl));
        return ['status' => (int)curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE),
            'headers' => $responseHeaders, 'body' => $result, 'json' => json_decode($result, true)];
    }
    public function sessionId(): string
    {
        foreach (curl_getinfo($this->curl, CURLINFO_COOKIELIST) as $cookie) {
            $parts = explode("\t", $cookie);
            if (($parts[5] ?? '') === 'MEDLABSESSID') return $parts[6] ?? '';
        }
        return '';
    }
}

$passed = 0;
function checkSecurity(bool $condition, string $label): void
{
    global $passed;
    if (!$condition) throw new RuntimeException($label);
    ++$passed;
    echo "PASS $label\n";
}
function loginSecurity(SecurityHttpClient $client, string $identifier, string $password): string
{
    $page = $client->request('/login.php');
    checkSecurity($page['status'] === 200, 'login disponible');
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $page['body'], $match);
    checkSecurity(isset($match[1]), 'CSRF de login emitido');
    $previous = $client->sessionId();
    $login = $client->request('/api/admin/C_Auth.php', 'POST', http_build_query([
        'csrf' => $match[1], 'identificador' => $identifier, 'password' => $password,
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    checkSecurity($login['status'] === 302 && strpos($login['headers']['location'] ?? '', 'index.php') !== false,
        'credenciales válidas autentican');
    checkSecurity($previous !== '' && $client->sessionId() !== '' && $previous !== $client->sessionId(),
        'identificador de sesión rota al autenticar');
    $home = $client->request('/index.php');
    checkSecurity($home['status'] === 200, 'sesión autenticada accede a inicio');
    preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $home['body'], $match);
    checkSecurity(isset($match[1]), 'CSRF de API emitido');
    return $match[1];
}
function formHeadersSecurity(string $csrf): array
{
    return ['Content-Type: application/x-www-form-urlencoded', 'X-CSRF-Token: ' . $csrf];
}

$db = null;
$userIds = [];
$roleIds = [];
$prefix = 'sec_' . bin2hex(random_bytes(5));
$password = 'MedLab prueba segura 2026!';
$exitCode = 0;
try {
    // Puerto literal: jamás usa el .env ni la instancia MySQL de desarrollo.
    $db = new PDO('mysql:host=127.0.0.1;port=13316;dbname=MedLabSecurityTest_20261103;charset=utf8mb4', 'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]);
    $insertRole = $db->prepare('INSERT INTO roles(nombre,descripcion,es_administrador) VALUES(?,?,?)');
    $insertRole->execute([$prefix . '_admin', 'Prueba desechable', 1]);
    $adminRole = (int)$db->lastInsertId(); $roleIds[] = $adminRole;
    $insertRole->execute([$prefix . '_student', 'Prueba desechable', 0]);
    $studentRole = (int)$db->lastInsertId(); $roleIds[] = $studentRole;
    $insertUser = $db->prepare('INSERT INTO usuarios(codigo_usuario,nombres,apellidos,correo,password_hash,id_rol) VALUES(?,?,?,?,?,?)');
    $insertUser->execute([$prefix . '_admin', 'Prueba', 'Administrador', $prefix . '_a@example.invalid', Password::crear($password), $adminRole]);
    $adminId = (int)$db->lastInsertId(); $userIds[] = $adminId;
    $insertUser->execute([$prefix . '_student', 'Prueba', 'Estudiante', $prefix . '_s@example.invalid', Password::crear($password), $studentRole]);
    $studentId = (int)$db->lastInsertId(); $userIds[] = $studentId;

    $anonymous = new SecurityHttpClient($base);
    foreach (['/index.php', '/usuarios.php', '/roles.php'] as $path) {
        checkSecurity($anonymous->request($path)['status'] === 302, 'anónimo redirigido: ' . $path);
    }
    foreach (['C_Usuarios', 'C_Roles', 'C_Conexion'] as $controller) {
        checkSecurity($anonymous->request('/api/admin/' . $controller . '.php')['status'] === 401,
            'API rechaza anónimo: ' . $controller);
    }
    foreach (['/database/MedLabDB.sql', '/api/core/.env', '/api/models/M_Usuarios.php', '/README.md'] as $path) {
        checkSecurity($anonymous->request($path)['status'] === 403, 'Apache bloquea archivo interno: ' . $path);
    }
    $loginPage = $anonymous->request('/login.php');
    checkSecurity(isset($loginPage['headers']['content-security-policy'])
        && ($loginPage['headers']['x-frame-options'] ?? '') === 'DENY'
        && ($loginPage['headers']['x-content-type-options'] ?? '') === 'nosniff', 'cabeceras defensivas activas');
    $admin = new SecurityHttpClient($base);
    $csrf = loginSecurity($admin, $prefix . '_admin', $password);
    $headers = formHeadersSecurity($csrf);
    $users = $admin->request('/api/admin/C_Usuarios.php');
    checkSecurity($users['status'] === 200, 'administrador lista usuarios');
    checkSecurity(strpos($users['body'], 'password_hash') === false && strpos($users['body'], '$2y$') === false
        && strpos($users['body'], '$argon2') === false, 'API no expone hashes');

    $student = new SecurityHttpClient($base);
    loginSecurity($student, $prefix . '_student', $password);
    checkSecurity($student->request('/api/admin/C_Usuarios.php')['status'] === 403, 'estudiante no administra API');
    checkSecurity($student->request('/usuarios.php')['status'] === 403, 'estudiante no accede a página administrativa');

    checkSecurity($admin->request('/api/admin/C_Roles.php', 'POST', 'nombre=prueba',
        ['Content-Type: application/x-www-form-urlencoded'])['status'] === 403, 'escritura sin CSRF bloqueada');
    checkSecurity($admin->request('/api/admin/C_Roles.php', 'POST', 'nombre=prueba',
        formHeadersSecurity(str_repeat('0', 64)))['status'] === 403, 'CSRF incorrecto bloqueado');
    foreach (['nombre[]=x', 'nombre=a&nombre=b', 'nombre=%FF', 'nombre=a%00b'] as $body) {
        checkSecurity($admin->request('/api/admin/C_Roles.php', 'POST', $body, $headers)['status'] === 422,
            'entrada malformada rechazada: ' . $body);
    }
    checkSecurity($admin->request('/api/admin/C_Roles.php', 'POST', 'nombre=' . str_repeat('a', 16385), $headers)['status'] === 413,
        'cuerpo mayor de 16 KiB rechazado');
    checkSecurity($admin->request('/api/admin/C_Roles.php', 'POST', '{}',
        ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf])['status'] === 415, 'MIME inesperado rechazado');
    checkSecurity($admin->request('/api/admin/C_Roles.php', 'DELETE', 'id_rol=1%20OR%201%3D1', $headers)['status'] === 422,
        'inyección en identificador rechazada');
    checkSecurity($admin->request('/api/admin/C_Conexion.php', 'POST', '', $headers)['status'] === 405,
        'método no permitido rechazado');

    $payload = "x' OR 1=1 --";
    $create = $admin->request('/api/admin/C_Roles.php', 'POST', http_build_query([
        'nombre' => $payload, 'descripcion' => '<script>alert(1)</script>', 'es_administrador' => '1',
    ]), $headers);
    checkSecurity($create['status'] === 200 && isset($create['json']['data']['id_rol']), 'SQLi tratado como datos al crear rol');
    $textRole = (int)$create['json']['data']['id_rol']; $roleIds[] = $textRole;
    $stored = $db->prepare('SELECT nombre,descripcion,es_administrador FROM roles WHERE id_rol=?');
    $stored->execute([$textRole]); $storedRole = $stored->fetch();
    checkSecurity($storedRole['nombre'] === $payload && $storedRole['descripcion'] === '<script>alert(1)</script>',
        'payload permanece texto exacto en BD');
    checkSecurity((int)$storedRole['es_administrador'] === 0, 'campo enviado no concede privilegios de administrador');
    $rename = $admin->request('/api/admin/C_Roles.php', 'PUT', http_build_query([
        'id_rol' => $adminRole, 'nombre' => 'Otro nombre', 'descripcion' => '',
    ]), $headers);
    checkSecurity($rename['status'] === 409, 'rol administrador protegido frente a renombrado');
    checkSecurity($admin->request('/api/admin/C_Usuarios.php', 'DELETE', 'id_usuario=' . $adminId, $headers)['status'] === 409,
        'administrador no puede eliminar su propia cuenta');

    $studentData = ['id_usuario' => $studentId, 'codigo_usuario' => $prefix . '_student', 'nombres' => 'Prueba',
        'apellidos' => 'Estudiante', 'correo' => $prefix . '_s@example.invalid', 'telefono' => '',
        'id_rol' => $studentRole, 'activo' => '1', 'password' => 'corta'];
    checkSecurity($admin->request('/api/admin/C_Usuarios.php', 'PUT', http_build_query($studentData), $headers)['status'] === 422,
        'contraseña nueva corta rechazada por servidor');
    $studentData['password'] = 'Nueva prueba segura 2026!';
    checkSecurity($admin->request('/api/admin/C_Usuarios.php', 'PUT', http_build_query($studentData), $headers)['status'] === 200,
        'administrador actualiza contraseña del estudiante');
    checkSecurity($student->request('/api/admin/C_Roles.php')['status'] === 401, 'cambio de contraseña revoca sesión anterior');
    $logout = $admin->request('/api/admin/C_Auth.php', 'POST', http_build_query(['accion' => 'salir', 'csrf' => $csrf]),
        ['Content-Type: application/x-www-form-urlencoded']);
    checkSecurity($logout['status'] === 302 && $admin->request('/api/admin/C_Usuarios.php')['status'] === 401,
        'logout invalida sesión');
    echo "\n$passed comprobaciones de seguridad correctas.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if ($db instanceof PDO) {
        // Solo IDs creados por esta ejecución, sin TRUNCATE ni DROP.
        foreach (array_reverse($userIds) as $id) {
            $delete = $db->prepare('DELETE FROM usuarios WHERE id_usuario=? AND codigo_usuario LIKE ?');
            $delete->execute([$id, $prefix . '%']);
        }
        foreach (array_reverse($roleIds) as $id) {
            $delete = $db->prepare('DELETE FROM roles WHERE id_rol=?');
            $delete->execute([$id]);
        }
    }
}
exit($exitCode);
