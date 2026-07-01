<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$cp_id = (int)($_GET['cp_id'] ?? 0);
if (!$cp_id) { echo json_encode([]); exit; }

$stmt = $db_conn->prepare("
    SELECT p.id AS product_id, p.productName, cps.closing_qty AS available_qty
    FROM channel_partner_stock cps
    JOIN products p ON p.id = cps.product_id
    WHERE cps.channel_partner_id = ? AND cps.closing_qty > 0 AND p.deleted_at IS NULL
    ORDER BY p.productName
");
$stmt->bind_param("i", $cp_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($products);
