<?php include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=base64_decode($_REQUEST['returnid']);
$SubTotal=$_REQUEST['SubTotal'];
$discount=$_REQUEST['discount']; 

$total_amount=$SubTotal-$discount;

//update credit amount
$select_ProductDetails123="select * from user_return_stock where returnid='$returnid'";
		$fetch_ProductDetails123=mysqli_query($db_conn,$select_ProductDetails123);
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
			
		$selectcountcredit="select count(*) as numCredit from return_credit where usertype='$usertype' and userid='$userid'";
		$fetchcountcredit=mysqli_query($db_conn,$selectcountcredit);
		$resultcountcredit=mysqli_fetch_array($fetchcountcredit);
		if($resultcountcredit['numCredit']==0)
		{
			//insert credit
		    $insertcredit="insert into return_credit (usertype,userid,credit_amount) 
			values ('$usertype','$userid','$total_amount')";
			mysqli_query($db_conn,$insertcredit);
			
		}else{
			
			//update credit
			$selectcountcredit12="select * from return_credit where usertype='$usertype' and userid='$userid'";
		$fetchcountcredit12=mysqli_query($db_conn,$selectcountcredit12);
		$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
		$creditamount=$resultcountcredit12['credit_amount']+$total_amount;
		
$updatecredit="update return_credit set credit_amount='$creditamount' where usertype='$usertype' and userid='$userid'";
mysqli_query($db_conn,$updatecredit);
			
		}
		
		}
		*/
		//----------------------------------------------------------------------
			
			
		}


//update user_return_stock table
$update_returntable="update user_return_stock set subtotal='$SubTotal',discount='$discount',total='$total_amount',
status='accept' where returnid='$returnid'";
mysqli_query($db_conn,$update_returntable);

$update_returntable12="update user_return_stock_items set status='accept' where returnid='$returnid'";
mysqli_query($db_conn,$update_returntable12);

//GET INVOICE NUMBER
if($usertype=="customer")
{
$select_InvoiceNumber="select * from invoice where inv_id='$invid'";
}else{
	$select_InvoiceNumber="select * from user_invoice where inv_id='$invid'";
}
$fetch_InvoiceNumber=mysqli_query($db_conn,$select_InvoiceNumber);
$result_InvoiceNumber=mysqli_fetch_array($fetch_InvoiceNumber);
		
$_SESSION['successMessage']="Credit Note Added Successfully against Invoice Number is : ".$result_InvoiceNumber['inv_number']."";
echo "<script>window.location='cnote_manage.php?returnaddedsuccess';</script>";

?>