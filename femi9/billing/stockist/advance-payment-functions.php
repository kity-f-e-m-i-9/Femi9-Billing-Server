<?php
/**
 * Advance Payment Functions - SIMPLIFIED FOR TRIGGER-BASED SCHEMA
 * Works with existing database trigger that auto-updates balance
 */

declare(strict_types=1);

function isAdvancePaymentMandatory(string $userType): bool
{
    $mandatoryTypes = ['super_stockiest', 'stockiest'];
    return in_array(strtolower($userType), $mandatoryTypes, true);
}

function validateAdvanceBalanceForInvoice(
    mysqli $dbConn,
    string $customerId,
    string $customerType,
    float $invoiceAmount,
    string $toUserId = ''
): array {
    $result = [
        'can_create' => false,
        'message' => '',
        'available_balance' => 0.00,
        'is_mandatory' => false,
        'required_amount' => $invoiceAmount
    ];

    $isMandatory = isAdvancePaymentMandatory($customerType);
    $result['is_mandatory'] = $isMandatory;

    if (!$isMandatory) {
        $result['can_create'] = true;
        $result['message'] = "Invoice can be created. Advance payment not required for $customerType";
        return $result;
    }

    $availableBalance = getAvailableAdvanceBalance($dbConn, $customerId, $customerType, $toUserId);
    $result['available_balance'] = $availableBalance;

    if ($availableBalance <= 0) {
        $result['can_create'] = false;
        $result['message'] = "Cannot create invoice. No advance balance available. Please add advance payment first.";
        return $result;
    }

    if ($availableBalance < $invoiceAmount) {
        $shortage = $invoiceAmount - $availableBalance;
        $result['can_create'] = false;
        $result['message'] =
            "Insufficient balance. Available: Rs." . inr_format($availableBalance, 2) .
            ", Required: Rs." . inr_format($invoiceAmount, 2) .
            ", Shortage: Rs." . inr_format($shortage, 2);
        return $result;
    }

    $result['can_create'] = true;
    $result['message'] = "Sufficient balance. Rs." . inr_format($invoiceAmount, 2) . " will be deducted";
    return $result;
}

function getAvailableAdvanceBalance(
    mysqli $dbConn,
    string $customerId,
    string $customerType,
    string $toUserId = ''
): float {
    if (!isAdvancePaymentMandatory($customerType)) {
        return 0.00;
    }

    // to_user_id filter REMOVED
    $query = "SELECT COALESCE(SUM(balance_amount), 0) AS total_balance
              FROM advance_payments
              WHERE deleted_at IS NULL
                AND balance_amount > 0
                AND from_user_id = ?
                AND from_user_type = ?";

    $stmt = $dbConn->prepare($query);
    if (!$stmt) {
        error_log("Balance query prepare failed: " . $dbConn->error);
        return 0.00;
    }

    $stmt->bind_param("ss", $customerId, $customerType);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $balance = floatval($row['total_balance']);
        $stmt->close();
        return $balance;
    }

    $stmt->close();
    return 0.00;
}

function getAdvancePaymentsForAdjustment(
    mysqli $dbConn,
    string $customerId,
    string $customerType,
    string $toUserId,
    float $requiredAmount
): array {
    if (!isAdvancePaymentMandatory($customerType)) {
        return [];
    }

    // UPDATED: Removed status filter - only check balance_amount > 0
    $query = "SELECT id, amount, balance_amount, adjusted_amount, payment_date
              FROM advance_payments
              WHERE deleted_at IS NULL
                AND balance_amount > 0
                AND from_user_id = ?
                AND from_user_type = ?
              ORDER BY payment_date ASC, id ASC";

    $stmt = $dbConn->prepare($query);
    if (!$stmt) {
        error_log("Adjustment query prepare failed: " . $dbConn->error);
        return [];
    }

    $stmt->bind_param("ss", $customerId, $customerType);
    $stmt->execute();
    $result = $stmt->get_result();

    $payments = [];
    $cumulativeAmount = 0;

    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
        $cumulativeAmount += floatval($row['balance_amount']);

        if ($cumulativeAmount >= $requiredAmount) {
            break;
        }
    }

    $stmt->close();
    return $payments;
}

/**
 * UPDATED: Manually set status based on balance after adjustment
 */
