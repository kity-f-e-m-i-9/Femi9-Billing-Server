<?php include("checksession.php"); 
include("config.php");
error_reporting(0); 

if(isset($_REQUEST['invoice-submit']))
{ 
	$invoice_id=$_REQUEST['invoice_id'];
	//
	$selectdetails="select * from user_invoice where inv_id='$invoice_id'";
	$fetchdetails=mysqli_query($db_conn,$selectdetails);
	$resultdetails=mysqli_fetch_array($fetchdetails);
	//
	$usertype=$resultdetails['to_user_type'];
	$userid=$resultdetails['to_user_id'];
	
	$Login_user_IDvl=$resultdetails['from_user_id'];
	
	$SubTotal=$_REQUEST['SubTotal'];
	if($_REQUEST['discount']!=NULL){ $discount=$_REQUEST['discount']; }else{ $discount="0";}
	$credit=$_REQUEST['credit'];
	$roundoff=$_REQUEST['roundoff'];
	$courier_charges=$_REQUEST['courier_charges'];
	
	$total_amount=$SubTotal-$discount-$credit+$courier_charges;
	$total_amount=round($total_amount);
    
	
	//insert receipt
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
		//
		$receipt_method=$_REQUEST['receipt_method'];
		$receipt_remarks=str_replace("'","&#39;",$_REQUEST['receipt_remarks']);
		
		$insertreceipt="insert into receipt (receiptid,inv_id,invoice_amount,received,receivable,date,from_user_type,
		from_user_id,to_user_type,to_user_id,receipt_method,receipt_remarks) 
		values 
		('$invoice_id','$invoice_id','$total_amount','$receivedamount','$receivableamount','$receiptdate','".$resultdetails['from_user_type']."','".$resultdetails['from_user_id']."','$usertype','$userid','$receipt_method','$receipt_remarks')";
		mysqli_query($db_conn,$insertreceipt);
	}
	
	
	
	if($_REQUEST['invoice_id']!=NULL)
	{
		
		if($_SESSION['INVOICEFINISH']!=NULL && $credit!=0)
		{
	//less credit amount
	$selectcountcredit12="select * from return_credit where usertype='$usertype' and userid='$userid'";
	$fetchcountcredit12=mysqli_query($db_conn,$selectcountcredit12);
	$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
	$creditamount=$resultcountcredit12['credit_amount']-$credit;
		
		$updatecredit="update return_credit set credit_amount='$creditamount' where usertype='$usertype' and userid='$userid'";
		mysqli_query($db_conn,$updatecredit);
		}
		
		unset($_SESSION['INVOICEFINISH']);
	
	$update_invoice="update user_invoice set credit='$credit',sub_total='$SubTotal',discount='$discount',total='$total_amount',roundoff='$roundoff',courier_charges='$courier_charges' where inv_id='$invoice_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
	mysqli_query($db_conn,$update_invoice);
	
	//update status in stock_request table
	$updatebilled="update stock_request set status='billed',inv_id='$invoice_id' where reqid='$invoice_id'";
	mysqli_query($db_conn,$updatebilled);
		
	}
	
	echo "<script>window.location='user-invoice-print.php?invoiceid=".base64_encode($invoice_id)."';</script>";
}
?>