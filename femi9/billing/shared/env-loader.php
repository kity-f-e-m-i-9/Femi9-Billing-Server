<?php
/**
 * Shared .env file loader
 * Loads from parent billing directory
 */

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

// Load from parent billing directory
$envPath = __DIR__ . '/.env';
loadEnv($envPath);
?>