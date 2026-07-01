<?php include("checksession.php");

$invtype=$_REQUEST['invtype'];
$invuser=$_REQUEST['invuser'];
$invid=base64_decode($_REQUEST['invid']);

//SUPER STOCKIST, STOCKIST, DISTRIBUTOR
if($invtype=="noncustomer")
{
	
	$select_count_items="select * from user_invoice_items where inv_id='$invid'";
	$fethc_count_items=mysqli_query($db_conn,$select_count_items);
	$result_count_items=mysqli_num_rows($fethc_count_items);
	if($result_count_items==0)
	{

$del_inv1="delete from user_invoice where inv_id='$invid'";
mysqli_query($db_conn,$del_inv1);

$del_receipt="delete from receipt where inv_id='$invid'";
mysqli_query($db_conn,$del_receipt);

$_SESSION['successMessage']="One Invoice Deleted!";
echo "<script>window.location='user-manage-invoice?deletedsuccess&&invuser=$invuser';</script>";
	}else{
		$_SESSION['errorMessage']="This Invoice contains no empty items!";
		echo "<script>window.location='user-manage-invoice?productnonempty&&invuser=$invuser';</script>";
	}
}

//SHOP
if($invtype=="shop")
{
	
	$select_count_items="select * from user_invoice_items where inv_id='$invid'";
	$fethc_count_items=mysqli_query($db_conn,$select_count_items);
	$result_count_items=mysqli_num_rows($fethc_count_items);
	if($result_count_items==0)
	{

$del_inv1="delete from user_invoice where inv_id='$invid'";
mysqli_query($db_conn,$del_inv1);

$del_receipt="delete from receipt where inv_id='$invid'";
mysqli_query($db_conn,$del_receipt);

$_SESSION['successMessage']="One Invoice Deleted!";
echo "<script>window.location='shop-user-manage-invoice?deletedsuccess';</script>";
	}else{
		
		$_SESSION['errorMessage']="This Invoice contains no empty items!";
		echo "<script>window.location='shop-user-manage-invoice?productnonempty';</script>";
	}
}




//SHOP
if($invtype=="customer")
{
	
	$select_count_items="select * from invoice_items where inv_id='$invid'";
	$fethc_count_items=mysqli_query($db_conn,$select_count_items);
	$result_count_items=mysqli_num_rows($fethc_count_items);
	if($result_count_items==0)
	{

$del_inv1="delete from invoice where inv_id='$invid'";
mysqli_query($db_conn,$del_inv1);

$del_receipt="delete from receipt where inv_id='$invid'";
mysqli_query($db_conn,$del_receipt);

$_SESSION['successMessage']="One Invoice Deleted!";
echo "<script>window.location='customer-user-manage-invoice?deletedsuccess';</script>";
	}else{
		
		$_SESSION['errorMessage']="This Invoice contains no empty items!";
		echo "<script>window.location='customer-user-manage-invoice?productnonempty';</script>";
	}
}


?>