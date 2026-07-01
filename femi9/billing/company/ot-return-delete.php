<?php include("checksession.php");
include("config.php");
error_reporting(0);

$Roowid=base64_decode($_REQUEST['id']);
$tempid=$_REQUEST['tempid'];

$select_count_product="select * from ot_sales_return where id='$Roowid'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	
	$product_id=$result_count_product['prid'];
	$input_qty=$result_count_product['qty'];
	$godownid=$result_count_product['godownid'];
	
	if($product_id!=NULL)
	{
		//update stock
		$select_stockDetails="select * from stock where product_id='$product_id' and user_type='$Login_user_TYPEvl' and user_id='$godownid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['sales_qty']+$input_qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$input_qty;
		
		$update_stockDetails="update stock set sales_qty='$update_Input_stock',closing_qty='$update_Closing_stock' where product_id='$product_id' and user_type='$Login_user_TYPEvl' and user_id='$godownid'";
		mysqli_query($db_conn,$update_stockDetails);
		
		
	}
	
$del_product="delete from ot_sales_return where id='$Roowid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='ot-sale-return?deletedDone&&tempid=$tempid';</script>";
?>