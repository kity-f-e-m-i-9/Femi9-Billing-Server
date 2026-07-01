<?php include("checksession.php");
include("config.php");

if(isset($_REQUEST['inv_id']))
{
	$invoice_id_encode=$_REQUEST['inv_id'];
	$invuser=$_REQUEST['invuser'];
	$customer_id=$_REQUEST['userid'];
	
	$rowid_encode=$_REQUEST['rowid'];
	$rowid_decode=base64_decode($rowid_encode);
	
	//
	$select_INVProductDetails="select * from user_invoice_items where id='$rowid_decode'";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	if($result_INVProductDetails['pr_id']!=NULL)
	{
	
		$pr_id=$result_INVProductDetails['pr_id'];
		$qty=$result_INVProductDetails['qty'];
		
		$Login_user_IDvl=$result_INVProductDetails['from_user_id'];
		

		
	}
	
	$delRecord="delete from user_invoice_items where id='$rowid_decode'";
	mysqli_query($db_conn,$delRecord);
	
	echo "<script>window.location='shop-user-invoice-add?InvoiceID=".$invoice_id_encode."&&DeleteSuccess&&invuser=".$invuser."&&ActionRemove&&action=".$_SESSION['ACTIONEDIT']."&&gid=".$Login_user_IDvl."';</script>";
	
}
?>