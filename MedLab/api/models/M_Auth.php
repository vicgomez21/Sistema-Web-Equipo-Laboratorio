<?php
require_once __DIR__ . '/../core/Password.php';

final class M_Auth
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function AUTENTICAR(string $identificador, string $password): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT u.id_usuario, u.nombres, u.apellidos, u.id_rol, u.password_hash, u.activo,
                    u.version_sesion, r.nombre AS rol, r.es_administrador
             FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.correo = :correo OR u.codigo_usuario = :codigo LIMIT 2'
        );
        $stmt->execute(['correo' => $identificador, 'codigo' => $identificador]);
        $candidatos = $stmt->fetchAll();
        $usuario = count($candidatos) === 1 ? $candidatos[0] : null;
        // Una cuenta inexistente/ambigua también realiza una verificación de coste similar.
        $valida = Password::verificar($password, $usuario['password_hash'] ?? Password::hashDeRelleno());
        if (!$usuario || !$valida || (int)$usuario['activo'] !== 1) {
            return null;
        }
        if (Password::necesitaActualizar($usuario['password_hash'])) {
            // La contraseña antigua ya se verificó; no exige cambiarla para actualizar el hash.
            $hashNuevo = Password::rehashVerificado($password);
            $actualizar = $this->connection->prepare(
                'UPDATE usuarios SET password_hash = :nuevo WHERE id_usuario = :id
                 AND password_hash = :anterior AND version_sesion = :version AND activo = 1'
            );
            $actualizar->execute(['nuevo' => $hashNuevo, 'id' => $usuario['id_usuario'],
                'anterior' => $usuario['password_hash'], 'version' => $usuario['version_sesion']]);
            if ($actualizar->rowCount() !== 1) {
                return null; // La cuenta cambió mientras se comprobaba la contraseña.
            }
        }
        unset($usuario['password_hash'], $usuario['activo']);
        return $usuario;
    }
}
