<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (!isset($_REQUEST['addInvoice2'])) { header("Location: customer-invoice-add.php"); exit; }

$inv_id      = $_REQUEST['inv_id']      ?? '';
$customer_id = $_REQUEST['customer_id'] ?? '';
$date        = date("Y-m-d", strtotime($_REQUEST['date'] ?? date("Y-m-d")));
$inv_year    = date("Y", strtotime($date));
$tp_id       = (int)$Login_user_IDvl;

// Fetch invoice gst_type and buyer_gsttype
$s = $db_conn->prepare("SELECT gst_type,buyer_gsttype FROM invoice WHERE inv_id=? LIMIT 1");
$s->bind_param('s', $inv_id);
$s->execute();
$invRow     = $s->get_result()->fetch_assoc();
$s->close();
$gst_type   = $invRow['gst_type']     ?? 'inner';
$buyer_gsttype = $invRow['buyer_gsttype'] ?? 'unregister';


// Update invoice date/customer
$s = $db_conn->prepare("UPDATE invoice SET customer_id=?,date=?,inv_year=? WHERE inv_id=? AND user_type=? AND user_id=?");
$s->bind_param('sssss' . 'i', $customer_id, $date, $inv_year, $inv_id, $Login_user_TYPEvl, $tp_id);
$s->execute(); $s->close();

$pr_id  = (int)($_REQUEST['pr_id']  ?? 0);
$amount = (float)($_REQUEST['amount'] ?? 0);
$qty    = (int)($_REQUEST['qty']    ?? 0);

$s = $db_conn->prepare("SELECT gst,hsn,rwpoints FROM products WHERE id=?");
$s->bind_param('i', $pr_id);
$s->execute();
$prod = $s->get_result()->fetch_assoc();
$s->close();
$gst_percentage  = $prod['gst']     ?? 0;
$hsn             = $prod['hsn']     ?? '';
$rwpoints        = (($prod['rwpoints'] ?? 0) * $qty);
$gstamount_singlepr = '0';

$totalamount = $amount * $qty;

if (($_REQUEST['discount_percentage'] ?? 0) > 0) {
    $discount_percentage = (float)$_REQUEST['discount_percentage'];
    $discount_amount     = number_format($totalamount * $discount_percentage / 100, 2, '.', '');
} else {
    $discount_amount     = (float)($_REQUEST['discount_amount'] ?? 0);
    $discount_percentage = $totalamount > 0 ? number_format($discount_amount * 100 / $totalamount, 2, '.', '') : 0;
}

$subtotal        = number_format($totalamount - $discount_amount, 2, '.', '');
$gstamount_total = $subtotal * $gst_percentage / 100;
$total           = $subtotal + $gstamount_total;

// Check TP stock
$s = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
$s->bind_param('ii', $tp_id, $pr_id);
$s->execute();
$stockRow  = $s->get_result()->fetch_assoc();
$s->close();
$available = $stockRow ? (int)$stockRow['closing_qty'] : 0;

if ($available < $qty) {
    echo "<script>window.location='customer-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&InvalidStock&&AlertStockError';</script>";
    exit;
}

// Check duplicate item
$s = $db_conn->prepare("SELECT COUNT(*) AS n FROM invoice_items WHERE inv_id=? AND pr_id=? AND user_type=? AND user_id=? AND customer_id=?");
$s->bind_param('siiss', $inv_id, $pr_id, $Login_user_TYPEvl, $tp_id, $customer_id);
$s->execute();
$dupChk = $s->get_result()->fetch_assoc();
$s->close();
if ((int)$dupChk['n'] > 0) {
    echo "<script>window.location='customer-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&ItemAlreadyExists&&AlertMessage';</script>";
    exit;
}

$rwpoints_sls = $rwpoints;
$rwpoints_i = (int)$rwpoints;
$rwpoints_sls_i = (int)$rwpoints_sls;
$s = $db_conn->prepare(
    "INSERT INTO invoice_items
     (inv_id,pr_id,amount,qty,total,user_type,user_id,customer_id,
      gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
      discount_percentage,discount_amount,gst_type,hsn,date,buyer_gsttype,rwpoints,rwpoints_sls)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$s->bind_param('sididsisdsddddssssii',
    $inv_id, $pr_id, $amount, $qty, $total,
    $Login_user_TYPEvl, $tp_id, $customer_id,
    $gst_percentage, $gstamount_singlepr, $gstamount_total, $subtotal,
    $discount_percentage, $discount_amount, $gst_type, $hsn, $date,
    $buyer_gsttype, $rwpoints_i, $rwpoints_sls_i
);
$s->execute(); $s->close();

echo "<script>window.location='customer-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&AddedSuccess&&FemiAdded';</script>";
