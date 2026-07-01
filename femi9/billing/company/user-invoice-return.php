<?php
/**
 * Invoice Return Handler
 * Femi9 Billing Application
 * 
 * Handles invoice returns/cancellations with automatic advance payment credit-back
 * for Super Stockist and Stockist
 * 
 * @version 1.0
 * @date 2025-12-31
 */

declare(strict_types=1);

include("checksession.php");
include("config.php");
include("advance-payment-functions.php");
require_once("include/StockService.php");

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Sanitize input data
 * 
 * @param mixed $data Input data
 * @param string $type Data type
 * @return mixed Sanitized data
 */
function sanitizeInput($data, string $type = 'string')
{
    if ($data === null || $data === '') {
        return null;
    }

    switch ($type) {
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT) !== false ? (int)$data : 0;
        
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT) !== false ? (float)$data : 0.0;
        
        case 'string':
        default:
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Redirect with message
 * 
 * @param string $location Redirect URL
 * @param string|null $message Message
 * @param bool $isError Is error message
 * @return void
 */
function redirectWithMessage(string $location, ?string $message = null, bool $isError = false): void
{
    if ($message) {
        if ($isError) {
            $_SESSION['errorMessage'] = $message;
        } else {
            $_SESSION['successMessage'] = $message;
        }
    }
    
    header("Location: $location");
    exit();
}

// =============================================================================
// MAIN RETURN PROCESSING LOGIC
// =============================================================================

