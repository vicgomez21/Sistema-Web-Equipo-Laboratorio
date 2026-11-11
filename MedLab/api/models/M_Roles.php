<?php

/**
 * Modelo de la tabla roles (CRUD).
 */
class M_Roles
{
    private Connection $connection;
    private Response $response;

    public function __construct(Connection $connection, Response $response)
    {
        $this->connection = $connection;
        $this->response = $response;
    }

    function SELECT_ROLES()
    {
        $query = "SELECT r.id_rol, r.nombre, r.descripcion, COUNT(u.id_usuario) AS total_usuarios
                  FROM roles r
                  LEFT JOIN usuarios u ON u.id_rol = r.id_rol
                  GROUP BY r.id_rol, r.nombre, r.descripcion
                  ORDER BY r.id_rol DESC";
        $statement = $this->connection->prepare($query);
        $statement->execute();
        $this->response->success("Roles obtenidos correctamente", $statement->fetchAll(), 1);
    }

    function INSERT_ROL($arrayData)
    {
        $datos = $this->VALIDAR_DATOS($arrayData);

        $query = "INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)";
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($datos);
        } catch (PDOException $e) {
            $this->RESPONDER_ERROR_BD($e);
        }
        $this->response->success("Rol agregado correctamente", ['id_rol' => $this->connection->lastInsertId()], 1);
    }

    function UPDATE_ROL($arrayData)
    {
        $idRol = $this->OBTENER_ID($arrayData['id_rol'] ?? null);
        $datos = $this->VALIDAR_DATOS($arrayData);
        $actual = $this->VERIFICAR_QUE_EXISTE($idRol);
        if ((bool) $actual['es_administrador'] && $actual['nombre'] !== $datos['nombre']) {
            $this->response->error('El nombre del rol administrador está protegido.', 3008, 409);
        }

        $query = "UPDATE roles SET nombre = :nombre, descripcion = :descripcion WHERE id_rol = :id_rol";
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($datos + ['id_rol' => $idRol]);
        } catch (PDOException $e) {
            $this->RESPONDER_ERROR_BD($e);
        }
        $this->response->success("Rol actualizado correctamente", [], 1);
    }

    function DELETE_ROL($valorId)
    {
        $idRol = $this->OBTENER_ID($valorId);
        $actual = $this->VERIFICAR_QUE_EXISTE($idRol);
        if ((bool) $actual['es_administrador']) {
            $this->response->error('El rol administrador no se puede eliminar.', 3009, 409);
        }

        $query = "DELETE FROM roles WHERE id_rol = :id_rol";
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute(['id_rol' => $idRol]);
        } catch (PDOException $e) {
            $this->RESPONDER_ERROR_BD($e);
        }
        $this->response->success("Rol eliminado correctamente", [], 1);
    }

    # ---------- Métodos de apoyo ----------

    private function VALIDAR_DATOS(array $arrayData): array
    {
        foreach (['nombre', 'descripcion'] as $campo) {
            if (isset($arrayData[$campo]) && !is_string($arrayData[$campo])) {
                $this->response->error('Los campos deben contener un solo valor de texto.', 3010, 422);
            }
            if (isset($arrayData[$campo]) && (!mb_check_encoding($arrayData[$campo], 'UTF-8') || strpos($arrayData[$campo], "\0") !== false)) {
                $this->response->error('Los campos contienen caracteres no válidos.', 3010, 422);
            }
        }
        $nombre = trim($arrayData['nombre'] ?? '');
        $descripcion = trim($arrayData['descripcion'] ?? '');

        if ($nombre === '') {
            $this->response->error("El nombre del rol es obligatorio", 3001, 422);
        }
        if (mb_strlen($nombre) > 30) {
            $this->response->error("El nombre no puede superar los 30 caracteres", 3002, 422);
        }
        if (mb_strlen($descripcion) > 150) {
            $this->response->error("La descripción no puede superar los 150 caracteres", 3003, 422);
        }

        return [
            'nombre'      => $nombre,
            'descripcion' => $descripcion === '' ? null : $descripcion,
        ];
    }

    private function OBTENER_ID($valor): int
    {
        if (!is_int($valor) && !is_string($valor)) {
            $this->response->error("Identificador de rol no válido", 3004, 422);
        }
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $this->response->error("Identificador de rol no válido", 3004, 422);
        }
        return $id;
    }

    private function VERIFICAR_QUE_EXISTE(int $idRol): array
    {
        $statement = $this->connection->prepare("SELECT nombre, es_administrador FROM roles WHERE id_rol = :id_rol");
        $statement->execute(['id_rol' => $idRol]);
        $rol = $statement->fetch();
        if (!$rol) {
            $this->response->error("El rol no existe", 3005, 404);
        }
        return $rol;
    }

    // Traduce los errores de MySQL más comunes a mensajes entendibles
    private function RESPONDER_ERROR_BD(PDOException $e)
    {
        $codigoMysql = $e->errorInfo[1] ?? 0;

        if ($codigoMysql == 1062) {
            $this->response->error("Ya existe un rol con ese nombre", 3006, 409);
        }
        if ($codigoMysql == 1451) {
            $this->response->error("No se puede eliminar el rol porque tiene usuarios asociados", 3007, 409);
        }
        throw $e; // cualquier otro error lo atrapa el controlador
    }
}
