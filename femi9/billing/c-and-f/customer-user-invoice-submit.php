<?php include("checksession.php"); 
include("config.php"); 
error_reporting(0);

if(isset($_REQUEST['invoice-submit']))
{
	$invoice_id=$_REQUEST['invoice_id'];
	
	$selectdetails="select * from invoice where inv_id='$invoice_id'";
	$fetchdetails=mysqli_query($db_conn,$selectdetails);
	$resultdetails=mysqli_fetch_array($fetchdetails);
	$Login_user_IDvl=$resultdetails['user_id']; 
	
///UPDATE INVOCIE - IF EDIT INVOICE ACTIO ONLY	
if($_REQUEST['update_invoice_date']!=NULL)
{
$update_invoice_date=date("Y-m-d",strtotime($_REQUEST['update_invoice_date']));

//1
$update_invoice12="update invoice set date='$update_invoice_date' where inv_id='$invoice_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
mysqli_query($db_conn,$update_invoice12);

//2	
$update_invoice_ITEMS12="update invoice_items set date='$update_invoice_date' where inv_id='$invoice_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
mysqli_query($db_conn,$update_invoice_ITEMS12);

	}
//--------------------------------------------
	
	
	$SubTotal=$_REQUEST['sub_total'];
	if($_REQUEST['discount']!=NULL){ $discount=$_REQUEST['discount'];}else{$discount="0";}
	$roundoff=$_REQUEST['roundoff'];
	$courier_charges=$_REQUEST['courier_charges'];
	
	$total_amount=$SubTotal-$discount+$courier_charges;
	$total_amount=round($total_amount);
	
	
	//DELETE RECEIPT DETAILS IF EDIT FUNCTION
	if($_SESSION['ACTIONEDIT']=="edit"){
		
		$delReceipt="delete from receipt where inv_id='$invoice_id'";
		mysqli_query($db_conn,$delReceipt);
	}
	
	
	//INSERT RECEIPT DETAILS
	$insertreceiptcount="select count(*) as numreceipt from receipt where receiptid='$invoice_id'";
	$fetchreceipt=mysqli_query($db_conn,$insertreceiptcount);
	$resultreceipt=mysqli_fetch_array($fetchreceipt);
	if($resultreceipt['numreceipt']==0)
	{
		$receiptdate=$resultdetails['date'];
		
		if($_REQUEST['receivedamount']!=NULL)
		{
		$receivedamount=$_REQUEST['receivedamount'];
		}
		else
		{$receivedamount="0";}
	
		$receivableamount=$total_amount-$receivedamount;
		$receivableamount=round($receivableamount);
		
		$usertype="customer";
		$userid=$resultdetails['customer_id'];
		
		$receipt_method=$_REQUEST['receipt_method'];
		$receipt_remarks=str_replace("'","&#39;",$_REQUEST['receipt_remarks']);
		
		$insertreceipt="insert into receipt (receiptid,inv_id,invoice_amount,received,receivable,date,from_user_type,
		from_user_id,to_user_type,to_user_id,receipt_method,receipt_remarks) 
		values 
		('$invoice_id','$invoice_id','$total_amount','$receivedamount','$receivableamount','$receiptdate','".$resultdetails['user_type']."','".$resultdetails['user_id']."','$usertype','$userid','$receipt_method','$receipt_remarks')";
		mysqli_query($db_conn,$insertreceipt);
	}
	//-------------------------------------
	
	
	if($_REQUEST['invoice_id']!=NULL)
	{
		$update_invoice="update invoice set sub_total='$SubTotal',discount='$discount',
	total='$total_amount',roundoff='$roundoff',courier_charges='$courier_charges' where inv_id='$invoice_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
	mysqli_query($db_conn,$update_invoice);
	
	
	
	}
	
	echo "<script>window.location='customer-user-invoice-print.php?invoiceid=".base64_encode($invoice_id)."';</script>";
}
?>