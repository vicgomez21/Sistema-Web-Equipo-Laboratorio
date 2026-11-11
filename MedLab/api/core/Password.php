<?php

/** Política única de contraseñas. PHP incorpora una sal aleatoria en cada hash. */
final class Password
{
    private static function algoritmo(): string
    {
        return defined('PASSWORD_ARGON2ID') && in_array(PASSWORD_ARGON2ID, password_algos(), true)
            ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    private static function opciones(): array
    {
        return self::algoritmo() === PASSWORD_BCRYPT
            ? ['cost' => 12]
            : ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1];
    }

    public static function validarNueva(string $password): ?string
    {
        if (!mb_check_encoding($password, 'UTF-8') || strpos($password, "\0") !== false) {
            return 'La contraseña contiene caracteres no válidos.';
        }
        if (mb_strlen($password, 'UTF-8') < 15) {
            return 'La contraseña debe tener al menos 15 caracteres.';
        }
        // Evita la truncación silenciosa de bcrypt en instalaciones sin Argon2id.
        if (strlen($password) > 72) {
            return 'La contraseña no puede superar los 72 bytes (algunos caracteres ocupan varios bytes).';
        }
        return null;
    }

    public static function crear(string $password): string
    {
        $error = self::validarNueva($password);
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }
        return self::rehashVerificado($password);
    }

    /** Solo después de verificar un hash legado; conserva contraseñas antiguas cortas. */
    public static function rehashVerificado(string $password): string
    {
        if ($password === '' || strlen($password) > 72 || strpos($password, "\0") !== false) {
            throw new InvalidArgumentException('Contraseña no válida.');
        }
        $hash = password_hash($password, self::algoritmo(), self::opciones());
        if ($hash === false) {
            throw new RuntimeException('No se pudo generar el hash.');
        }
        return $hash;
    }

    public static function verificar(string $password, string $hash): bool
    {
        return $password !== '' && strlen($password) <= 72 && strpos($password, "\0") === false
            && password_verify($password, $hash);
    }

    public static function necesitaActualizar(string $hash): bool
    {
        return password_needs_rehash($hash, self::algoritmo(), self::opciones());
    }

    public static function hashDeRelleno(): string
    {
        // Hashes de valores aleatorios descartados; igual coste que una cuenta nueva.
        return self::algoritmo() === PASSWORD_BCRYPT
            ? '$2y$12$nBmGMqyyhiCUyrn1mGTs/eIHwXyZ2.OTdDIo7xD0G8BWNrdTqEYsW'
            : '$argon2id$v=19$m=19456,t=2,p=1$Y2NyaW1TUGljMlJQSFNRYw$fFjKR9jJcAZ4kBV1aFUZP3HyuvHt7+2sQlocDZ85XI8';
    }
}
