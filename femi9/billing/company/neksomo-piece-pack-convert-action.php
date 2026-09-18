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

$productId   = (int)($_POST['product_id'] ?? 0);
$godownId    = (string)(int)($_POST['godownid'] ?? 0);
$warehouseId = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$direction   = $_POST['direction'] ?? '';
$packCount   = (int)($_POST['pack_count'] ?? 0);

if (!$productId || !$godownId || $packCount < 1 || !in_array($direction, ['pieces_to_pack', 'pack_to_pieces'], true)) {
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

$stmt = $db_conn->prepare("SELECT pieces_per_pack FROM products WHERE id = ? AND pieces_per_pack > 1");
$stmt->bind_param('i', $productId);
$stmt->execute();
$productRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$productRow) {
    $_SESSION['errorMessage'] = "Selected product does not support pieces/pack conversion.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}
$piecesPerPack = (int)$productRow['pieces_per_pack'];

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';
$refId        = 'CONV-' . date('YmdHis') . '-' . random_int(100, 999);

try {
    if ($direction === 'pieces_to_pack') {
        $result = $stockService->convertPiecesToPack(
            $productId, 'company', $godownId, $piecesPerPack, $packCount,
            $refId, $createdBy, false, $warehouseId
        );
        $_SESSION['sucMessage'] = "Assembled $packCount pack(s). New stock: {$result['closing_qty_after']} pack(s), {$result['extra_pieces_after']} loose piece(s).";
    } else {
        $result = $stockService->convertPackToPieces(
            $productId, 'company', $godownId, $piecesPerPack, $packCount,
            $refId, $createdBy, false, $warehouseId
        );
        $_SESSION['sucMessage'] = "Broke open $packCount pack(s). New stock: {$result['closing_qty_after']} pack(s), {$result['extra_pieces_after']} loose piece(s).";
    }
} catch (StockException $e) {
    $_SESSION['errorMessage'] = $e->getMessage();
} catch (\Throwable $e) {
    error_log("neksomo-piece-pack-convert-action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
}

header("Location: neksomo-piece-pack-convert.php");
exit;
