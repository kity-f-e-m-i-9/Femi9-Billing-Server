<?php
include("checksession.php"); require_once("include/PermissionCheck.php"); requireAnyPermission(['internal_transfer','stock_transfers']);
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/GodownStockMove.php");
require_once("include/GodownCoverCartonMove.php");
include("config.php");

// Finance-only, same as the rest of the Internal Stock Transfer area —
// but this is a SEPARATE concept from Internal Transfer/Auto Transfer:
// a direct godown-to-godown stock move with no invoice. Never writes to
// internal_transfer / internal_transfer_invoice.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
    header("Location: godown-stock-move.php");
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$fromCompanyGodownId = filter_var($_POST['from_company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$toCompanyGodownId   = filter_var($_POST['to_company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$fromWarehouseId      = filter_var($_POST['from_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$toWarehouseId        = filter_var($_POST['to_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$note                 = trim((string) ($_POST['note'] ?? ''));
$note                 = $note !== '' ? mb_substr($note, 0, 255) : null;
$createdBy            = $_SESSION['LOGIN_USER'] ?? 'system';

$rawProductIds = $_POST['product_id'] ?? [];
$rawQtys       = $_POST['qty'] ?? [];

// Optional covers / carton boxes moved in the same batch. Each list is
// parsed independently; blank rows (nothing selected) are ignored.
$parseExtra = function (string $idKey, string $qtyKey): array {
    $ids  = is_array($_POST[$idKey] ?? null) ? $_POST[$idKey] : [];
    $qtys = is_array($_POST[$qtyKey] ?? null) ? $_POST[$qtyKey] : [];
    $out = [];
    foreach ($ids as $i => $rawId) {
        $id  = filter_var($rawId, FILTER_VALIDATE_INT) ?: 0;
        $qty = filter_var($qtys[$i] ?? '', FILTER_VALIDATE_INT) ?: 0;
        if (!$id && $rawId === '' && ($qtys[$i] ?? '') === '') continue;
        if (!$id || $qty <= 0) {
            $_SESSION['errorMessage'] = "Please fill in every field for every cover / carton row.";
            header("Location: godown-stock-move.php");
            exit;
        }
        $out[] = ['id' => $id, 'qty' => $qty];
    }
    $ids = array_column($out, 'id');
    if (count($ids) !== count(array_unique($ids))) {
        $_SESSION['errorMessage'] = "Each cover / carton type can only appear once per move batch.";
        header("Location: godown-stock-move.php");
        exit;
    }
    return $out;
};
$coverRows  = $parseExtra('cover_product_id', 'cover_qty');
$cartonRows = $parseExtra('carton_type_id', 'carton_qty');

if (!is_array($rawProductIds)) { $rawProductIds = []; }

if (!$fromCompanyGodownId || !$toCompanyGodownId || (empty($rawProductIds) && !$coverRows && !$cartonRows)) {
    $_SESSION['errorMessage'] = "Please fill in every required field.";
    header("Location: godown-stock-move.php");
    exit;
}

// A move only counts as a genuine no-op when company profile AND physical
// warehouse are identical on both sides — same profile with different
// warehouses (e.g. G1 -> G2 within the same LLP) is a legitimate move.
if ($fromCompanyGodownId === $toCompanyGodownId && $fromWarehouseId === $toWarehouseId) {
    $_SESSION['errorMessage'] = "From and To must be different (same company profile and same godown).";
    header("Location: godown-stock-move.php");
    exit;
}

if (!is_godown_allowed($db_conn, $fromCompanyGodownId) || !is_godown_allowed($db_conn, $toCompanyGodownId)) {
    $_SESSION['errorMessage'] = "You are not authorized to use one of these company profiles.";
    header("Location: godown-stock-move.php");
    exit;
}

// Validate and normalize every row up front, before touching StockService —
// any malformed row fails the whole batch immediately rather than partway
// through a transaction.
$rows = [];
foreach ($rawProductIds as $i => $rawPid) {
    $productId = filter_var($rawPid, FILTER_VALIDATE_INT) ?: 0;
    $qty       = filter_var($rawQtys[$i] ?? '', FILTER_VALIDATE_INT) ?: 0;

    if (!$productId || $qty <= 0) {
        $_SESSION['errorMessage'] = "Please fill in every field for every product.";
        header("Location: godown-stock-move.php");
        exit;
    }

    $rows[] = ['product_id' => $productId, 'qty' => $qty];
}

// A product appearing twice in one batch is never a legitimate
// request — reject up front rather than silently applying two moves
// to the same row in sequence.
$seenProductIds = array_column($rows, 'product_id');
if (count($seenProductIds) !== count(array_unique($seenProductIds))) {
    $_SESSION['errorMessage'] = "Each product can only appear once per move batch.";
    header("Location: godown-stock-move.php");
    exit;
}

$stockService = new StockService($db_conn);

// All-or-nothing: one shared transaction across every row in the batch —
// if any product's move fails (e.g. insufficient stock to draw from),
// none of the batch's moves are applied.
$db_conn->begin_transaction();
try {
    foreach ($rows as $row) {
        $refId = generate_godown_stock_move_ref_id();

        $outResult = $stockService->transferOut(
            $row['product_id'], $Login_user_TYPEvl, (string) $fromCompanyGodownId, $row['qty'],
            'transfer', $refId, $createdBy, true, $fromWarehouseId
        );
        $stockService->transferIn(
            $row['product_id'], $Login_user_TYPEvl, (string) $toCompanyGodownId, $row['qty'],
            'transfer', $refId, $createdBy, true,
            $outResult['consumed_rate'] ?? null, $toWarehouseId
        );

        record_godown_stock_move(
            $db_conn, $refId, $row['product_id'], $fromCompanyGodownId, $toCompanyGodownId,
            $fromWarehouseId, $toWarehouseId, $row['qty'], $note, $createdBy
        );
    }

    foreach ($coverRows as $c) {
        move_covers($db_conn, $c['id'], $fromCompanyGodownId, $fromWarehouseId,
            $toCompanyGodownId, $toWarehouseId, $c['qty'], $note, generate_godown_stock_move_ref_id(), $createdBy);
    }
    foreach ($cartonRows as $c) {
        move_cartons($db_conn, $c['id'], $fromCompanyGodownId, $fromWarehouseId,
            $toCompanyGodownId, $toWarehouseId, $c['qty'], $note, generate_godown_stock_move_ref_id(), $createdBy);
    }

    $db_conn->commit();
    $parts = [];
    if ($rows)       $parts[] = count($rows) . " product(s)";
    if ($coverRows)  $parts[] = count($coverRows) . " cover type(s)";
    if ($cartonRows) $parts[] = count($cartonRows) . " carton type(s)";
    $_SESSION['sucMessage'] = implode(", ", $parts) . " moved successfully.";
    header("Location: godown-stock-move.php");
    exit;
} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
    header("Location: godown-stock-move.php");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[godown-stock-move] ' . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    header("Location: godown-stock-move.php");
    exit;
}
