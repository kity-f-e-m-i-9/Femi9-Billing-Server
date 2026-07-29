<?php
include("checksession.php");
include("config.php");
require_once("include/ShopInvoiceHistory.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Direct qty/price/discount edit on an existing invoice line — only offered
// for invoices that came from a DM-assigned order (see shop-invoice-add.php's
// $isFromDmOrder). The TP's own manually-built invoices keep the normal
// Remove-then-re-Add flow; this exists so a DM's suggested order can be
// adjusted in place instead.

$invid_enc = $_POST['invid']   ?? '';
$invuser   = $_POST['invuser'] ?? 'shop';
$rowid     = (int)base64_decode($_POST['rowid'] ?? '');
$inv_id    = base64_decode($invid_enc);
$tp_id     = (int)$Login_user_IDvl;
$tp_id_str = (string)$tp_id;
$actionEdit = $_SESSION['ACTIONEDIT'] ?? 'edit';

if ($rowid <= 0 || $inv_id === '') {
    header("Location: shop-invoice-add.php?InvoiceID=$invid_enc&invuser=$invuser&action=$actionEdit");
    exit;
}

$stmtInv = $db_conn->prepare("SELECT status FROM user_invoice WHERE inv_id=? AND from_user_type=? AND from_user_id=? LIMIT 1");
$stmtInv->bind_param('sss', $inv_id, $Login_user_TYPEvl, $tp_id_str);
$stmtInv->execute();
$invRow = $stmtInv->get_result()->fetch_assoc();
$stmtInv->close();
if (!$invRow) {
    header('Location: shop-manage-invoice.php');
    exit;
}
if ($invRow['status'] === 'cancelled') {
    $_SESSION['errorMessage'] = "This invoice has been voided and can no longer be edited.";
    header('Location: shop-manage-invoice.php');
    exit;
}

$stmtDm = $db_conn->prepare("SELECT 1 FROM tp_orders WHERE invoiced_inv_id=? AND assigned_by_ms_id IS NOT NULL LIMIT 1");
$stmtDm->bind_param('s', $inv_id);
$stmtDm->execute();
$isFromDmOrder = (bool)$stmtDm->get_result()->fetch_assoc();
$stmtDm->close();
if (!$isFromDmOrder) {
    header("Location: shop-invoice-add.php?InvoiceID=$invid_enc&invuser=$invuser&action=$actionEdit");
    exit;
}

$stmtRow = $db_conn->prepare("SELECT pr_id, qty, amount, hsn FROM user_invoice_items WHERE id=? AND inv_id=?");
$stmtRow->bind_param('is', $rowid, $inv_id);
$stmtRow->execute();
$oldRow = $stmtRow->get_result()->fetch_assoc();
$stmtRow->close();
if (!$oldRow) {
    header("Location: shop-invoice-add.php?InvoiceID=$invid_enc&invuser=$invuser&action=$actionEdit");
    exit;
}

$pr_id  = (int)$oldRow['pr_id'];
$oldQty = (int)$oldRow['qty'];
$qty    = (int)($_POST['qty'] ?? 0);
// Price (MRP) is not editable from this screen — always the line's existing amount.
$amount = (float)$oldRow['amount'];
$hsn    = $oldRow['hsn'] ?? '';

// GST% is a TP-entered override for this line (posted value), not re-derived
// from the product master — the DM-order default is often 0% and the TP
// needs to be able to set the real rate here.
$gst_percentage = (float)($_POST['gst_percentage'] ?? 0);

$totalamount = $amount * $qty;

// Discount is entered in rupees only here (no separate % field to fight
// with) — the percentage is always derived from it, never the other way
// round, so there's a single source of truth.
$discount_amount     = (float)($_POST['discount_amount'] ?? 0);
$discount_percentage = $totalamount > 0 ? round($discount_amount * 100 / $totalamount, 2) : 0;

$subtotal        = (float)number_format($totalamount - $discount_amount, 2, '.', '');
$gstamount_total = $subtotal * $gst_percentage / 100;
$total           = $subtotal + $gstamount_total;

$stmtUpd = $db_conn->prepare(
    "UPDATE user_invoice_items
     SET qty=?, amount=?, total=?, subtotal=?, discount_percentage=?, discount_amount=?, gst_percentage=?, gstamount_total=?, hsn=?
     WHERE id=?"
);
$stmtUpd->bind_param('idddddddsi', $qty, $amount, $total, $subtotal, $discount_percentage, $discount_amount, $gst_percentage, $gstamount_total, $hsn, $rowid);
$stmtUpd->execute();
$stmtUpd->close();

logShopInvoiceChange(
    $db_conn, $inv_id, $pr_id, 'qty_changed', $oldQty, $qty, $Login_user_TYPEvl, $tp_id_str,
    $oldQty !== $qty ? 'Qty edited on invoice' : 'Price/discount edited on invoice'
);

header("Location: shop-invoice-add.php?InvoiceID={$invid_enc}&&UpdatedSuccess&&invuser={$invuser}&&action={$actionEdit}");
exit;
