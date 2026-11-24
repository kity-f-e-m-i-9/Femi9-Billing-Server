<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('shop');
include("config.php");
require_once("include/StockService.php");
error_reporting(0);

if(isset($_REQUEST['invoice-submit']))
{
	
	$invoice_id=$_REQUEST['invoice_id'];


//get invoice details
	$select_invoicedtails="select * from user_invoice where inv_id=?";
	$fetch_invoicedtails=db_q($db_conn,$select_invoicedtails,[$invoice_id]);
	$result_invoicedetails=mysqli_fetch_array($fetch_invoicedtails);
	
	$Login_user_IDvl=$result_invoicedetails['from_user_id'];
	$Login_user_TYPEvl=$result_invoicedetails['from_user_type']; // Get seller type

	// A DM Get Order Shop Invoice that has already been fully released and
	// receipted (real StockService::deduct() already ran via Auto Transfer's
	// release_shop_invoices_from_existing_stock()) must NEVER be editable —
	// same "nothing left to edit" rule as the fulfilled_by_cp_id guard below.
	// Without this, Edit's unconditional "delete from receipt" a few lines
	// down, followed by the source_ms_order_id draft-stash branch further
	// below, deletes the real receipt and flips the invoice back to
	// "Invoiced — Stock Pending" even though the stock already moved —
	// only self-healing on the next unrelated Release click. Checked via
	// receipt existence (the same "already resolved" signal
	// AutoTransferDemand.php's own demand query uses), not just
	// source_ms_order_id being set, so a still-draft DM-order invoice (no
	// receipt yet) stays editable as before. 2026-11-21, code-review finding.
	if ($_SESSION['ACTIONEDIT'] === 'edit' && !empty($result_invoicedetails['source_ms_order_id'])) {
		$chkReceipt = db_q($db_conn, "select count(*) as numreceipt from receipt where receiptid=?", [$invoice_id]);
		$rowReceipt = mysqli_fetch_array($chkReceipt);
		if (!empty($rowReceipt['numreceipt'])) {
			unset($_SESSION['ACTIONEDIT']);
			$_SESSION['errorMessage'] = "This invoice's stock has already been released and receipted — it can't be edited. Use Return (Credit Note) instead to correct it.";
			echo "<script>window.location='shop-user-manage-invoice';</script>";
			exit;
		}
	}

	// A CP-fulfilled DM-order invoice (fulfilled_by_cp_id set) already deducted
	// the CP's own channel_partner_stock at creation and is immutable by design
	// (same "nothing left to edit" rule ms-order-invoice-add.php and
	// shop-user-invoice-add.php's Convert Invoice button already follow). Moved
	// here (was previously after the header UPDATE below) — that UPDATE ran
	// unconditionally BEFORE this guard rejected the request, so opening Edit
	// on a CP-fulfilled invoice and submitting changed amounts silently wrote
	// the new sub_total/discount/total/roundoff/courier_charges into
	// user_invoice THEN showed "can't be edited, use Credit Note instead" —
	// falsely implying nothing was changed while the invoice's own totals no
	// longer matched what was actually deducted at creation. 2026-11-21,
	// code-review finding.
	if (!empty($result_invoicedetails['fulfilled_by_cp_id'])) {
		unset($_SESSION['ACTIONEDIT']);
		$_SESSION['errorMessage'] = "This invoice was fulfilled by a Channel Partner's own stock and can't be edited. Use Return (Credit Note) instead to correct it.";
		echo "<script>window.location='shop-user-manage-invoice';</script>";
		exit;
	}

///UPDATE INVOCIE - IF EDIT INVOICE ACTIO ONLY
if($_REQUEST['update_invoice_date']!=NULL)
{
$update_invoice_date=date("Y-m-d",strtotime($_REQUEST['update_invoice_date']));

//1
$update_invoice12="update user_invoice set date=? where inv_id=? and from_user_type=? and from_user_id=?";
db_q($db_conn,$update_invoice12,[$update_invoice_date,$invoice_id,$Login_user_TYPEvl,$Login_user_IDvl]);

//2	
$update_invoice_ITEMS12="update user_invoice_items set date=? where inv_id=? and from_user_type=? and from_user_id=?";
db_q($db_conn,$update_invoice_ITEMS12,[$update_invoice_date,$invoice_id,$Login_user_TYPEvl,$Login_user_IDvl]);

	}
//--------------------------------------------

	
	//DELETE RECEIPT DETAILS IF EDIT FUNCTION
	if($_SESSION['ACTIONEDIT']=="edit"){
		
		$delReceipt="delete from receipt where inv_id=?";
		db_q($db_conn,$delReceipt,[$invoice_id]);
	}

	
	
	$SubTotal=$_REQUEST['SubTotal'];
	if($_REQUEST['discount']!=NULL){ $discount=$_REQUEST['discount'];}else{$discount="0";}
	$roundoff=$_REQUEST['roundoff'];
	$courier_charges=$_REQUEST['courier_charges'];
	
	$total_amount=$SubTotal-$discount+$courier_charges;
	$total_amount=round($total_amount);
	
	if($_REQUEST['invoice_id']!=NULL)
	{
	$update_invoice="update user_invoice set sub_total=?,discount=?,total=?,roundoff=?,courier_charges=? where inv_id=? and from_user_type=? and from_user_id=?";
	db_q($db_conn,$update_invoice,[$SubTotal,$discount,$total_amount,$roundoff,$courier_charges,$invoice_id,$Login_user_TYPEvl,$Login_user_IDvl]);
	}
	
	$shopid=$result_invoicedetails['to_user_id'];
    $invdate=$result_invoicedetails['date'];

    // (fulfilled_by_cp_id guard moved above the header UPDATE — see the
    // comment there, 2026-11-21.)

    // ============================================================================
    // DM Get Order Shop Invoices — stock is released ONLY via Auto Transfer now,
    // never at Submit, even when the chosen warehouse already has enough stock.
    // User explicitly asked for this gate (2026-11-17) so every such invoice is
    // guaranteed to show up for staff review in Auto Transfer's Shop Invoices
    // list before its stock actually moves — matching the "Remove"/"Transfer"
    // workflow already used there for TP/CP POs. Regular (non-DM) Shop Invoices
    // are unaffected — they still deduct and receipt immediately below.
    // The payment fields the form still collects (receivedamount/receipt_method/
    // receipt_remarks) are stashed on user_invoice and used to build the real
    // receipt once Auto Transfer's record_auto_transfer_partial() (see
    // company/include/AutoTransferDemand.php) finishes transferring every line
    // of this invoice.
    // ============================================================================
    if (!empty($result_invoicedetails['source_ms_order_id'])) {
        $chkPendingCols = mysqli_query($db_conn, "SHOW COLUMNS FROM user_invoice LIKE 'pending_received_amount'");
        if ($chkPendingCols && mysqli_num_rows($chkPendingCols) === 0) {
            mysqli_query($db_conn, "ALTER TABLE user_invoice
                ADD COLUMN pending_received_amount DECIMAL(10,2) NULL DEFAULT NULL,
                ADD COLUMN pending_receipt_method VARCHAR(50) NULL DEFAULT NULL,
                ADD COLUMN pending_receipt_remarks VARCHAR(255) NULL DEFAULT NULL");
        }

        $pendingReceivedAmount = ($_REQUEST['receivedamount'] !== null && $_REQUEST['receivedamount'] !== '') ? (float)$_REQUEST['receivedamount'] : 0;
        $pendingReceiptMethod  = (string) ($_REQUEST['receipt_method'] ?? '');
        $pendingReceiptRemarks = str_replace("'", "&#39;", (string) ($_REQUEST['receipt_remarks'] ?? ''));

        $stmtStashPending = $db_conn->prepare(
            "UPDATE user_invoice SET pending_received_amount=?, pending_receipt_method=?, pending_receipt_remarks=? WHERE inv_id=?"
        );
        $stmtStashPending->bind_param('dsss', $pendingReceivedAmount, $pendingReceiptMethod, $pendingReceiptRemarks, $invoice_id);
        $stmtStashPending->execute();
        $stmtStashPending->close();

        unset($_SESSION['ACTIONEDIT']);
        $_SESSION['successMessage'] = "Invoice saved as Draft — stock has NOT moved yet. Select this order below and click \"Release Stock\" (or use Auto Transfer) to actually transfer the stock.";
        echo "<script>window.location='ms_prorders.php';</script>";
        exit;
    }

    // ============================================================================
    // STOCK UPDATE — reverse old stock on edit, then re-apply from current items
    // ============================================================================

    $is_edit_submission = (isset($_SESSION['ACTIONEDIT']) && $_SESSION['ACTIONEDIT'] === 'edit');
    $stockService = new StockService($db_conn);

    error_log("=== PROCESSING SHOP INVOICE STOCK UPDATES ===");
    error_log("Submission type: " . ($is_edit_submission ? 'EDIT (reverse + re-apply)' : 'NEW'));

    if ($is_edit_submission) {
        try {
            $reversed = $stockService->reverseAll('user_invoice', $invoice_id, $Login_user_IDvl);
            error_log("SHOP EDIT: reversed $reversed ledger entries for invoice $invoice_id");
        } catch (\Throwable $e) {
            error_log("SHOP EDIT: reversal warning (non-blocking): " . $e->getMessage());
        }
    }

    $stockUpdateFailed = false;
    try {
        $is_customer_invoice = false;
        if (!defined('INVOICE_STOCK_UPDATE_INCLUDED')) {
            define('INVOICE_STOCK_UPDATE_INCLUDED', true);
        }
        include("invoice-stock-update.php");
        error_log("Shop invoice stock updates completed successfully");
    } catch (\Throwable $e) {
        $stockUpdateFailed = true;
        error_log("CRITICAL: Stock update failed for invoice $invoice_id — " . $e->getMessage());
        error_log("Action Required: Manual stock adjustment needed for invoice: $invoice_id");
        // Previously silent (logged only, then redirected to the print page
        // as if nothing went wrong) — the invoice looked fully submitted
        // while stock was never actually deducted. Surfaced to the user
        // instead so it's obvious something still needs fixing (e.g. the
        // chosen godown's stock hasn't arrived from Auto Transfer yet). The
        // invoice/item header rows above this block are still saved either
        // way — only the receipt (see below) and the stock write itself are
        // held back — so this sends the user back to fix stock and
        // re-submit, not a full rollback. 2026-11-13, receipt-timing note added 2026-11-17.
        $_SESSION['errorMessage'] = "Invoice saved, but stock could not be updated: " . $e->getMessage() . ". Please resolve the stock issue (e.g. wait for Auto Transfer or Transfer Now) and submit again.";
    }

    error_log("=== STOCK UPDATES COMPLETED ===");

    // ── Receipt — only recorded once stock actually moved ───────────────────
    // Previously inserted unconditionally BEFORE the stock update above, even
    // on failure. company/include/AutoTransferDemand.php's Shop Invoice demand
    // query treats "a receipt row exists for this inv_id" as "already
    // resolved, stop showing it as needing stock" — so a failed Submit left a
    // receipt behind while stock_ledger had zero entries for it, permanently
    // hiding a genuinely-still-unstocked invoice from Auto Transfer with no
    // way to surface it again. Moved here so the receipt (the actual
    // "resolved" signal) only gets written once the stock write that it's
    // supposed to represent has actually succeeded. 2026-11-17, user-reported.
    if (!$stockUpdateFailed) {
        $insertreceiptcount="select count(*) as numreceipt from receipt where receiptid=?";
        $fetchreceipt=db_q($db_conn,$insertreceiptcount,[$invoice_id]);
        $resultreceipt=mysqli_fetch_array($fetchreceipt);
        if($resultreceipt['numreceipt']==0)
        {
            $receiptdate=$result_invoicedetails['date'];
            if($_REQUEST['receivedamount']!=NULL){
            $receivedamount=$_REQUEST['receivedamount'];
            }else{$receivedamount="0";}
            $receivableamount=$total_amount-$receivedamount;
            $receivableamount=round($receivableamount);

            $usertype=$result_invoicedetails['to_user_type'];
            $userid=$result_invoicedetails['to_user_id'];

            $receipt_method=$_REQUEST['receipt_method'];
            $receipt_remarks=str_replace("'","&#39;",$_REQUEST['receipt_remarks']);

            $insertreceipt="insert into receipt (receiptid,inv_id,invoice_amount,received,receivable,date,from_user_type,from_user_id,
            to_user_type,to_user_id,receipt_method,receipt_remarks)
            values
            ('".db_esc($invoice_id)."','".db_esc($invoice_id)."','$total_amount','".db_esc($receivedamount)."','$receivableamount','$receiptdate',
            '".$result_invoicedetails['from_user_type']."','".$result_invoicedetails['from_user_id']."',
            '$usertype','$userid','".db_esc($receipt_method)."','$receipt_remarks')";
            mysqli_query($db_conn,$insertreceipt);
        }
    }

	// Clear edit session
	unset($_SESSION['ACTIONEDIT']);

	if ($stockUpdateFailed) {
	    echo "<script>window.location='shop-user-invoice-add?invuser=shop&action=edit&InvoiceID=".base64_encode($invoice_id)."&gid=".urlencode((string)$result_invoicedetails['from_user_id'])."';</script>";
	} else {
	    echo "<script>window.location='shop-user-invoice-print?invoiceid=".base64_encode($invoice_id)."';</script>";
	}
}
?>