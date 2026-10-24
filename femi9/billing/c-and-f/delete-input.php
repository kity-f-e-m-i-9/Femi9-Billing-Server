<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); error_reporting(0);

$Roowid=$_REQUEST['Roowid'];
$Roowid=base64_decode($Roowid);

$select_count_product="select * from input_stock_users where id=?";
	$fetch_count_product=db_q($db_conn,$select_count_product,[$Roowid]);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	
	$product_id=$result_count_product['product_id'];
	$input_qty=$result_count_product['input_qty'];
	
	$user_type_Loginvl=$result_count_product['usertype'];
    $user_id_Loginvl=$result_count_product['userid'];
	
	if($product_id!=NULL)
	{
		//update stock
		$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$product_id,$user_type_Loginvl,$user_id_Loginvl]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['input_qty']-$input_qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$input_qty;
		
		$update_stockDetails="update stock set input_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_Input_stock,$update_Closing_stock,$product_id,$user_type_Loginvl,$user_id_Loginvl]);
		
		
	}
	
$del_product="delete from input_stock_users where id=?";
db_q($db_conn,$del_product,[$Roowid]);

echo "<script>window.location='manage-input?deletedDone';</script>";
?>