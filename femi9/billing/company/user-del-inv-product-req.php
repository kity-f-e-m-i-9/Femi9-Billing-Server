<?php include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if(isset($_REQUEST['inv_id']))
{
	$invoice_id_encode=$_REQUEST['inv_id'];
	$invuser=$_REQUEST['invuser'];
	$customer_id=$_REQUEST['userid'];
	
	$rowid_encode=$_REQUEST['rowid'];
	$rowid_decode=base64_decode($rowid_encode);
	
	//
	$select_INVProductDetails="select * from user_invoice_items where id='$rowid_decode'";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	if($result_INVProductDetails['pr_id']!=NULL)
	{
	
		$pr_id=$result_INVProductDetails['pr_id'];
		$qty=$result_INVProductDetails['qty'];
		
		$Login_user_IDvl=$result_INVProductDetails['from_user_id'];

		//------------------------------------------------------------------
		//Reverse the original deductAndCredit: restore company stock, pull
		//back the buyer's — same unassigned rows both sides always used,
		//via StockService instead of two independent warehouse_id-less
		//UPDATEs that could stomp another warehouse row for this product.
		//------------------------------------------------------------------
		try {
			$stockService = new StockService($db_conn);
			$db_conn->begin_transaction();
			$stockService->reverseDeduct(
				(int)$pr_id, $Login_user_TYPEvl, $Login_user_IDvl, (int)$qty,
				'user_invoice', $invoice_id_encode, $Login_user_IDvl, true
			);
			if (in_array($invuser, StockService::STOCK_MAINTAINING_TYPES, true)) {
				$stockService->reverseCredit(
					(int)$pr_id, $invuser, $customer_id, (int)$qty,
					'user_invoice', $invoice_id_encode, $Login_user_IDvl, true
				);
			}
			$db_conn->commit();
		} catch (\Throwable $e) {
			$db_conn->rollback();
			error_log("user-del-inv-product-req stock reversal error: " . $e->getMessage());
		}

	}
	
	$delRecord="delete from user_invoice_items where id='$rowid_decode'";
	mysqli_query($db_conn,$delRecord);
	
	echo "<script>window.location='stock_request_details?reqid=".$invoice_id_encode."&&DeleteSuccess&&invuser=".$invuser."&&ActionRemove&&gid=".$Login_user_IDvl."';</script>";
	
}
?>