<?php
/** Contadores compartidos entre sesiones/procesos, fuera del directorio público. */
class LoginLimiter {
    private string $directory;
    const WINDOW = 900;
    const ACCOUNT_LIMIT = 5;
    const IP_LIMIT = 30;
    public function __construct(?string $directory = null) {
        $this->directory = $directory ?? sys_get_temp_dir() . '/medlab-login-' . substr(hash('sha256', __DIR__), 0, 24);
    }
    private function locked(callable $operation) {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('No se pudo crear el almacenamiento de intentos.');
        }
        $file = @fopen($this->directory . '/attempts.lock', 'c');
        if (!$file) throw new RuntimeException('No se pudo abrir el almacenamiento de intentos.');
        try {
            if (!flock($file, LOCK_EX)) throw new RuntimeException('No se pudo bloquear el almacenamiento de intentos.');
            $dataFile = $this->directory . '/attempts.json';
            $raw = '';
            if (is_file($dataFile)) {
                if (filesize($dataFile) > 2097152) throw new RuntimeException('Almacenamiento de intentos fuera de límite.');
                $raw = file_get_contents($dataFile);
                if ($raw === false || $raw === '') throw new RuntimeException('Almacenamiento de intentos no válido.');
            }
            $state = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($state)) throw new RuntimeException('Almacenamiento de intentos no válido.');
            $result = $operation($state);
            $json = json_encode($state, JSON_THROW_ON_ERROR);
            // Reemplazo atómico: el bloqueo está en otro archivo y nunca se trunca el estado vigente.
            $temporary = tempnam($this->directory, 'attempt-');
            if ($temporary === false) throw new RuntimeException('No se pudo guardar el límite de intentos.');
            try {
                if (file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $dataFile)) {
                    throw new RuntimeException('No se pudo guardar el límite de intentos.');
                }
            } finally { if (is_file($temporary)) unlink($temporary); }
            return $result;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
    private function accountKey(string $account): string { return 'a:' . hash('sha256', $account); }
    public function reserve(string $account, string $ip, ?int $now = null): int {
        $now = $now ?? time();
        return $this->locked(function (&$state) use ($account, $ip, $now) {
            foreach ($state as $key => $entry) if ($entry['until'] <= $now) unset($state[$key]);
            $limits = [$this->accountKey($account) => self::ACCOUNT_LIMIT, 'i:' . hash('sha256', $ip) => self::IP_LIMIT];
            $retry = 0;
            foreach ($limits as $key => $limit) {
                if (($state[$key]['count'] ?? 0) >= $limit) $retry = max($retry, $state[$key]['until'] - $now);
            }
            if ($retry > 0) return $retry;
            $newKeys = count(array_diff(array_keys($limits), array_keys($state)));
            if (count($state) + $newKeys > 5000) throw new RuntimeException('Almacenamiento de intentos lleno.');
            foreach ($limits as $key => $limit) {
                if (!isset($state[$key])) $state[$key] = ['count' => 0, 'until' => $now + self::WINDOW];
                $state[$key]['count']++;
            }
            return 0;
        });
    }
    public function success(string $account): void {
        $this->locked(function (&$state) use ($account) { unset($state[$this->accountKey($account)]); });
    }
}
