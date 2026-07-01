<?php include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);

$rowid=$_REQUEST['rowid'];
$rowid_decode=base64_decode($rowid);

$select_Returndetails="select * from user_return_stock where returnid='$returnid_decode'";
$fetch_Returndetails=mysqli_query($db_conn,$select_Returndetails);
$result_Returndetails=mysqli_fetch_array($fetch_Returndetails);

$from_usertype=$result_Returndetails['from_usertype']; //ss, stockist, distributor, shop, customer
$from_userid=$result_Returndetails['from_userid'];	

$to_usertype=$result_Returndetails['to_usertype'];
$to_userid=$result_Returndetails['to_userid'];	

	$select_INVProductDetails="select * from user_return_stock_items where id='$rowid_decode'";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	if($result_INVProductDetails['prid']!=NULL)
	{
	
		$prid=$result_INVProductDetails['prid'];
		$returnqty=$result_INVProductDetails['qty'];
		$return_amount=$result_INVProductDetails['total'];
		
		//--------------------------------------------------
		//STOCK DECREMENT TO RECEIVED USERS (billed by users)
		$select_stockDetails="select * from stock where product_id='$prid' and user_type='$to_usertype' and user_id='$to_userid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['sales_qty']+$returnqty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$returnqty;
		
		$update_stockDetails="update stock set sales_qty='$update_returnqty',closing_qty='$update_Closing_stock' where product_id='$prid' and user_type='$to_usertype' and user_id='$to_userid'";
		mysqli_query($db_conn,$update_stockDetails);
		
		
		if($from_usertype=="super_stockiest" || $from_usertype=="stockiest" || $from_usertype=="super_distributor" || $from_usertype=="distributor")
		{
			
		//STOCK INCREMENT TO SENT USERS (billed to users)
		$select_stockDetails12="select * from stock where product_id='$prid' and user_type='$from_usertype' and user_id='$from_userid'";
		$fetch_stockDetails12=mysqli_query($db_conn,$select_stockDetails12);
		$result_stockDetails12=mysqli_fetch_array($fetch_stockDetails12);
		
		$update_returnqty12=$result_stockDetails12['input_qty']+$returnqty;
		$update_Closing_stock12=$result_stockDetails12['closing_qty']+$returnqty;
		
		$update_stockDetails12="update stock set input_qty='$update_returnqty12',closing_qty='$update_Closing_stock12' where product_id='$prid' and user_type='$from_usertype' and user_id='$from_userid'";
		mysqli_query($db_conn,$update_stockDetails12);
		
		}
		
		//---------------------------------------------
		
		
		if($_REQUEST['redirurl']=="cnote_details")
	{
		
		
		if($from_usertype=="super_stockiest" || $from_usertype=="stockiest" || $from_usertype=="super_distributor" || $from_usertype=="distributor")
		{
		
		//update credit
		$selectcountcredit12="select * from return_credit where usertype='$from_usertype' and userid='$from_userid'";
		$fetchcountcredit12=mysqli_query($db_conn,$selectcountcredit12);
		$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
		$creditamount=$resultcountcredit12['credit_amount']-$return_amount;
		
		$updatecredit="update return_credit set credit_amount='$creditamount' where usertype='$from_usertype' and userid='$from_userid'";
		mysqli_query($db_conn,$updatecredit);
		
		}
	}
		
		
		
	}
	
	$delRecord="delete from user_return_stock_items where id='$rowid_decode'";
	mysqli_query($db_conn,$delRecord);
	
	if($_REQUEST['redirurl']=="cnote_details")
	{
	echo "<script>window.location='cnote_details.php?returnid=$returnid&&DeleteSuccess';</script>";
		
	}else{
	
	echo "<script>window.location='cnote_new.php?returnid=$returnid&&InvoiceID=".$_REQUEST['InvoiceID']."&&DeleteSuccess';</script>";
	}

?>