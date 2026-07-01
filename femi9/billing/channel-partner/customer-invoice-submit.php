<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (!isset($_REQUEST['invoice-submit'])) { header("Location: customer-manage-invoice.php"); exit; }

$invoice_id     = $_REQUEST['invoice_id'] ?? '';
$SubTotal       = (float)($_REQUEST['sub_total']       ?? 0);
$discount       = (float)($_REQUEST['discount']        ?? 0);
$roundoff       = (float)($_REQUEST['roundoff']        ?? 0);
$courier        = (float)($_REQUEST['courier_charges'] ?? 0);
$total_amount   = round($SubTotal - $discount + $courier);
$tp_id          = (int)$Login_user_IDvl;
$is_edit        = isset($_SESSION['ACTIONEDIT']) && $_SESSION['ACTIONEDIT'] === 'edit';
$created_by     = $_SESSION['LOGIN_USER'] ?? 'system';

// Update invoice date if editing
if (!empty($_REQUEST['update_invoice_date'])) {
    $upd_date = date("Y-m-d", strtotime($_REQUEST['update_invoice_date']));
    mysqli_query($db_conn, "UPDATE invoice SET date='$upd_date' WHERE inv_id='$invoice_id' AND user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl'");
    mysqli_query($db_conn, "UPDATE invoice_items SET date='$upd_date' WHERE inv_id='$invoice_id' AND user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl'");
}

$inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM invoice WHERE inv_id='$invoice_id' LIMIT 1"));

// Reverse previous stock deductions on edit
if ($is_edit) {
    $stmtLed = $db_conn->prepare("SELECT product_id, qty FROM territory_partner_stock_ledger WHERE territory_partner_id=? AND ref_type='tp_invoice' AND ref_id=?");
    $stmtLed->bind_param('is', $tp_id, $invoice_id);
    $stmtLed->execute();
    $prevEntries = $stmtLed->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtLed->close();
    foreach ($prevEntries as $le) {
        $stmt = $db_conn->prepare("UPDATE territory_partner_stock SET closing_qty=closing_qty+? WHERE territory_partner_id=? AND product_id=?");
        $stmt->bind_param('iii', $le['qty'], $tp_id, $le['product_id']);
        $stmt->execute(); $stmt->close();
    }
    $stmtDel = $db_conn->prepare("DELETE FROM territory_partner_stock_ledger WHERE territory_partner_id=? AND ref_type='tp_invoice' AND ref_id=?");
    $stmtDel->bind_param('is', $tp_id, $invoice_id);
    $stmtDel->execute(); $stmtDel->close();
}

// Deduct stock for each item
$stmtItems = $db_conn->prepare("SELECT pr_id, qty FROM invoice_items WHERE inv_id=? AND user_type=? AND user_id=?");
$stmtItems->bind_param('ssi', $invoice_id, $Login_user_TYPEvl, $tp_id);
$stmtItems->execute();
$items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtItems->close();

foreach ($items as $item) {
    $prid = (int)$item['pr_id'];
    $qty  = (int)$item['qty'];

    $stmtBefore = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
    $stmtBefore->bind_param('ii', $tp_id, $prid);
    $stmtBefore->execute();
    $before = (int)($stmtBefore->get_result()->fetch_assoc()['closing_qty'] ?? 0);
    $stmtBefore->close();

    $after = max(0, $before - $qty);
    $stmt = $db_conn->prepare("UPDATE territory_partner_stock SET closing_qty=? WHERE territory_partner_id=? AND product_id=?");
    $stmt->bind_param('iii', $after, $tp_id, $prid);
    $stmt->execute(); $stmt->close();

    $stmtL = $db_conn->prepare("INSERT INTO territory_partner_stock_ledger
        (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
        VALUES (?, ?, 'deduct', ?, ?, ?, 'tp_invoice', ?, 'customer invoice', ?)");
    $stmtL->bind_param('iiiiiss', $tp_id, $prid, $qty, $before, $after, $invoice_id, $created_by);
    $stmtL->execute(); $stmtL->close();
}

// Update invoice totals
mysqli_query($db_conn, "UPDATE invoice SET sub_total='$SubTotal',discount='$discount',total='$total_amount',roundoff='$roundoff',courier_charges='$courier'
    WHERE inv_id='$invoice_id' AND user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl'");

// Insert/update receipt
$receivedamount  = (float)($_REQUEST['receivedamount']  ?? 0);
$receipt_method  = $_REQUEST['receipt_method']           ?? '';
$receipt_remarks = str_replace("'", "&#39;", $_REQUEST['receipt_remarks'] ?? '');
$receiptdate     = $inv['date']           ?? date("Y-m-d");
$to_user_type    = 'customer';
$to_user_id      = $inv['customer_id']    ?? '';
$from_utype      = $inv['user_type']      ?? $Login_user_TYPEvl;
$from_uid        = $inv['user_id']        ?? $Login_user_IDvl;

$existingReceipt = mysqli_fetch_array(mysqli_query($db_conn, "SELECT id,received FROM receipt WHERE inv_id='$invoice_id' LIMIT 1"));
if ($existingReceipt) {
    $new_received   = (float)$existingReceipt['received'] + $receivedamount;
    $new_receivable = round($total_amount - $new_received);
    mysqli_query($db_conn, "UPDATE receipt SET received='$new_received',receivable='$new_receivable',invoice_amount='$total_amount',receipt_method='$receipt_method',receipt_remarks='$receipt_remarks' WHERE inv_id='$invoice_id'");
} else {
    $receivable = round($total_amount - $receivedamount);
    mysqli_query($db_conn, "INSERT INTO receipt
        (receiptid,inv_id,invoice_amount,received,receivable,date,from_user_type,from_user_id,to_user_type,to_user_id,receipt_method,receipt_remarks)
        VALUES ('$invoice_id','$invoice_id','$total_amount','$receivedamount','$receivable','$receiptdate',
        '$from_utype','$from_uid','$to_user_type','$to_user_id','$receipt_method','$receipt_remarks')");
}

unset($_SESSION['ACTIONEDIT']);
echo "<script>window.location='customer-invoice-print.php?invoiceid=" . base64_encode($invoice_id) . "';</script>";
