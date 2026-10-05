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

	// The return credited the warehouse the item was originally sold from
	// (ot-sale-return.php), so the undo must debit that same bucket.
	$warehouseId=null;
	$stmtWh=$db_conn->prepare("SELECT warehouse_id FROM ot_sales WHERE tempid = ? AND prid = ? LIMIT 1");
	$retTempid=(string)$result_count_product['tempid'];
	$retPrid=(int)$product_id;
	$stmtWh->bind_param('si', $retTempid, $retPrid);
	$stmtWh->execute();
	$rowWh=$stmtWh->get_result()->fetch_assoc();
	$stmtWh->close();
	if($rowWh && $rowWh['warehouse_id']!==null){ $warehouseId=(int)$rowWh['warehouse_id']; }
	
	if($product_id!=NULL)
	{
		//update stock — via StockService, scoped to the original sale's warehouse.
		try {
			$stockService = new StockService($db_conn);
			$stockService->otDeduct(
				(int)$product_id, $Login_user_TYPEvl, $godownid, (int)$input_qty,
				(string)$Roowid, $Login_user_IDvl ?? $godownid,
				false, $warehouseId
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