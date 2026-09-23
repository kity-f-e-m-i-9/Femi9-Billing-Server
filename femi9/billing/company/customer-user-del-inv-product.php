<?php include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if(isset($_REQUEST['inv_id']))
{
	$invoice_id_encode = $_REQUEST['inv_id'];
	$customer_id = $_REQUEST['userid'];

	$rowid_encode = $_REQUEST['rowid'];
	$rowid_decode = base64_decode($rowid_encode);
	$inv_id = base64_decode($invoice_id_encode);

	$stmt_item = $db_conn->prepare("SELECT * FROM invoice_items WHERE id = ?");
	$stmt_item->bind_param("i", $rowid_decode);
	$stmt_item->execute();
	$result_INVProductDetails = $stmt_item->get_result()->fetch_assoc();
	$stmt_item->close();

	$Login_user_IDvl = $result_INVProductDetails['user_id'] ?? '';

	if ($result_INVProductDetails && $result_INVProductDetails['pr_id'] != NULL)
	{
		$pr_id = $result_INVProductDetails['pr_id'];
		$qty = $result_INVProductDetails['qty'];
		$user_type = $result_INVProductDetails['user_type'];
		$user_id = $result_INVProductDetails['user_id'];

		// Which physical warehouse the seller-side deduction came from — same
		// column invoice-stock-update.php reads at creation time. Reading it
		// here keeps this reversal scoped to the exact row that was actually
		// deducted.
		$stmt_wh = $db_conn->prepare("SELECT warehouse_id FROM invoice WHERE inv_id = ? LIMIT 1");
		$stmt_wh->bind_param("s", $inv_id);
		$stmt_wh->execute();
		$whRow = $stmt_wh->get_result()->fetch_assoc();
		$stmt_wh->close();
		$warehouseId = ($whRow && $whRow['warehouse_id'] !== null) ? (int) $whRow['warehouse_id'] : null;

		$db_conn->begin_transaction();
		try {
			$stmtDel = $db_conn->prepare("DELETE FROM invoice_items WHERE id = ?");
			$stmtDel->bind_param("i", $rowid_decode);
			$stmtDel->execute();
			$stmtDel->close();

			// Reverse stock only if it was already applied (ledger guard
			// prevents double-restore on a still-draft invoice, where the
			// Submit step never ran and no stock was ever deducted) — same
			// guard used for the SS/S/SD/D and shop invoice item-delete paths.
			$stockService = new StockService($db_conn);
			$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

			if ($stockService->hasLedgerEntry('invoice', $inv_id)) {
				// Restore seller stock: closing_qty ↑, sales_qty ↓ — scoped
				// to the same warehouse the original deduction targeted
				// (only applies when the seller is company; other seller
				// types have no warehouse concept). A customer buyer never
				// maintains its own stock ledger, so there is no buyer-side
				// reversal here (unlike the SS/shop invoice item-delete flows).
				$stockService->reverseDeduct(
					$pr_id, $user_type, $user_id, $qty,
					'invoice', $inv_id, $createdBy,
					true, // externalTransaction
					$user_type === 'company' ? $warehouseId : null
				);
			}

			$db_conn->commit();
		} catch (\Throwable $e) {
			$db_conn->rollback();
			error_log("customer-user-del-inv-product.php error: " . $e->getMessage());
			$_SESSION['errorMessage'] = "Failed to remove item. Please try again.";
			echo "<script>window.location='customer-user-invoice-add?InvoiceID=".$invoice_id_encode."&&gid=".$Login_user_IDvl."';</script>";
			exit;
		}
	}
	else
	{
		// Row not found — delete defensively without stock reversal
		$stmtDel = $db_conn->prepare("DELETE FROM invoice_items WHERE id = ?");
		$stmtDel->bind_param("i", $rowid_decode);
		$stmtDel->execute();
		$stmtDel->close();
	}

	echo "<script>window.location='customer-user-invoice-add?InvoiceID=".$invoice_id_encode."&&DeleteSuccess&&ActionRemove&&gid=".$Login_user_IDvl."';</script>";

}
?>
