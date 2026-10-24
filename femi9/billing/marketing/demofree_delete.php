<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

$Roowid=$_REQUEST['Roowid'];
$Roowid=base64_decode($Roowid);

$tempid=$_REQUEST['tempid'];

$select_count_product="select * from demofreedamage where id=?";
	$fetch_count_product=db_q($db_conn,$select_count_product,[$Roowid]);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	
	$product_id=$result_count_product['product_id'];
	$qty_value=$result_count_product['qty'];
	
	$usertype=$result_count_product['usertype'];
	$userid=$result_count_product['userid'];
	
	if($product_id!=NULL)
	{
		//STOCK INCREMENT - FROM COMPANY
		$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$product_id,$usertype,$userid]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['sent_qty']-$qty_value;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty_value;
		
		$update_stockDetails="update stock set sent_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_Input_stock,$update_Closing_stock,$product_id,$usertype,$userid]);
		
	}
	
$del_product="delete from demofreedamage where id=?";
db_q($db_conn,$del_product,[$Roowid]);

$_SESSION['sucMessage']="One Product Details Deleted! (Demo/Free/Damage)";
echo "<script>window.location='demofree_details?deletedDone&&tempid=".urlencode($tempid)."';</script>";
?>