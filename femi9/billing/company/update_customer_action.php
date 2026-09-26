<?php
session_start();
include("include/db-connect.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
	$invid=$_POST["invid"];
	$invuser=$_POST["invuser"];
	$old_customer_id=$_POST["old_customer_id"];
	$new_customer_id=$_POST["new_customer_id"];

//-------------------------------------------------------------------	
//-------------------------------------------------------------------	

$select_invdetails="select * from user_invoice where inv_id='$invid'";
$fetch_invdetails=mysqli_query($db_conn,$select_invdetails);
$result_invdetails=mysqli_fetch_array($fetch_invdetails);
$cus_tempid=$result_invdetails['to_user_id'];

if($old_customer_id==$cus_tempid)
{	


$select_prlist="select * from user_invoice_items where inv_id='$invid'";
$fetch_prlist=mysqli_query($db_conn,$select_prlist);
while($resultprlist=mysqli_fetch_array($fetch_prlist))
{

$product_id=$resultprlist['pr_id'];
$qty=$resultprlist['qty'];

// Move this line's stock from the old customer account to the new one —
// via StockService (scoped by user_type + the unassigned warehouse row,
// unlike the old raw UPDATEs which matched on user_id + product_id alone
// and could hit a same-id row under a different user_type or warehouse).
try {
	$stockService = new StockService($db_conn);
	$db_conn->begin_transaction();
	$stockService->reverseCredit(
		(int)$product_id, $invuser, $old_customer_id, (int)$qty,
		'user_invoice', $invid, $invuser, true
	);
	$stockService->credit(
		(int)$product_id, $invuser, $new_customer_id, (int)$qty,
		'user_invoice', $invid, $invuser, true
	);
	$db_conn->commit();
} catch (\Throwable $e) {
	$db_conn->rollback();
	error_log("update_customer_action stock move error: " . $e->getMessage());
}

}

}


$update_user_invoice="update user_invoice set to_user_id='$new_customer_id' where inv_id='$invid'";
mysqli_query($db_conn,$update_user_invoice);

$update_user_invoice12="update user_invoice_items set to_user_id='$new_customer_id' where inv_id='$invid'";
mysqli_query($db_conn,$update_user_invoice12);

$update_user_receipt="update receipt set to_user_id='$new_customer_id' where inv_id='$invid'";
mysqli_query($db_conn,$update_user_receipt);

//------------------------------------------------------------------
//------------------------------------------------------------------	
	
	//$_SESSION['successMessage']="Customer Update Success!";
	echo "<script>window.location='update_customer?invuser=$invuser&&InvoiceID=$invid&&updatedsuccess';</script>";
	
	
	
}
else
{ echo "<script>window.location='dashboard';</script>";
}
?>