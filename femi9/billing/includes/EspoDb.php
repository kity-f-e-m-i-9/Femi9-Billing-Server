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
        $conn = @mysqli_connect($host, $username, $password, $dbname, $port);

        if (!$conn) {
            return null;
        }

        return $conn;
    }
}
