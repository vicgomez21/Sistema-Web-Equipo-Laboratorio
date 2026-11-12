<?php
class M_Auth {
    private Connection $connection;
    public function __construct(Connection $connection) { $this->connection = $connection; }
    public function AUTENTICAR(string $identificador, string $password): ?array {
        $stmt = $this->connection->prepare('SELECT u.*, r.nombre AS rol FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.correo = :correo OR u.codigo_usuario = :codigo LIMIT 1');
        $stmt->execute(['correo' => $identificador, 'codigo' => $identificador]);
        $usuario = $stmt->fetch();
        // Hash fijo de relleno para que una cuenta inexistente también verifique una contraseña.
        $valida = password_verify($password, $usuario['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$usuario || !$valida || !$usuario['activo']) return null;
        return array_intersect_key($usuario, array_flip(['id_usuario', 'nombres', 'apellidos', 'rol']));
    }
}
