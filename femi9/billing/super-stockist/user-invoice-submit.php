<?php
/**
 * User Invoice Submit - SUPER STOCKIST VERSION
 * Femi9 Billing Application
 * 
 * Handles final invoice submission with:
 * - Automatic receipt generation
 * - Advance payment deduction (Stockist only for SS)
 * - Balance validation
 * - Transaction safety
 * 
 * Key Differences:
 * - Only Stockist invoices require advance payment
 * - Uses Super Stockist's own ID (stored in invoice)
 * - Distributor/Super Distributor use manual receipt entry
 * 
 * @version 2.0 - Super Stockist Adapted
 * @date 2025-01-30
 */

declare(strict_types=1);

include("checksession.php"); 
include("config.php");
require_once("advance-payment-functions.php");

// ✨ DAILY REWARD INTEGRATION - Load reward helper functions
require_once 'include/invoice-reward-integration.php';

// Enable error logging (NOT display in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/invoice-submit-errors.log');

if (isset($_REQUEST['invoice-submit'])) {  
    $invoice_id = mysqli_real_escape_string($db_conn, trim($_REQUEST['invoice_id'] ?? ''));
    
    if (empty($invoice_id)) {
        die("Error: Invoice ID is required");
    }
    
    error_log(str_repeat("=", 80));
    error_log("[SS-SUBMIT] === INVOICE SUBMISSION STARTED ===");
    error_log("[SS-SUBMIT] Invoice ID: $invoice_id");
    error_log("[SS-SUBMIT] Timestamp: " . date('Y-m-d H:i:s'));
    error_log(str_repeat("=", 80));
    
    // ============================================================================
    // STEP 1: GET INVOICE DETAILS
    // ============================================================================
    
    $stmt = $db_conn->prepare("SELECT * FROM user_invoice WHERE inv_id = ?");
    if (!$stmt) {
        error_log("[SS-SUBMIT] FATAL: Failed to prepare invoice query: " . $db_conn->error);
        die("Database error occurred");
    }
    
    $stmt->bind_param("s", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $resultdetails = $result->fetch_assoc();
    $stmt->close();
    
    if (!$resultdetails) {
        error_log("[SS-SUBMIT] FATAL: Invoice not found: $invoice_id");
        die("Error: Invoice not found");
    }
    
    // ✅ Get values from invoice (SS's own ID is stored as from_user_id)
    $Login_user_IDvl = $resultdetails['from_user_id'];
    $customer_type = $resultdetails['to_user_type'];
    $customer_id = $resultdetails['to_user_id'];
    $company_id = $resultdetails['from_user_id']; // SS's own ID
    $company_type = $resultdetails['from_user_type']; // 'super_stockiest'
    $invoice_number = $resultdetails['inv_number'];
    $invoice_date = $resultdetails['date'];
    
    // ✅ Check if this user type requires advance payment (stockiest only)
    $requires_advance = isAdvancePaymentMandatory($customer_type);
    
    error_log("[SS-SUBMIT] Customer: $customer_id ($customer_type)");
    error_log("[SS-SUBMIT] Super Stockist: $company_id ($company_type)");
    error_log("[SS-SUBMIT] Invoice Number: $invoice_number");
    error_log("[SS-SUBMIT] Requires Advance Payment: " . ($requires_advance ? 'YES (Stockist)' : 'NO (Distributor/Super Distributor)'));
    
    // ============================================================================
    // STEP 2: UPDATE INVOICE DATE (IF EDITED)
    // ============================================================================
    
    if (!empty($_REQUEST['update_invoice_date'])) {
        $update_invoice_date = date("Y-m-d", strtotime($_REQUEST['update_invoice_date']));
        
        error_log("[SS-SUBMIT] Updating invoice date to: $update_invoice_date");
        
        // Use prepared statements
        $stmt1 = $db_conn->prepare(
            "UPDATE user_invoice SET date = ? WHERE inv_id = ? AND from_user_type = ? AND from_user_id = ?"
        );
        $stmt1->bind_param("ssss", $update_invoice_date, $invoice_id, $Login_user_TYPEvl, $Login_user_IDvl);
        $stmt1->execute();
        $stmt1->close();
        
        $stmt2 = $db_conn->prepare(
            "UPDATE user_invoice_items SET date = ? WHERE inv_id = ? AND from_user_type = ? AND from_user_id = ?"
        );
        $stmt2->bind_param("ssss", $update_invoice_date, $invoice_id, $Login_user_TYPEvl, $Login_user_IDvl);
        $stmt2->execute();
        $stmt2->close();
        
        $invoice_date = $update_invoice_date;
        error_log("[SS-SUBMIT] Invoice date updated successfully");
    }
    
    // ============================================================================
    // STEP 3: CALCULATE TOTALS
    // ============================================================================
    
    $SubTotal = floatval($_REQUEST['SubTotal'] ?? 0);
    $discount = !empty($_REQUEST['discount']) ? floatval($_REQUEST['discount']) : 0.00;
    $credit = floatval($_REQUEST['credit'] ?? 0);
    $roundoff = floatval($_REQUEST['roundoff'] ?? 0);
    $courier_charges = floatval($_REQUEST['courier_charges'] ?? 0);
    
    $total_amount = $SubTotal - $discount - $credit + $courier_charges;
    $total_amount = round($total_amount, 2);
    
    error_log("[SS-SUBMIT] Invoice Totals:");
    error_log("[SS-SUBMIT]   - Subtotal: Rs." . number_format($SubTotal, 2));
    error_log("[SS-SUBMIT]   - Discount: Rs." . number_format($discount, 2));
    error_log("[SS-SUBMIT]   - Credit: Rs." . number_format($credit, 2));
    error_log("[SS-SUBMIT]   - Courier: Rs." . number_format($courier_charges, 2));
    error_log("[SS-SUBMIT]   - TOTAL: Rs." . number_format($total_amount, 2));
    
    // ============================================================================
    // STEP 4: DELETE EXISTING RECEIPT (IF EDIT MODE)
    // ============================================================================
    
   
    
    // ============================================================================
    // STEP 5: CREATE RECEIPT
    // ============================================================================
    
    $stmt = $db_conn->prepare("SELECT COUNT(*) as numreceipt FROM receipt WHERE receiptid = ?");
    $stmt->bind_param("s", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $resultreceipt = $result->fetch_assoc();
    $stmt->close();
    
    if ($resultreceipt['numreceipt'] == 0) {
        $receiptdate = $resultdetails['date'];
        
        error_log("[SS-SUBMIT] Creating receipt...");
        
        // ========================================================================
        // AUTOMATIC RECEIPT FOR STOCKIST (ADVANCE PAYMENT)
        // MANUAL RECEIPT FOR DISTRIBUTOR/SUPER DISTRIBUTOR
        // ========================================================================
        if ($requires_advance) {
            // ✅ STOCKIST: Automatic receipt via advance payment
            // IMPORTANT: Only invoice amount (excluding courier) is paid via advance
            // Courier charges must be paid separately via add-receipt page
            $receivedamount = $total_amount - $courier_charges;
            $receivableamount = $courier_charges;  // Courier charges still need to be paid
            $receipt_method = "Advance Payment";
            $receipt_remarks = "Paid via advance payment adjustment (auto-deducted). Courier charges (Rs." . 
                              number_format($courier_charges, 2) . ") to be paid separately.";
            
            error_log("[SS-SUBMIT] Receipt Type: AUTO (Advance Payment - Stockist)");
            error_log("[SS-SUBMIT]   - Invoice Amount (advance): Rs." . number_format($receivedamount, 2));
            error_log("[SS-SUBMIT]   - Courier Charges (separate): Rs." . number_format($courier_charges, 2));
            error_log("[SS-SUBMIT]   - Receivable (courier only): Rs." . number_format($receivableamount, 2));
        } else {
            // ✅ DISTRIBUTOR/SUPER DISTRIBUTOR: Manual entry
            $receivedamount = !empty($_REQUEST['receivedamount']) ? floatval($_REQUEST['receivedamount']) : 0.00;
            $receivableamount = $total_amount - $receivedamount;
            $receivableamount = round($receivableamount, 2);
            
            $receipt_method = mysqli_real_escape_string($db_conn, $_REQUEST['receipt_method'] ?? 'Cash');
            $receipt_remarks = mysqli_real_escape_string($db_conn, str_replace("'", "&#39;", $_REQUEST['receipt_remarks'] ?? ''));
            
            error_log("[SS-SUBMIT] Receipt Type: MANUAL (Distributor/Super Distributor)");
            error_log("[SS-SUBMIT]   - Received: Rs." . number_format($receivedamount, 2));
            error_log("[SS-SUBMIT]   - Receivable: Rs." . number_format($receivableamount, 2));
        }
        
        $usertype = $resultdetails['to_user_type'];
        $userid = $resultdetails['to_user_id'];
        
        // ========================================================================
        // INSERT RECEIPT WITH PAYMENT TYPE
        // ========================================================================
        
        // Determine payment type based on user type and amount
        $payment_type = 'regular'; // Default
        
        if ($requires_advance) {
            // For advance mandatory users (stockist), this receipt is for product amount only
            $payment_type = 'advance_product';
        }
        
        error_log("[SS-SUBMIT] Inserting receipt with payment_type: $payment_type");
        
        // Check if payment_type column exists
        $column_check = mysqli_query($db_conn, "SHOW COLUMNS FROM receipt LIKE 'payment_type'");
        $payment_type_exists = mysqli_num_rows($column_check) > 0;
        
        if ($payment_type_exists) {
            // New schema with payment_type
            $stmt = $db_conn->prepare("
                INSERT INTO receipt (
                    receiptid, 
                    inv_id, 
                    invoice_amount, 
                    received, 
                    receivable, 
                    date, 
                    from_user_type, 
                    from_user_id, 
                    to_user_type, 
                    to_user_id, 
                    receipt_method, 
                    receipt_remarks,
                    payment_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                error_log("[SS-SUBMIT] FATAL: Failed to prepare receipt insert: " . $db_conn->error);
                die("Database error occurred");
            }
            
            $stmt->bind_param(
                "ssdddssssssss",
                $invoice_id,
                $invoice_id,
                $total_amount,
                $receivedamount,
                $receivableamount,
                $receiptdate,
                $company_type,
                $company_id,
                $customer_type,
                $customer_id,
                $receipt_method,
                $receipt_remarks,
                $payment_type
            );
        } else {
            // Legacy schema without payment_type
            $stmt = $db_conn->prepare("
                INSERT INTO receipt (
                    receiptid, 
                    inv_id, 
                    invoice_amount, 
                    received, 
                    receivable, 
                    date, 
                    from_user_type, 
                    from_user_id, 
                    to_user_type, 
                    to_user_id, 
                    receipt_method, 
                    receipt_remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                error_log("[SS-SUBMIT] FATAL: Failed to prepare receipt insert: " . $db_conn->error);
                die("Database error occurred");
            }
            
            $stmt->bind_param(
                "ssdddsssssss",
                $invoice_id,
                $invoice_id,
                $total_amount,
                $receivedamount,
                $receivableamount,
                $receiptdate,
                $company_type,
                $company_id,
                $customer_type,
                $customer_id,
                $receipt_method,
                $receipt_remarks
            );
        }
        
        if ($stmt->execute()) {
            error_log("[SS-SUBMIT] Receipt created successfully");
            error_log("[SS-SUBMIT]   - Receipt ID: $invoice_id");
            error_log("[SS-SUBMIT]   - Invoice Amount: Rs." . number_format($total_amount, 2));
            error_log("[SS-SUBMIT]   - Received: Rs." . number_format($receivedamount, 2));
            error_log("[SS-SUBMIT]   - Receivable: Rs." . number_format($receivableamount, 2));
            error_log("[SS-SUBMIT]   - Payment Type: $payment_type");
        } else {
            error_log("[SS-SUBMIT] FATAL: Failed to create receipt: " . $stmt->error);
            die("Failed to create receipt");
        }
        
        $stmt->close();
    }
    
    // ============================================================================
    // STEP 6: UPDATE INVOICE TOTALS
    // ============================================================================
    
    if (!empty($_REQUEST['invoice_id'])) {
        if (!empty($_SESSION['INVOICEFINISH']) && $credit != 0) {
            if (empty($_SESSION['ACTIONEDIT'])) {
                // LESS CREDIT AMOUNT - IF NEW INVOICE ONLY
                // (Legacy code - kept for compatibility)
            }
        }
        
        unset($_SESSION['INVOICEFINISH']);
        
        error_log("[SS-SUBMIT] Updating invoice totals...");
        
        $stmt = $db_conn->prepare("
            UPDATE user_invoice 
            SET credit = ?, sub_total = ?, discount = ?, total = ?, roundoff = ?, courier_charges = ?
            WHERE inv_id = ? AND from_user_type = ? AND from_user_id = ?
        ");
        
        if (!$stmt) {
            error_log("[SS-SUBMIT] FATAL: Failed to prepare invoice update: " . $db_conn->error);
            die("Database error occurred");
        }
        
        $stmt->bind_param(
            "dddddssss",
            $credit,
            $SubTotal,
            $discount,
            $total_amount,
            $roundoff,
            $courier_charges,
            $invoice_id,
            $Login_user_TYPEvl,
            $Login_user_IDvl
        );
        
        if ($stmt->execute()) {
            error_log("[SS-SUBMIT] Invoice totals updated successfully");

            // ============================================================================
            // ✨ DAILY REWARD INTEGRATION - Check and award daily reward
            // ============================================================================

            // Only award rewards for NEW invoices (not when editing)
            if (empty($_SESSION['ACTIONEDIT']) || $_SESSION['ACTIONEDIT'] !== 'edit') {

                $rewardResult = checkAndAwardDailyReward(
                    $db_conn,
                    $company_type,          // 'super_stockiest'
                    $company_id,            // Super Stockist's own ID
                    $invoice_id,            // Invoice ID
                    $invoice_number         // Invoice number
                );

                // Store reward result in session for notification on next page
                if (isset($rewardResult['success']) && $rewardResult['success']) {
                    $_SESSION['reward_notification'] = $rewardResult;
                }

                // Log reward attempt for debugging
                if (!$rewardResult['success'] && !isset($rewardResult['already_rewarded'])) {
                    error_log("[SS-SUBMIT] Daily Reward Error: " . ($rewardResult['message'] ?? 'Unknown error'));
                }
            }

            // ============================================================================
            // End of Daily Reward Integration
            // ============================================================================

        } else {
            error_log("[SS-SUBMIT] FATAL: Invoice update failed: " . $stmt->error);
            die("Failed to update invoice");
        }

        $stmt->close();
        
        // ========================================================================
        // STEP 7: ADVANCE PAYMENT ADJUSTMENT - STOCKIST ONLY
        // ========================================================================
        
        if ($requires_advance) {
            error_log(str_repeat("-", 80));
            error_log("[SS-SUBMIT] === ADVANCE PAYMENT ADJUSTMENT START (STOCKIST) ===");
            
            // ====================================================================
            // STEP 7A: RESTORE BALANCE (IF EDIT MODE)
            // ====================================================================
            
            if (isset($_SESSION['ACTIONEDIT']) && $_SESSION['ACTIONEDIT'] === "edit") {
                error_log("[SS-SUBMIT] EDIT MODE: Restoring previous balance...");
                
                $restoreResult = restoreAdvancePaymentOnInvoiceEdit(
                    $db_conn,
                    $invoice_id,
                    $invoice_number,
                    date('Y-m-d'),
                    "Invoice edited - restoring balance before re-adjustment",
                    $company_id,
                    $company_type
                );
                
                if (!$restoreResult['success']) {
                    error_log("[SS-SUBMIT] WARNING: Failed to restore balance: " . $restoreResult['message']);
                    // Continue anyway - admin can manually fix
                } else {
                    error_log("[SS-SUBMIT] SUCCESS: Restored Rs." . number_format($restoreResult['credited_amount'], 2));
                }
            }
            
            // ====================================================================
            // STEP 7B: VALIDATE BALANCE
            // ====================================================================
            
            error_log("[SS-SUBMIT] Validating advance balance...");
            
            // IMPORTANT: Validate balance excluding courier charges
            // Courier charges are paid separately and don't require advance balance
            $amount_requiring_advance = $total_amount - $courier_charges;
            
            error_log("[SS-SUBMIT] Total invoice: Rs.$total_amount");
            error_log("[SS-SUBMIT] Courier charges (separate): Rs.$courier_charges");
            error_log("[SS-SUBMIT] Amount requiring advance balance: Rs.$amount_requiring_advance");
            error_log("[SS-SUBMIT] DEBUG - Calling validateAdvanceBalanceForInvoice with:");
            error_log("[SS-SUBMIT] DEBUG - Customer ID: $customer_id");
            error_log("[SS-SUBMIT] DEBUG - Customer Type: $customer_type");
            error_log("[SS-SUBMIT] DEBUG - Amount: $amount_requiring_advance");
            error_log("[SS-SUBMIT] DEBUG - Company ID (SS): $company_id");
            
            $validation = validateAdvanceBalanceForInvoice(
                $db_conn,
                $customer_id,
                $customer_type,
                $amount_requiring_advance,  // Exclude courier charges
                $company_id // ✅ Super Stockist's own ID
            );
            
            error_log("[SS-SUBMIT] Validation Result: " . ($validation['can_create'] ? 'PASS' : 'FAIL'));
            error_log("[SS-SUBMIT]   - Available: Rs." . number_format($validation['available_balance'], 2));
            error_log("[SS-SUBMIT]   - Required: Rs." . number_format($amount_requiring_advance, 2));
            error_log("[SS-SUBMIT]   - Message: " . $validation['message']);
            
            if (!$validation['can_create']) {
                // Insufficient balance - ROLLBACK INVOICE
                error_log(str_repeat("!", 80));
                error_log("[SS-SUBMIT] CRITICAL: INSUFFICIENT BALANCE - ROLLING BACK INVOICE");
                error_log("[SS-SUBMIT] Message: " . $validation['message']);
                error_log(str_repeat("!", 80));
                
                // Delete invoice, items, and receipt
                $db_conn->query("DELETE FROM user_invoice_items WHERE inv_id = '$invoice_id'");
                $db_conn->query("DELETE FROM receipt WHERE inv_id = '$invoice_id'");
                $db_conn->query("DELETE FROM user_invoice WHERE inv_id = '$invoice_id'");
                
                error_log("[SS-SUBMIT] Invoice rolled back successfully");
                
                unset($_SESSION['ACTIONEDIT']);
                
                $safe_message = htmlspecialchars($validation['message'], ENT_QUOTES);
                echo "<script>alert('$safe_message'); window.location='user-invoice-add.php?invuser=$customer_type';</script>";
                exit;
            }
            
            // ====================================================================
            // STEP 7C: PROCESS DEDUCTION (FIFO)
            // ====================================================================
            
            error_log("[SS-SUBMIT] Processing advance payment deduction (FIFO)...");
            
            // IMPORTANT: Exclude courier charges from advance payment deduction
            // Courier charges must be paid separately via receipt
            $amount_for_advance_deduction = $total_amount - $courier_charges;
            
            error_log("[SS-SUBMIT] Total invoice amount: Rs.$total_amount");
            error_log("[SS-SUBMIT] Courier charges: Rs.$courier_charges");
            error_log("[SS-SUBMIT] Amount to deduct from advance: Rs.$amount_for_advance_deduction");
            error_log("[SS-SUBMIT] DEBUG - Calling processInvoiceAdvancePaymentDeduction with:");
            error_log("[SS-SUBMIT] DEBUG - Invoice: $invoice_id / $invoice_number");
            error_log("[SS-SUBMIT] DEBUG - From User (Stockist): $customer_id ($customer_type)");
            error_log("[SS-SUBMIT] DEBUG - To User (SS): $company_id ($company_type)");
            error_log("[SS-SUBMIT] DEBUG - Created By: $company_id ($company_type)");
            
            $adjustmentResult = processInvoiceAdvancePaymentDeduction(
                $db_conn,
                $invoice_id,
                $invoice_number,
                $amount_for_advance_deduction,  // Exclude courier charges
                $invoice_date,
                $customer_id, // Stockist ID
                $customer_type, // 'stockiest'
                $company_id, // Super Stockist ID
                $company_type, // 'super_stockiest'
                $company_id, // Created by SS
                $company_type
            );
            
            if ($adjustmentResult['success']) {
                error_log(str_repeat("=", 80));
                error_log("[SS-SUBMIT] SUCCESS: ADJUSTMENT COMPLETED");
                error_log("[SS-SUBMIT]   - Adjusted Amount: Rs." . number_format($adjustmentResult['adjusted_amount'], 2));
                error_log("[SS-SUBMIT]   - Message: " . $adjustmentResult['message']);
                
                if (!empty($adjustmentResult['adjustments'])) {
                    error_log("[SS-SUBMIT] Adjustment Details:");
                    foreach ($adjustmentResult['adjustments'] as $adj) {
                        error_log("[SS-SUBMIT]   * Payment ID {$adj['payment_id']}: Rs." . 
                                 number_format($adj['amount_adjusted'], 2) . 
                                 " (Balance: Rs." . number_format($adj['new_balance'], 2) . ")");
                    }
                }
                error_log(str_repeat("=", 80));
                
                // ✅ SUCCESS: Show confirmation (optional - can be removed in production)
                // Uncomment below to show success alert
                // echo "<script>alert('✅ Advance Payment Adjusted Successfully!\\nAmount: Rs." . number_format($adjustmentResult['adjusted_amount'], 2) . "');</script>";
                
            } else {
                // ❌ ADJUSTMENT FAILED - ROLLBACK ENTIRE INVOICE
                error_log(str_repeat("!", 80));
                error_log("[SS-SUBMIT] CRITICAL: ADJUSTMENT FAILED - ROLLING BACK INVOICE");
                error_log("[SS-SUBMIT]   - Message: " . $adjustmentResult['message']);
                error_log(str_repeat("!", 80));
                
                // Delete invoice, items, receipt, and restore stock
                error_log("[SS-SUBMIT] Rolling back invoice: $invoice_id");
                
                // 1. Get all invoice items to restore stock
                $stmt = $db_conn->prepare("SELECT pr_id, qty, to_user_type, to_user_id 
                                           FROM user_invoice_items 
                                           WHERE inv_id = ?");
                $stmt->bind_param("s", $invoice_id);
                $stmt->execute();
                $items_result = $stmt->get_result();
                
                while ($item = $items_result->fetch_assoc()) {
                    $pr_id = $item['pr_id'];
                    $qty = $item['qty'];
                    $to_user_type = $item['to_user_type'];
                    $to_user_id = $item['to_user_id'];
                    
                    // Restore SS stock (add back)
                    $db_conn->query("UPDATE stock 
                                    SET sales_qty = sales_qty - $qty, 
                                        closing_qty = closing_qty + $qty 
                                    WHERE product_id = '$pr_id' 
                                    AND user_type = '$company_type' 
                                    AND user_id = '$company_id'");
                    
                    // Reduce customer stock (remove)
                    $db_conn->query("UPDATE stock 
                                    SET input_qty = input_qty - $qty, 
                                        closing_qty = closing_qty - $qty 
                                    WHERE product_id = '$pr_id' 
                                    AND user_type = '$to_user_type' 
                                    AND user_id = '$to_user_id'");
                    
                    error_log("[SS-SUBMIT] Stock restored for product: $pr_id");
                }
                $stmt->close();
                
                // 2. Delete invoice items
                $db_conn->query("DELETE FROM user_invoice_items WHERE inv_id = '$invoice_id'");
                error_log("[SS-SUBMIT] Invoice items deleted");
                
                // 3. Delete receipt
                $db_conn->query("DELETE FROM receipt WHERE inv_id = '$invoice_id'");
                error_log("[SS-SUBMIT] Receipt deleted");
                
                // 4. Delete invoice
                $db_conn->query("DELETE FROM user_invoice WHERE inv_id = '$invoice_id'");
                error_log("[SS-SUBMIT] Invoice deleted");
                
                error_log("[SS-SUBMIT] ROLLBACK COMPLETED");
                
                unset($_SESSION['ACTIONEDIT']);
                
                $safe_message = addslashes($adjustmentResult['message']);
                echo "<script>
                    alert('❌ Invoice Creation FAILED!\\n\\nReason: Advance payment adjustment failed\\n\\nError: $safe_message\\n\\nThe invoice has been rolled back. Please contact admin or add more advance payment.');
                    window.location='user-invoice-add.php?invuser=$customer_type';
                </script>";
                exit;
            }
            
            error_log("[SS-SUBMIT] === ADVANCE PAYMENT ADJUSTMENT END ===");
            error_log(str_repeat("-", 80));
        } else {
            error_log("[SS-SUBMIT] Advance payment not required for user type: $customer_type (Distributor/Super Distributor)");
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
            $reversed = $stockService->reverseAll('user_invoice', $invoice_id, $company_id);
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
    error_log("[SS-SUBMIT] === INVOICE SUBMISSION COMPLETED SUCCESSFULLY ===");
    error_log("[SS-SUBMIT] Redirecting to invoice print: $invoice_id");
    error_log(str_repeat("=", 80));
    
    echo "<script>window.location='user-invoice-print.php?invoiceid=" . base64_encode($invoice_id) . "';</script>";
    exit;
}
?>