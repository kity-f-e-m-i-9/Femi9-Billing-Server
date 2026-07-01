<?php include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);

$select_Returndetails="select * from user_return_stock where returnid='$returnid_decode'";
$fetch_Returndetails=mysqli_query($db_conn,$select_Returndetails);
$result_Returndetails=mysqli_fetch_array($fetch_Returndetails);
$total_amount=$result_Returndetails['total'];

$fromusertype=$result_Returndetails['from_usertype'];
$fromuserid=$result_Returndetails['from_userid'];

$invnumber=$result_Returndetails['invnumber'];

//increment stock
$selectitems="select * from user_return_stock_items where returnid='$returnid_decode'";
$fetchitems=mysqli_query($db_conn,$selectitems);
while($resultitems=mysqli_fetch_array($fetchitems))
{
	
	$prid=$resultitems['prid'];
	$qty=$resultitems['qty'];
	
	
	$select_stockDetails="select * from stock where product_id='$prid' and user_type='$fromusertype' and user_id='$fromuserid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['returnqty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set returnqty='$update_returnqty',closing_qty='$update_Closing_stock' where product_id='$prid' and user_type='$fromusertype' and user_id='$fromuserid'";
		mysqli_query($db_conn,$update_stockDetails);
	
}


$deleteitem="delete from user_return_stock_items where returnid='$returnid_decode'";
mysqli_query($db_conn,$deleteitem);


if($invnumber!=NULL)
{
//decrement credit amount
$selectcountcredit12="select * from return_credit where usertype='$fromusertype' and userid='$fromuserid'";
		$fetchcountcredit12=mysqli_query($db_conn,$selectcountcredit12);
		$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
		$creditamount=$resultcountcredit12['credit_amount']-$total_amount;
		
		$updatecredit="update return_credit set credit_amount='$creditamount' where usertype='$fromusertype' and userid='$fromuserid'";
		mysqli_query($db_conn,$updatecredit);
}

$deleteitem12="delete from user_return_stock where returnid='$returnid_decode'";
mysqli_query($db_conn,$deleteitem12);

echo "<script>window.location='stock-return-manage.php?deletedone';</script>";

?>