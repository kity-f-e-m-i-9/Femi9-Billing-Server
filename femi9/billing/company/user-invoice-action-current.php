<?php
/**
 * User Invoice Action - Product Addition Only
 * Femi9 Billing Application
 * 
 * This file handles ONLY product addition to invoices
 * NO advance payment deduction happens here
 * 
 * Advance payment will be deducted during invoice SUBMISSION (invoice-submit.php)
 * 
 * @version 3.0
 * @date 2026-01-01
 */

session_start();
include("checksession.php");
include("config.php");

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Collect input (keeping existing variable names for compatibility)
$randum_number = $_REQUEST['randum_number'];
$inv_id = $_REQUEST['inv_id'];
$invuser = $_REQUEST['invuser'];
$username = $_REQUEST['username'];
$usertype = $_REQUEST['usertype'];
$godownid = $_REQUEST['godownid'];

// Check invoice number duplicate
if (isset($_REQUEST['invoice_number_accept']) && $_REQUEST['invoice_number_accept'] == 0) {
    $_SESSION['errorMessage'] = "Invoice Number already exists!";
    echo "<script>window.location='user-invoice-add?invuser=$invuser&&invoicealready';</script>";
    exit;
}

$inv_number = str_replace("'", "", $_REQUEST['inv_number']);
$id_only = "0";

// Check opening stock exists
$select_count_opstock13 = "select count(*) as numopstock12 from stock where user_type='$Login_user_TYPEvl' and user_id='$godownid'";
$fetch_count_opstock13 = mysqli_query($db_conn, $select_count_opstock13);
$result_count_opstock13 = mysqli_fetch_array($fetch_count_opstock13);

if ($result_count_opstock13['numopstock12'] == 0) {
    echo "<script>window.location='user-invoice-add?invuser=" . $invuser . "&&gid=" . $godownid . "&&stocknotupdated&&action=" . $_SESSION['ACTIONEDIT'] . "&&stockerror';</script>";
    exit;
}

$customer_id = $_REQUEST['customer_id'];
$date = date("Y-m-d", strtotime($_REQUEST['date']));
$inv_year = date("Y", strtotime($_REQUEST['date']));
$pr_id = $_REQUEST['pr_id'];
$amount = $_REQUEST['amount'];
$qty = $_REQUEST['qty'];
$totalamount = $amount * $qty;

// Get product details (GST, HSN, Reward Points)
$selectproducts = "select * from products where id='$pr_id'";
$fetchproducts = mysqli_query($db_conn, $selectproducts);
$resultproducts = mysqli_fetch_array($fetchproducts);
$gst_percentage = $resultproducts['gst'];
$hsn = $resultproducts['hsn'];
$rwpoints = $resultproducts['rwpoints'] * $qty;
$gstamount_singlepr = "0";

// Calculate discount
if ($_REQUEST['discount_percentage'] > 0) {
    $discount_percentage = $_REQUEST['discount_percentage'];
    $discount_amount = $totalamount * $discount_percentage / 100;
    $discount_amount = number_format($discount_amount, 2, '.', '');
} else {
    $discount_amount = $_REQUEST['discount_amount'];
    $discount_percentage = $discount_amount * 100 / $totalamount;
    $discount_percentage = number_format($discount_percentage, 2, '.', '');
}

$subtotal = $totalamount - $discount_amount;
$subtotal = number_format($subtotal, 2, '.', '');

$gstamount_total = ($subtotal * $gst_percentage / 100);
$total = $subtotal + $gstamount_total;

// Get company state code
$sqladminlog = "select * from admin_log where usertype='admin'";
$resultadminlog = mysqli_query($db_conn, $sqladminlog);
$fetch_resultlog = mysqli_fetch_array($resultadminlog);
$admin_statecode = $fetch_resultlog['state'];

// Get customer table name
if ($invuser == "candf") {
    $tablename = "c_and_f";
} else if ($invuser == "super_stockiest") {
    $tablename = "super_stockiest";
} else if ($invuser == "stockiest") {
    $tablename = "stockiest";
} else if ($invuser == "super_distributor") {
    $tablename = "super_distributor";
} else if ($invuser == "distributor") {
    $tablename = "distributor";
} else if ($invuser == "outlet") {
    $tablename = "outlet";
}

// Get customer details
$selecutomser_dtails = "select * from " . $tablename . " where temp_id='$customer_id'";
$fetchcutomser_dtails = mysqli_query($db_conn, $selecutomser_dtails);
$resultcutomser_dtails = mysqli_fetch_array($fetchcutomser_dtails);
$customer_state = $resultcutomser_dtails['state_id'];

$buyer_GSTIN = $resultcutomser_dtails['gstin'];
$buyer_GSTIN_count = strlen($buyer_GSTIN);
if ($buyer_GSTIN_count == 15) {
    $buyer_gsttype = "register";
} else {
    $buyer_gsttype = "unregister";
}

if ($customer_state == $admin_statecode) {
    $gst_type = "inner";
} else {
    $gst_type = "outer";
}

// Check if invoice already exists
$select_count_invoice = "select count(*) as numInvoice from user_invoice where inv_id='$inv_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$godownid' and to_user_type='$invuser' and to_user_id='$customer_id'";
$fetch_count_invoice = mysqli_query($db_conn, $select_count_invoice);
$result_count_invoice = mysqli_fetch_array($fetch_count_invoice);

