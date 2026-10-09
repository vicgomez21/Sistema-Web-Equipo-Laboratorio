<?php
require_once(__DIR__ . "/Configuration.php");
require_once(__DIR__ . "/Response.php");

date_default_timezone_set("America/Guatemala");

/**
 * Única clase que abre la conexión a MySQL (PDO).
 * Todos los modelos reciben un objeto Connection; nadie más escribe el DSN.
 */
class Connection extends PDO
{
    public function __construct()
    {
        try {
            $dsn = "mysql:host=" . HOST_DB . ";port=" . PORT_DB . ";dbname=" . DATABASE . ";charset=" . CHARSET;
            parent::__construct($dsn, USER_DB, PASSWORD_DB, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // El detalle técnico va al log de PHP, no al usuario
            error_log("[MedLab] Error de conexion: " . $e->getMessage());
            Response::error("No se pudo conectar con la base de datos", -1001, 500);
        }
    }
}
