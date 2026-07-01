<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$invoice_id_encode = $_REQUEST['invid']   ?? '';
$invuser           = $_REQUEST['invuser'] ?? 'shop';
$rowid             = (int)base64_decode($_REQUEST['rowid'] ?? '');
$actionEdit        = $_SESSION['ACTIONEDIT'] ?? '';

if ($rowid <= 0) {
    header("Location: shop-invoice-add.php?InvoiceID=$invoice_id_encode&invuser=$invuser&action=$actionEdit");
    exit;
}

// Delete the item only — stock changes happen exclusively at submit time
$stmt = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id=?");
$stmt->bind_param('i', $rowid);
$stmt->execute();
$stmt->close();

echo "<script>window.location='shop-invoice-add.php?InvoiceID={$invoice_id_encode}&&DeleteSuccess&&invuser={$invuser}&&ActionRemove&&action={$actionEdit}';</script>";
