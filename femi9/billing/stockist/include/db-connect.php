<?php
error_reporting(0);

require_once __DIR__ . '/../../shared/env-loader.php';

$servername = $_ENV['DB_HOST']     ?? 'localhost';
$db_port    = (int)($_ENV['DB_PORT'] ?? 3306);
$username   = $_ENV['DB_USERNAME'] ?? 'billing0femi9_femi9admin';
$password   = $_ENV['DB_PASSWORD'] ?? 'mavNip-xukvyk-9veqra';
$dbname     = $_ENV['DB_NAME']     ?? 'billing0femi9_billingapp';

$db_conn = mysqli_connect($servername, $username, $password, $dbname, $db_port);
if (!$db_conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$business_name = "Femi9 - Happy day Everyday";
?>
