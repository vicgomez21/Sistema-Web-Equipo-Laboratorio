<?php
require_once __DIR__ . '/../core/Password.php';

/**
 * Modelo de la tabla usuarios (CRUD).
 * Nunca devuelve password_hash al frontend.
 */
class M_Usuarios
{
    private Connection $connection;
    private Response $response;

    public function __construct(Connection $connection, Response $response)
    {
        $this->connection = $connection;
        $this->response = $response;
    }

    function SELECT_USUARIOS()
    {
        $query = "SELECT u.id_usuario, u.codigo_usuario, u.nombres, u.apellidos, u.correo, u.telefono,
                         u.id_rol, r.nombre AS rol, u.activo, u.fecha_registro
                  FROM usuarios u
                  INNER JOIN roles r ON r.id_rol = u.id_rol
                  ORDER BY u.id_usuario DESC";
        $statement = $this->connection->prepare($query);
        $statement->execute();
        $this->response->success("Usuarios obtenidos correctamente", $statement->fetchAll(), 1);
    }

    function INSERT_USUARIO($arrayData)
    {
        $datos = $this->VALIDAR_DATOS($arrayData, true);

        $query = "INSERT INTO usuarios (codigo_usuario, nombres, apellidos, correo, password_hash, telefono, id_rol, activo)
                  VALUES (:codigo_usuario, :nombres, :apellidos, :correo, :password_hash, :telefono, :id_rol, :activo)";
        try {
            $this->BLOQUEAR_ADMINISTRACION();
            $this->VALIDAR_IDENTIFICADORES($datos, 0);
            $statement = $this->connection->prepare($query);
            $statement->execute($datos);
            $idUsuario = $this->connection->lastInsertId();
            $this->connection->commit();
        } catch (PDOException $e) {
            $this->REVERTIR();
            $this->RESPONDER_ERROR_BD($e);
        } catch (Throwable $e) {
            $this->REVERTIR();
            throw $e;
        }
        $this->response->success("Usuario agregado correctamente", ['id_usuario' => $idUsuario], 1);
    }

    function UPDATE_USUARIO($arrayData)
    {
        $idUsuario = $this->OBTENER_ID($arrayData['id_usuario'] ?? null);
        $datos = $this->VALIDAR_DATOS($arrayData, false);
        // La contraseña solo se cambia si se envió una nueva
        $campos = "codigo_usuario = :codigo_usuario, nombres = :nombres, apellidos = :apellidos,
                   correo = :correo, telefono = :telefono, id_rol = :id_rol, activo = :activo";
        if ($datos['password_hash'] !== null) {
            $campos .= ", password_hash = :password_hash";
        } else {
            unset($datos['password_hash']);
        }

        try {
            $administradores = $this->BLOQUEAR_ADMINISTRACION();
            $actual = $this->VERIFICAR_QUE_EXISTE($idUsuario);
            $this->VALIDAR_IDENTIFICADORES($datos, $idUsuario);
            $rol = $this->connection->prepare('SELECT es_administrador FROM roles WHERE id_rol = :id_rol');
            $rol->execute(['id_rol' => $datos['id_rol']]);
            $esAdministrador = (bool) $rol->fetchColumn();
            $pierdeAcceso = !$datos['activo'] || !$esAdministrador;
            if ($idUsuario === (int) ($_SESSION['usuario']['id_usuario'] ?? 0) && $pierdeAcceso) {
                $this->RECHAZAR('No puedes desactivar ni quitar los permisos de tu propia cuenta.', 3115, 409);
            }
            if ($pierdeAcceso && in_array($idUsuario, $administradores, true) && count($administradores) <= 1) {
                $this->RECHAZAR('Debe permanecer al menos un administrador activo.', 3116, 409);
            }
            if (isset($datos['password_hash']) || (int) $actual['id_rol'] !== $datos['id_rol'] || (int) $actual['activo'] !== $datos['activo']) {
                $campos .= ', version_sesion = version_sesion + 1';
            }
            $query = "UPDATE usuarios SET $campos WHERE id_usuario = :id_usuario";
            $statement = $this->connection->prepare($query);
            $statement->execute($datos + ['id_usuario' => $idUsuario]);
            $this->connection->commit();
        } catch (PDOException $e) {
            $this->REVERTIR();
            $this->RESPONDER_ERROR_BD($e);
        } catch (Throwable $e) {
            $this->REVERTIR();
            throw $e;
        }
        $this->response->success("Usuario actualizado correctamente", [], 1);
    }