if ($result_count_invoice['numInvoice'] == 0) {
    // Insert invoice header
    $insert_Invoice = "insert into user_invoice (inv_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,from_user_type,from_user_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
    values 
    ('$inv_id','$id_only','$inv_number','$date','$inv_year','0','0','0',
    '$invuser','$customer_id','$Login_user_TYPEvl','$godownid','$gst_type','0','0','0','1',
    '$buyer_gsttype','$username','$usertype')";
    mysqli_query($db_conn, $insert_Invoice);
}

// Check available stock
$select_count_AVSTOCK = "select * from stock where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$godownid'";
$FETCH_count_AVSTOCK = mysqli_query($db_conn, $select_count_AVSTOCK);
$RESULT_count_AVSTOCK = mysqli_fetch_array($FETCH_count_AVSTOCK);
$AVMstock = $RESULT_count_AVSTOCK['closing_qty'];

if ($AVMstock < $qty) {
    echo "<script>window.location='user-invoice-add?InvoiceID=" . base64_encode($inv_id) . "&&InvalidStock&&invuser=" . $invuser . "&&AlertStockError&&action=" . $_SESSION['ACTIONEDIT'] . "&&gid=" . $godownid . "';</script>";
    exit;
}

// Check if product already in invoice
$select_count_invoiceItem = "select count(*) as numInvoiceItem from user_invoice_items where inv_id='$inv_id' and pr_id='$pr_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$godownid' and to_user_type='$invuser' and to_user_id='$customer_id'";
$fetch_count_invoiceItem = mysqli_query($db_conn, $select_count_invoiceItem);
$result_count_invoiceItem = mysqli_fetch_array($fetch_count_invoiceItem);

if ($result_count_invoiceItem['numInvoiceItem'] == 0) {
    // Insert invoice item
    $insert_InvoiceItems = "insert into user_invoice_items (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,
    gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
    discount_percentage,discount_amount,gst_type,hsn,date,rwpoints,buyer_gsttype)
    values ('$inv_id','$pr_id','$amount','$qty','$total','$invuser','$customer_id','$Login_user_TYPEvl','$godownid','$gst_percentage','$gstamount_singlepr','$gstamount_total','$subtotal',
    '$discount_percentage','$discount_amount','$gst_type','$hsn','$date','$rwpoints','$buyer_gsttype')";
    mysqli_query($db_conn, $insert_InvoiceItems);
    
    // Update company stock (decrement)
    $select_stockDetails = "select * from stock where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$godownid'";
    $fetch_stockDetails = mysqli_query($db_conn, $select_stockDetails);
    $result_stockDetails = mysqli_fetch_array($fetch_stockDetails);
    
    $update_Sales_stock = $result_stockDetails['sales_qty'] + $qty;
    $update_Closing_stock = $result_stockDetails['closing_qty'] - $qty;
    
    $update_stockDetails = "update stock set sales_qty='$update_Sales_stock',closing_qty='$update_Closing_stock' where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$godownid'";
    mysqli_query($db_conn, $update_stockDetails);
    
    // Check if customer stock record exists
    $select_stockDetailscheck = "select count(*) as numprcheck from stock where product_id='$pr_id' and user_type='$invuser' and user_id='$customer_id'";
    $fetch_stockDetailscheck = mysqli_query($db_conn, $select_stockDetailscheck);
    $result_stockDetailscheck = mysqli_fetch_array($fetch_stockDetailscheck);
    
    if ($result_stockDetailscheck['numprcheck'] == 0) {
        $insertprstock = "insert into stock (product_id,opening_qty,opening_date,input_qty,sales_qty,sent_qty,returnqty,closing_qty,user_type,user_id) values ('$pr_id','0','$date','0','0','0','0','0','$invuser','$customer_id')";
        mysqli_query($db_conn, $insertprstock);
    }
    
    // Update customer stock (increment)
    $select_stockDetails12 = "select * from stock where product_id='$pr_id' and user_type='$invuser' and user_id='$customer_id'";
    $fetch_stockDetails12 = mysqli_query($db_conn, $select_stockDetails12);
    $result_stockDetails12 = mysqli_fetch_array($fetch_stockDetails12);
    
    $update_Sales_stock12 = $result_stockDetails12['input_qty'] + $qty;
    $update_Closing_stock12 = $result_stockDetails12['closing_qty'] + $qty;
    
    $update_stockDetails = "update stock set input_qty='$update_Sales_stock12',closing_qty='$update_Closing_stock12' where product_id='$pr_id' and user_type='$invuser' and user_id='$customer_id'";
    mysqli_query($db_conn, $update_stockDetails);
    
    echo "<script>window.location='user-invoice-add?InvoiceID=" . base64_encode($inv_id) . "&&AddedSuccess&&invuser=" . $invuser . "&&FemiAdded&&action=" . $_SESSION['ACTIONEDIT'] . "&&gid=" . $godownid . "';</script>";
} else {
    echo "<script>window.location='user-invoice-add?InvoiceID=" . base64_encode($inv_id) . "&&ItemAlreadyExists&&invuser=" . $invuser . "&&AlertMessage&&action=" . $_SESSION['ACTIONEDIT'] . "&&gid=" . $godownid . "';</script>";
}
?>