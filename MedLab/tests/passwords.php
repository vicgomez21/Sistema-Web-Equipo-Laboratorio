<?php
// Pruebas CLI puras: no abre sesiones ni conexiones y no modifica la base de datos.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../api/core/Password.php';

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$password = 'Clave de prueba segura 2026';
$a = Password::crear($password);
$b = Password::crear($password);
comprobar($a !== $b, 'Dos altas de la misma contraseña deben generar sales y hashes distintos.');
comprobar(Password::verificar($password, $a), 'Debe aceptar la contraseña correcta.');
comprobar(!Password::verificar('Contraseña incorrecta', $a), 'Debe rechazar una contraseña incorrecta.');
comprobar(Password::validarNueva('corta') !== null, 'Debe rechazar menos de 15 caracteres.');
comprobar(Password::validarNueva(str_repeat('a', 14)) !== null, 'Debe rechazar exactamente 14 caracteres.');
comprobar(Password::validarNueva(str_repeat('a', 15)) === null, 'Debe aceptar 15 caracteres.');
comprobar(Password::validarNueva(str_repeat('a', 72)) === null, 'Debe aceptar 72 bytes.');
comprobar(Password::validarNueva(str_repeat('a', 73)) !== null, 'Debe rechazar 73 bytes.');
comprobar(Password::validarNueva('Clave con NUL' . chr(0) . '2026') !== null, 'Debe rechazar NUL.');
comprobar(Password::validarNueva(str_repeat('é', 36)) === null, 'Debe aceptar 72 bytes Unicode.');
comprobar(Password::validarNueva(str_repeat('é', 37)) !== null, 'Debe rechazar 74 bytes Unicode.');
comprobar(Password::validarNueva(str_repeat('a', 15) . chr(255)) !== null, 'Debe rechazar UTF-8 inválido.');
comprobar(!Password::necesitaActualizar($a), 'Un hash nuevo ya usa la política actual.');

$legacy = password_hash('antigua12', PASSWORD_BCRYPT, ['cost' => 10]);
comprobar(Password::necesitaActualizar($legacy), 'Debe detectar un hash legado de coste inferior.');
comprobar(Password::verificar('antigua12', $legacy), 'Conserva el acceso a contraseñas legadas válidas.');
$rehash = Password::rehashVerificado('antigua12');
comprobar(Password::verificar('antigua12', $rehash), 'El rehash conserva la contraseña.');
comprobar(!Password::necesitaActualizar($rehash), 'El rehash ya usa la política actual.');

fwrite(STDOUT, 'PASS: sal aleatoria, verificación, política, Unicode/NUL y rehash legado ('
    . password_get_info($a)['algoName'] . ").\n");
