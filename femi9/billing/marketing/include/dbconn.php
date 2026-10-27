<?php
// Credentials come from shared/.env (never hard-code them here).
require_once __DIR__ . '/../../shared/env-loader.php';
$servername = $_ENV['DB_HOST'] ?? 'localhost';
$username   = $_ENV['DB_USERNAME'] ?? '';
$password   = $_ENV['DB_PASSWORD'] ?? '';
$dbname     = $_ENV['DB_NAME'] ?? 'billing0femi9_billingapp';
?>
