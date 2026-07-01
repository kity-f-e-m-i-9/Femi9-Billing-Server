<?php include("checksession.php");
include("config.php");

if(isset($_REQUEST['inv_id']))
{
	$invoice_id_encode=$_REQUEST['inv_id'];
	$customer_id=$_REQUEST['userid'];
	
	$rowid_encode=$_REQUEST['rowid'];
	$rowid_decode=base64_decode($rowid_encode);
	
	//
	$select_INVProductDetails="select * from invoice_items where id='$rowid_decode'";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	$Login_user_IDvl=$result_INVProductDetails['user_id'];
	
	if($result_INVProductDetails['pr_id']!=NULL)
	{
	
		$pr_id=$result_INVProductDetails['pr_id'];
		$qty=$result_INVProductDetails['qty'];
		
		
	}
	
	$delRecord="delete from invoice_items where id='$rowid_decode'";
	mysqli_query($db_conn,$delRecord);
	
	echo "<script>window.location='customer-user-invoice-add?InvoiceID=".$invoice_id_encode."&&DeleteSuccess&&ActionRemove&&gid=".$Login_user_IDvl."';</script>";
	
}
?>