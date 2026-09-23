<?php
include("checksession.php");
require_once("include/GodownAccess.php");
header('Content-Type: application/json');
error_reporting(0);

$godown_id   = (int)($_GET['godown_id'] ?? 0);
$warehouseId = filter_var($_GET['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
if (!$godown_id || !is_godown_allowed($db_conn, $godown_id)) { echo json_encode([]); exit; }

if ($warehouseId !== null) {
    // A specific physical warehouse was picked — show only that warehouse's
    // own closing_qty, since that's exactly what StockService::deduct() will
    // check against at submit time. Showing the combined total here (as
    // before) let a user pick a warehouse with little/no stock while the
    // UI still showed the sum across all warehouses as "available",
    // causing a confusing "insufficient stock" failure on submit.
    $stmt = $db_conn->prepare("
        SELECT p.id AS product_id, p.productName, s.closing_qty AS available_qty
        FROM stock s
        JOIN products p ON p.id = s.product_id
        WHERE s.user_type = 'company' AND s.user_id = ? AND s.warehouse_id = ? AND p.deleted_at IS NULL
          AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
          AND s.closing_qty > 0
        ORDER BY p.productName
    ");
    $stmt->bind_param("si", $godown_id, $warehouseId);
} else {
    // No warehouse selected yet — fall back to the combined total across all
    // warehouses (legacy view), same as before physical warehouses existed.
    $stmt = $db_conn->prepare("
        SELECT p.id AS product_id, p.productName, SUM(s.closing_qty) AS available_qty
        FROM stock s
        JOIN products p ON p.id = s.product_id
        WHERE s.user_type = 'company' AND s.user_id = ? AND p.deleted_at IS NULL
          AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
        GROUP BY p.id, p.productName
        HAVING SUM(s.closing_qty) > 0
        ORDER BY p.productName
    ");
    $stmt->bind_param("s", $godown_id);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($products);
