<?php require_once __DIR__ . '/../shared/db-helpers.php';
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
mysqli_query($db_conn, "UPDATE user_invoice SET to_user_id='".db_esc($customer_id)."',date='".db_esc($date)."',inv_year='".db_esc($inv_year)."' WHERE inv_id='".db_esc($inv_id)."' AND from_user_type='".db_esc($Login_user_TYPEvl)."' AND from_user_id='".db_esc($Login_user_IDvl)."'");

$invRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT gst_type,buyer_gsttype FROM user_invoice WHERE inv_id='".db_esc($inv_id)."' LIMIT 1"));
$gst_type      = $invRow['gst_type']      ?? 'inner';
$buyer_gsttype = $invRow['buyer_gsttype'] ?? 'unregister';

$prod = mysqli_fetch_array(mysqli_query($db_conn, "SELECT gst,gst_type,hsn,rwpoints FROM products WHERE id='".db_esc($pr_id)."'"));
$gst_percentage     = $prod['gst'];
$gst_type_item      = $prod['gst_type'] ?: 'exclusive';
$hsn                = $prod['hsn'];
$rwpoints           = $prod['rwpoints'] * $qty;
$gstamount_singlepr = "0";
$totalamount        = $amount * $qty;

if (($_POST['discount_percentage'] ?? 0) > 0) {
    $discount_percentage = (float)$_POST['discount_percentage'];
    $discount_amount     = number_format($totalamount * $discount_percentage / 100, 2, '.', '');
} else {
    $discount_amount     = (float)($_POST['discount_amount'] ?? 0);
    $discount_percentage = $totalamount > 0 ? inr_format($discount_amount * 100 / $totalamount, 2) : 0;
}

$subtotal = number_format($totalamount - $discount_amount, 2, '.', '');

// Same convention as territory-partner/shop-invoice-action2.php: inclusive-tax
// products already have GST baked into the entered price, so tax is carved
// out of subtotal (not added again into total); exclusive-tax products get
// GST added on top.
if ($gst_type_item === 'inclusive' && $gst_percentage > 0) {
    $taxable_value   = $subtotal * 100 / (100 + $gst_percentage);
    $gstamount_total = $subtotal - $taxable_value;
    $total           = $subtotal;
} else {
    $gstamount_total = $subtotal * $gst_percentage / 100;
    $total           = $subtotal + $gstamount_total;
}

// Check TP stock
$stmtStk = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
$stmtStk->bind_param('ii', $tp_id, $pr_id);
$stmtStk->execute();
$stockRow = $stmtStk->get_result()->fetch_assoc();
$stmtStk->close();
$available = $stockRow ? (int)$stockRow['closing_qty'] : 0;

if ($available < $qty) {
    echo "<script>window.location='shop-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&InvalidStock&&invuser=".urlencode($invuser)."&&action=".($_SESSION['ACTIONEDIT']??'')."';</script>";
    exit;
}

$dupChk = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) AS n FROM user_invoice_items WHERE inv_id='".db_esc($inv_id)."' AND pr_id='".db_esc($pr_id)."' AND from_user_type='".db_esc($Login_user_TYPEvl)."' AND from_user_id='".db_esc($Login_user_IDvl)."'"));
if ((int)$dupChk['n'] > 0) {
    echo "<script>window.location='shop-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&ItemAlreadyExists&&invuser=".urlencode($invuser)."&&action=".($_SESSION['ACTIONEDIT']??'')."';</script>";
    exit;
}

mysqli_query($db_conn, "INSERT INTO user_invoice_items
    (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,
     gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
     discount_percentage,discount_amount,gst_type,hsn,date,rwpoints,buyer_gsttype,rwpoints_sls)
    VALUES ('".db_esc($inv_id)."','".db_esc($pr_id)."','".db_esc($amount)."','".db_esc($qty)."','".db_esc($total)."',
    '".db_esc($invuser)."','".db_esc($customer_id)."','".db_esc($Login_user_TYPEvl)."','".db_esc($Login_user_IDvl)."',
    '".db_esc($gst_percentage)."','".db_esc($gstamount_singlepr)."','".db_esc($gstamount_total)."','".db_esc($subtotal)."',
    '".db_esc($discount_percentage)."','".db_esc($discount_amount)."','".db_esc($gst_type)."','".db_esc($hsn)."','".db_esc($date)."','0','".db_esc($buyer_gsttype)."','".db_esc($rwpoints)."')");

echo "<script>window.location='shop-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&AddedSuccess&&invuser=".urlencode($invuser)."&&action=".($_SESSION['ACTIONEDIT']??'')."';</script>";
