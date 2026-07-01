<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$godown_id = (int)($_GET['godown_id'] ?? 0);
if (!$godown_id) { echo json_encode([]); exit; }

$stmt = $db_conn->prepare("
    SELECT p.id AS product_id, p.productName, s.closing_qty AS available_qty
    FROM stock s
    JOIN products p ON p.id = s.product_id
    WHERE s.user_type = 'company' AND s.user_id = ? AND s.closing_qty > 0 AND p.deleted_at IS NULL
    ORDER BY p.productName
");
$stmt->bind_param("s", $godown_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($products);
