<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/NeksomoStockBridge.php");
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
$godownId = (string)((int)($_GET['godown_id'] ?? 0));

function fetch_stock_row(mysqli $db_conn, int $productId, string $godownId, ?int $warehouseId): ?array
{
    $sql = "SELECT closing_qty, extra_pieces FROM stock
            WHERE product_id = ? AND user_type = 'company' AND user_id = ?
              AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?');
    $stmt = $db_conn->prepare($sql);
    if ($warehouseId === null) {
        $stmt->bind_param('is', $productId, $godownId);
    } else {
        $stmt->bind_param('isi', $productId, $godownId, $warehouseId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// If this finished product is mapped to a raw Neksomo product, "Current
// Stock" for the Pieces -> Pack direction means the RAW product's own
// piece stock at this same (godown, warehouse) — not this finished
// product's own extra_pieces — since assembling now draws from the raw
// pool, not from a loose-piece remainder sitting on the finished SKU
// itself. See neksomo-piece-pack-convert-action.php's mapped-product path.
$rawSource = get_neksomo_source_for_company_product($db_conn, $productId);

if ($rawSource) {
    $rawProductId = (int) $rawSource['neksomo_product_id'];
    $rawRow = fetch_stock_row($db_conn, $rawProductId, $godownId, $warehouseId);
    echo json_encode([
        'mapped'          => true,
        'raw_product_id'  => $rawProductId,
        'raw_pieces'      => (int) ($rawRow['closing_qty'] ?? 0),
        'closing_qty'     => 0,
        'extra_pieces'    => 0,
        'pieces_per_pack' => $piecesPerPack,
    ]);
    exit;
}

$stockRow = fetch_stock_row($db_conn, $productId, $godownId, $warehouseId);

echo json_encode([
    'mapped'          => false,
    'closing_qty'     => (int)($stockRow['closing_qty'] ?? 0),
    'extra_pieces'    => (int)($stockRow['extra_pieces'] ?? 0),
    'pieces_per_pack' => $piecesPerPack,
]);
