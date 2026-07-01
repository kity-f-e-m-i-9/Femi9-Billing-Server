<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (!isset($_REQUEST['addInvoice'])) { header("Location: shop-invoice-add.php"); exit; }

$inv_id      = $_REQUEST['inv_id']      ?? '';
$invuser     = $_REQUEST['invuser']     ?? 'shop';
$customer_id = $_REQUEST['customer_id'] ?? '';
$date        = date("Y-m-d", strtotime($_REQUEST['date'] ?? date("Y-m-d")));
$inv_year    = date("Y", strtotime($date));
$tp_id       = (int)$Login_user_IDvl;

// Check invoice number duplicate (set by AJAX in form)
if (($_REQUEST['invoice_number_accept'] ?? '1') == '0') {
    $_SESSION['errorMessage'] = "Invoice Number already exists!";
    echo "<script>window.location='shop-invoice-add.php?invoicealready&&invuser=$invuser';</script>";
    exit;
}

$inv_number = str_replace("'", '', $_REQUEST['inv_number'] ?? '');

$pr_id  = (int)($_REQUEST['pr_id']  ?? 0);
$amount = (float)($_REQUEST['amount'] ?? 0);
$qty    = (int)($_REQUEST['qty']    ?? 0);

// Product details
$prod = mysqli_fetch_array(mysqli_query($db_conn, "SELECT gst,hsn,rwpoints FROM products WHERE id='$pr_id'"));
$gst_percentage  = $prod['gst']      ?? 0;
$hsn             = $prod['hsn']      ?? '';
$rwpoints        = ($prod['rwpoints'] ?? 0) * $qty;

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

// Customer/GST type
$shopRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM shop WHERE temp_id='$customer_id' LIMIT 1"));
$buyer_GSTIN   = $shopRow['gstin'] ?? '';
$buyer_gsttype = strlen($buyer_GSTIN) === 15 ? 'register' : 'unregister';

$shopStateRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT st_name FROM state WHERE id='{$shopRow['state_id']}' LIMIT 1"));
$shop_state_name = $shopStateRow['st_name'] ?? '';
$tpRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT branch_state FROM territory_partners WHERE id='$tp_id' LIMIT 1"));
$tp_state = $tpRow['branch_state'] ?? '';
$gst_type = (strtolower($shop_state_name) === strtolower($tp_state)) ? 'inner' : 'outer';

// Create invoice if not exists
$chk = mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COUNT(*) AS n FROM user_invoice WHERE inv_id='$inv_id' AND from_user_type='$Login_user_TYPEvl' AND from_user_id='$Login_user_IDvl' AND to_user_type='$invuser' AND to_user_id='$customer_id'"));
if ((int)$chk['n'] === 0) {
    mysqli_query($db_conn, "INSERT INTO user_invoice
        (inv_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,
         from_user_type,from_user_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
        VALUES ('$inv_id','0','$inv_number','$date','$inv_year','0','0','0',
        '$invuser','$customer_id','$Login_user_TYPEvl','$Login_user_IDvl',
        '$gst_type','0','0','0','1','$buyer_gsttype','Nil','Nil')");
}

// Check TP stock
$stmtStk = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
$stmtStk->bind_param('ii', $tp_id, $pr_id);
$stmtStk->execute();
$stockRow = $stmtStk->get_result()->fetch_assoc();
$stmtStk->close();
$available = $stockRow ? (int)$stockRow['closing_qty'] : 0;

if ($available < $qty) {
    echo "<script>window.location='shop-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&InvalidStock&&invuser=$invuser&&action=" . ($_SESSION['ACTIONEDIT'] ?? '') . "&&AlertStockError';</script>";
    exit;
}

// Check duplicate item
$dupChk = mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COUNT(*) AS n FROM user_invoice_items WHERE inv_id='$inv_id' AND pr_id='$pr_id' AND from_user_type='$Login_user_TYPEvl' AND from_user_id='$Login_user_IDvl' AND to_user_type='$invuser' AND to_user_id='$customer_id'"));
if ((int)$dupChk['n'] > 0) {
    echo "<script>window.location='shop-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&ItemAlreadyExists&&invuser=$invuser&&action=" . ($_SESSION['ACTIONEDIT'] ?? '') . "&&AlertMessage';</script>";
    exit;
}

// Insert item
mysqli_query($db_conn, "INSERT INTO user_invoice_items
    (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,
     gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
     discount_percentage,discount_amount,gst_type,hsn,date,rwpoints,buyer_gsttype,rwpoints_sls)
    VALUES ('$inv_id','$pr_id','$amount','$qty','$total',
    '$invuser','$customer_id','$Login_user_TYPEvl','$Login_user_IDvl',
    '$gst_percentage','$gstamount_singlepr','$gstamount_total','$subtotal',
    '$discount_percentage','$discount_amount','$gst_type','$hsn','$date','0','$buyer_gsttype','$rwpoints')");

echo "<script>window.location='shop-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&&AddedSuccess&&invuser=$invuser&&action=" . ($_SESSION['ACTIONEDIT'] ?? '') . "&&FemiAdded';</script>";
