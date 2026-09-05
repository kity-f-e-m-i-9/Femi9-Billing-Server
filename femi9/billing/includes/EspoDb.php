<?php
/**
 * Read-only connection to the externally-hosted EspoCRM MySQL database.
 * Never issue write queries through the connection this returns.
 */

require_once __DIR__ . '/../shared/env-loader.php';

if (!function_exists('getEspoDbConnection')) {
    function getEspoDbConnection(): ?mysqli {
        $host     = $_ENV['ESPO_DB_HOST'] ?? '';
        $port     = (int)($_ENV['ESPO_DB_PORT'] ?? 3306);
        $username = $_ENV['ESPO_DB_USERNAME'] ?? '';
        $password = $_ENV['ESPO_DB_PASSWORD'] ?? '';
        $dbname   = $_ENV['ESPO_DB_NAME'] ?? '';

        if ($host === '' || $username === '' || $dbname === '') {
            return null;
        }

        mysqli_report(MYSQLI_REPORT_OFF);

        // This connects across datacenters to EspoCRM's remote DB. Without an
        // explicit timeout, mysqli_connect() falls back to the OS/PHP default
        // (often 75s+, or effectively unbounded against a silently-dropping
        // firewall) — and since PHP sessions use file-based locking by default,
        // a hung connect/read here blocks every other page the same logged-in
        // user has open. Keep these bounded and fail fast to null instead.
        $conn = mysqli_init();
        if ($conn === false) {
            return null;
        }
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        $conn->options(MYSQLI_OPT_READ_TIMEOUT, 10);

        $connected = @$conn->real_connect($host, $username, $password, $dbname, $port);

        if (!$connected) {
            return null;
        }

        return $conn;
    }
}
