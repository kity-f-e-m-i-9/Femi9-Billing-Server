<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);

$rowid=$_REQUEST['rowid'];
$rowid_decode=base64_decode($rowid);

//get return details
$select_Returndetails="select * from user_return_stock where returnid=?";
$fetch_Returndetails=db_q($db_conn,$select_Returndetails,[$returnid_decode]);
$result_Returndetails=mysqli_fetch_array($fetch_Returndetails);

$fromusertype=$result_Returndetails['from_usertype'];
$fromuserid=$result_Returndetails['from_userid'];

$invnumber=$result_Returndetails['invnumber'];
$invnumber_encode=base64_encode($invnumber);	
	
	$select_INVProductDetails="select * from user_return_stock_items where id=?";
	$fetch_INVProductDetails=db_q($db_conn,$select_INVProductDetails,[$rowid_decode]);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	if($result_INVProductDetails['prid']!=NULL)
	{
	
		$prid=$result_INVProductDetails['prid'];
		$qty=$result_INVProductDetails['qty'];
		
		//------------------------------
		//2. stock increment to company
		//------------------------------
		$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$prid,$fromusertype,$fromuserid]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['returnqty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set returnqty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_returnqty,$update_Closing_stock,$prid,$fromusertype,$fromuserid]);
		
	}
	
	$delRecord="delete from user_return_stock_items where id=?";
	db_q($db_conn,$delRecord,[$rowid_decode]);
	
	echo "<script>window.location='stock_return_add2.php?returnid=".urlencode($returnid)."&&invnumber=$invnumber_encode&&DeleteSuccess';</script>";

?>