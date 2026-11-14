<?php
include("checksession.php"); require_once("include/PermissionCheck.php"); requireStaffOnly();
require_once("include/GodownAccess.php");
require_once("include/StockService.php"); // StockException, thrown by the covers/cartons source-balance check
require_once("include/GodownStockMove.php"); // generate_godown_stock_move_ref_id()
require_once("include/GodownCoverCartonMove.php");
include("config.php");

// Covers + Carton Box transfer for the Godown (stockviewer) login — same
// audience as the other Bundle-menu pages. Covers/cartons only; product
// stock moves stay on godown-stock-move-action.php (finance).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin', 'stockviewer'], true)) {
    header("Location: dashboard.php");
    exit;
}

$back = "cover-carton-transfer.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
    header("Location: $back");
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$fromCompanyGodownId = filter_var($_POST['from_company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$toCompanyGodownId   = filter_var($_POST['to_company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$fromWarehouseId     = filter_var($_POST['from_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$toWarehouseId       = filter_var($_POST['to_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$note                = trim((string) ($_POST['note'] ?? ''));
$note                = $note !== '' ? mb_substr($note, 0, 255) : null;
$createdBy           = $_SESSION['LOGIN_USER'] ?? 'system';

// Each list is parsed independently; fully blank rows are ignored.
$parseExtra = function (string $idKey, string $qtyKey) use ($back): array {
    $ids  = is_array($_POST[$idKey] ?? null) ? $_POST[$idKey] : [];
    $qtys = is_array($_POST[$qtyKey] ?? null) ? $_POST[$qtyKey] : [];
    $out = [];
    foreach ($ids as $i => $rawId) {
        $id  = filter_var($rawId, FILTER_VALIDATE_INT) ?: 0;
        $qty = filter_var($qtys[$i] ?? '', FILTER_VALIDATE_INT) ?: 0;
        if (!$id && $rawId === '' && ($qtys[$i] ?? '') === '') continue;
        if (!$id || $qty <= 0) {
            $_SESSION['errorMessage'] = "Please fill in every field for every cover / carton row.";
            header("Location: $back");
            exit;
        }
        $out[] = ['id' => $id, 'qty' => $qty];
    }
    $ids = array_column($out, 'id');
    if (count($ids) !== count(array_unique($ids))) {
        $_SESSION['errorMessage'] = "Each cover / carton type can only appear once per transfer.";
        header("Location: $back");
        exit;
    }
    return $out;
};
$coverRows  = $parseExtra('cover_product_id', 'cover_qty');
$cartonRows = $parseExtra('carton_type_id', 'carton_qty');

if (!$fromCompanyGodownId || !$toCompanyGodownId || (!$coverRows && !$cartonRows)) {
    $_SESSION['errorMessage'] = "Please fill in every required field and add at least one cover or carton box.";
    header("Location: $back");
    exit;
}

// A genuine no-op only when profile AND physical godown are identical.
if ($fromCompanyGodownId === $toCompanyGodownId && $fromWarehouseId === $toWarehouseId) {
    $_SESSION['errorMessage'] = "From and To must be different (same company profile and same godown).";
    header("Location: $back");
    exit;
}

if (!is_godown_allowed($db_conn, $fromCompanyGodownId) || !is_godown_allowed($db_conn, $toCompanyGodownId)) {
    $_SESSION['errorMessage'] = "You are not authorized to use one of these company profiles.";
    header("Location: $back");
    exit;
}

// All-or-nothing: any short source balance rolls the whole batch back.
$db_conn->begin_transaction();
try {
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
    if ($coverRows)  $parts[] = count($coverRows) . " cover type(s)";
    if ($cartonRows) $parts[] = count($cartonRows) . " carton type(s)";
    $_SESSION['sucMessage'] = implode(", ", $parts) . " transferred successfully.";
} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[cover-carton-transfer] ' . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
}
header("Location: $back");
exit;
