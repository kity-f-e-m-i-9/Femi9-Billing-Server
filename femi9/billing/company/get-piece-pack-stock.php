<?php
include("checksession.php");
require_once("include/GodownAccess.php");
header('Content-Type: application/json');
error_reporting(0);

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching every other Neksomo-only page's gate.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$productId = (int)($_GET['product_id'] ?? 0);
$warehouseId = filter_var($_GET['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;

if (!$productId) {
    echo json_encode(['error' => 'invalid_product']);
    exit;
}

$productStmt = $db_conn->prepare("SELECT pieces_per_pack FROM products WHERE id = ?");
$productStmt->bind_param('i', $productId);
$productStmt->execute();
$productRow = $productStmt->get_result()->fetch_assoc();
$productStmt->close();

if (!$productRow) {
    echo json_encode(['error' => 'product_not_found']);
    exit;
}

$piecesPerPack = max((int)($productRow['pieces_per_pack'] ?? 1), 1);

$sql = "SELECT closing_qty, extra_pieces FROM stock
        WHERE product_id = ? AND user_type = 'company' AND user_id = ?
          AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?');
$stmt = $db_conn->prepare($sql);
$godownId = (string)((int)($_GET['godown_id'] ?? 0));
if ($warehouseId === null) {
    $stmt->bind_param('is', $productId, $godownId);
} else {
    $stmt->bind_param('isi', $productId, $godownId, $warehouseId);
}
$stmt->execute();
$stockRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'closing_qty'     => (int)($stockRow['closing_qty'] ?? 0),
    'extra_pieces'    => (int)($stockRow['extra_pieces'] ?? 0),
    'pieces_per_pack' => $piecesPerPack,
]);
