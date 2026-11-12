<?php
/** Serializa cambios que podrían dejar al sistema sin administración. */
class AdminGuard {
    public static function isAdmin(string $name): bool {
        return strtolower(trim($name)) === 'administrador';
    }
    public static function begin(Connection $db): array {
        $db->beginTransaction();
        // Mutex compartido por cambios de usuarios/roles, siempre en el mismo orden.
        return $db->query('SELECT id_rol, nombre FROM roles ORDER BY id_rol FOR UPDATE')->fetchAll();
    }
    private static function reject(Connection $db, string $message, int $code): void {
        if ($db->inTransaction()) $db->rollBack();
        Response::error($message, $code, 409);
    }
    public static function user(Connection $db, array $roles, int $id, ?int $newRole, int $active): void {
        $adminRoles = [];
        foreach ($roles as $role) if (self::isAdmin($role['nombre'])) $adminRoles[] = (int)$role['id_rol'];
        $stmt = $db->prepare('SELECT id_rol, activo FROM usuarios WHERE id_usuario = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if (!$user || !(int)$user['activo'] || !in_array((int)$user['id_rol'], $adminRoles, true)) return;
        if ($active === 1 && $newRole !== null && in_array($newRole, $adminRoles, true)) return;
        // Locking read usa el estado más reciente aun en REPEATABLE READ.
        $admins = $db->query('SELECT id_usuario, id_rol FROM usuarios WHERE activo = 1 ORDER BY id_usuario FOR UPDATE')->fetchAll();
        $count = 0;
        foreach ($admins as $admin) if (in_array((int)$admin['id_rol'], $adminRoles, true)) $count++;
        if ($count <= 1) self::reject($db, 'Debe quedar al menos un administrador activo. Crea o habilita otro antes de realizar este cambio.', 3115);
    }
    public static function role(Connection $db, array $roles, int $id, ?string $newName): void {
        foreach ($roles as $role) {
            if ((int)$role['id_rol'] === $id && self::isAdmin($role['nombre']) && ($newName === null || !self::isAdmin($newName))) {
                self::reject($db, 'El rol Administrador es del sistema y no se puede eliminar ni cambiar su nombre. Puedes editar su descripción.', 3008);
            }
        }
    }
}
