<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
include("config.php");
require_once("include/StockService.php");
error_reporting(0);

$Roowid=base64_decode($_REQUEST['id']);
$tempid=$_REQUEST['tempid'];

$select_count_product="select * from ot_sales_return where id=?";
	$fetch_count_product=db_q($db_conn,$select_count_product,[$Roowid]);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	
	$product_id=$result_count_product['prid'];
	$input_qty=$result_count_product['qty'];
	$godownid=$result_count_product['godownid'];

	// Debit the SAME warehouse this return actually credited — stored on
	// the return row itself (ot_sales_return.warehouse_id, set from the
	// Godown picker on ot-sale-return.php) wherever present, since that
	// picker lets a return be credited to a different warehouse than the
	// item was originally sold from. Older rows created before that picker
	// existed have no warehouse_id of their own — for those, fall back to
	// the original sale's warehouse (the old behaviour), since that's where
	// their credit actually landed.
	if ($result_count_product['warehouse_id'] !== null) {
		$warehouseId = (int)$result_count_product['warehouse_id'];
	} else {
		$warehouseId = null;
		$stmtWh = $db_conn->prepare("SELECT warehouse_id FROM ot_sales WHERE tempid = ? AND prid = ? LIMIT 1");
		$retTempid = (string)$result_count_product['tempid'];
		$retPrid   = (int)$product_id;
		$stmtWh->bind_param('si', $retTempid, $retPrid);
		$stmtWh->execute();
		$rowWh = $stmtWh->get_result()->fetch_assoc();
		$stmtWh->close();
		if ($rowWh && $rowWh['warehouse_id'] !== null) { $warehouseId = (int)$rowWh['warehouse_id']; }
	}

	if($product_id!=NULL)
	{
		// Undo the return via its own ledger action (not otDeduct) — same
		// stock math, but keeps this out of "Sales Qty" reporting since no
		// real sale happened; it only cancels the earlier "Return Qty".
		try {
			$stockService = new StockService($db_conn);
			$stockService->otReverseUndo(
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
	
$del_product="delete from ot_sales_return where id=?";
db_q($db_conn,$del_product,[$Roowid]);

echo "<script>window.location='ot-sale-return?deletedDone&&tempid=$tempid';</script>";
?>