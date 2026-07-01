<?php include("checksession.php"); error_reporting(0);

$Roowid=$_REQUEST['Roowid'];
$Roowid=base64_decode($Roowid);

$select_count_product="select * from input_stock_users where id='$Roowid'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	
	$product_id=$result_count_product['product_id'];
	$input_qty=$result_count_product['input_qty'];
	
	$user_type_Loginvl=$result_count_product['usertype'];
    $user_id_Loginvl=$result_count_product['userid'];
	
	if($product_id!=NULL)
	{
		//update stock
		$select_stockDetails="select * from stock where product_id='$product_id' 
		and user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['input_qty']-$input_qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$input_qty;
		
		$update_stockDetails="update stock set input_qty='$update_Input_stock',
		closing_qty='$update_Closing_stock' where product_id='$product_id' 
		and user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
		mysqli_query($db_conn,$update_stockDetails);
		
	}
	
$del_product="delete from input_stock_users where id='$Roowid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='manage-input-users?deletedDone';</script>";
exit;
?>