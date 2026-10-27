<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

$Roowid=$_REQUEST['rowid'];
$Roowid=base64_decode($Roowid);

	$select_count_product="select * from internal_transfer_ss where id=?";
	$fetch_count_product=db_q($db_conn,$select_count_product,[$Roowid]);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	
	$prid=$result_count_product['prid'];
	$qty=$result_count_product['qty'];
	
	$from_usertype=$result_count_product['from_usertype'];
	$from_userid=$result_count_product['from_userid'];
	
	$to_usertype=$result_count_product['to_usertype'];
	$to_userid=$result_count_product['to_userid'];
	
	if($prid!=NULL)
	{
		
		//----------------------------------------------------------------------
		//----------------------------------------------------------------------
		//STOCK INCREMENT - FROM SUPER STOCKIST
		$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$prid,$from_usertype,$from_userid]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['sent_qty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set sent_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_Input_stock,$update_Closing_stock,$prid,$from_usertype,$from_userid]);
		
		
		//STOCK DECREMENT - TO STOCKIST
		$select_stockDetails2="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails2=db_q($db_conn,$select_stockDetails2,[$prid,$to_usertype,$to_userid]);
		$result_stockDetails2=mysqli_fetch_array($fetch_stockDetails2);
		
		$update_Input_stock2=$result_stockDetails2['input_qty']-$qty;
		$update_Closing_stock2=$result_stockDetails2['closing_qty']-$qty;
		
		$update_stockDetails2="update stock set input_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails2,[$update_Input_stock2,$update_Closing_stock2,$prid,$to_usertype,$to_userid]);
		//----------------------------------------------------------------------
		//----------------------------------------------------------------------
		
		
	}
	
$del_product="delete from internal_transfer_ss where id=?";
db_q($db_conn,$del_product,[$Roowid]);

$_SESSION['sucMessage']="One Internal Stock Transfer Details Deleted Successfully!";
echo "<script>window.location='manage_internal?deletedDone';</script>";

exit();
?>