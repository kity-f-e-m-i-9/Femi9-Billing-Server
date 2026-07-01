<?php include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);

$rowid=$_REQUEST['rowid'];
$rowid_decode=base64_decode($rowid);

//get return details
$select_Returndetails="select * from user_return_stock where returnid='$returnid_decode'";
$fetch_Returndetails=mysqli_query($db_conn,$select_Returndetails);
$result_Returndetails=mysqli_fetch_array($fetch_Returndetails);

$fromusertype=$result_Returndetails['from_usertype'];
$fromuserid=$result_Returndetails['from_userid'];

$invnumber=$result_Returndetails['invnumber'];
$invnumber_encode=base64_encode($invnumber);	
	
	$select_INVProductDetails="select * from user_return_stock_items where id='$rowid_decode'";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	if($result_INVProductDetails['prid']!=NULL)
	{
	
		$prid=$result_INVProductDetails['prid'];
		$qty=$result_INVProductDetails['qty'];
		
		//------------------------------
		//2. stock increment to company
		//------------------------------
		$select_stockDetails="select * from stock where product_id='$prid' and user_type='$fromusertype' and user_id='$fromuserid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['returnqty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set returnqty='$update_returnqty',closing_qty='$update_Closing_stock' where product_id='$prid' and user_type='$fromusertype' and user_id='$fromuserid'";
		mysqli_query($db_conn,$update_stockDetails);
		
	}
	
	$delRecord="delete from user_return_stock_items where id='$rowid_decode'";
	mysqli_query($db_conn,$delRecord);
	
	echo "<script>window.location='stock_return_add2.php?returnid=$returnid&&invnumber=$invnumber_encode&&DeleteSuccess';</script>";

?>