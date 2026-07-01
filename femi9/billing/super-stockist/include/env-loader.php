<?php
/**
 * Simple .env file loader
 * Reads .env file and loads variables into $_ENV
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        die("Error: .env file not found at: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set in environment
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Auto-load .env from parent directory
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);
?>