function processInvoiceAdvancePaymentDeduction(
    mysqli $dbConn,
    string $invId,
    string $invNumber,
    float $invoiceTotal,
    string $invoiceDate,
    string $fromUserId,
    string $fromUserType,
    string $toUserId,
    string $toUserType,
    string $createdByUserId,
    string $createdByUserType
): array {
    $result = [
        'success' => false,
        'message' => '',
        'adjusted_amount' => 0.00,
        'adjustments' => []
    ];

    try {
        if (!isAdvancePaymentMandatory($fromUserType)) {
            $result['success'] = true;
            $result['message'] = "Advance payment not required for $fromUserType";
            return $result;
        }

        $payments = getAdvancePaymentsForAdjustment(
            $dbConn,
            $fromUserId,
            $fromUserType,
            $toUserId,
            $invoiceTotal
        );

        if (empty($payments)) {
            throw new Exception("No advance payments available");
        }

        $dbConn->begin_transaction();

        $remainingAmount = $invoiceTotal;
        $totalAdjusted = 0.00;

        foreach ($payments as $payment) {
            if ($remainingAmount <= 0) break;

            $paymentId = intval($payment['id']);
            $availableBalance = floatval($payment['balance_amount']);
            $adjustmentAmount = min($remainingAmount, $availableBalance);

            $balanceBefore = $availableBalance;
            $balanceAfter = $availableBalance - $adjustmentAmount;

            // Insert adjustment record
            $stmt = $dbConn->prepare("
                INSERT INTO advance_payment_adjustments (
                    advance_payment_id,
                    invoice_id,
                    invoice_number,
                    adjusted_amount,
                    adjustment_date,
                    adjustment_type,
                    balance_before,
                    balance_after,
                    adjusted_by_user_id,
                    adjusted_by_user_type,
                    remarks
                ) VALUES (?, ?, ?, ?, ?, 'invoice', ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $dbConn->error);
            }

            $remarks = "Invoice $invNumber - Rs." . inr_format($adjustmentAmount, 2) . " deducted";

            $stmt->bind_param(
                "issdsddsss",
                $paymentId,
                $invId,
                $invNumber,
                $adjustmentAmount,
                $invoiceDate,
                $balanceBefore,
                $balanceAfter,
                $createdByUserId,
                $createdByUserType,
                $remarks
            );

            $stmt->execute();
            $stmt->close();

            // **ADDED: Manually update status based on remaining balance**
            $newStatus = ($balanceAfter > 0) ? 'partially_adjusted' : 'fully_adjusted';
            
            $stmt = $dbConn->prepare("
                UPDATE advance_payments
                SET status = ?
                WHERE id = ?
            ");
            
            $stmt->bind_param("si", $newStatus, $paymentId);
            $stmt->execute();
            $stmt->close();

            $totalAdjusted += $adjustmentAmount;
            $remainingAmount -= $adjustmentAmount;
        }

        $dbConn->commit();

        $result['success'] = true;
        $result['adjusted_amount'] = $totalAdjusted;
        $result['message'] = "Adjusted Rs." . inr_format($totalAdjusted, 2);

    } catch (Exception $e) {
        $dbConn->rollback();
        $result['message'] = $e->getMessage();
        error_log("ADJUSTMENT FAILED: " . $e->getMessage());
    }

    return $result;
}

function restoreAdvancePaymentOnInvoiceEdit(
    mysqli $dbConn,
    string $invId,
    string $invNumber,
    string $returnDate,
    string $reason,
    string $createdByUserId,
    string $createdByUserType
): array {
    $result = [
        'success' => false,
        'credited_amount' => 0.00,
        'message' => ''
    ];

    try {
        $stmt = $dbConn->prepare("
            SELECT id, advance_payment_id, adjusted_amount
            FROM advance_payment_adjustments
            WHERE invoice_id = ?
              AND deleted_at IS NULL
              AND adjustment_type = 'invoice'
              AND adjusted_amount > 0
        ");

        $stmt->bind_param("s", $invId);
        $stmt->execute();
        $adjustments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($adjustments)) {
            $result['success'] = true;
            $result['message'] = "No adjustments to restore";
            return $result;
        }

        $dbConn->begin_transaction();

        foreach ($adjustments as $adj) {
            $stmt = $dbConn->prepare("
                UPDATE advance_payment_adjustments
                SET deleted_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("i", $adj['id']);
            $stmt->execute();
            $stmt->close();

            $stmt = $dbConn->prepare("
                INSERT INTO advance_payment_adjustments (
                    advance_payment_id,
                    invoice_id,
                    invoice_number,
                    adjusted_amount,
                    adjustment_date,
                    adjustment_type,
                    adjusted_by_user_id,
                    adjusted_by_user_type,
                    remarks
                ) VALUES (?, ?, ?, ?, ?, 'correction', ?, ?, ?)
            ");

            $negativeAmount = -$adj['adjusted_amount'];
            $remarks = "Reversal: Invoice $invNumber edited";

            $stmt->bind_param(
                "issdssss",
                $adj['advance_payment_id'],
                $invId,
                $invNumber,
                $negativeAmount,
                $returnDate,
                $createdByUserId,
                $createdByUserType,
                $remarks
            );

            $stmt->execute();
            $stmt->close();

            $result['credited_amount'] += $adj['adjusted_amount'];
        }

        $dbConn->commit();

        $result['success'] = true;
        $result['message'] = "Restored Rs." . inr_format($result['credited_amount'], 2);

    } catch (Exception $e) {
        $dbConn->rollback();
        $result['message'] = $e->getMessage();
        error_log("RESTORATION FAILED: " . $e->getMessage());
    }

    return $result;
}

/**
 * NEW FUNCTION: Add advance payment credit for stock returns (Credit Note)
 * UPDATED: Matches actual database schema
 */
function addAdvancePaymentCreditForReturn(
    mysqli $dbConn,
    string $returnId,
    string $invId,
    string $invNumber,
    float  $returnAmount,
    string $returnDate,
    string $invoiceDate,
    string $fromUserId,
    string $fromUserType,
    string $toUserId,
    string $toUserType,
    string $createdByUserId,
    string $createdByUserType
): array {
    $result = [
        'success' => false,
        'message' => '',
        'payment_id' => 0
    ];

    try {
        // Only for super_stockiest and stockiest
        if (!isAdvancePaymentMandatory($fromUserType)) {
            $result['success'] = true;
            $result['message'] = "Advance payment credit not applicable for $fromUserType";
            return $result;
        }

        // Check if return amount is valid
        if ($returnAmount <= 0) {
            $result['success'] = true;
            $result['message'] = "No amount to credit";
            return $result;
        }

        $dbConn->begin_transaction();

        // Get user names for the name fields
        $fromUserName = getUserName($dbConn, $fromUserId, $fromUserType);
        $toUserName = getUserName($dbConn, $toUserId, $toUserType);

        // Build remarks with return info
        $remarks = "Credit Note - Invoice: $invNumber, Return ID: $returnId, Amount: Rs." . inr_format($returnAmount, 2);

        // Insert advance payment record - matching actual schema
        $stmt = $dbConn->prepare("
            INSERT INTO advance_payments (
                from_user_id,
                from_user_type,
                from_user_name,
                to_user_id,
                to_user_type,
                to_user_name,
                amount,
                balance_amount,
                adjusted_amount,
                payment_date,
                payment_mode,
                reference_number,
                remarks,
                status,
                created_by_user_id,
                created_by_user_type,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'credit_note', ?, ?, 'active', ?, ?, NOW())
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $dbConn->error);
        }

        $referenceNumber = "CN-" . $returnId;

        $stmt->bind_param(
            "ssssssddsssss",
            $fromUserId,           // from_user_id (user returning stock)
            $fromUserType,         // from_user_type
            $fromUserName,         // from_user_name
            $toUserId,             // to_user_id (company receiving return)
            $toUserType,           // to_user_type
            $toUserName,           // to_user_name
            $returnAmount,         // amount
            $returnAmount,         // balance_amount (full amount available)
            $returnDate,           // payment_date (return date)
            $referenceNumber,      // reference_number (CN-ReturnID)
            $remarks,              // remarks (includes return_id and invoice info)
            $createdByUserId,      // created_by_user_id
            $createdByUserType     // created_by_user_type
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $paymentId = $dbConn->insert_id;
        $stmt->close();

        $dbConn->commit();

        $result['success'] = true;
        $result['payment_id'] = $paymentId;
        $result['message'] = "Advance payment credit of Rs." . inr_format($returnAmount, 2) . " added successfully";

        error_log("RETURN CREDIT SUCCESS: Return ID $returnId, Payment ID $paymentId, Amount: $returnAmount");

    } catch (Exception $e) {
        $dbConn->rollback();
        $result['message'] = "Failed to add advance payment credit: " . $e->getMessage();
        error_log("RETURN CREDIT FAILED: " . $e->getMessage());
    }

    return $result;
}

/**
 * Helper function to get user name
 */
function getUserName(mysqli $dbConn, string $userId, string $userType): string {
    if($userType == 'company') {
        return 'Company';
    }
    
    $tableName = '';
    switch(strtolower($userType)) {
        case 'super_stockiest':
            $tableName = 'super_stockiest';
            break;
        case 'stockiest':
            $tableName = 'stockiest';
            break;
        case 'super_distributor':
            $tableName = 'super_distributor';
            break;
        case 'distributor':
            $tableName = 'distributor';
            break;
        case 'c_and_f':
        case 'candf':
            $tableName = 'c_and_f';
            break;
        default:
            return 'Unknown';
    }
    
    $query = "SELECT name FROM $tableName WHERE temp_id = ? LIMIT 1";
    $stmt = $dbConn->prepare($query);
    
    if($stmt) {
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['name'];
        }
        $stmt->close();
    }
    
    return 'Unknown';
}

/**
 * NEW FUNCTION: Reverse advance payment credit when return is deleted
 * UPDATED: Uses reference_number to find records
 */
function reverseAdvancePaymentCreditForReturn(
    mysqli $dbConn,
    string $returnId,
    string $invNumber,
    string $deletionDate,
    string $deletedByUserId,
    string $deletedByUserType,
    string $reason = 'Return deleted'
): array {
    $result = [
        'success' => false,
        'message' => '',
        'reversed_amount' => 0.00
    ];

    try {
        // Find advance payment records for this return using reference_number
        $referenceNumber = "CN-" . $returnId;
        $stmt = $dbConn->prepare("
            SELECT id, amount, balance_amount, from_user_id, from_user_type
            FROM advance_payments
            WHERE deleted_at IS NULL
              AND payment_mode = 'credit_note'
              AND reference_number = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $dbConn->error);
        }

        $stmt->bind_param("s", $referenceNumber);
        $stmt->execute();
        $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($payments)) {
            $result['success'] = true;
            $result['message'] = "No advance payment credit found for this return";
            return $result;
        }

        $dbConn->begin_transaction();

        foreach ($payments as $payment) {
            $paymentId = intval($payment['id']);
            $originalAmount = floatval($payment['amount']);
            $remainingBalance = floatval($payment['balance_amount']);

            // Soft delete the advance payment record
            $stmt = $dbConn->prepare("
                UPDATE advance_payments
                SET deleted_at = NOW(),
                    status = 'cancelled'
                WHERE id = ?
            ");

            $stmt->bind_param("i", $paymentId);
            $stmt->execute();
            $stmt->close();

            // If any amount was already consumed, we need to add negative adjustment
            $consumedAmount = $originalAmount - $remainingBalance;
            
            if ($consumedAmount > 0) {
                // Create negative adjustment to reverse consumed portion
                $stmt = $dbConn->prepare("
                    INSERT INTO advance_payment_adjustments (
                        advance_payment_id,
                        invoice_id,
                        invoice_number,
                        adjusted_amount,
                        adjustment_date,
                        adjustment_type,
                        adjusted_by_user_id,
                        adjusted_by_user_type,
                        remarks
                    ) VALUES (?, NULL, ?, ?, ?, 'return_reversal', ?, ?, ?)
                ");

                $negativeAmount = -$consumedAmount;
                $remarks = "Return deleted - Reversal of credit note - Return ID: $returnId - Reason: $reason";

                $stmt->bind_param(
                    "isdsss",
                    $paymentId,
                    $invNumber,
                    $negativeAmount,
                    $deletionDate,
                    $deletedByUserId,
                    $deletedByUserType,
                    $remarks
                );

                $stmt->execute();
                $stmt->close();
            }

            $result['reversed_amount'] += $originalAmount;
        }

        $dbConn->commit();

        $result['success'] = true;
        $result['message'] = "Reversed advance payment credit of Rs." . inr_format($result['reversed_amount'], 2);

        error_log("RETURN REVERSAL SUCCESS: Return ID $returnId, Reversed Amount: " . $result['reversed_amount']);

    } catch (Exception $e) {
        $dbConn->rollback();
        $result['message'] = "Failed to reverse advance payment credit: " . $e->getMessage();
        error_log("RETURN REVERSAL FAILED: " . $e->getMessage());
    }

    return $result;
}


/**
 * Helper: Check if advance payment credit already exists for a return
 * Uses reference_number (CN-ReturnID)
 */
function hasReturnAdvanceCreditByReference(
    mysqli $dbConn,
    string $referenceNumber
): bool {
    $stmt = $dbConn->prepare("
        SELECT COUNT(*) AS cnt
        FROM advance_payments
        WHERE deleted_at IS NULL
          AND reference_number = ?
    ");

    if (!$stmt) {
        error_log("hasReturnAdvanceCreditByReference prepare failed: " . $dbConn->error);
        return false;
    }

    $stmt->bind_param("s", $referenceNumber);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ((int)$row['cnt'] > 0);
}


function getAdvancePaymentSummary(
    mysqli $dbConn,
    string $customerId,
    string $customerType,
    string $toUserId = ''
): array {
    $summary = [
        'total_paid' => 0.00,
        'total_adjusted' => 0.00,
        'available_balance' => 0.00,
        'payment_count' => 0,
        'is_mandatory' => isAdvancePaymentMandatory($customerType)
    ];

    if (!$summary['is_mandatory']) {
        return $summary;
    }

    // to_user_id filter REMOVED
    $query = "SELECT
                COUNT(*) as payment_count,
                COALESCE(SUM(amount), 0) as total_paid,
                COALESCE(SUM(adjusted_amount), 0) as total_adjusted,
                COALESCE(SUM(balance_amount), 0) as available_balance
              FROM advance_payments
              WHERE deleted_at IS NULL
                AND from_user_id = ?
                AND from_user_type = ?";

    $stmt = $dbConn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ss", $customerId, $customerType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $summary['payment_count'] = (int)$row['payment_count'];
            $summary['total_paid'] = (float)$row['total_paid'];
            $summary['total_adjusted'] = (float)$row['total_adjusted'];
            $summary['available_balance'] = (float)$row['available_balance'];
        }
        $stmt->close();
    }

    return $summary;
}

/**
 * Legacy function (unchanged)
 */
function consumeAdvancePayment($db_conn, $user_id, $user_type, $amount, $invoice_id, $invoice_number) {

    $stmt = mysqli_prepare($db_conn,
        "SELECT id, balance_amount
         FROM advance_payments
         WHERE user_id = ?
           AND user_type = ?
           AND balance_amount > 0
           AND status = 'active'
         ORDER BY payment_date ASC, id ASC"
    );

    mysqli_stmt_bind_param($stmt, "ss", $user_id, $user_type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $available_advances = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $total_available = array_sum(array_column($available_advances, 'balance_amount'));
    if ($total_available < $amount) return false;

    $remaining = $amount;

    foreach ($available_advances as $advance) {
        if ($remaining <= 0) break;

        $to_consume = min($remaining, (float)$advance['balance_amount']);

        $stmt = mysqli_prepare($db_conn,
            "UPDATE advance_payments
             SET used_amount = used_amount + ?,
                 balance_amount = balance_amount - ?
             WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "ddi", $to_consume, $to_consume, $advance['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $remaining -= $to_consume;
    }

    return true;
}

/**
 * ============================================================================
 * RETURN QUANTITY VALIDATION FUNCTIONS
 * ============================================================================
 */

/**
 * Get already returned quantity for a specific invoice-product combination
 * 
 * @param mysqli $dbConn Database connection
 * @param string $invid Invoice ID
 * @param string $prid Product ID
 * @param string|null $current_returnid Current return ID to exclude from calculation (for editing)
 * @return int Total quantity already returned (excluding current return)
 */
function getAlreadyReturnedQty(
    mysqli $dbConn,
    string $invid,
    string $prid,
    ?string $current_returnid = null
): int {
    // Sanitize inputs to prevent SQL injection
    $invid = mysqli_real_escape_string($dbConn, $invid);
    $prid = mysqli_real_escape_string($dbConn, $prid);
    
    // Build exclusion clause for current return
    $exclude_clause = '';
    if ($current_returnid !== null && $current_returnid !== '') {
        $current_returnid = mysqli_real_escape_string($dbConn, $current_returnid);
        $exclude_clause = " AND returnid != '$current_returnid'";
    }
    
    // Query to sum all returned quantities for this invoice+product combination
    // Includes BOTH 'pending' and 'accept'/'completed' status to prevent duplicate returns
    $query = "
        SELECT COALESCE(SUM(qty), 0) AS total_returned
        FROM user_return_stock_items
        WHERE invnumber = '$invid'
          AND prid = '$prid'
          AND status IN ('pending', 'accept', 'completed')
          $exclude_clause
    ";
    
    $result = mysqli_query($dbConn, $query);
    
    if (!$result) {
        error_log("getAlreadyReturnedQty ERROR: " . mysqli_error($dbConn));
        return 0;
    }
    
    $row = mysqli_fetch_assoc($result);
    return (int)($row['total_returned'] ?? 0);
}

/**
 * Get detailed return availability information for an invoice-product combination
 * Shows original qty, already returned qty, and available qty for return
 * 
 * @param mysqli $dbConn Database connection
 * @param string $invid Invoice ID  
 * @param string $prid Product ID
 * @param string $from_usertype User type (customer, super_stockiest, etc.)
 * @param string|null $current_returnid Current return ID to exclude
 * @return array Contains original_qty, returned_qty, available_qty, damaged_qty, error
 */
function getReturnAvailability(
    mysqli $dbConn,
    string $invid,
    string $prid,
    string $from_usertype,
    ?string $current_returnid = null
): array {
    // Sanitize inputs
    $invid = mysqli_real_escape_string($dbConn, $invid);
    $prid = mysqli_real_escape_string($dbConn, $prid);
    $from_usertype = mysqli_real_escape_string($dbConn, $from_usertype);
    
    // Determine correct table based on user type
    $item_table = ($from_usertype === 'customer') ? 'invoice_items' : 'user_invoice_items';
    
    // Get original invoice quantity
    $inv_query = "
        SELECT qty 
        FROM $item_table 
        WHERE inv_id = '$invid' 
          AND pr_id = '$prid'
        LIMIT 1
    ";
    
    $inv_result = mysqli_query($dbConn, $inv_query);
    
    if (!$inv_result || mysqli_num_rows($inv_result) === 0) {
        return [
            'original_qty' => 0,
            'returned_qty' => 0,
            'available_qty' => 0,
            'damaged_qty' => 0,
            'error' => 'Product not found in invoice'
        ];
    }
    
    $inv_item = mysqli_fetch_assoc($inv_result);
    $original_qty = (int)$inv_item['qty'];
    
    // Get already returned quantity (excludes current return if provided)
    $returned_qty = getAlreadyReturnedQty($dbConn, $invid, $prid, $current_returnid);
    
    // Get total damaged quantity
    $exclude_clause = '';
    if ($current_returnid !== null && $current_returnid !== '') {
        $current_returnid_escaped = mysqli_real_escape_string($dbConn, $current_returnid);
        $exclude_clause = " AND returnid != '$current_returnid_escaped'";
    }
    
    $damaged_query = "
        SELECT COALESCE(SUM(damaged_qty), 0) AS total_damaged
        FROM user_return_stock_items
        WHERE invnumber = '$invid'
          AND prid = '$prid'
          AND status IN ('pending', 'accept', 'completed')
          $exclude_clause
    ";
    
    $damaged_result = mysqli_query($dbConn, $damaged_query);
    $damaged_row = mysqli_fetch_assoc($damaged_result);
    $damaged_qty = (int)($damaged_row['total_damaged'] ?? 0);
    
    // Calculate available quantity for return
    $available_qty = $original_qty - $returned_qty;
    
    return [
        'original_qty' => $original_qty,
        'returned_qty' => $returned_qty,
        'available_qty' => max(0, $available_qty), // Never return negative
        'damaged_qty' => $damaged_qty,
        'error' => null
    ];
}