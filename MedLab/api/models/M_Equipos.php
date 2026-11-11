<?php

/** Modelo CRUD del inventario de equipos. */
class M_Equipos
{
    private Connection $connection;
    private Response $response;

    public function __construct(Connection $connection, Response $response)
    {
        $this->connection = $connection;
        $this->response = $response;
    }

    public function SELECT_EQUIPOS(): void
    {
        $statement = $this->connection->prepare(
            'SELECT e.id_equipo, e.codigo_inventario, e.nombre, e.descripcion,
                    e.modelo, e.numero_serie, e.ubicacion, e.fecha_adquisicion,
                    e.activo, e.fecha_registro, e.id_categoria, e.id_estado, e.id_condicion,
                    m.nombre AS marca,
                    c.nombre AS categoria, es.nombre AS estado, co.nombre AS condicion
             FROM equipos e
             LEFT JOIN marcas m ON m.id_marca = e.id_marca
             INNER JOIN categorias c ON c.id_categoria = e.id_categoria
             INNER JOIN estados_equipo es ON es.id_estado = e.id_estado
             INNER JOIN condiciones_equipo co ON co.id_condicion = e.id_condicion
             ORDER BY e.id_equipo DESC'
        );
        $statement->execute();
        $catalogos = [
            'marcas' => $this->LISTAR_MARCAS(),
            'categorias' => $this->LISTAR_CATALOGO('categorias', 'id_categoria', true),
            'estados' => $this->LISTAR_CATALOGO('estados_equipo', 'id_estado'),
            'condiciones' => $this->LISTAR_CATALOGO('condiciones_equipo', 'id_condicion'),
        ];
        $this->response->success(
            'Equipos obtenidos correctamente',
            $statement->fetchAll(),
            1,
            ['catalogos' => $catalogos]
        );
    }

    public function INSERT_EQUIPO(array $arrayData): void
    {
        $datos = $this->VALIDAR_DATOS($arrayData);
        try {
            $this->connection->beginTransaction();
            $relaciones = $this->RESOLVER_RELACIONES($datos);
            $statement = $this->connection->prepare(
                'INSERT INTO equipos
                    (codigo_inventario, nombre, descripcion, id_marca, modelo, numero_serie,
                     id_categoria, id_estado, id_condicion, ubicacion, fecha_adquisicion, activo)
                 VALUES
                    (:codigo_inventario, :nombre, :descripcion, :id_marca, :modelo, :numero_serie,
                     :id_categoria, :id_estado, :id_condicion, :ubicacion, :fecha_adquisicion, :activo)'
            );
            $statement->execute($this->PARAMETROS_EQUIPO($datos, $relaciones));
            $idEquipo = $this->connection->lastInsertId();
            $this->connection->commit();
        } catch (PDOException $error) {
            $this->REVERTIR();
            $this->RESPONDER_ERROR_BD($error);
        } catch (Throwable $error) {
            $this->REVERTIR();
            throw $error;
        }
        $this->response->success('Equipo agregado correctamente', ['id_equipo' => $idEquipo], 1);
    }

    public function UPDATE_EQUIPO(array $arrayData): void
    {
        $idEquipo = $this->OBTENER_ID($arrayData['id_equipo'] ?? null);
        $datos = $this->VALIDAR_DATOS($arrayData);
        $this->VERIFICAR_QUE_EXISTE($idEquipo);
        try {
            $this->connection->beginTransaction();
            $relaciones = $this->RESOLVER_RELACIONES($datos);
            $statement = $this->connection->prepare(
                'UPDATE equipos SET
                    codigo_inventario = :codigo_inventario, nombre = :nombre,
                    descripcion = :descripcion, id_marca = :id_marca, modelo = :modelo,
                    numero_serie = :numero_serie, id_categoria = :id_categoria,
                    id_estado = :id_estado, id_condicion = :id_condicion,
                    ubicacion = :ubicacion, fecha_adquisicion = :fecha_adquisicion, activo = :activo
                 WHERE id_equipo = :id_equipo'
            );
            $statement->execute($this->PARAMETROS_EQUIPO($datos, $relaciones) + ['id_equipo' => $idEquipo]);
            $this->connection->commit();
        } catch (PDOException $error) {
            $this->REVERTIR();
            $this->RESPONDER_ERROR_BD($error);
        } catch (Throwable $error) {
            $this->REVERTIR();
            throw $error;
        }
        $this->response->success('Equipo actualizado correctamente', [], 1);
    }

