<?php

/**
 * Modelo de prueba: verifica que PHP se conecta a MedLabDB
 * y que existen las 9 tablas del diseño.
 */
class M_Conexion
{
    private Connection $connection;
    private Response $response;

    // Tablas definidas en MedLabDB (solo se leen, no se crean desde PHP)
    private const TABLAS_ESPERADAS = [
        'roles',
        'categorias',
        'estados_equipo',
        'condiciones_equipo',
        'estados_prestamo',
        'usuarios',
        'equipos',
        'prestamos',
        'detalle_prestamo',
    ];

    public function __construct(Connection $connection, Response $response)
    {
        $this->connection = $connection;
        $this->response = $response;
    }

    function VERIFICAR_CONEXION()
    {
        $info = $this->connection
            ->query("SELECT DATABASE() AS base_datos, VERSION() AS version_mysql")
            ->fetch();

        $query = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :base_datos";
        $statement = $this->connection->prepare($query);
        $statement->execute(['base_datos' => DATABASE]);
        $existentes = array_map('strtolower', $statement->fetchAll(PDO::FETCH_COLUMN));

        $tablas = [];
        $totalExistentes = 0;
        foreach (self::TABLAS_ESPERADAS as $nombre) {
            $existe = in_array($nombre, $existentes, true);
            if ($existe) {
                $totalExistentes++;
            }
            $tablas[] = ['nombre' => $nombre, 'existe' => $existe];
        }

        $this->response->success("Conexión exitosa con " . DATABASE, [
            'base_datos'        => $info['base_datos'],
            'version_mysql'     => $info['version_mysql'],
            'tablas'            => $tablas,
            'total_esperadas'   => count(self::TABLAS_ESPERADAS),
            'total_existentes'  => $totalExistentes,
        ], 1);
    }
}
