<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/RawMaterialBundles.php");
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

// If this finished product is mapped to a raw Neksomo product, Pieces ->
// Pack conversion draws SOLELY from a specific open bundle — the pooled
// stock.closing_qty row is not involved at all (see docs/superpowers/
// specs/2026-09-22-raw-material-bundle-tracking-design.md). "Current
// Stock" for a mapped product is therefore the sum of its open bundles'
// remaining pieces at this (godown, warehouse), not the pooled figure.
$rawSource = get_neksomo_source_for_company_product($db_conn, $productId);

if ($rawSource) {
    $rawProductId = (int) $rawSource['neksomo_product_id'];

    $openBundles = get_open_raw_material_bundles($db_conn, $rawProductId, (int) $godownId, $warehouseId);
    $totalBundlePieces = array_sum(array_column($openBundles, 'remaining_pieces'));

    echo json_encode([
        'mapped'          => true,
        'raw_product_id'  => $rawProductId,
        'raw_pieces'      => $totalBundlePieces,
        'open_bundles'    => $openBundles,
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
