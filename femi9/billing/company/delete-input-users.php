<?php include("checksession.php"); require_once("include/StockService.php"); error_reporting(0);

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
		//update stock — via StockService, scoped to the same unassigned row
		//this always used (this table has no warehouse_id column).
		try {
			$stockService = new StockService($db_conn);
			$stockService->reverseCredit(
				(int)$product_id, $user_type_Loginvl, $user_id_Loginvl, (int)$input_qty,
				'input_stock_users', (string)$Roowid, $user_id_Loginvl
			);
		} catch (\Throwable $e) {
			error_log("delete-input-users stock reversal error: " . $e->getMessage());
		}

	}
	
$del_product="delete from input_stock_users where id='$Roowid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='manage-input-users?deletedDone';</script>";
exit;
?>