<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=base64_decode($_REQUEST['returnid']);
$SubTotal=$_REQUEST['SubTotal'];
$discount=$_REQUEST['discount']; 

$total_amount=$SubTotal-$discount;

//update credit amount
$select_ProductDetails123="select * from user_return_stock where returnid=?";
		$fetch_ProductDetails123=db_q($db_conn,$select_ProductDetails123,[$returnid]);
		$result_ProductDetails123=mysqli_fetch_array($fetch_ProductDetails123);
		$invid=$result_ProductDetails123['invnumber'];
		
		$usertype=$result_ProductDetails123['from_usertype']; 
		$userid=$result_ProductDetails123['from_userid'];
		
		if($result_ProductDetails123['status']=="pending")
		{
			
			
		//--------------------------------------------------------------------------
		/*
		if($usertype=="super_stockiest" || $usertype=="stockiest" || $usertype=="distributor")
		{
			
		$selectcountcredit="select count(*) as numCredit from return_credit where usertype=? and userid=?";
		$fetchcountcredit=db_q($db_conn,$selectcountcredit,[$usertype,$userid]);
		$resultcountcredit=mysqli_fetch_array($fetchcountcredit);
		if($resultcountcredit['numCredit']==0)
		{
			//insert credit
		    $insertcredit="insert into return_credit (usertype,userid,credit_amount) 
			values (?,?,?)";
			db_q($db_conn,$insertcredit,[$usertype,$userid,$total_amount]);
			
		}else{
			
			//update credit
			$selectcountcredit12="select * from return_credit where usertype=? and userid=?";
		$fetchcountcredit12=db_q($db_conn,$selectcountcredit12,[$usertype,$userid]);
		$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
		$creditamount=$resultcountcredit12['credit_amount']+$total_amount;
		
$updatecredit="update return_credit set credit_amount=? where usertype=? and userid=?";
db_q($db_conn,$updatecredit,[$creditamount,$usertype,$userid]);
			
		}
		
		}
		*/
		//----------------------------------------------------------------------
			
			
		}


//update user_return_stock table
$update_returntable="update user_return_stock set subtotal=?,discount=?,total=?,
status='accept' where returnid=?";
db_q($db_conn,$update_returntable,[$SubTotal,$discount,$total_amount,$returnid]);

$update_returntable12="update user_return_stock_items set status='accept' where returnid=?";
db_q($db_conn,$update_returntable12,[$returnid]);

//GET INVOICE NUMBER
if($usertype=="customer")
{
$select_InvoiceNumber="select * from invoice where inv_id='".db_esc($invid)."'";
}else{
	$select_InvoiceNumber="select * from user_invoice where inv_id=?";
}
$fetch_InvoiceNumber=db_q($db_conn,$select_InvoiceNumber,[$invid]);
$result_InvoiceNumber=mysqli_fetch_array($fetch_InvoiceNumber);
		
$_SESSION['successMessage']="Credit Note Added Successfully against Invoice Number is : ".$result_InvoiceNumber['inv_number']."";
echo "<script>window.location='cnote_manage.php?returnaddedsuccess';</script>";

?>