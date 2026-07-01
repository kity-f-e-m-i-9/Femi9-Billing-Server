<?php
include("checksession.php");
include("config.php");
header('Content-Type: application/json');

$tp_id = (int)($_GET['tp_id'] ?? 0);
if (!$tp_id) { echo json_encode(['has_stock' => false]); exit; }

$stmt = $db_conn->prepare("SELECT stock_initialized FROM territory_partners WHERE id=? AND is_active=1 LIMIT 1");
$stmt->bind_param("i", $tp_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode(['has_stock' => $row && (int)$row['stock_initialized'] === 1]);