    function DELETE_USUARIO($valorId)
    {
        $idUsuario = $this->OBTENER_ID($valorId);
        $query = "DELETE FROM usuarios WHERE id_usuario = :id_usuario";
        try {
            $administradores = $this->BLOQUEAR_ADMINISTRACION();
            $this->VERIFICAR_QUE_EXISTE($idUsuario);
            if ($idUsuario === (int) ($_SESSION['usuario']['id_usuario'] ?? 0)) {
                $this->RECHAZAR('No puedes eliminar tu propia cuenta.', 3117, 409);
            }
            if (in_array($idUsuario, $administradores, true) && count($administradores) <= 1) {
                $this->RECHAZAR('Debe permanecer al menos un administrador activo.', 3116, 409);
            }
            $statement = $this->connection->prepare($query);
            $statement->execute(['id_usuario' => $idUsuario]);
            $this->connection->commit();
        } catch (PDOException $e) {
            $this->REVERTIR();
            $this->RESPONDER_ERROR_BD($e);
        } catch (Throwable $e) {
            $this->REVERTIR();
            throw $e;
        }
        $this->response->success("Usuario eliminado correctamente", [], 1);
    }

    # ---------- Métodos de apoyo ----------

    private function VALIDAR_DATOS(array $arrayData, bool $esNuevo): array
    {
        foreach (['codigo_usuario', 'nombres', 'apellidos', 'correo', 'telefono', 'password', 'id_rol', 'activo'] as $campo) {
            if (isset($arrayData[$campo]) && !is_string($arrayData[$campo]) && !is_int($arrayData[$campo])) {
                $this->response->error('Los campos deben contener un solo valor de texto.', 3118, 422);
            }
            if (isset($arrayData[$campo]) && (!mb_check_encoding((string) $arrayData[$campo], 'UTF-8') || strpos((string) $arrayData[$campo], "\0") !== false)) {
                $this->response->error('Los campos contienen caracteres no válidos.', 3118, 422);
            }
        }
        $codigo    = trim($arrayData['codigo_usuario'] ?? '');
        $nombres   = trim($arrayData['nombres'] ?? '');
        $apellidos = trim($arrayData['apellidos'] ?? '');
        $correo    = trim($arrayData['correo'] ?? '');
        $telefono  = trim($arrayData['telefono'] ?? '');
        $password  = $arrayData['password'] ?? '';
        $idRol     = $this->OBTENER_ID($arrayData['id_rol'] ?? null);
        $valorActivo = (string) ($arrayData['activo'] ?? '1');
        if (!in_array($valorActivo, ['0', '1'], true)) {
            $this->response->error('El estado activo debe ser 0 o 1.', 3119, 422);
        }
        $activo = (int) $valorActivo;

        if ($codigo === '' || mb_strlen($codigo) > 30) {
            $this->response->error("El código de usuario es obligatorio (máximo 30 caracteres)", 3101, 422);
        }
        if ($nombres === '' || mb_strlen($nombres) > 60) {
            $this->response->error("Los nombres son obligatorios (máximo 60 caracteres)", 3102, 422);
        }
        if ($apellidos === '' || mb_strlen($apellidos) > 60) {
            $this->response->error("Los apellidos son obligatorios (máximo 60 caracteres)", 3103, 422);
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
            $this->response->error("Ingresa un correo válido (máximo 150 caracteres)", 3104, 422);
        }
        if (mb_strlen($telefono) > 20) {
            $this->response->error("El teléfono no puede superar los 20 caracteres", 3105, 422);
        }
        if ($idRol === false || $idRol < 1) {
            $this->response->error("Selecciona un rol", 3106, 422);
        }
        if ($esNuevo || $password !== '') {
            $error = Password::validarNueva($password);
            if ($error !== null) $this->response->error($error, 3107, 422);
        }

        return [
            'codigo_usuario' => $codigo,
            'nombres'        => $nombres,
            'apellidos'      => $apellidos,
            'correo'         => $correo,
            'password_hash'  => $password !== '' ? Password::crear($password) : null,
            'telefono'       => $telefono === '' ? null : $telefono,
            'id_rol'         => $idRol,
            'activo'         => $activo,
        ];
    }

