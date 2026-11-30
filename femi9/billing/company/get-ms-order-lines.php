<?php
// AJAX data source for ms_prorders.php's Edit/Changes modals — current
// product lines (qty + effective price) and the full edit history for one
// DM order. 2026-11-13, user request.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once(__DIR__ . '/../shared/MsOrderEdits.php');
header('Content-Type: application/json');
error_reporting(0);

msOrderEditsEnsureSchema($db_conn);

$order_id = $_GET['order_id'] ?? '';
if ($order_id === '') {
    echo json_encode(['success' => false, 'error' => 'order_id required']);
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT mo.pr_id, mo.qty, mo.price_override, p.productName, p.outlet_price
     FROM ms_orders mo LEFT JOIN products p ON p.id = mo.pr_id
     WHERE mo.order_id = ? ORDER BY mo.id ASC"
);
$stmt->bind_param('s', $order_id);
$stmt->execute();
$lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$outLines = [];
foreach ($lines as $l) {
    if ((int)$l['pr_id'] <= 0) { continue; }
    $outLines[] = [
        'pr_id' => (int)$l['pr_id'],
        'name' => $l['productName'] ?? ('#' . $l['pr_id']),
        'qty' => (int)$l['qty'],
        'price' => $l['price_override'] !== null ? (float)$l['price_override'] : (float)$l['outlet_price'],
        'has_override' => $l['price_override'] !== null,
    ];
}

$log = msOrderFetchEditLog($db_conn, $order_id);
$outLog = [];
foreach ($log as $e) {
    $outLog[] = [
        'product' => $e['productName'] ?? ('#' . $e['pr_id']),
        'old_qty' => (int)$e['old_qty'], 'new_qty' => (int)$e['new_qty'],
        'old_price' => $e['old_price'] !== null ? (float)$e['old_price'] : null,
        'new_price' => $e['new_price'] !== null ? (float)$e['new_price'] : null,
        'reason' => $e['reason'],
        'by_type' => $e['edited_by_type'], 'by_name' => $e['edited_by_name'],
        'at' => $e['edited_at'],
    ];
}

echo json_encode(['success' => true, 'invoiced' => msOrderIsInvoiced($db_conn, $order_id), 'lines' => $outLines, 'log' => $outLog]);
