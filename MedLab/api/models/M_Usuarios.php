<?php

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
            $statement = $this->connection->prepare($query);
            $statement->execute($datos);
        } catch (PDOException $e) {
            $this->RESPONDER_ERROR_BD($e);
        }
        $this->response->success("Usuario agregado correctamente", ['id_usuario' => $this->connection->lastInsertId()], 1);
    }

    function UPDATE_USUARIO($arrayData)
    {
        $idUsuario = $this->OBTENER_ID($arrayData['id_usuario'] ?? null);
        $datos = $this->VALIDAR_DATOS($arrayData, false);
        $this->VERIFICAR_QUE_EXISTE($idUsuario);

        // La contraseña solo se cambia si se envió una nueva
        $campos = "codigo_usuario = :codigo_usuario, nombres = :nombres, apellidos = :apellidos,
                   correo = :correo, telefono = :telefono, id_rol = :id_rol, activo = :activo";
        if ($datos['password_hash'] !== null) {
            $campos .= ", password_hash = :password_hash";
        } else {
            unset($datos['password_hash']);
        }

        $query = "UPDATE usuarios SET $campos WHERE id_usuario = :id_usuario";
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($datos + ['id_usuario' => $idUsuario]);
        } catch (PDOException $e) {
            $this->RESPONDER_ERROR_BD($e);
        }
        $this->response->success("Usuario actualizado correctamente", [], 1);
    }

    function DELETE_USUARIO($valorId)
    {
        $idUsuario = $this->OBTENER_ID($valorId);
        $this->VERIFICAR_QUE_EXISTE($idUsuario);

        $query = "DELETE FROM usuarios WHERE id_usuario = :id_usuario";
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute(['id_usuario' => $idUsuario]);
        } catch (PDOException $e) {
            $this->RESPONDER_ERROR_BD($e);
        }
        $this->response->success("Usuario eliminado correctamente", [], 1);
    }

    # ---------- Métodos de apoyo ----------

    private function VALIDAR_DATOS(array $arrayData, bool $esNuevo): array
    {
        $codigo    = trim($arrayData['codigo_usuario'] ?? '');
        $nombres   = trim($arrayData['nombres'] ?? '');
        $apellidos = trim($arrayData['apellidos'] ?? '');
        $correo    = trim($arrayData['correo'] ?? '');
        $telefono  = trim($arrayData['telefono'] ?? '');
        $password  = $arrayData['password'] ?? '';
        $idRol     = filter_var($arrayData['id_rol'] ?? null, FILTER_VALIDATE_INT);
        $activo    = (($arrayData['activo'] ?? '1') === '0') ? 0 : 1;

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
        if (($esNuevo || $password !== '') && mb_strlen($password) < 8) {
            $this->response->error("La contraseña debe tener al menos 8 caracteres", 3107, 422);
        }

        return [
            'codigo_usuario' => $codigo,
            'nombres'        => $nombres,
            'apellidos'      => $apellidos,
            'correo'         => $correo,
            'password_hash'  => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'telefono'       => $telefono === '' ? null : $telefono,
            'id_rol'         => $idRol,
            'activo'         => $activo,
        ];
    }

    private function OBTENER_ID($valor): int
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $this->response->error("Identificador de usuario no válido", 3108, 422);
        }
        return $id;
    }

    private function VERIFICAR_QUE_EXISTE(int $idUsuario)
    {
        $statement = $this->connection->prepare("SELECT 1 FROM usuarios WHERE id_usuario = :id_usuario");
        $statement->execute(['id_usuario' => $idUsuario]);
        if (!$statement->fetchColumn()) {
            $this->response->error("El usuario no existe", 3109, 404);
        }
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
