<?php require_once __DIR__ . '/../shared/db-helpers.php'; 
include("checksession.php"); 
include("config.php"); 
error_reporting(0);

// ✨ DAILY REWARD INTEGRATION
require_once 'include/invoice-reward-integration.php';

if (isset($_REQUEST['invoice-submit'])) {

    $invoice_id = $_REQUEST['invoice_id'] ?? '';

    if (empty($invoice_id)) {
        echo "<script>alert('Invalid invoice.');history.back();</script>";
        exit;
    }

    // =========================================================================
    // STEP 1: Fetch invoice details  (using $resultdetails consistently)
    // =========================================================================
    $selectdetails = "SELECT * FROM invoice WHERE inv_id=?";
    $fetchdetails  = db_q($db_conn,$selectdetails,[$invoice_id]);
    $resultdetails = mysqli_fetch_array($fetchdetails);

    if (!$resultdetails) {
        error_log("ERROR: Invoice not found: $invoice_id");
        echo "<script>alert('Invoice not found.');history.back();</script>";
        exit;
    }

    $Login_user_IDvl = $resultdetails['user_id'];

    // =========================================================================
    // STEP 2: Update invoice date (edit mode only)
    // =========================================================================
    if (!empty($_REQUEST['update_invoice_date'])) {
        $update_invoice_date = date("Y-m-d", strtotime($_REQUEST['update_invoice_date']));

        $update_invoice12 = "UPDATE invoice 
                             SET date=? 
                             WHERE inv_id=? 
                             AND user_type=? 
                             AND user_id=?";
        db_q($db_conn,$update_invoice12,[$update_invoice_date,$invoice_id,$Login_user_TYPEvl,$Login_user_IDvl]);

        $update_invoice_ITEMS12 = "UPDATE invoice_items 
                                   SET date=? 
                                   WHERE inv_id=? 
                                   AND user_type=? 
                                   AND user_id=?";
        db_q($db_conn,$update_invoice_ITEMS12,[$update_invoice_date,$invoice_id,$Login_user_TYPEvl,$Login_user_IDvl]);
    }

    // =========================================================================
    // STEP 3: Calculate totals
    // =========================================================================
    $SubTotal        = (float)($_REQUEST['sub_total']       ?? 0);
    $discount        = (float)($_REQUEST['discount']        ?? 0);
    $roundoff        = (float)($_REQUEST['roundoff']        ?? 0);
    $courier_charges = (float)($_REQUEST['courier_charges'] ?? 0);

    $total_amount = round($SubTotal - $discount + $courier_charges);

    // =========================================================================
    // STEP 4: Receipt — accumulate logic
    // =========================================================================

    // Safe date — always use invoice date, fallback to today
    $receiptdate = !empty($resultdetails['date']) ? $resultdetails['date'] : date('Y-m-d');

    $receivedamount  = (float)($_REQUEST['receivedamount']  ?? 0);
    $receipt_method  = $_REQUEST['receipt_method']          ?? '';
    $receipt_remarks = str_replace("'", "&#39;", $_REQUEST['receipt_remarks'] ?? '');

    // For customer invoices: from = logged-in user, to = customer
    $from_user_type = $resultdetails['user_type'];
    $from_user_id   = $resultdetails['user_id'];
    $to_user_type   = 'customer';
    $to_user_id     = $resultdetails['customer_id'];

    // Check if receipt row already exists
    $checkReceipt      = "SELECT id, received FROM receipt WHERE inv_id=? LIMIT 1";
    $fetchCheckReceipt = db_q($db_conn,$checkReceipt,[$invoice_id]);
    $existingReceipt   = mysqli_fetch_array($fetchCheckReceipt);

    if ($existingReceipt) {
        // Receipt exists — ADD new amount to existing, recalculate receivable
        $new_total_received = (float)$existingReceipt['received'] + $receivedamount;
        $new_receivable     = round($total_amount - $new_total_received);

        $updateReceipt = "UPDATE receipt 
                          SET received        = ?,
                              receivable      = ?,
                              invoice_amount  = ?,
                              receipt_method  = ?,
                              receipt_remarks = ?
                          WHERE inv_id=?";
        db_q($db_conn,$updateReceipt,[$new_total_received,$new_receivable,$total_amount,$receipt_method,$receipt_remarks,$invoice_id]);

    } else {
        // No receipt yet — insert fresh row
        $receivableamount = round($total_amount - $receivedamount);

        $insertreceipt = "INSERT INTO receipt 
                          (receiptid, inv_id, invoice_amount, received, receivable, date,
                           from_user_type, from_user_id, to_user_type, to_user_id,
                           receipt_method, receipt_remarks) 
                          VALUES 
                          (?, ?, ?, ?,
                           ?, ?,
                           ?, ?,
                           ?, ?,
                           ?, ?)";
        db_q($db_conn,$insertreceipt,[$invoice_id,$invoice_id,$total_amount,$receivedamount,$receivableamount,$receiptdate,$from_user_type,$from_user_id,$to_user_type,$to_user_id,$receipt_method,$receipt_remarks]);
    }

    // =========================================================================
    // STEP 5: Update invoice totals
    // =========================================================================
    if (!empty($_REQUEST['invoice_id'])) {
        $update_invoice = "UPDATE invoice 
                           SET sub_total        = ?,
                               discount         = ?,
                               total            = ?,
                               roundoff         = ?,
                               courier_charges  = ?
                           WHERE inv_id=? 
                           AND user_type=? 
                           AND user_id=?";
        $invoice_update_result = db_q($db_conn,$update_invoice,[$SubTotal,$discount,$total_amount,$roundoff,$courier_charges,$invoice_id,$Login_user_TYPEvl,$Login_user_IDvl]);

        // =====================================================================
        // ✨ DAILY REWARD — new invoices only
        // =====================================================================
        if ($invoice_update_result && $_SESSION['ACTIONEDIT'] != 'edit') {
            $invoice_number = $resultdetails['inv_number'];

            $rewardResult = checkAndAwardDailyReward(
                $db_conn,
                $Login_user_TYPEvl,
                $Login_user_IDvl,
                $invoice_id,
                $invoice_number
            );

            if (isset($rewardResult['success']) && $rewardResult['success']) {
                $_SESSION['reward_notification'] = $rewardResult;
            }

            if (!$rewardResult['success'] && !isset($rewardResult['already_rewarded'])) {
                error_log("Daily Reward Error: " . ($rewardResult['message'] ?? 'Unknown error'));
            }
        }
    }

    // ============================================================================
    // STOCK UPDATE — reverse old stock on edit, then re-apply from current items
    // ============================================================================

    $is_edit_submission = (isset($_SESSION['ACTIONEDIT']) && $_SESSION['ACTIONEDIT'] === 'edit');
    require_once('include/StockService.php');
    $stockService = new StockService($db_conn);

    error_log('=== PROCESSING STOCK UPDATES ===');
    error_log('Submission type: ' . ($is_edit_submission ? 'EDIT (reverse + re-apply)' : 'NEW'));

    if ($is_edit_submission) {
        try {
            $reversed = $stockService->reverseAll('invoice', $invoice_id, $Login_user_IDvl);
            error_log('EDIT: reversed $reversed ledger entries for invoice $invoice_id');
        } catch (\Throwable $e) {
            error_log('EDIT: reversal warning (non-blocking): ' . $e->getMessage());
        }
    }

    try {
        $is_customer_invoice = true;
        if (!defined('INVOICE_STOCK_UPDATE_INCLUDED')) {
            define('INVOICE_STOCK_UPDATE_INCLUDED', true);
        }
        include('invoice-stock-update.php');
        error_log('Stock updates completed successfully');
    } catch (\Throwable $e) {
        error_log('CRITICAL: Stock update failed for invoice $invoice_id — ' . $e->getMessage());
        error_log('Action Required: Manual stock adjustment needed for invoice: $invoice_id');
    }

    error_log('=== STOCK UPDATES COMPLETED ===');

    // =========================================================================
    // STEP 7: Redirect to print
    // =========================================================================
    unset($_SESSION['ACTIONEDIT']);
    echo "<script>window.location='customer-user-invoice-print.php?invoiceid=" . base64_encode($invoice_id) . "';</script>";
}
?>