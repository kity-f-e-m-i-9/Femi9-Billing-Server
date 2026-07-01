<?php include("checksession.php");
include("config.php");
error_reporting(0);

$Roowid=$_REQUEST['Roowid'];
$Roowid=base64_decode($Roowid);

$tempid=$_REQUEST['tempid'];

$select_count_product="select * from demofreedamage where id='$Roowid'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	
	$product_id=$result_count_product['product_id'];
	$qty_value=$result_count_product['qty'];
	
	$usertype=$result_count_product['usertype'];
	$userid=$result_count_product['userid'];
	
	if($product_id!=NULL)
	{
		//STOCK INCREMENT - FROM COMPANY
		$select_stockDetails="select * from stock where product_id='$product_id' and user_type='$usertype' and user_id='$userid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['sent_qty']-$qty_value;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty_value;
		
		$update_stockDetails="update stock set sent_qty='$update_Input_stock',closing_qty='$update_Closing_stock' where product_id='$product_id' and user_type='$usertype' and user_id='$userid'";
		mysqli_query($db_conn,$update_stockDetails);
		
	}
	
$del_product="delete from demofreedamage where id='$Roowid'";
mysqli_query($db_conn,$del_product);

$_SESSION['sucMessage']="One Product Details Deleted! (Demo/Free/Damage)";
echo "<script>window.location='demofree_details?deletedDone&&tempid=$tempid';</script>";
?>