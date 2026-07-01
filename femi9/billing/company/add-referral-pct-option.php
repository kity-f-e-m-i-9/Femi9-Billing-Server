<?php
include("checksession.php");
error_reporting(0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$pct = (float)($_POST['percentage'] ?? 0);
if ($pct <= 0 || $pct > 100) {
    echo json_encode(['success' => false, 'message' => 'Invalid percentage']);
    exit;
}

$db_conn->query("
    CREATE TABLE IF NOT EXISTS referral_percentage_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        percentage DECIMAL(5,2) NOT NULL,
        UNIQUE KEY uk_pct (percentage)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$stmt = $db_conn->prepare("INSERT IGNORE INTO referral_percentage_options (percentage) VALUES (?)");
$stmt->bind_param("d", $pct);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'percentage' => $pct]);
exit;
