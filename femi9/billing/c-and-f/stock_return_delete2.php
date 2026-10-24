<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);

$select_Returndetails="select * from user_return_stock where returnid=?";
$fetch_Returndetails=db_q($db_conn,$select_Returndetails,[$returnid_decode]);
$result_Returndetails=mysqli_fetch_array($fetch_Returndetails);
$total_amount=$result_Returndetails['total'];

$fromusertype=$result_Returndetails['from_usertype'];
$fromuserid=$result_Returndetails['from_userid'];

$invnumber=$result_Returndetails['invnumber'];

//increment stock
$selectitems="select * from user_return_stock_items where returnid=?";
$fetchitems=db_q($db_conn,$selectitems,[$returnid_decode]);
while($resultitems=mysqli_fetch_array($fetchitems))
{
	
	$prid=$resultitems['prid'];
	$qty=$resultitems['qty'];
	
	
	$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$prid,$fromusertype,$fromuserid]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['returnqty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set returnqty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_returnqty,$update_Closing_stock,$prid,$fromusertype,$fromuserid]);
	
}


$deleteitem="delete from user_return_stock_items where returnid=?";
db_q($db_conn,$deleteitem,[$returnid_decode]);


if($invnumber!=NULL)
{
//decrement credit amount
$selectcountcredit12="select * from return_credit where usertype=? and userid=?";
		$fetchcountcredit12=db_q($db_conn,$selectcountcredit12,[$fromusertype,$fromuserid]);
		$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
		$creditamount=$resultcountcredit12['credit_amount']-$total_amount;
		
		$updatecredit="update return_credit set credit_amount=? where usertype=? and userid=?";
		db_q($db_conn,$updatecredit,[$creditamount,$fromusertype,$fromuserid]);
}

$deleteitem12="delete from user_return_stock where returnid=?";
db_q($db_conn,$deleteitem12,[$returnid_decode]);

echo "<script>window.location='stock-return-manage.php?deletedone';</script>";

?>