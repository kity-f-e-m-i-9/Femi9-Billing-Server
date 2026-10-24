<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); 
include("config.php");
error_reporting(0); 

if(isset($_REQUEST['invoice-submit']))
{ 
	$invoice_id=$_REQUEST['invoice_id'];
	//
	$selectdetails="select * from user_invoice where inv_id=?";
	$fetchdetails=db_q($db_conn,$selectdetails,[$invoice_id]);
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
	$insertreceiptcount="select count(*) as numreceipt from receipt where receiptid=?";
	$fetchreceipt=db_q($db_conn,$insertreceiptcount,[$invoice_id]);
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
		('".db_esc($invoice_id)."','".db_esc($invoice_id)."','$total_amount','".db_esc($receivedamount)."','$receivableamount','$receiptdate','".$resultdetails['from_user_type']."','".$resultdetails['from_user_id']."','$usertype','$userid','".db_esc($receipt_method)."','$receipt_remarks')";
		mysqli_query($db_conn,$insertreceipt);
	}
	
	
	
	if($_REQUEST['invoice_id']!=NULL)
	{
		
		if($_SESSION['INVOICEFINISH']!=NULL && $credit!=0)
		{
	//less credit amount
	$selectcountcredit12="select * from return_credit where usertype=? and userid=?";
	$fetchcountcredit12=db_q($db_conn,$selectcountcredit12,[$usertype,$userid]);
	$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
	$creditamount=$resultcountcredit12['credit_amount']-$credit;
		
		$updatecredit="update return_credit set credit_amount=? where usertype=? and userid=?";
		db_q($db_conn,$updatecredit,[$creditamount,$usertype,$userid]);
		}
		
		unset($_SESSION['INVOICEFINISH']);
	
	$update_invoice="update user_invoice set credit=?,sub_total=?,discount=?,total=?,roundoff=?,courier_charges=? where inv_id=? and from_user_type=? and from_user_id=?";
	db_q($db_conn,$update_invoice,[$credit,$SubTotal,$discount,$total_amount,$roundoff,$courier_charges,$invoice_id,$Login_user_TYPEvl,$Login_user_IDvl]);
	
	//update status in stock_request table
	$updatebilled="update stock_request set status='billed',inv_id=? where reqid=?";
	db_q($db_conn,$updatebilled,[$invoice_id,$invoice_id]);
	
		
	}
	
	
	echo "<script>window.location='user-invoice-print?invoiceid=".base64_encode($invoice_id)."';</script>";
}
?>