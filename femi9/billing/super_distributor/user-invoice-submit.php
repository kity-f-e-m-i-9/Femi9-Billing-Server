<?php 
declare(strict_types=1);

// Start output buffering to prevent header issues
ob_start();

include("checksession.php"); 
include("config.php");

// Disable error display (enable only for debugging)
error_reporting(E_ALL);
ini_set('display_errors','1');

// ✨ DAILY REWARD INTEGRATION - Load reward helper functions
require_once 'include/invoice-reward-integration.php';

if (isset($_REQUEST['invoice-submit'])) {
    
    $invoice_id = mysqli_real_escape_string($db_conn, $_REQUEST['invoice_id']);
    
    // ===================================================================
    // UPDATE INVOICE DATE - IF EDIT INVOICE ACTION ONLY
    // ===================================================================
    if (!empty($_REQUEST['update_invoice_date'])) {
        $update_invoice_date = date("Y-m-d", strtotime($_REQUEST['update_invoice_date']));
        
        // Update invoice date
        $stmt = mysqli_prepare($db_conn, 
            "UPDATE user_invoice 
            SET date = ? 
            WHERE inv_id = ? 
            AND from_user_type = ? 
            AND from_user_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ssss', 
            $update_invoice_date, 
            $invoice_id, 
            $Login_user_TYPEvl, 
            $Login_user_IDvl
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Update invoice items date
        $stmt2 = mysqli_prepare($db_conn, 
            "UPDATE user_invoice_items 
            SET date = ? 
            WHERE inv_id = ? 
            AND from_user_type = ? 
            AND from_user_id = ?"
        );
        mysqli_stmt_bind_param($stmt2, 'ssss', 
            $update_invoice_date, 
            $invoice_id, 
            $Login_user_TYPEvl, 
            $Login_user_IDvl
        );
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
    }
  
    // ===================================================================
    // CALCULATE TOTALS
    // ===================================================================
    $SubTotal = floatval($_REQUEST['SubTotal'] ?? 0);
    $discount = floatval($_REQUEST['discount'] ?? 0);
    $roundoff = floatval($_REQUEST['roundoff'] ?? 0);
    $courier_charges = floatval($_REQUEST['courier_charges'] ?? 0);
    
    $total_amount = $SubTotal - $discount + $courier_charges;
    $total_amount = round($total_amount, 2);
    
    // ===================================================================
    // GET INVOICE DETAILS
    // ===================================================================
    $stmt_inv = mysqli_prepare($db_conn, 
        "SELECT * FROM user_invoice WHERE inv_id = ?"
    );
    mysqli_stmt_bind_param($stmt_inv, 's', $invoice_id);
    mysqli_stmt_execute($stmt_inv);
    $result_invoicedetails = mysqli_stmt_get_result($stmt_inv);
    $invoice_data = mysqli_fetch_assoc($result_invoicedetails);
    mysqli_stmt_close($stmt_inv);
    
    $shopid = $invoice_data['to_user_id'];
    $invdate = $invoice_data['date'];
    
    $receiptdate = !empty($invdate) ? $invdate : date('Y-m-d');
    $receivedamount  = ($_REQUEST['receivedamount'] != NULL) ? (float)$_REQUEST['receivedamount'] : 0;
    $receipt_method  = $_REQUEST['receipt_method'];
    $receipt_remarks = str_replace("'", "&#39;", $_REQUEST['receipt_remarks']);
    
    $usertype = $invoice_data['to_user_type'];
    $userid   = $invoice_data['to_user_id'];
    
    // Check if receipt row already exists for this invoice
    $checkReceipt      = "SELECT id, received FROM receipt WHERE inv_id='$invoice_id' LIMIT 1";
    $fetchCheckReceipt = mysqli_query($db_conn, $checkReceipt);
    $existingReceipt   = mysqli_fetch_array($fetchCheckReceipt);
    
    if ($existingReceipt) {
        // Receipt exists — ADD new amount to existing received, recalculate receivable
        $new_total_received  = (float)$existingReceipt['received'] + $receivedamount;
        $new_receivable      = round($total_amount - $new_total_received);
    
        $updateReceipt = "UPDATE receipt 
                          SET received        = '$new_total_received',
                              receivable      = '$new_receivable',
                              invoice_amount  = '$total_amount',
                              receipt_method  = '$receipt_method',
                              receipt_remarks = '$receipt_remarks'
                          WHERE inv_id='$invoice_id'";
        mysqli_query($db_conn, $updateReceipt);
    
    } else {
        // No receipt yet — insert fresh row
        $receivableamount = round($total_amount - $receivedamount);
    
        $insertreceipt = "INSERT INTO receipt 
                  (receiptid, inv_id, invoice_amount, received, receivable, date,
                   from_user_type, from_user_id, to_user_type, to_user_id,
                   receipt_method, receipt_remarks) 
                  VALUES 
                  ('$invoice_id', '$invoice_id', '$total_amount', '$receivedamount',
                   '$receivableamount', '$receiptdate',
                   '".$invoice_data['from_user_type']."',
                   '".$invoice_data['from_user_id']."',
                   '$usertype', '$userid',
                   '$receipt_method', '$receipt_remarks')";
        mysqli_query($db_conn, $insertreceipt);
    }
    
    // ===================================================================
    // UPDATE INVOICE TOTALS
    // ===================================================================
    $stmt_update = mysqli_prepare($db_conn, 
        "UPDATE user_invoice 
        SET sub_total = ?, discount = ?, total = ?, roundoff = ?, courier_charges = ? 
        WHERE inv_id = ? 
        AND from_user_type = ? 
        AND from_user_id = ?"
    );
    
    mysqli_stmt_bind_param($stmt_update, 'dddddsss', 
        $SubTotal, $discount, $total_amount, $roundoff, $courier_charges,
        $invoice_id, $Login_user_TYPEvl, $Login_user_IDvl
    );
    
    $invoice_update_result = mysqli_stmt_execute($stmt_update);
    mysqli_stmt_close($stmt_update);
    
    // ===================================================================
    // ✨ DAILY REWARD INTEGRATION - Silent processing (no popup)
    // ===================================================================
    if ($invoice_update_result) {
        // Only award rewards for NEW invoices (not when editing)
        if (!isset($_SESSION['ACTIONEDIT']) || $_SESSION['ACTIONEDIT'] !== 'edit') {
            
            try {
                // Get invoice number from database
                $invoice_number = $invoice_data['inv_number'] ?? '';
                
                // Check and award daily reward silently
                checkAndAwardDailyReward(
                    $db_conn,
                    $Login_user_TYPEvl,          // User type
                    $Login_user_IDvl,            // User ID
                    $invoice_id,                 // Invoice ID
                    $invoice_number              // Invoice number
                );
                
                // DO NOT store in session - no popup needed
                
            } catch (Exception $e) {
                // Log but don't fail the invoice process
                error_log("Daily Reward Error: " . $e->getMessage());
            }
        }
    }
    
    // ===================================================================
    // ✨ MONTHLY TARGET REWARDS - Silent Real-time Processing
    // ===================================================================
    if ($invoice_update_result) {
        // Only for NEW invoices (not editing)
        if (!isset($_SESSION['ACTIONEDIT']) || $_SESSION['ACTIONEDIT'] !== 'edit') {
            
            try {
                if (file_exists('../includes/MonthlyTargetCalculator.class.php') && 
                    file_exists('../includes/monthly_target_rewards_processor.php')) {
                    
                    require_once('../includes/MonthlyTargetCalculator.class.php');
                    require_once('../includes/monthly_target_rewards_processor.php');
                    
                    $processor = new MonthlyTargetRewardsProcessor($db_conn);
                    $processor->processAfterInvoice(
                        $Login_user_TYPEvl,       // User type
                        $Login_user_IDvl,         // User's temp_id
                        $invoice_data['date']     // Invoice date
                    );
                }
            } catch (Exception $e) {
                // Log error silently - don't break invoice creation
                error_log("Monthly Target Rewards Error: " . $e->getMessage());
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
            $reversed = $stockService->reverseAll('user_invoice', $invoice_id, $Login_user_IDvl);
            error_log('EDIT: reversed $reversed ledger entries for invoice $invoice_id');
        } catch (\Throwable $e) {
            error_log('EDIT: reversal warning (non-blocking): ' . $e->getMessage());
        }
    }

    try {
        $is_customer_invoice = false;
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
    
    unset($_SESSION['ACTIONEDIT']);
    
    error_log(str_repeat("=", 80));
    error_log("=== INVOICE SUBMISSION COMPLETED SUCCESSFULLY ===");
    error_log("Redirecting to invoice print: $invoice_id");
    error_log(str_repeat("=", 80));
    
    // ===================================================================
    // REDIRECT TO PRINT PAGE - Critical Fix
    // ===================================================================
    
    // Clear any output buffer content
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Build redirect URL
    $redirect_url = 'user-invoice-print.php?invoiceid=' . urlencode(base64_encode($invoice_id));
    
    // Perform redirect using header
    header("Location: " . $redirect_url);
    exit();
}

// If we reach here without redirect, clear buffer
if (ob_get_level()) {
    ob_end_flush();
}
?>