<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (!isset($_REQUEST['addInvoice2'])) { header("Location: shop-invoice-add.php"); exit; }

$inv_id      = $_POST['inv_id']      ?? '';
$invuser     = $_POST['invuser']     ?? 'shop';
$customer_id = $_POST['customer_id'] ?? '';
$date        = date("Y-m-d", strtotime($_POST['date'] ?? date("Y-m-d")));
$inv_year    = date("Y", strtotime($date));
$pr_id       = (int)($_POST['pr_id']  ?? 0);
$amount      = (float)($_POST['amount'] ?? 0);
$qty         = (int)($_POST['qty']    ?? 0);
$tp_id       = (int)$Login_user_IDvl;


// Update invoice date/customer
$s = $db_conn->prepare("UPDATE user_invoice SET to_user_id=?,date=?,inv_year=? WHERE inv_id=? AND from_user_type=? AND from_user_id=?");
$s->bind_param('sssss' . 'i', $customer_id, $date, $inv_year, $inv_id, $Login_user_TYPEvl, $tp_id);
$s->execute(); $s->close();

$s = $db_conn->prepare("SELECT gst_type,buyer_gsttype FROM user_invoice WHERE inv_id=? LIMIT 1");
$s->bind_param('s', $inv_id);
$s->execute();
$invRow = $s->get_result()->fetch_assoc();
$s->close();
$gst_type      = $invRow['gst_type']      ?? 'inner';
$buyer_gsttype = $invRow['buyer_gsttype'] ?? 'unregister';

$s = $db_conn->prepare("SELECT gst,hsn,rwpoints FROM products WHERE id=?");
$s->bind_param('i', $pr_id);
$s->execute();
$prod = $s->get_result()->fetch_assoc();
$s->close();
$gst_percentage     = $prod['gst']      ?? 0;
$hsn                = $prod['hsn']      ?? '';
$rwpoints           = ($prod['rwpoints'] ?? 0) * $qty;
$gstamount_singlepr = '0';
$totalamount        = $amount * $qty;

if (($_POST['discount_percentage'] ?? 0) > 0) {
    $discount_percentage = (float)$_POST['discount_percentage'];
    $discount_amount     = number_format($totalamount * $discount_percentage / 100, 2, '.', '');
} else {
    $discount_amount     = (float)($_POST['discount_amount'] ?? 0);
    $discount_percentage = $totalamount > 0 ? inr_format($discount_amount * 100 / $totalamount, 2) : 0;
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
    echo "<script>window.location='shop-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&InvalidStock&&invuser=$invuser&&action=".($_SESSION['ACTIONEDIT']??'')."';</script>";
    exit;
}

$s = $db_conn->prepare("SELECT COUNT(*) AS n FROM user_invoice_items WHERE inv_id=? AND pr_id=? AND from_user_type=? AND from_user_id=?");
$s->bind_param('sisi', $inv_id, $pr_id, $Login_user_TYPEvl, $tp_id);
$s->execute();
$dupChk = $s->get_result()->fetch_assoc();
$s->close();
if ((int)$dupChk['n'] > 0) {
    echo "<script>window.location='shop-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&ItemAlreadyExists&&invuser=$invuser&&action=".($_SESSION['ACTIONEDIT']??'')."';</script>";
    exit;
}

$rwpoints_sls = $rwpoints;
$rwpoints_i = (int)$rwpoints;
$rwpoints_sls_i = (int)$rwpoints_sls;
$s = $db_conn->prepare(
    "INSERT INTO user_invoice_items
     (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,
      gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
      discount_percentage,discount_amount,gst_type,hsn,date,rwpoints,buyer_gsttype,rwpoints_sls)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$s->bind_param('sididsssi' . 'dsddddsssisi',
    $inv_id, $pr_id, $amount, $qty, $total,
    $invuser, $customer_id, $Login_user_TYPEvl, $tp_id,
    $gst_percentage, $gstamount_singlepr, $gstamount_total, $subtotal,
    $discount_percentage, $discount_amount, $gst_type, $hsn, $date,
    $rwpoints_i, $buyer_gsttype, $rwpoints_sls_i
);
$s->execute(); $s->close();

echo "<script>window.location='shop-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&AddedSuccess&&invuser=$invuser&&action=".($_SESSION['ACTIONEDIT']??'')."&&FemiAdded';</script>";
