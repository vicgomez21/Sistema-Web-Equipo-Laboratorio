<?php

/** Límites persistentes y compartidos entre sesiones y procesos PHP. */
final class LoginThrottle
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function reservar(string $identificador, string $direccion): bool
    {
        // inet_pton evita varias representaciones textuales de la misma dirección IPv6.
        $ip = @inet_pton($direccion);
        $claveIp = hash('sha256', 'ip:' . ($ip === false ? 'desconocida' : bin2hex($ip)));

        try {
            $this->connection->beginTransaction();
            $ahora = (int)$this->connection->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
            $insertar = $this->connection->prepare(
                'INSERT INTO intentos_login (clave, intentos, ventana_inicio, bloqueado_hasta, actualizado_en)
                 VALUES (:clave, 0, :inicio, NULL, :actualizado)
                 ON DUPLICATE KEY UPDATE clave = VALUES(clave)'
            );
            $leer = $this->connection->prepare(
                'SELECT intentos, ventana_inicio, bloqueado_hasta FROM intentos_login WHERE clave = :clave FOR UPDATE'
            );
            $actualizar = $this->connection->prepare(
                'UPDATE intentos_login SET intentos = :intentos, ventana_inicio = :inicio,
                 bloqueado_hasta = :bloqueado, actualizado_en = :actualizado WHERE clave = :clave'
            );
            // Todos los procesos bloquean primero IP y después cuenta: orden sin ciclos.
            if (!$this->reservarClave($claveIp, 30, $ahora, $insertar, $leer, $actualizar)) {
                $this->connection->commit();
                // Una IP bloqueada no puede llenar la tabla usando identificadores inventados.
                return false;
            }

            // La collation de MySQL resuelve variantes de código/correo al mismo ID.
            $consulta = $this->connection->prepare(
                'SELECT id_usuario FROM usuarios WHERE correo = :correo OR codigo_usuario = :codigo LIMIT 2'
            );
            $consulta->execute(['correo' => $identificador, 'codigo' => $identificador]);
            $usuarios = $consulta->fetchAll(PDO::FETCH_COLUMN);
            $cuenta = count($usuarios) === 1 ? 'usuario:' . $usuarios[0]
                : 'identificador:' . mb_strtolower(trim($identificador), 'UTF-8');
            $permitido = $this->reservarClave(hash('sha256', 'cuenta:' . $cuenta), 5, $ahora,
                $insertar, $leer, $actualizar);
            $this->connection->commit();
            // Éxitos consumen intentos: una cuenta conocida no permite evadir el límite IP.
            return $permitido;
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $e; // Sin tabla/BD disponible, el controlador no autentica.
        }
    }

    private function reservarClave(string $clave, int $limite, int $ahora,
        PDOStatement $insertar, PDOStatement $leer, PDOStatement $actualizar): bool
    {
        $fechaActual = gmdate('Y-m-d H:i:s', $ahora);
        $insertar->execute(['clave' => $clave, 'inicio' => $fechaActual, 'actualizado' => $fechaActual]);
        $leer->execute(['clave' => $clave]);
        $fila = $leer->fetch();
        if (!$fila) {
            throw new RuntimeException('No se pudo reservar el intento.');
        }
        $inicio = (int)strtotime($fila['ventana_inicio'] . ' UTC');
        $intentos = (int)$fila['intentos'];
        if ($ahora >= $inicio + 900) {
            $inicio = $ahora;
            $intentos = 0;
        }
        $permitido = $intentos < $limite;
        // Reserva ANTES del hash; solicitudes simultáneas no exceden el límite.
        $intentos = min($limite, $intentos + 1);
        $actualizar->execute([
            'clave' => $clave, 'intentos' => $intentos,
            'inicio' => gmdate('Y-m-d H:i:s', $inicio),
            'bloqueado' => $intentos >= $limite ? gmdate('Y-m-d H:i:s', $inicio + 900) : null,
            'actualizado' => $fechaActual,
        ]);
        return $permitido;
    }
}
