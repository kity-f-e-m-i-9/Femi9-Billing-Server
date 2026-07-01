<?php include("checksession.php"); 
include("config.php"); 
error_reporting(0);

if(isset($_REQUEST['invoice-submit']))
{
	$invoice_id=$_REQUEST['invoice_id'];
	$SubTotal=$_REQUEST['SubTotal'];
	$discount=$_REQUEST['discount'];
	$total_amount=$SubTotal-$discount;
	$total_amount=round($total_amount);
	
	if($_REQUEST['invoice_id']!=NULL)
	{
	$update_invoice="update user_invoice set sub_total='$SubTotal',discount='$discount',total='$total_amount' where inv_id='$invoice_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
	mysqli_query($db_conn,$update_invoice);
	
	//update status in stock_request table
	$updatebilled="update stock_request set status='billed' where reqid='$invoice_id'";
	mysqli_query($db_conn,$updatebilled);
	
	}
	
	echo "<script>window.location='user-invoice-print?invoiceid=".base64_encode($invoice_id)."';</script>";
}
?>