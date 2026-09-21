<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
require_once("include/GodownAccess.php");

error_reporting(0);

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

$noteId = (int) base64_decode($_REQUEST['returnid'] ?? '');

if ($noteId <= 0) {
    $_SESSION['errorMessage'] = "Invalid record.";
    echo "<script>window.location='internal_transfer_return_manage';</script>";
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT transfer_id, tempid, product_id, qty, send_from, send_to
     FROM internal_transfer_return WHERE id = ?"
);
$stmt->bind_param('i', $noteId);
$stmt->execute();
$note = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$note) {
    $_SESSION['errorMessage'] = "Invalid record.";
    echo "<script>window.location='internal_transfer_return_manage';</script>";
    exit;
}

$transferId = (int)    $note['transfer_id'];
$tempid     = (string) $note['tempid'];
$product_id = (int)    $note['product_id'];
$qty        = (int)    $note['qty'];
$send_from  = (string) $note['send_from'];
$send_to    = (string) $note['send_to'];

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {
    // Undo the return: re-apply the original transfer for this qty
    // (debit send_from, credit send_to) — the inverse of what
    // internal_transfer_return_action.php did with reverseTransferOut/In.
    // transferOut() itself refuses (throws StockException) if send_from no
    // longer has enough stock to give back, e.g. it was moved on again
    // since the return — same guard style as reverseTransferIn's
    // insufficient-stock check.
    $stockService->transferOut(
        $product_id, $Login_user_TYPEvl, $send_from, $qty,
        'transfer', $tempid, $createdBy,
        true
    );

    $stockService->transferIn(
        $product_id, $Login_user_TYPEvl, $send_to, $qty,
        'transfer', $tempid, $createdBy,
        true
    );

    $stmtUpd = $db_conn->prepare(
        "UPDATE internal_transfer SET returned_qty = returned_qty - ? WHERE id = ?"
    );
    $stmtUpd->bind_param('ii', $qty, $transferId);
    $stmtUpd->execute();
    $stmtUpd->close();

    $stmtDel = $db_conn->prepare("DELETE FROM internal_transfer_return WHERE id = ?");
    $stmtDel->bind_param('i', $noteId);
    $stmtDel->execute();
    $stmtDel->close();

    $db_conn->commit();

    $_SESSION['sucMessage'] = "Credit note deleted and stock reversed successfully!";
    echo "<script>window.location='internal_transfer_return_manage';</script>";

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("internal_transfer_return_delete error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Cannot delete this credit note — the returned stock has already "
        . "been sold or moved on since the return. Please reconcile manually.";
    echo "<script>window.location='internal_transfer_return_manage';</script>";
    exit;
}
