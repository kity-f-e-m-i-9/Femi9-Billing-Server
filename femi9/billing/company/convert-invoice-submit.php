<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); 
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
	$update_invoice="update user_invoice set sub_total=?,discount=?,total=? where inv_id=? and from_user_type=? and from_user_id=?";
	db_q($db_conn,$update_invoice,[$SubTotal,$discount,$total_amount,$invoice_id,$Login_user_TYPEvl,$Login_user_IDvl]);
	
	//update status in stock_request table
	$updatebilled="update stock_request set status='billed' where reqid=?";
	db_q($db_conn,$updatebilled,[$invoice_id]);
	
	}
	
	echo "<script>window.location='user-invoice-print?invoiceid=".base64_encode($invoice_id)."';</script>";
}
?>