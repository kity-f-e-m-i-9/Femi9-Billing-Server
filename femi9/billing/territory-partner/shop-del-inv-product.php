<?php
include("checksession.php");
include("config.php");
require_once("include/ShopInvoiceHistory.php");
error_reporting(0);

$invoice_id_encode = $_REQUEST['invid']   ?? '';
$invuser           = $_REQUEST['invuser'] ?? 'shop';
$rowid             = (int)base64_decode($_REQUEST['rowid'] ?? '');
$actionEdit        = $_SESSION['ACTIONEDIT'] ?? '';

if ($rowid <= 0) {
    header("Location: shop-invoice-add.php?InvoiceID=$invoice_id_encode&invuser=$invuser&action=$actionEdit");
    exit;
}

$inv_id_decoded = base64_decode($invoice_id_encode);

// A voided invoice is read-only.
$stmtVoidChk = $db_conn->prepare("SELECT status FROM user_invoice WHERE inv_id=? LIMIT 1");
$stmtVoidChk->bind_param('s', $inv_id_decoded);
$stmtVoidChk->execute();
$voidChkRow = $stmtVoidChk->get_result()->fetch_assoc();
$stmtVoidChk->close();
if (($voidChkRow['status'] ?? '') === 'cancelled') {
    $_SESSION['errorMessage'] = "This invoice has been voided and can no longer be edited.";
    echo "<script>window.location='shop-manage-invoice.php';</script>";
    exit;
}

// Look up the line before deleting, so the removal can be logged.
$stmtRow = $db_conn->prepare("SELECT pr_id, qty FROM user_invoice_items WHERE id=?");
$stmtRow->bind_param('i', $rowid);
$stmtRow->execute();
$delRow = $stmtRow->get_result()->fetch_assoc();
$stmtRow->close();

// Delete the item only — stock changes happen exclusively at submit time
$stmt = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id=?");
$stmt->bind_param('i', $rowid);
$stmt->execute();
$stmt->close();

if ($delRow) {
    logShopInvoiceChange($db_conn, $inv_id_decoded, (int)$delRow['pr_id'], 'removed', (int)$delRow['qty'], null, $Login_user_TYPEvl, (string)$Login_user_IDvl);
}

echo "<script>window.location='shop-invoice-add.php?InvoiceID={$invoice_id_encode}&&DeleteSuccess&&invuser={$invuser}&&ActionRemove&&action={$actionEdit}';</script>";
