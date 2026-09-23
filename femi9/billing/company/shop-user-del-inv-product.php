<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (isset($_REQUEST['inv_id']))
{
	$invoice_id_encode = $_REQUEST['inv_id'];
	$invuser = $_REQUEST['invuser'];
	$customer_id = $_REQUEST['userid'];

	$rowid_encode = $_REQUEST['rowid'];
	$rowid_decode = base64_decode($rowid_encode);
	$inv_id = base64_decode($invoice_id_encode);

	$stmt_item = $db_conn->prepare("SELECT * FROM user_invoice_items WHERE id = ?");
	$stmt_item->bind_param("i", $rowid_decode);
	$stmt_item->execute();
	$result_INVProductDetails = $stmt_item->get_result()->fetch_assoc();
	$stmt_item->close();

	$Login_user_IDvl = $customer_id;

	if ($result_INVProductDetails && $result_INVProductDetails['pr_id'] != NULL)
	{
		$pr_id = $result_INVProductDetails['pr_id'];
		$qty = $result_INVProductDetails['qty'];
		$from_user_type = $result_INVProductDetails['from_user_type'];
		$from_user_id = $result_INVProductDetails['from_user_id'];
		$to_user_type = $result_INVProductDetails['to_user_type'];
		$to_user_id = $result_INVProductDetails['to_user_id'];

		$Login_user_IDvl = $from_user_id;

		// Which physical warehouse the seller-side deduction came from — same
		// column invoice-stock-update.php reads at creation time. Reading it
		// here keeps this reversal scoped to the exact row that was actually
		// deducted.
		$stmt_wh = $db_conn->prepare("SELECT warehouse_id FROM user_invoice WHERE inv_id = ? LIMIT 1");
		$stmt_wh->bind_param("s", $inv_id);
		$stmt_wh->execute();
		$whRow = $stmt_wh->get_result()->fetch_assoc();
		$stmt_wh->close();
		$warehouseId = ($whRow && $whRow['warehouse_id'] !== null) ? (int) $whRow['warehouse_id'] : null;

		$db_conn->begin_transaction();
		try {
			$stmtDel = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
			$stmtDel->bind_param("i", $rowid_decode);
			$stmtDel->execute();
			$stmtDel->close();

			// Reverse stock only if it was already applied (ledger guard
			// prevents double-restore on a still-draft invoice, where the
			// Submit step never ran and no stock was ever deducted) — same
			// guard user-invoice-item-delete.php uses for the non-shop path.
			$stockService = new StockService($db_conn);
			$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

			if ($stockService->hasLedgerEntry('user_invoice', $inv_id)) {
				// Restore seller stock: closing_qty ↑, sales_qty ↓ — scoped
				// to the same warehouse the original deduction targeted
				// (only applies when the seller is company; other seller
				// types have no warehouse concept).
				$stockService->reverseDeduct(
					$pr_id, $from_user_type, $from_user_id, $qty,
					'user_invoice', $inv_id, $createdBy,
					true, // externalTransaction
					$from_user_type === 'company' ? $warehouseId : null
				);

				// Remove buyer stock if buyer maintains inventory: closing_qty ↓, input_qty ↓
				if (in_array($to_user_type, StockService::STOCK_MAINTAINING_TYPES, true)) {
					$stockService->reverseCredit(
						$pr_id, $to_user_type, $to_user_id, $qty,
						'user_invoice', $inv_id, $createdBy,
						true // externalTransaction
					);
				}
			}

			$db_conn->commit();
		} catch (\Throwable $e) {
			$db_conn->rollback();
			error_log("shop-user-del-inv-product.php error: " . $e->getMessage());
			$_SESSION['errorMessage'] = "Failed to remove item. Please try again.";
			echo "<script>window.location='shop-user-invoice-add?InvoiceID=".$invoice_id_encode."&&invuser=".$invuser."&&action=".$_SESSION['ACTIONEDIT']."&&gid=".$Login_user_IDvl."';</script>";
			exit;
		}
	}
	else
	{
		// Row not found — delete defensively without stock reversal
		$stmtDel = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
		$stmtDel->bind_param("i", $rowid_decode);
		$stmtDel->execute();
		$stmtDel->close();
	}

	echo "<script>window.location='shop-user-invoice-add?InvoiceID=".$invoice_id_encode."&&DeleteSuccess&&invuser=".$invuser."&&ActionRemove&&action=".$_SESSION['ACTIONEDIT']."&&gid=".$Login_user_IDvl."';</script>";

}
?>