    private function OBTENER_ID($valor): int
    {
        if (!is_int($valor) && !is_string($valor)) {
            $this->response->error("Identificador no válido", 3108, 422);
        }
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $this->response->error("Identificador de usuario no válido", 3108, 422);
        }
        return $id;
    }

    private function VERIFICAR_QUE_EXISTE(int $idUsuario): array
    {
        $statement = $this->connection->prepare("SELECT id_rol, activo FROM usuarios WHERE id_usuario = :id_usuario FOR UPDATE");
        $statement->execute(['id_usuario' => $idUsuario]);
        $usuario = $statement->fetch();
        if (!$usuario) {
            $this->RECHAZAR("El usuario no existe", 3109, 404);
        }
        return $usuario;
    }

    // Serializa las mutaciones de cuentas para impedir perder administradores con dos solicitudes concurrentes.
    private function BLOQUEAR_ADMINISTRACION(): array
    {
        $this->connection->beginTransaction();
        $this->connection->query('SELECT id_rol FROM roles WHERE es_administrador = 1 ORDER BY id_rol FOR UPDATE')->fetchAll();
        $statement = $this->connection->query('SELECT u.id_usuario, u.version_sesion FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.activo = 1 AND r.es_administrador = 1 ORDER BY u.id_usuario FOR UPDATE');
        $administradores = $statement->fetchAll();
        $ids = array_map('intval', array_column($administradores, 'id_usuario'));
        $actor = (int) ($_SESSION['usuario']['id_usuario'] ?? 0);
        $version = (int) ($_SESSION['usuario']['version_sesion'] ?? 0);
        $vigente = false;
        foreach ($administradores as $administrador) {
            if ((int) $administrador['id_usuario'] === $actor && (int) $administrador['version_sesion'] === $version) $vigente = true;
        }
        if (!$vigente) {
            $this->RECHAZAR('Tu cuenta ya no tiene permiso para administrar usuarios.', 3120, 403);
        }
        return $ids;
    }

    private function VALIDAR_IDENTIFICADORES(array $datos, int $idUsuario): void
    {
        $statement = $this->connection->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario <> :id_usuario AND (correo = :codigo OR codigo_usuario = :correo) LIMIT 1');
        $statement->execute(['id_usuario' => $idUsuario, 'codigo' => $datos['codigo_usuario'], 'correo' => $datos['correo']]);
        if ($statement->fetchColumn()) {
            $this->RECHAZAR('El correo o código coincide con el identificador de otra cuenta.', 3121, 409);
        }
    }

    private function REVERTIR(): void
    {
        if ($this->connection->inTransaction()) $this->connection->rollBack();
    }

    private function RECHAZAR(string $mensaje, int $codigo, int $estado): void
    {
        $this->REVERTIR();
        $this->response->error($mensaje, $codigo, $estado);
    }

    // Traduce los errores de MySQL más comunes a mensajes entendibles
    private function RESPONDER_ERROR_BD(PDOException $e)
    {
        $codigoMysql = $e->errorInfo[1] ?? 0;
        $detalle = $e->errorInfo[2] ?? '';

        if ($codigoMysql == 1062) {
            // El mensaje de MySQL indica qué índice único se repitió
            preg_match("/for key '(?:\w+\.)?(\w+)'/", $detalle, $coincidencia);
            $llave = $coincidencia[1] ?? '';
            if ($llave === 'codigo_usuario') {
                $this->response->error("Ya existe un usuario con ese código", 3110, 409);
            }
            if ($llave === 'correo') {
                $this->response->error("Ya existe un usuario con ese correo", 3111, 409);
            }
            $this->response->error("Ya existe un registro con esos datos", 3112, 409);
        }
        if ($codigoMysql == 1452) {
            $this->response->error("El rol seleccionado no existe", 3113, 422);
        }
        if ($codigoMysql == 1451) {
            $this->response->error("No se puede eliminar el usuario porque tiene préstamos asociados. Puedes desactivarlo en su lugar.", 3114, 409);
        }
        throw $e; // cualquier otro error lo atrapa el controlador
    }
}
