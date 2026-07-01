<?php 
include("checksession.php");
include("config.php");

error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/invoice-action-errors.log');

if(isset($_REQUEST['addInvoice2']))
{
	// ✅ Validate required fields exist
	$required_fields = ['inv_id', 'invuser', 'customer_id', 'date', 'godownid', 'pr_id', 'amount', 'qty'];
	$missing_fields = [];
	
	foreach ($required_fields as $field) {
		if (!isset($_REQUEST[$field]) || empty($_REQUEST[$field])) {
			$missing_fields[] = $field;
		}
	}
	
	if (!empty($missing_fields)) {
		error_log("[SS-ACTION2] ERROR: Missing fields: " . implode(', ', $missing_fields));
		echo "<script>alert('Error: Missing required fields: " . implode(', ', $missing_fields) . "'); window.history.back();</script>";
		exit;
	}
	 
	$inv_id = mysqli_real_escape_string($db_conn, $_REQUEST['inv_id']);
	
	// Get invoice details for GST type
	$select_INVProductDetails = "select * from user_invoice where inv_id='$inv_id'";
	$fetch_INVProductDetails = mysqli_query($db_conn, $select_INVProductDetails);
	$result_INVProductDetails = mysqli_fetch_array($fetch_INVProductDetails);
	
	if (!$result_INVProductDetails) {
		error_log("[SS-ACTION2] ERROR: Invoice not found: $inv_id");
		echo "<script>alert('Error: Invoice not found'); window.history.back();</script>";
		exit;
	}
	
	$gst_type = $result_INVProductDetails['gst_type'];
	$buyer_gsttype = $result_INVProductDetails['buyer_gsttype'];
		
	$invuser = mysqli_real_escape_string($db_conn, $_REQUEST['invuser']);
	$customer_id = mysqli_real_escape_string($db_conn, $_REQUEST['customer_id']);
	$date = date("Y-m-d", strtotime($_REQUEST['date']));
	$inv_year = date("Y", strtotime($_REQUEST['date']));
	
	$godownid = mysqli_real_escape_string($db_conn, $_REQUEST['godownid']);
	$Login_user_IDvl = $godownid;
	
	error_log("[SS-ACTION2] Invoice: $inv_id, Customer: $customer_id, SS: $Login_user_IDvl");
		
	// Update invoice header (customer, date)
	$update_Invoice = "update user_invoice set to_user_id='$customer_id', date='$date', inv_year='$inv_year' 
	                   where inv_id='$inv_id' 
	                   and from_user_type='$Login_user_TYPEvl' 
	                   and from_user_id='$Login_user_IDvl' 
	                   and to_user_type='$invuser' 
	                   and to_user_id='$customer_id'";
	mysqli_query($db_conn, $update_Invoice);
	
	//-------------------------------------------
	// Insert product details
	//-------------------------------------------
	
	$pr_id = mysqli_real_escape_string($db_conn, $_REQUEST['pr_id']);
	$amount = floatval($_REQUEST['amount']);
	$qty = floatval($_REQUEST['qty']);
	
	// ✅ Validate numeric values
	if ($amount <= 0) {
		error_log("[SS-ACTION2] ERROR: Invalid amount: $amount");
		echo "<script>alert('Error: Product price must be greater than zero'); window.history.back();</script>";
		exit;
	}
	
	if ($qty <= 0) {
		error_log("[SS-ACTION2] ERROR: Invalid quantity: $qty");
		echo "<script>alert('Error: Quantity must be greater than zero'); window.history.back();</script>";
		exit;
	}
	
	//--------------------------------------------------------------------------------
	// CALCULATE TOTALS, GST, DISCOUNT
	//--------------------------------------------------------------------------------
	$totalamount = $amount * $qty;
	
	// Get product GST and details
	$selectproducts = "select * from products where id='$pr_id'";
	$fetchproducts = mysqli_query($db_conn, $selectproducts);
	$resultproducts = mysqli_fetch_array($fetchproducts);
	
	if (!$resultproducts) {
		error_log("[SS-ACTION2] ERROR: Product not found: $pr_id");
		echo "<script>alert('Error: Product not found'); window.history.back();</script>";
		exit;
	}
	
	$gst_percentage = $resultproducts['gst'];
	$hsn = $resultproducts['hsn'];
	$rwpoints = $resultproducts['rwpoints'] * $qty;
	
	$gstamount_singlepr = "0";
	
	if(isset($_REQUEST['discount_percentage']) && $_REQUEST['discount_percentage'] > 0) {
		$discount_percentage = floatval($_REQUEST['discount_percentage']);
		$discount_amount = $totalamount * $discount_percentage / 100;
		$discount_amount = number_format($discount_amount, 2, '.', '');
	} else {
		$discount_amount = isset($_REQUEST['discount_amount']) ? floatval($_REQUEST['discount_amount']) : 0;
		$discount_percentage = $totalamount > 0 ? ($discount_amount * 100 / $totalamount) : 0;
		$discount_percentage = number_format($discount_percentage, 2, '.', '');
	}
	
	$subtotal = $totalamount - $discount_amount;
	$subtotal = number_format($subtotal, 2, '.', '');
	 
	$gstamount_total = ($subtotal * $gst_percentage / 100); 
	$total = $subtotal + $gstamount_total;
	
	error_log("[SS-ACTION2] Product: $pr_id, Qty: $qty, Amount: $amount, Total: $total");
	
	//--------------------------------------------------------------------------------
	// CHECK AVAILABLE STOCK
	//--------------------------------------------------------------------------------
	$select_count_AVSTOCK = "select * from stock where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
	$FETCH_count_AVSTOCK = mysqli_query($db_conn, $select_count_AVSTOCK);
	$RESULT_count_AVSTOCK = mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock = $RESULT_count_AVSTOCK['closing_qty'] ?? 0;
	
	if($AVMstock < $qty) {
		error_log("[SS-ACTION2] ERROR: Insufficient stock. Available: $AVMstock, Required: $qty");
		echo "<script>window.location='user-invoice-add?InvoiceID=" . base64_encode($inv_id) . 
		     "&&InvalidStock&&invuser=" . $invuser . 
		     "&&AlertStockError&&action=" . $_SESSION['ACTIONEDIT'] . "';</script>";
		exit;
	}
	
	//--------------------------------------------------------------------------------
	// CHECK IF PRODUCT ALREADY EXISTS
	//--------------------------------------------------------------------------------
	$select_count_invoiceItem = "select count(*) as numInvoiceItem from user_invoice_items 
	                             where inv_id='$inv_id' and pr_id='$pr_id' 
	                             and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' 
	                             and to_user_type='$invuser' and to_user_id='$customer_id'";
	$fetch_count_invoiceItem = mysqli_query($db_conn, $select_count_invoiceItem);
	$result_count_invoiceItem = mysqli_fetch_array($fetch_count_invoiceItem);
	
	if($result_count_invoiceItem['numInvoiceItem'] == 0) {
		
		//--------------------------------------------------------------------------------
		// 1. INSERT INVOICE ITEM
		//--------------------------------------------------------------------------------
		$insert_InvoiceItems = "insert into user_invoice_items (
		                        inv_id, pr_id, amount, qty, total, to_user_type, to_user_id, 
		                        from_user_type, from_user_id, gst_percentage, gstamount_singlepr, 
		                        gstamount_total, subtotal, discount_percentage, discount_amount, 
		                        gst_type, hsn, date, rwpoints, buyer_gsttype)
		                        values (
		                        '$inv_id', '$pr_id', '$amount', '$qty', '$total', 
		                        '$invuser', '$customer_id', '$Login_user_TYPEvl', '$Login_user_IDvl', 
		                        '$gst_percentage', '$gstamount_singlepr', '$gstamount_total', 
		                        '$subtotal', '$discount_percentage', '$discount_amount', 
		                        '$gst_type', '$hsn', '$date', '$rwpoints', '$buyer_gsttype')";
		
		if (!mysqli_query($db_conn, $insert_InvoiceItems)) {
			error_log("[SS-ACTION2] ERROR: Failed to insert invoice item: " . mysqli_error($db_conn));
			echo "<script>alert('Error: Failed to add product'); window.history.back();</script>";
			exit;
		}
		
		error_log("[SS-ACTION2] Invoice item inserted successfully");
		
		
		// ✅ Redirect without gid parameter
		echo "<script>window.location='user-invoice-add?InvoiceID=" . base64_encode($inv_id) . 
		     "&&AddedSuccess&&invuser=" . $invuser . 
		     "&&FemiAdded&&action=" . $_SESSION['ACTIONEDIT'] . "';</script>";
		
	} else {
		// Product already exists
		error_log("[SS-ACTION2] ERROR: Product already exists in invoice");
		echo "<script>window.location='user-invoice-add?InvoiceID=" . base64_encode($inv_id) . 
		     "&&ItemAlreadyExists&&invuser=" . $invuser . 
		     "&&AlertMessage&&action=" . $_SESSION['ACTIONEDIT'] . "';</script>";
	}
}
	
?>