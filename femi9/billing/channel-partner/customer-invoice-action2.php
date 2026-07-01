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
$invRow     = mysqli_fetch_array(mysqli_query($db_conn, "SELECT gst_type,buyer_gsttype FROM invoice WHERE inv_id='$inv_id' LIMIT 1"));
$gst_type   = $invRow['gst_type']     ?? 'inner';
$buyer_gsttype = $invRow['buyer_gsttype'] ?? 'unregister';

// Update invoice date/customer
mysqli_query($db_conn, "UPDATE invoice SET customer_id='$customer_id',date='$date',inv_year='$inv_year'
    WHERE inv_id='$inv_id' AND user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl'");

$pr_id  = (int)($_REQUEST['pr_id']  ?? 0);
$amount = (float)($_REQUEST['amount'] ?? 0);
$qty    = (int)($_REQUEST['qty']    ?? 0);

$prod            = mysqli_fetch_array(mysqli_query($db_conn, "SELECT gst,hsn,rwpoints FROM products WHERE id='$pr_id'"));
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
$stmtStk = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
$stmtStk->bind_param('ii', $tp_id, $pr_id);
$stmtStk->execute();
$stockRow  = $stmtStk->get_result()->fetch_assoc();
$stmtStk->close();
$available = $stockRow ? (int)$stockRow['closing_qty'] : 0;

if ($available < $qty) {
    echo "<script>window.location='customer-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&InvalidStock&&AlertStockError';</script>";
    exit;
}

// Check duplicate item
$dupChk = mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COUNT(*) AS n FROM invoice_items WHERE inv_id='$inv_id' AND pr_id='$pr_id' AND user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl' AND customer_id='$customer_id'"));
if ((int)$dupChk['n'] > 0) {
    echo "<script>window.location='customer-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&ItemAlreadyExists&&AlertMessage';</script>";
    exit;
}

// Insert item
mysqli_query($db_conn, "INSERT INTO invoice_items
    (inv_id,pr_id,amount,qty,total,user_type,user_id,customer_id,
     gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
     discount_percentage,discount_amount,gst_type,hsn,date,buyer_gsttype,rwpoints,rwpoints_sls)
    VALUES ('$inv_id','$pr_id','$amount','$qty','$total',
    '$Login_user_TYPEvl','$Login_user_IDvl','$customer_id',
    '$gst_percentage','$gstamount_singlepr','$gstamount_total','$subtotal',
    '$discount_percentage','$discount_amount','$gst_type','$hsn','$date','$buyer_gsttype','$rwpoints','$rwpoints')");

echo "<script>window.location='customer-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&AddedSuccess&&FemiAdded';</script>";
