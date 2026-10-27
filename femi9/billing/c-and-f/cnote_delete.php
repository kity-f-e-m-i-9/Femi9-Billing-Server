<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);

$rowid=$_REQUEST['rowid'];
$rowid_decode=base64_decode($rowid);

$select_Returndetails="select * from user_return_stock where returnid=?";
$fetch_Returndetails=db_q($db_conn,$select_Returndetails,[$returnid_decode]);
$result_Returndetails=mysqli_fetch_array($fetch_Returndetails);

$from_usertype=$result_Returndetails['from_usertype']; //ss, stockist, distributor, shop, customer
$from_userid=$result_Returndetails['from_userid'];	

$to_usertype=$result_Returndetails['to_usertype'];
$to_userid=$result_Returndetails['to_userid'];	

	$select_INVProductDetails="select * from user_return_stock_items where id=?";
	$fetch_INVProductDetails=db_q($db_conn,$select_INVProductDetails,[$rowid_decode]);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	if($result_INVProductDetails['prid']!=NULL)
	{
	
		$prid=$result_INVProductDetails['prid'];
		$returnqty=$result_INVProductDetails['qty'];
		$return_amount=$result_INVProductDetails['total'];
		
		//--------------------------------------------------
		//STOCK DECREMENT TO RECEIVED USERS (billed by users)
		$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$prid,$to_usertype,$to_userid]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['sales_qty']+$returnqty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$returnqty;
		
		$update_stockDetails="update stock set sales_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_returnqty,$update_Closing_stock,$prid,$to_usertype,$to_userid]);
		
		
		if($from_usertype=="super_stockiest" || $from_usertype=="stockiest" || $from_usertype=="super_distributor" || $from_usertype=="distributor")
		{
			
		//STOCK INCREMENT TO SENT USERS (billed to users)
		$select_stockDetails12="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails12=db_q($db_conn,$select_stockDetails12,[$prid,$from_usertype,$from_userid]);
		$result_stockDetails12=mysqli_fetch_array($fetch_stockDetails12);
		
		$update_returnqty12=$result_stockDetails12['input_qty']+$returnqty;
		$update_Closing_stock12=$result_stockDetails12['closing_qty']+$returnqty;
		
		$update_stockDetails12="update stock set input_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails12,[$update_returnqty12,$update_Closing_stock12,$prid,$from_usertype,$from_userid]);
		
		}
		
		//---------------------------------------------
		
		
		if($_REQUEST['redirurl']=="cnote_details")
	{
		
		
		if($from_usertype=="super_stockiest" || $from_usertype=="stockiest" || $from_usertype=="super_distributor" || $from_usertype=="distributor")
		{
		
		//update credit
		$selectcountcredit12="select * from return_credit where usertype=? and userid=?";
		$fetchcountcredit12=db_q($db_conn,$selectcountcredit12,[$from_usertype,$from_userid]);
		$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
		$creditamount=$resultcountcredit12['credit_amount']-$return_amount;
		
		$updatecredit="update return_credit set credit_amount=? where usertype=? and userid=?";
		db_q($db_conn,$updatecredit,[$creditamount,$from_usertype,$from_userid]);
		
		}
	}
		
		
		
	}
	
	$delRecord="delete from user_return_stock_items where id=?";
	db_q($db_conn,$delRecord,[$rowid_decode]);
	
	if($_REQUEST['redirurl']=="cnote_details")
	{
	echo "<script>window.location='cnote_details.php?returnid=".urlencode($returnid)."&&DeleteSuccess';</script>";
		
	}else{
	
	echo "<script>window.location='cnote_new.php?returnid=".urlencode($returnid)."&&InvoiceID=".urlencode($_REQUEST['InvoiceID'])."&&DeleteSuccess';</script>";
	}

?>