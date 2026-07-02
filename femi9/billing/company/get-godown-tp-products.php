<?php
include("checksession.php");
require_once("include/GodownAccess.php");
header('Content-Type: application/json');
error_reporting(0);

$godown_id = (int)($_GET['godown_id'] ?? 0);
if (!$godown_id || !is_godown_allowed($db_conn, $godown_id)) { echo json_encode([]); exit; }

$uid = (string)$godown_id;
$stmt = $db_conn->prepare("
    SELECT p.id AS product_id, p.productName, s.closing_qty AS available_qty,
           COALESCE(NULLIF(p.stockist_price, 0), p.mrp, 0) AS rate
    FROM stock s
    JOIN products p ON p.id = s.product_id
    WHERE s.user_type = 'company' AND s.user_id = ? AND s.closing_qty > 0 AND p.deleted_at IS NULL
    ORDER BY p.productName
");
$stmt->bind_param("s", $uid);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($products);