try {
    // Get action type
    $action = $_REQUEST['action'] ?? '';
    
    if ($action !== 'return_invoice' && $action !== 'cancel_invoice') {
        throw new Exception("Invalid action specified");
    }

    // Get parameters
    $invId = sanitizeInput($_REQUEST['inv_id'] ?? '');
    $invNumber = sanitizeInput($_REQUEST['inv_number'] ?? '');
    $invuser = sanitizeInput($_REQUEST['invuser'] ?? '');
    $returnReason = sanitizeInput($_REQUEST['return_reason'] ?? 'Invoice Return');
    $returnDate = date('Y-m-d');

    // Validate required fields
    if (empty($invId) || empty($invNumber)) {
        throw new Exception("Invoice ID and number are required");
    }

    // Get current user details
    $createdByUserId = $_SESSION['user_id'] ?? '';
    $createdByUserType = $_SESSION['user_type'] ?? '';

    // =============================================================================
    // STEP 1: Get invoice details
    // =============================================================================
    
    $stmt = $db_conn->prepare("
        SELECT 
            inv_id,
            inv_number,
            to_user_id,
            to_user_type,
            from_user_id,
            from_user_type,
            total,
            date as invoice_date
        FROM user_invoice 
        WHERE inv_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("Database error: " . $db_conn->error);
    }

    $stmt->bind_param("s", $invId);
    $stmt->execute();
    $invoiceResult = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$invoiceResult) {
        throw new Exception("Invoice not found: $invNumber");
    }

    $fromUserId = $invoiceResult['from_user_id'];
    $fromUserType = $invoiceResult['from_user_type'];
    $toUserId = $invoiceResult['to_user_id'];
    $toUserType = $invoiceResult['to_user_type'];
    $invoiceTotal = floatval($invoiceResult['total']);
    $invoiceDate = $invoiceResult['invoice_date'];

    // =============================================================================
    // STEP 2: Begin transaction
    // =============================================================================
    
    $db_conn->begin_transaction();

    try {
        // =============================================================================
        // STEP 3: Get all invoice items for stock reversal
        // =============================================================================
        
        $stmt = $db_conn->prepare("
            SELECT 
                pr_id,
                qty,
                total
            FROM user_invoice_items 
            WHERE inv_id = ?
        ");

        $stmt->bind_param("s", $invId);
        $stmt->execute();
        $invoiceItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($invoiceItems)) {
            throw new Exception("No items found in invoice");
        }

        // =============================================================================
        // STEP 4: Reverse stock movements via StockService (ledger + FOR UPDATE)
        // =============================================================================

        $stockService = new StockService($db_conn);
        $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

        // Use reverseAll() so the ledger is the single source of truth.
        // If stock was applied via StockService (new path), reverseAll() finds the
        // deduct/credit entries and reverses them. For legacy invoices with no ledger
        // entries, reverseAll() returns 0 and we fall back to the direct per-item approach.
        $reversedByLedger = $stockService->reverseAll('user_invoice', $invId, $createdBy);

        if ($reversedByLedger === 0) {
            // Legacy invoice — no ledger entries. Reverse directly via StockService
            // methods so a ledger entry IS created going forward.
            foreach ($invoiceItems as $item) {
                $productId = (int)   $item['pr_id'];
                $qty       = (int)   $item['qty'];

                // Restore seller stock: closing_qty ↑, sales_qty ↓
                $stockService->reverseDeduct(
                    $productId, $fromUserType, $fromUserId, $qty,
                    'user_invoice', $invId, $createdBy,
                    true // externalTransaction
                );

                // Remove buyer stock if buyer maintains inventory: closing_qty ↓, input_qty ↓
                if (in_array($toUserType, StockService::STOCK_MAINTAINING_TYPES, true)) {
                    $stockService->reverseCredit(
                        $productId, $toUserType, $toUserId, $qty,
                        'user_invoice', $invId, $createdBy,
                        true // externalTransaction
                    );
                }

                error_log("Stock reversed (legacy) for product $productId: qty $qty");
            }
        } else {
            error_log("Stock reversed via ledger for invoice $invId: $reversedByLedger entries");
        }

        // =============================================================================
        // STEP 5: Credit back advance payment (if applicable)
        // =============================================================================
        
        $advancePaymentResult = creditBackAdvancePaymentOnReturn(
            $db_conn,
            $invId,
            $invNumber,
            $returnDate,
            $createdByUserId,
            $createdByUserType,
            $returnReason
        );

        if (!$advancePaymentResult['success']) {
            // Log warning but don't fail the entire transaction
            error_log("Advance payment credit-back warning for invoice $invNumber: " . 
                     $advancePaymentResult['message']);
        }

        // =============================================================================
        // STEP 6: Mark invoice items as deleted (soft delete)
        // =============================================================================
        
        $stmtDeleteItems = $db_conn->prepare("
            UPDATE user_invoice_items 
            SET deleted_at = NOW(),
                notes = CONCAT(COALESCE(notes, ''), ' | Returned: ', ?)
            WHERE inv_id = ?
        ");

        $stmtDeleteItems->bind_param("ss", $returnReason, $invId);
        
        if (!$stmtDeleteItems->execute()) {
            throw new Exception("Failed to mark invoice items as deleted");
        }
        $stmtDeleteItems->close();

        // =============================================================================
        // STEP 7: Mark invoice as cancelled/returned
        // =============================================================================
        
        $stmtUpdateInvoice = $db_conn->prepare("
            UPDATE user_invoice 
            SET status = 'returned',
                return_date = ?,
                return_reason = ?,
                updated_at = NOW()
            WHERE inv_id = ?
        ");

        $stmtUpdateInvoice->bind_param("sss", $returnDate, $returnReason, $invId);
        
        if (!$stmtUpdateInvoice->execute()) {
            throw new Exception("Failed to update invoice status");
        }
        $stmtUpdateInvoice->close();

        // =============================================================================
        // STEP 8: Insert return record for audit trail
        // =============================================================================
        
        $stmtInsertReturn = $db_conn->prepare("
            INSERT INTO invoice_returns (
                inv_id,
                inv_number,
                return_date,
                return_reason,
                from_user_id,
                from_user_type,
                to_user_id,
                to_user_type,
                total_amount,
                advance_payment_credited,
                processed_by_user_id,
                processed_by_user_type,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");

        $advanceCredited = $advancePaymentResult['credited_amount'] ?? 0;

        $stmtInsertReturn->bind_param(
            "ssssssssddss",
            $invId,
            $invNumber,
            $returnDate,
            $returnReason,
            $fromUserId,
            $fromUserType,
            $toUserId,
            $toUserType,
            $invoiceTotal,
            $advanceCredited,
            $createdByUserId,
            $createdByUserType
        );

        if (!$stmtInsertReturn->execute()) {
            // Log but don't fail - audit table might not exist yet
            error_log("Warning: Could not insert return record: " . $stmtInsertReturn->error);
        }
        $stmtInsertReturn->close();

        // =============================================================================
        // STEP 9: Commit transaction
        // =============================================================================
        
        $db_conn->commit();

        // Prepare success message
        $successMessage = "Invoice $invNumber has been returned successfully. Stock has been reversed.";
        
        if ($advanceCredited > 0) {
            $successMessage .= " Advance payment of ₹" . number_format($advanceCredited, 2) . 
                              " has been credited back.";
        }

        $_SESSION['successMessage'] = $successMessage;
        $_SESSION['advance_payment_credited'] = $advanceCredited;

        error_log("Invoice return successful: $invNumber, Advance credited: ₹$advanceCredited");

        // Redirect back to invoice list
        redirectWithMessage(
            "user-manage-invoice?invuser=$invuser&return_success",
            $successMessage,
            false
        );

    } catch (Exception $e) {
        // Rollback on error
        $db_conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Invoice return error: " . $e->getMessage());
    
    redirectWithMessage(
        "user-manage-invoice?invuser=" . ($invuser ?? '') . "&return_error",
        "Return failed: " . $e->getMessage(),
        true
    );
}
