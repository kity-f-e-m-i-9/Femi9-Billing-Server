<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching the Convert Pieces<->Packs page this feature feeds into.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
    header("Location: input-stock-bundles.php");
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$rawProductId     = filter_var($_POST['raw_product_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$companyGodownId  = filter_var($_POST['company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$warehouseId      = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$nominalPieces    = filter_var($_POST['nominal_pieces'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$bundleCount      = filter_var($_POST['bundle_count'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$createdBy        = $_SESSION['LOGIN_USER'] ?? 'system';

if (!$rawProductId || !$companyGodownId || $nominalPieces <= 0 || $bundleCount <= 0) {
    $_SESSION['errorMessage'] = "Please fill in every required field.";
    header("Location: input-stock-bundles.php");
    exit;
}

if (!is_godown_allowed($db_conn, $companyGodownId)) {
    $_SESSION['errorMessage'] = "You are not authorized to use this company profile.";
    header("Location: input-stock-bundles.php");
    exit;
}

// Only raw (NKS-tagged) products are eligible — same scope the form
// itself restricts its dropdown to, re-validated here.
$prodStmt = $db_conn->prepare("SELECT temp_id FROM products WHERE id = ?");
$prodStmt->bind_param('i', $rawProductId);
$prodStmt->execute();
$prodRow = $prodStmt->get_result()->fetch_assoc();
$prodStmt->close();
if (!$prodRow || strpos((string) $prodRow['temp_id'], 'NKS-') !== 0) {
    $_SESSION['errorMessage'] = "Selected product is not a raw material product.";
    header("Location: input-stock-bundles.php");
    exit;
}

$refId = 'BUNDLEIN' . date('YmdHis') . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
$totalPieces = $nominalPieces * $bundleCount;

$stockService = new StockService($db_conn);

$db_conn->begin_transaction();
try {
    // Credits the raw product's pooled stock row by the full nominal
    // total — same mechanism Add Purchase from Manufacturer already
    // used for raw intake, kept intact so every existing stock view
    // (Convert Pieces<->Packs' "raw pc available", overall stock
    // reports, etc.) stays correct. Bundle tracking below is an
    // additional layer on top, not a replacement for this.
    $stockService->credit(
        $rawProductId, $Login_user_TYPEvl, (string) $companyGodownId, $totalPieces,
        'adjustment', $refId, $createdBy, true, $warehouseId
    );

    create_raw_material_bundles(
        $db_conn, $rawProductId, $companyGodownId, $warehouseId,
        $nominalPieces, $bundleCount, $refId, $createdBy
    );

    $db_conn->commit();
    $_SESSION['sucMessage'] = "$bundleCount bundle(s) recorded, $totalPieces total pieces credited.";
    header("Location: input-stock-bundles.php");
    exit;
} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
    header("Location: input-stock-bundles.php");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[input-stock-bundles] ' . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    header("Location: input-stock-bundles.php");
    exit;
}
