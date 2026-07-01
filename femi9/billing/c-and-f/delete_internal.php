<?php include("checksession.php");
include("config.php");
error_reporting(0);

$Roowid=$_REQUEST['rowid'];
$Roowid=base64_decode($Roowid);

	$select_count_product="select * from internal_transfer_ss where id='$Roowid'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
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
		$select_stockDetails="select * from stock where product_id='$prid' and user_type='$from_usertype' and user_id='$from_userid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['sent_qty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set sent_qty='$update_Input_stock',closing_qty='$update_Closing_stock' where product_id='$prid' and user_type='$from_usertype' and user_id='$from_userid'";
		mysqli_query($db_conn,$update_stockDetails);
		
		
		//STOCK DECREMENT - TO STOCKIST
		$select_stockDetails2="select * from stock where product_id='$prid' and user_type='$to_usertype' and user_id='$to_userid'";
		$fetch_stockDetails2=mysqli_query($db_conn,$select_stockDetails2);
		$result_stockDetails2=mysqli_fetch_array($fetch_stockDetails2);
		
		$update_Input_stock2=$result_stockDetails2['input_qty']-$qty;
		$update_Closing_stock2=$result_stockDetails2['closing_qty']-$qty;
		
		$update_stockDetails2="update stock set input_qty='$update_Input_stock2',closing_qty='$update_Closing_stock2' where product_id='$prid' and user_type='$to_usertype' and user_id='$to_userid'";
		mysqli_query($db_conn,$update_stockDetails2);
		//----------------------------------------------------------------------
		//----------------------------------------------------------------------
		
		
	}
	
$del_product="delete from internal_transfer_ss where id='$Roowid'";
mysqli_query($db_conn,$del_product);

$_SESSION['sucMessage']="One Internal Stock Transfer Details Deleted Successfully!";
echo "<script>window.location='manage_internal?deletedDone';</script>";

exit();
?>