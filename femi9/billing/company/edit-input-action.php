<?php
/**
 * Edit Input Stock - Action Handler
 *
 * Reverses the old credit (same product/godown/warehouse/qty the original
 * row applied) and re-applies a fresh credit with the edited values, inside
 * one transaction — mirrors delete-input.php's reversal plus input-action.php's
 * credit application, so stock.closing_qty and stock_ledger stay consistent.
 */

ob_start();
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('add_input_stock');
include("config.php");
require_once("include/StockService.php");
require_once("include/GodownAccess.php");

function redirectTo(string $url): never
{
    ob_end_clean();
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('manage-input');
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    redirectTo('manage-input?saveerror');
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$id          = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
$godownId    = filter_var($_POST['godownid'] ?? 0, FILTER_VALIDATE_INT);
$warehouseId = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$productId   = filter_var($_POST['product_id'] ?? 0, FILTER_VALIDATE_INT);
$inputQty    = filter_var($_POST['input_qty'] ?? 0, FILTER_VALIDATE_INT);
$inputDate   = $_POST['input_date'] ?? '';
$remarks     = htmlspecialchars(strip_tags(trim($_POST['input_remarks'] ?? '')), ENT_QUOTES, 'UTF-8');

if (!$id || $id <= 0 || !$godownId || !$productId || !$inputQty || $inputQty <= 0 || !$warehouseId || $remarks === '') {
    redirectTo("edit-input?Roowid=" . base64_encode((string)$id) . "&invalid");
}
if (!is_godown_allowed($db_conn, $godownId)) {
    redirectTo('manage-input');
}

$dateObj = DateTime::createFromFormat('Y-m-d', $inputDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $inputDate) {
    redirectTo("edit-input?Roowid=" . base64_encode((string)$id) . "&invalid");
}
$inputDate = $dateObj->format('Y-m-d');

$stmt = $db_conn->prepare("SELECT * FROM input_stock WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$original = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$original) {
    redirectTo('manage-input');
}

// Rows at or below this id were relabeled to warehouse_id=2 (G1) by the
// 2026-09-23 backfill, but their actual stock credit is still sitting in the
// unassigned bucket — see delete-input.php for the full explanation. Treat
// these as unassigned on reversal regardless of their stored warehouse_id.
const INPUT_STOCK_BACKFILL_CUTOFF_ID = 926;

$oldProductId   = (int) $original['product_id'];
$oldQty         = (int) $original['input_qty'];
$oldGodownId    = (string) $original['godownid'];
$oldWarehouseId = ($id <= INPUT_STOCK_BACKFILL_CUTOFF_ID)
    ? null
    : ($original['warehouse_id'] !== null ? (int) $original['warehouse_id'] : null);
$tempId         = (string) $original['tempid'];

$db_conn->begin_transaction();
try {
    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    // Reverse what the original entry credited.
    $reverseResult = $stockService->reverseCredit(
        $oldProductId, 'company', $oldGodownId, $oldQty,
        'adjustment', $tempId, $createdBy,
        true, // externalTransaction
        $oldWarehouseId
    );
    if (empty($reverseResult['success'])) {
        error_log("edit-input-action.php: reverseCredit no-op for product=$oldProductId godown=$oldGodownId warehouse=" . ($oldWarehouseId ?? 'NULL') . " — " . ($reverseResult['reason'] ?? 'unknown'));
    }

    // Apply the edited values as a fresh credit.
    $stockService->credit(
        $productId, 'company', (string)$godownId, $inputQty,
        'adjustment', $tempId, $createdBy,
        true, // externalTransaction
        $warehouseId
    );

    // Update the input_stock record itself.
    $stmtUpd = $db_conn->prepare(
        "UPDATE input_stock
         SET product_id = ?, input_qty = ?, input_date = ?, godownid = ?, warehouse_id = ?, input_remarks = ?
         WHERE id = ?"
    );
    $stmtUpd->bind_param('iisiisi', $productId, $inputQty, $inputDate, $godownId, $warehouseId, $remarks, $id);
    $stmtUpd->execute();
    $stmtUpd->close();

    $db_conn->commit();

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('edit-input-action.php transaction failed: ' . $e->getMessage());
    redirectTo("edit-input?Roowid=" . base64_encode((string)$id) . "&saveerror");
}

redirectTo('manage-input?updatedSuccess');
