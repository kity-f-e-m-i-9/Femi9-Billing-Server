<?php include("checksession.php");
include("config.php");
require_once("include/StockService.php");
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
		//update stock — via StockService, scoped to the same unassigned row
		//this form has always used (no warehouse picker here).
		try {
			$stockService = new StockService($db_conn);
			$stockService->otDeduct(
				(int)$product_id, $Login_user_TYPEvl, $godownid, (int)$input_qty,
				(string)$Roowid, $Login_user_IDvl ?? $godownid
			);
		} catch (StockException $e) {
			$_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
			echo "<script>window.location='ot-sale-return?stockerror&&tempid=$tempid';</script>";
			exit;
		}
	}
	
$del_product="delete from ot_sales_return where id='$Roowid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='ot-sale-return?deletedDone&&tempid=$tempid';</script>";
?>