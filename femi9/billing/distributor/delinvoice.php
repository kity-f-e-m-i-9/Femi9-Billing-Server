<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$invtype=$_REQUEST['invtype'];
$invuser=$_REQUEST['invuser'];
$invid=base64_decode($_REQUEST['invid']);

//SUPER STOCKIST, STOCKIST, DISTRIBUTOR
if($invtype=="noncustomer")
{
	
	$select_count_items="select * from user_invoice_items where inv_id=?";
	$fethc_count_items=db_q($db_conn,$select_count_items,[$invid]);
	$result_count_items=mysqli_num_rows($fethc_count_items);
	if($result_count_items==0)
	{

$del_inv1="delete from user_invoice where inv_id=?";
db_q($db_conn,$del_inv1,[$invid]);

$del_receipt="delete from receipt where inv_id=?";
db_q($db_conn,$del_receipt,[$invid]);

$_SESSION['successMessage']="One Invoice Deleted!";
echo "<script>window.location='user-manage-invoice?deletedsuccess&&invuser=$invuser';</script>";
	}else{
		$_SESSION['errorMessage']="This Invoice contains no empty items!";
		echo "<script>window.location='user-manage-invoice?productnonempty&&invuser=$invuser';</script>";
	}
}


//SHOP
if($invtype=="customer")
{
	
	$select_count_items="select * from invoice_items where inv_id=?";
	$fethc_count_items=db_q($db_conn,$select_count_items,[$invid]);
	$result_count_items=mysqli_num_rows($fethc_count_items);
	if($result_count_items==0)
	{

$del_inv1="delete from invoice where inv_id=?";
db_q($db_conn,$del_inv1,[$invid]);

$del_receipt="delete from receipt where inv_id=?";
db_q($db_conn,$del_receipt,[$invid]);

$_SESSION['successMessage']="One Invoice Deleted!";
echo "<script>window.location='customer-user-manage-invoice?deletedsuccess';</script>";
	}else{
		
		$_SESSION['errorMessage']="This Invoice contains no empty items!";
		echo "<script>window.location='customer-user-manage-invoice?productnonempty';</script>";
	}
}


?>