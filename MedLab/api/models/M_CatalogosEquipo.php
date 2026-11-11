<?php

/** CRUD reutilizable para categorías, estados y condiciones de equipo. */
class M_CatalogosEquipo
{
    private Connection $connection;
    private Response $response;
    private string $tabla;
    private string $idCampo;
    private string $entidad;
    private int $limiteNombre;
    private bool $tieneActivo;

    private function __construct(
        Connection $connection,
        Response $response,
        string $tabla,
        string $idCampo,
        string $entidad,
        int $limiteNombre,
        bool $tieneActivo
    ) {
        $this->connection = $connection;
        $this->response = $response;
        $this->tabla = $tabla;
        $this->idCampo = $idCampo;
        $this->entidad = $entidad;
        $this->limiteNombre = $limiteNombre;
        $this->tieneActivo = $tieneActivo;
    }

    public static function categorias(Connection $connection, Response $response): self
    {
        return new self($connection, $response, 'categorias', 'id_categoria', 'categoría', 60, true);
    }

    public static function estados(Connection $connection, Response $response): self
    {
        return new self($connection, $response, 'estados_equipo', 'id_estado', 'estado de equipo', 30, false);
    }

    public static function condiciones(Connection $connection, Response $response): self
    {
        return new self($connection, $response, 'condiciones_equipo', 'id_condicion', 'condición de equipo', 30, false);
    }

    public function SELECT_CATALOGO(): void
    {
        $activo = $this->tieneActivo ? ', c.activo' : '';
        $query = "SELECT c.{$this->idCampo}, c.nombre, c.descripcion{$activo},
                         COUNT(e.id_equipo) AS total_equipos
                  FROM {$this->tabla} c
                  LEFT JOIN equipos e ON e.{$this->idCampo} = c.{$this->idCampo}
                  GROUP BY c.{$this->idCampo}, c.nombre, c.descripcion" .
                  ($this->tieneActivo ? ', c.activo' : '') .
                 " ORDER BY c.{$this->idCampo} DESC";
        $statement = $this->connection->prepare($query);
        $statement->execute();
        $this->response->success('Catálogo obtenido correctamente', $statement->fetchAll(), 1);
    }

    public function INSERT_CATALOGO(array $arrayData): void
    {
        $datos = $this->VALIDAR_DATOS($arrayData);
        $columnas = $this->tieneActivo ? '(nombre, descripcion, activo)' : '(nombre, descripcion)';
        $valores = $this->tieneActivo ? '(:nombre, :descripcion, :activo)' : '(:nombre, :descripcion)';
        try {
            $statement = $this->connection->prepare(
                "INSERT INTO {$this->tabla} {$columnas} VALUES {$valores}"
            );
            $statement->execute($datos);
        } catch (PDOException $error) {
            $this->RESPONDER_ERROR_BD($error);
        }
        $this->response->success(
            'Registro agregado correctamente',
            [$this->idCampo => $this->connection->lastInsertId()],
            1
        );
    }

    public function UPDATE_CATALOGO(array $arrayData): void
    {
        $id = $this->OBTENER_ID($arrayData[$this->idCampo] ?? null);
        $datos = $this->VALIDAR_DATOS($arrayData);
        $this->VERIFICAR_QUE_EXISTE($id);
        if ($this->tieneActivo && $datos['activo'] === 0 && $this->TOTAL_EQUIPOS($id) > 0) {
            $this->response->error(
                'No se puede desactivar la categoría porque tiene equipos asociados.',
                3410,
                409
            );
        }
        $asignaciones = $this->tieneActivo
            ? 'nombre = :nombre, descripcion = :descripcion, activo = :activo'
            : 'nombre = :nombre, descripcion = :descripcion';
        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->tabla} SET {$asignaciones} WHERE {$this->idCampo} = :id"
            );
            $statement->execute($datos + ['id' => $id]);
        } catch (PDOException $error) {
            $this->RESPONDER_ERROR_BD($error);
        }
        $this->response->success('Registro actualizado correctamente', [], 1);
    }

    public function DELETE_CATALOGO($valorId): void
    {
        $id = $this->OBTENER_ID($valorId);
        $this->VERIFICAR_QUE_EXISTE($id);
        try {
            $statement = $this->connection->prepare(
                "DELETE FROM {$this->tabla} WHERE {$this->idCampo} = :id"
            );
            $statement->execute(['id' => $id]);
        } catch (PDOException $error) {
            $this->RESPONDER_ERROR_BD($error);
        }
        $this->response->success('Registro eliminado correctamente', [], 1);
    }

    public function campoId(): string
    {
        return $this->idCampo;
    }

    private function VALIDAR_DATOS(array $arrayData): array
    {
        $campos = $this->tieneActivo ? ['nombre', 'descripcion', 'activo'] : ['nombre', 'descripcion'];
        foreach ($campos as $campo) {
            if (isset($arrayData[$campo]) && !is_string($arrayData[$campo])) {
                $this->response->error('Los campos deben contener un solo valor.', 3401, 422);
            }
            if (isset($arrayData[$campo]) &&
                (!mb_check_encoding($arrayData[$campo], 'UTF-8') || strpos($arrayData[$campo], "\0") !== false)) {
                $this->response->error('Los campos contienen caracteres no válidos.', 3402, 422);
            }
        }
        $nombre = trim($arrayData['nombre'] ?? '');
        $descripcion = trim($arrayData['descripcion'] ?? '');
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > $this->limiteNombre) {
            $this->response->error(
                'El nombre es obligatorio (máximo ' . $this->limiteNombre . ' caracteres).',
                3403,
                422
            );
        }
        if (mb_strlen($descripcion, 'UTF-8') > 150) {
            $this->response->error('La descripción no puede superar los 150 caracteres.', 3404, 422);
        }
        $datos = ['nombre' => $nombre, 'descripcion' => $descripcion === '' ? null : $descripcion];
        if ($this->tieneActivo) {
            $activo = (string)($arrayData['activo'] ?? '1');
            if (!in_array($activo, ['0', '1'], true)) {
                $this->response->error('El estado activo debe ser 0 o 1.', 3405, 422);
            }
            $datos['activo'] = (int)$activo;
        }
        return $datos;
    }

    private function OBTENER_ID($valor): int
    {
        if (!is_int($valor) && !is_string($valor)) {
            $this->response->error('Identificador no válido.', 3406, 422);
        }
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $this->response->error('Identificador no válido.', 3406, 422);
        }
        return $id;
    }

    private function VERIFICAR_QUE_EXISTE(int $id): void
    {
        $statement = $this->connection->prepare(
            "SELECT 1 FROM {$this->tabla} WHERE {$this->idCampo} = :id"
        );
        $statement->execute(['id' => $id]);
        if (!$statement->fetchColumn()) {
            $this->response->error(ucfirst($this->entidad) . ' no existe.', 3407, 404);
        }
    }

    private function TOTAL_EQUIPOS(int $id): int
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*) FROM equipos WHERE {$this->idCampo} = :id"
        );
        $statement->execute(['id' => $id]);
        return (int)$statement->fetchColumn();
    }

    private function RESPONDER_ERROR_BD(PDOException $error): void
    {
        $codigo = (int)($error->errorInfo[1] ?? 0);
        if ($codigo === 1062) {
            $this->response->error('Ya existe un registro con ese nombre.', 3408, 409);
        }
        if ($codigo === 1451) {
            $mensaje = 'No se puede eliminar porque tiene equipos asociados.';
            if ($this->tieneActivo) $mensaje .= ' Puedes desactivar la categoría.';
            $this->response->error($mensaje, 3409, 409);
        }
        throw $error;
    }
}
