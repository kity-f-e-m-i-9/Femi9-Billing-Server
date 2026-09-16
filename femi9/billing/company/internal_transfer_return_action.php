<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);

$rowid     = (int) base64_decode($_REQUEST['Roowid'] ?? '');
$tempid    = $_REQUEST['tempid'] ?? '';
$returnQty = (int) ($_REQUEST['return_qty'] ?? 0);

if ($rowid <= 0 || $returnQty <= 0) {
    $_SESSION['errorMessage'] = "Invalid return quantity.";
    echo "<script>window.location='internal_transfer_details?tempid=$tempid';</script>";
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT product_id, qty, returned_qty, send_from, send_to FROM internal_transfer WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['errorMessage'] = "Invalid record.";
    echo "<script>window.location='internal_transfer_details?tempid=$tempid';</script>";
    exit;
}

$product_id  = (int)    $row['product_id'];
$qty         = (int)    $row['qty'];
$returnedQty = (int)    $row['returned_qty'];
$send_from   = (string) $row['send_from'];
$send_to     = (string) $row['send_to'];

$returnable = $qty - $returnedQty;
if ($returnQty > $returnable) {
    $_SESSION['errorMessage'] = "Cannot return {$returnQty} units — only {$returnable} of the "
        . "original {$qty} units are still eligible to return.";
    echo "<script>window.location='internal_transfer_details?tempid=$tempid';</script>";
    exit;
}

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {
    // Restore source godown stock (sent_qty ↓, closing_qty ↑)
    $stockService->reverseTransferOut(
        $product_id, $Login_user_TYPEvl, $send_from, $returnQty,
        'transfer', $tempid, $createdBy,
        true
    );

    // Remove destination godown stock (input_qty ↓, closing_qty ↓)
    $reverseInResult = $stockService->reverseTransferIn(
        $product_id, $Login_user_TYPEvl, $send_to, $returnQty,
        'transfer', $tempid, $createdBy,
        true
    );

    if (($reverseInResult['success'] ?? false) === false
        && ($reverseInResult['reason'] ?? '') === 'insufficient_stock_to_reverse') {
        $db_conn->rollback();
        $available = $reverseInResult['available'];
        $_SESSION['errorMessage'] = "Cannot return this quantity — only {$available} units are "
            . "still in stock at the destination (the rest has already been sold or moved on). "
            . "Please reconcile manually.";
        echo "<script>window.location='internal_transfer_details?tempid=$tempid';</script>";
        exit;
    }

    $stmtUpd = $db_conn->prepare("UPDATE internal_transfer SET returned_qty = returned_qty + ? WHERE id = ?");
    $newReturnedQty = $returnQty;
    $stmtUpd->bind_param('ii', $newReturnedQty, $rowid);
    $stmtUpd->execute();
    $stmtUpd->close();

    $db_conn->commit();

    $_SESSION['sucMessage'] = "{$returnQty} unit(s) returned successfully!";
    echo "<script>window.location='internal_transfer_details?tempid=$tempid';</script>";

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("internal_transfer_return_action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Return failed. Please try again.";
    echo "<script>window.location='internal_transfer_details?tempid=$tempid';</script>";
    exit;
}