    public function DELETE_EQUIPO($valorId): void
    {
        $idEquipo = $this->OBTENER_ID($valorId);
        $this->VERIFICAR_QUE_EXISTE($idEquipo);
        try {
            $statement = $this->connection->prepare('DELETE FROM equipos WHERE id_equipo = :id_equipo');
            $statement->execute(['id_equipo' => $idEquipo]);
        } catch (PDOException $error) {
            $this->RESPONDER_ERROR_BD($error);
        }
        $this->response->success('Equipo eliminado correctamente', [], 1);
    }

    private function VALIDAR_DATOS(array $arrayData): array
    {
        $campos = [
            'codigo_inventario', 'nombre', 'descripcion', 'marca', 'modelo', 'numero_serie',
            'id_categoria', 'id_estado', 'id_condicion', 'ubicacion', 'fecha_adquisicion', 'activo',
        ];
        foreach ($campos as $campo) {
            if (isset($arrayData[$campo]) && !is_string($arrayData[$campo])) {
                $this->response->error('Los campos deben contener un solo valor.', 3301, 422);
            }
            if (isset($arrayData[$campo]) &&
                (!mb_check_encoding($arrayData[$campo], 'UTF-8') || strpos($arrayData[$campo], "\0") !== false)) {
                $this->response->error('Los campos contienen caracteres no válidos.', 3302, 422);
            }
        }

        $datos = [];
        foreach ($campos as $campo) $datos[$campo] = trim($arrayData[$campo] ?? '');
        $limites = [
            'codigo_inventario' => 30, 'nombre' => 100, 'descripcion' => 1000,
            'marca' => 50, 'modelo' => 50, 'numero_serie' => 80, 'ubicacion' => 100,
        ];
        foreach ($limites as $campo => $limite) {
            if (mb_strlen($datos[$campo], 'UTF-8') > $limite) {
                $this->response->error('El campo ' . str_replace('_', ' ', $campo) .
                    ' supera los ' . $limite . ' caracteres.', 3303, 422);
            }
        }
        foreach (['codigo_inventario', 'nombre', 'id_categoria', 'id_estado', 'id_condicion'] as $requerido) {
            if ($datos[$requerido] === '') {
                $this->response->error('Completa todos los campos obligatorios.', 3304, 422);
            }
        }
        if (!in_array($datos['activo'], ['0', '1'], true)) {
            $this->response->error('El estado activo debe ser 0 o 1.', 3305, 422);
        }
        if ($datos['fecha_adquisicion'] !== '') {
            $partes = explode('-', $datos['fecha_adquisicion']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $datos['fecha_adquisicion']) ||
                count($partes) !== 3 || !checkdate((int)$partes[1], (int)$partes[2], (int)$partes[0])) {
                $this->response->error('La fecha de adquisición no es válida.', 3306, 422);
            }
        }
        return $datos;
    }

    private function RESOLVER_RELACIONES(array $datos): array
    {
        return [
            'id_marca' => $datos['marca'] === '' ? null :
                $this->OBTENER_O_CREAR('marcas', 'id_marca', $datos['marca'], true),
            'id_categoria' => $this->VALIDAR_RELACION(
                'categorias', 'id_categoria', $datos['id_categoria'], true
            ),
            'id_estado' => $this->VALIDAR_RELACION(
                'estados_equipo', 'id_estado', $datos['id_estado']
            ),
            'id_condicion' => $this->VALIDAR_RELACION(
                'condiciones_equipo', 'id_condicion', $datos['id_condicion']
            ),
        ];
    }

    private function OBTENER_O_CREAR(string $tabla, string $columnaId, string $nombre, bool $tieneActivo = false): int
    {
        $permitidos = ['marcas' => 'id_marca'];
        if (($permitidos[$tabla] ?? null) !== $columnaId) throw new LogicException('Catálogo no permitido.');
        $columnas = $tieneActivo ? '(nombre, activo)' : '(nombre)';
        $valores = $tieneActivo ? '(:nombre, 1)' : '(:nombre)';
        $insertar = $this->connection->prepare(
            "INSERT INTO {$tabla} {$columnas} VALUES {$valores}
             ON DUPLICATE KEY UPDATE {$columnaId} = LAST_INSERT_ID({$columnaId})"
        );
        $insertar->execute(['nombre' => $nombre]);
        $id = (int)$this->connection->lastInsertId();
        if ($id > 0) return $id;
        $buscar = $this->connection->prepare(
            "SELECT {$columnaId} FROM {$tabla} WHERE nombre = :nombre LIMIT 1"
        );
        $buscar->execute(['nombre' => $nombre]);
        $id = (int)$buscar->fetchColumn();
        if ($id < 1) throw new RuntimeException('No se pudo resolver el catálogo.');
        return $id;
    }

    private function PARAMETROS_EQUIPO(array $datos, array $relaciones): array
    {
        return [
            'codigo_inventario' => $datos['codigo_inventario'],
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] === '' ? null : $datos['descripcion'],
            'id_marca' => $relaciones['id_marca'],
            'modelo' => $datos['modelo'] === '' ? null : $datos['modelo'],
            'numero_serie' => $datos['numero_serie'] === '' ? null : $datos['numero_serie'],
            'id_categoria' => $relaciones['id_categoria'],
            'id_estado' => $relaciones['id_estado'],
            'id_condicion' => $relaciones['id_condicion'],
            'ubicacion' => $datos['ubicacion'] === '' ? null : $datos['ubicacion'],
            'fecha_adquisicion' => $datos['fecha_adquisicion'] === '' ? null : $datos['fecha_adquisicion'],
            'activo' => (int)$datos['activo'],
        ];
    }

    private function VALIDAR_RELACION(
        string $tabla,
        string $columnaId,
        string $valor,
        bool $requiereActivo = false
    ): int
    {
        $permitidos = [
            'categorias' => 'id_categoria',
            'estados_equipo' => 'id_estado',
            'condiciones_equipo' => 'id_condicion',
        ];
        if (($permitidos[$tabla] ?? null) !== $columnaId) throw new LogicException('Relación no permitida.');
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $this->response->error('Selecciona una clasificación válida.', 3313, 422);
        }
        $activo = $requiereActivo ? ' AND activo = 1' : '';
        $statement = $this->connection->prepare(
            "SELECT {$columnaId} FROM {$tabla} WHERE {$columnaId} = :id{$activo}"
        );
        $statement->execute(['id' => $id]);
        if (!$statement->fetchColumn()) {
            $this->response->error('La clasificación seleccionada no existe o está inactiva.', 3314, 422);
        }
        return (int)$id;
    }

    private function LISTAR_MARCAS(): array
    {
        return $this->connection->query(
            'SELECT nombre FROM marcas WHERE activo = 1 ORDER BY nombre'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    private function LISTAR_CATALOGO(string $tabla, string $columnaId, bool $incluyeActivo = false): array
    {
        $permitidos = [
            'categorias' => 'id_categoria',
            'estados_equipo' => 'id_estado',
            'condiciones_equipo' => 'id_condicion',
        ];
        if (($permitidos[$tabla] ?? null) !== $columnaId) throw new LogicException('Catálogo no permitido.');
        $activo = $incluyeActivo ? ', activo' : '';
        return $this->connection->query(
            "SELECT {$columnaId}, nombre{$activo} FROM {$tabla} ORDER BY nombre"
        )->fetchAll();
    }

    private function OBTENER_ID($valor): int
    {
        if (!is_int($valor) && !is_string($valor)) {
            $this->response->error('Identificador de equipo no válido.', 3307, 422);
        }
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $this->response->error('Identificador de equipo no válido.', 3307, 422);
        }
        return $id;
    }

    private function VERIFICAR_QUE_EXISTE(int $idEquipo): void
    {
        $statement = $this->connection->prepare('SELECT 1 FROM equipos WHERE id_equipo = :id_equipo');
        $statement->execute(['id_equipo' => $idEquipo]);
        if (!$statement->fetchColumn()) {
            $this->response->error('El equipo no existe.', 3308, 404);
        }
    }

    private function REVERTIR(): void
    {
        if ($this->connection->inTransaction()) $this->connection->rollBack();
    }

    private function RESPONDER_ERROR_BD(PDOException $error): void
    {
        $codigo = (int)($error->errorInfo[1] ?? 0);
        $detalle = strtolower((string)($error->errorInfo[2] ?? ''));
        if ($codigo === 1062) {
            if (strpos($detalle, 'codigo_inventario') !== false) {
                $this->response->error('Ya existe un equipo con ese código de inventario.', 3309, 409);
            }
            if (strpos($detalle, 'numero_serie') !== false) {
                $this->response->error('Ya existe un equipo con ese número de serie.', 3310, 409);
            }
            $this->response->error('Ya existe un registro con esos datos.', 3311, 409);
        }
        if ($codigo === 1451) {
            $this->response->error(
                'No se puede eliminar el equipo porque tiene préstamos asociados. Puedes desactivarlo.',
                3312,
                409
            );
        }
        throw $error;
    }
}
