<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (!isset($_REQUEST['addInvoice'])) { header("Location: customer-invoice-add.php"); exit; }

$inv_id      = $_REQUEST['inv_id']      ?? '';
$invuser     = 'customer';
$customer_id = $_REQUEST['customer_id'] ?? '';
$date        = date("Y-m-d", strtotime($_REQUEST['date'] ?? date("Y-m-d")));
$inv_year    = date("Y", strtotime($date));
$tp_id       = (int)$Login_user_IDvl;

if (($_REQUEST['invoice_number_accept'] ?? '1') == '0') {
    $_SESSION['errorMessage'] = "Invoice Number already exists!";
    echo "<script>window.location='customer-invoice-add.php?invoicealready';</script>";
    exit;
}

$inv_number = str_replace("'", '', $_REQUEST['inv_number'] ?? '');

$pr_id  = (int)($_REQUEST['pr_id']  ?? 0);
$amount = (float)($_REQUEST['amount'] ?? 0);
$qty    = (int)($_REQUEST['qty']    ?? 0);

$prod            = mysqli_fetch_array(mysqli_query($db_conn, "SELECT gst,hsn,rwpoints FROM products WHERE id='$pr_id'"));
$gst_percentage  = $prod['gst']      ?? 0;
$hsn             = $prod['hsn']      ?? '';
$rwpoints        = (($prod['rwpoints'] ?? 0) * $qty);

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
$gstamount_singlepr = '0';

// Buyer GSTIN type
$custRow      = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM customers WHERE id='$customer_id' LIMIT 1"));
$buyer_GSTIN  = $custRow['gstin'] ?? '';
$buyer_gsttype = strlen($buyer_GSTIN) === 15 ? 'register' : 'unregister';
$gst_type     = 'inner';

// Create invoice if not exists
$chk = mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COUNT(*) AS n FROM invoice WHERE inv_id='$inv_id' AND user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl' AND customer_id='$customer_id'"));
if ((int)$chk['n'] === 0) {
    mysqli_query($db_conn, "INSERT INTO invoice
        (inv_id,id_only,inv_number,customer_id,date,inv_year,sub_total,discount,total,
         user_type,user_id,gst_type,roundoff,courier_charges,buyer_gsttype)
        VALUES ('$inv_id','0','$inv_number','$customer_id','$date','$inv_year','0','0','0',
        '$Login_user_TYPEvl','$Login_user_IDvl','$gst_type','0','0','$buyer_gsttype')");
}

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
