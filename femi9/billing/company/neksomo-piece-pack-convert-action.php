<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$godownId    = (string)(int)($_POST['godownid'] ?? 0);
$warehouseId = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;

$rawProductIds = $_POST['product_id'] ?? [];
$rawDirections = $_POST['direction'] ?? [];
$rawPackCounts = $_POST['pack_count'] ?? [];

if (!$godownId || !is_array($rawProductIds) || empty($rawProductIds)) {
    $_SESSION['errorMessage'] = "Invalid submission — please fill in every field.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

if (!is_godown_allowed($db_conn, (int)$godownId)) {
    $_SESSION['errorMessage'] = "You are not authorized to use this company profile.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

// Warehouse is required for this feature specifically (see plan's Global
// Constraints) — the picker is `required` client-side, this re-validates
// server-side since the client-side attribute alone is never trusted.
if ($warehouseId === null) {
    $_SESSION['errorMessage'] = "Please select a godown (physical).";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

// Validate and normalize every row up front, before touching StockService —
// any malformed row fails the whole batch immediately rather than partway
// through a transaction.
$rows = [];
foreach ($rawProductIds as $i => $rawProductId) {
    $productId = (int) $rawProductId;
    $direction = $rawDirections[$i] ?? '';
    $packCount = (int) ($rawPackCounts[$i] ?? 0);

    if (!$productId || $packCount < 1 || !in_array($direction, ['pieces_to_pack', 'pack_to_pieces'], true)) {
        $_SESSION['errorMessage'] = "Invalid submission — please fill in every field for every product.";
        header("Location: neksomo-piece-pack-convert.php");
        exit;
    }

    $rows[] = ['product_id' => $productId, 'direction' => $direction, 'pack_count' => $packCount];
}

// A product appearing twice in one batch is never a legitimate
// request — reject up front rather than silently applying two
// conversions to the same row in sequence.
$seenProductIds = array_column($rows, 'product_id');
if (count($seenProductIds) !== count(array_unique($seenProductIds))) {
    $_SESSION['errorMessage'] = "Each product can only appear once per conversion batch.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

$productIds = array_column($rows, 'product_id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$stmt = $db_conn->prepare("SELECT id, productName, pieces_per_pack FROM products WHERE id IN ($placeholders) AND pieces_per_pack > 1");
$stmt->bind_param(str_repeat('i', count($productIds)), ...$productIds);
$stmt->execute();
$productsById = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
    $productsById[(int) $p['id']] = $p;
}
$stmt->close();

foreach ($rows as $row) {
    if (!isset($productsById[$row['product_id']])) {
        $_SESSION['errorMessage'] = "One of the selected products does not support pieces/pack conversion.";
        header("Location: neksomo-piece-pack-convert.php");
        exit;
    }
}

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

// All-or-nothing: one shared transaction across every row in the batch —
// if any product's conversion fails (e.g. insufficient pieces/packs to
// draw from), none of the batch's conversions are applied.
$db_conn->begin_transaction();
try {
    $summaries = [];
    foreach ($rows as $row) {
        $piecesPerPack = (int) $productsById[$row['product_id']]['pieces_per_pack'];
        $productName   = $productsById[$row['product_id']]['productName'];
        $refId         = 'CONV-' . date('YmdHis') . '-' . random_int(100, 999);

        if ($row['direction'] === 'pieces_to_pack') {
            $result = $stockService->convertPiecesToPack(
                $row['product_id'], 'company', $godownId, $piecesPerPack, $row['pack_count'],
                $refId, $createdBy, true, $warehouseId
            );
            $summaries[] = "$productName: assembled {$row['pack_count']} pack(s) — now {$result['closing_qty_after']} pack(s), {$result['extra_pieces_after']} loose piece(s)";
        } else {
            $result = $stockService->convertPackToPieces(
                $row['product_id'], 'company', $godownId, $piecesPerPack, $row['pack_count'],
                $refId, $createdBy, true, $warehouseId
            );
            $summaries[] = "$productName: broke open {$row['pack_count']} pack(s) — now {$result['closing_qty_after']} pack(s), {$result['extra_pieces_after']} loose piece(s)";
        }
    }

    $db_conn->commit();
    $_SESSION['sucMessage'] = implode('. ', $summaries) . '.';
} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = $e->getMessage();
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("neksomo-piece-pack-convert-action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
}

header("Location: neksomo-piece-pack-convert.php");
exit;
