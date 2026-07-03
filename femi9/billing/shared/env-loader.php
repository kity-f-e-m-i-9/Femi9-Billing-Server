<?php
/**
 * Shared .env file loader
 * Loads from parent billing directory
 */

if (!function_exists('loadEnv')) {
    // Guarded against redeclaration: on a case-insensitive filesystem (macOS/
    // Windows), this file can get require_once'd via two differently-cased
    // paths (e.g. a URL with the wrong case in the project folder name),
    // which defeats require_once's path-based deduplication and would
    // otherwise cause a "Cannot redeclare loadEnv()" fatal error.
    function loadEnv($path) {
        if (!file_exists($path)) {
            die("Error: .env file not found at: $path");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, '"\'');

                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Load from parent billing directory
$envPath = __DIR__ . '/.env';
loadEnv($envPath);
?>