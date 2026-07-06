<?php
require_once("checksession.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);


/**
 * Advance Payment Reconciliation Script
 * 
 * Purpose: Retroactively convert paid invoices to use advance payment system
 * 
 * Features:
 * - Dry Run Mode: Preview changes without executing
 * - Execution Mode: Process invoices and update advance payments
 * - Rollback Mode: Revert changes from a previous execution
 * 
 * @version 1.1
 * @date 2026-01-22
 * 
 * CHANGELOG v1.1:
 * - Fixed: Balance query now checks customer's total advance balance regardless of to_user_id
 * - Fixed: Properly handles customers with no advance payment records
 * - Improved: Better error handling and validation
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging

// Try to include required files with error handling
try {
    if (file_exists("config.php")) {
        include("config.php");
    } else {
        throw new Exception("config.php not found");
    }
    
    if (file_exists("advance-payment-functions.php")) {
        require_once("advance-payment-functions.php");
    } else {
        throw new Exception("advance-payment-functions.php not found");
    }
    
    // Check if database connection exists
    if (!isset($db_conn) || !$db_conn) {
        throw new Exception("Database connection not established");
    }
} catch (Exception $e) {
    die("
    <html>
    <head><title>Configuration Error</title></head>
    <body style='font-family: Arial; padding: 50px; background: #fee2e2;'>
        <h1 style='color: #991b1b;'>Configuration Error</h1>
        <p style='color: #7f1d1d;'>" . htmlspecialchars($e->getMessage()) . "</p>
        <p><strong>Please ensure:</strong></p>
        <ul>
            <li>config.php exists in the same directory</li>
            <li>advance-payment-functions.php exists in the same directory</li>
            <li>Database connection is properly configured</li>
        </ul>
    </body>
    </html>
    ");
}

// ============================================================================
// EXECUTION LOG FUNCTIONS
// ============================================================================

/**
 * Create execution log table if not exists
 */
function createExecutionLogTable($db_conn) {
    $sql = "CREATE TABLE IF NOT EXISTS advance_payment_reconciliation_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        execution_id VARCHAR(50) NOT NULL,
        execution_date DATETIME NOT NULL,
        mode ENUM('dry_run', 'execution', 'rollback') NOT NULL,
        date_from DATE NOT NULL,
        date_to DATE NOT NULL,
        total_invoices INT DEFAULT 0,
        successful_invoices INT DEFAULT 0,
        failed_invoices INT DEFAULT 0,
        total_amount DECIMAL(15,2) DEFAULT 0,
        executed_by VARCHAR(100),
        status ENUM('started', 'completed', 'failed') DEFAULT 'started',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_execution_id (execution_id),
        INDEX idx_execution_date (execution_date)
    )";
    mysqli_query($db_conn, $sql);
}

/**
 * Create detailed invoice log table
 */
function createInvoiceLogTable($db_conn) {
    $sql = "CREATE TABLE IF NOT EXISTS advance_payment_reconciliation_invoice_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        execution_id VARCHAR(50) NOT NULL,
        invoice_id VARCHAR(100) NOT NULL,
        invoice_number VARCHAR(100) NOT NULL,
        customer_id VARCHAR(100) NOT NULL,
        customer_type VARCHAR(50) NOT NULL,
        customer_name VARCHAR(255),
        invoice_amount DECIMAL(15,2) NOT NULL,
        balance_before DECIMAL(15,2) NOT NULL,
        balance_after DECIMAL(15,2) NOT NULL,
        receipts_updated INT DEFAULT 0,
        old_receipt_data TEXT,
        status ENUM('success', 'failed', 'insufficient_balance') NOT NULL,
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_execution_id (execution_id),
        INDEX idx_invoice_id (invoice_id)
    )";
    mysqli_query($db_conn, $sql);
}

/**
 * Log execution start
 */
function logExecutionStart($db_conn, $execution_id, $mode, $date_from, $date_to, $executed_by = 'Admin') {
    $stmt = $db_conn->prepare("
        INSERT INTO advance_payment_reconciliation_log 
        (execution_id, execution_date, mode, date_from, date_to, executed_by, status)
        VALUES (?, NOW(), ?, ?, ?, ?, 'started')
    ");
    $stmt->bind_param("sssss", $execution_id, $mode, $date_from, $date_to, $executed_by);
    $stmt->execute();
    $stmt->close();
}

/**
 * Update execution log
 */
function logExecutionComplete($db_conn, $execution_id, $total, $successful, $failed, $amount) {
    $stmt = $db_conn->prepare("
        UPDATE advance_payment_reconciliation_log 
        SET total_invoices = ?, successful_invoices = ?, failed_invoices = ?, 
            total_amount = ?, status = 'completed'
        WHERE execution_id = ?
    ");
    $stmt->bind_param("iiids", $total, $successful, $failed, $amount, $execution_id);
    $stmt->execute();
    $stmt->close();
}


/**
 * UPDATED: Log individual invoice processing - Added 'partial' status
 * 
 * @version 2.0 - Added partial payment support
 */
function logInvoiceProcess($db_conn, $execution_id, $invoice_data, $status, $error = null) {
    $stmt = $db_conn->prepare("
        INSERT INTO advance_payment_reconciliation_invoice_log
        (execution_id, invoice_id, invoice_number, customer_id, customer_type, customer_name,
         invoice_amount, balance_before, balance_after, receipts_updated, old_receipt_data, 
         status, error_message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "ssssssdddisss",
        $execution_id,
        $invoice_data['invoice_id'],
        $invoice_data['invoice_number'],
        $invoice_data['customer_id'],
        $invoice_data['customer_type'],
        $invoice_data['customer_name'],
        $invoice_data['invoice_amount'],
        $invoice_data['balance_before'],
        $invoice_data['balance_after'],
        $invoice_data['receipts_updated'],
        $invoice_data['old_receipt_data'],
        $status,
        $error
    );
    
    $stmt->execute();
    $stmt->close();
}


// ============================================================================
// DATA FETCHING FUNCTIONS
// ============================================================================

/**
 * Fetch eligible invoices for reconciliation
 */
function fetchEligibleInvoices($db_conn, $date_from, $date_to) {
    $invoices = [];
    
    // Query to get fully paid invoices to Super Stockist and Stockist
    // Includes invoices from Company to SS/ST AND from Super Stockist to Stockist
    $sql = "
        SELECT 
            ui.inv_id,
            ui.inv_number,
            ui.date as invoice_date,
            ui.total as total_amount,
            ui.courier_charges,
            (ui.total - ui.courier_charges) as invoice_amount,
            ui.to_user_type as customer_type,
            ui.to_user_id as customer_id,
            ui.from_user_type,
            ui.from_user_id,
            COALESCE(SUM(r.received), 0) as total_received
        FROM user_invoice ui
        LEFT JOIN receipt r ON r.inv_id = ui.inv_id AND r.received > 0
        WHERE ui.date BETWEEN ? AND ?
        AND ui.to_user_type IN ('super_stockiest', 'stockiest')
        AND ui.from_user_type IN ('company', 'super_stockiest')
        GROUP BY ui.inv_id, ui.inv_number, ui.date, ui.total, ui.courier_charges, 
                 ui.to_user_type, ui.to_user_id, ui.from_user_type, ui.from_user_id
        HAVING total_received >= ui.total
    ";
    
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get customer name
        $customer_name = getCustomerName($db_conn, $row['customer_id'], $row['customer_type']);
        $row['customer_name'] = $customer_name;
        
        // Get current advance balance (FIXED: No longer filters by to_user_id)
        $balance = getCurrentAdvanceBalance($db_conn, $row['customer_id'], $row['customer_type']);
        $row['current_balance'] = $balance;
        
        // Get receipts for this invoice
        $receipts = getInvoiceReceipts($db_conn, $row['inv_id']);
        $row['receipts'] = $receipts;
        $row['receipt_count'] = count($receipts);
        
        // Check if already has advance payment receipts
        $has_advance_receipt = false;
        foreach ($receipts as $receipt) {
            if ($receipt['receipt_method'] === 'Advance Payment') {
                $has_advance_receipt = true;
                break;
            }
        }
        $row['already_processed'] = $has_advance_receipt;
        
        $invoices[] = $row;
    }
    
    $stmt->close();
    
    return $invoices;
}

/**
 * Get customer name
 */
function getCustomerName($db_conn, $customer_id, $customer_type) {
    $table_map = [
        'super_stockiest' => 'super_stockiest',
        'stockiest' => 'stockiest'
    ];
    
    if (!isset($table_map[$customer_type])) {
        return 'Unknown';
    }
    
    $table = $table_map[$customer_type];
    $stmt = $db_conn->prepare("SELECT name FROM $table WHERE temp_id = ?");
    $stmt->bind_param("s", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['name'] ?? 'Unknown';
}

/**
 * Get current advance balance
 * 
 * FIXED v1.1: Now checks total balance regardless of to_user_id
 * The customer's advance payment can be to any company - we just need to know their total balance
 */
function getCurrentAdvanceBalance($db_conn, $customer_id, $customer_type) {
    global $debug_output; // Store debug info for display
    
    if (!isset($debug_output)) {
        $debug_output = [];
    }
    
    // FIXED: Removed to_user_id filter - just check if customer has any advance balance
    $stmt = $db_conn->prepare("
        SELECT COALESCE(SUM(balance_amount), 0) as total_balance
        FROM advance_payments
        WHERE from_user_id = ? 
        AND from_user_type = ?
        AND status IN ('active', 'partially_adjusted')
        AND deleted_at IS NULL
    ");
    
    $stmt->bind_param("ss", $customer_id, $customer_type);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $balance = floatval($result['total_balance']);
    
    // Debug: Get all advance payments for this customer for diagnostic purposes
    $debug_stmt = $db_conn->prepare("
        SELECT id, to_user_id, to_user_type, balance_amount, status
        FROM advance_payments
        WHERE from_user_id = ? 
        AND from_user_type = ?
        AND deleted_at IS NULL
        ORDER BY id DESC
        LIMIT 5
    ");
    $debug_stmt->bind_param("ss", $customer_id, $customer_type);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    
    $payments = [];
    while ($debug_row = $debug_result->fetch_assoc()) {
        $payments[] = $debug_row;
    }
    $debug_stmt->close();
    
    // Store debug info
    $debug_output[$customer_id] = [
        'customer_id' => $customer_id,
        'customer_type' => $customer_type,
        'balance_found' => $balance,
        'payments' => $payments,
        'has_payments' => !empty($payments)
    ];
    
    return $balance;
}

/**
 * Get invoice receipts
 */
function getInvoiceReceipts($db_conn, $invoice_id) {
    $receipts = [];
    
    $stmt = $db_conn->prepare("
        SELECT * FROM receipt 
        WHERE inv_id = ? AND received > 0
        ORDER BY id ASC
    ");
    
    $stmt->bind_param("s", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $receipts[] = $row;
    }
    
    $stmt->close();
    
    return $receipts;
}

// ============================================================================
// EXECUTION FUNCTIONS
// ============================================================================



/**
 * CORE ENGINE
 * Used by BOTH Dry Run and Execution
 * Dry Run  => rollback
 * Execution => commit
 */
/**
 * SAFE EXECUTION ENGINE
 * - Used for REAL execution only
 * - Uses existing advance-payment engine
 * - NO manual balance updates
 */
function processInvoicesExecution(mysqli $db, array $invoices, string $execution_id): array
{
    $results = [
        'successful' => [],
        'failed' => [],
        'insufficient_balance' => []
    ];

    foreach ($invoices as $invoice) {

        $invoice_id     = $invoice['invoice_id'];
        $invoice_number = $invoice['invoice_number'];
        $customer_id    = $invoice['customer_id'];
        $customer_type  = $invoice['customer_type'];
        $invoice_amount = floatval($invoice['invoice_amount']);

        $remaining = $invoice_amount;

        // 🔹 Fetch usable advance payments FIFO
        $adv_sql = "
            SELECT id, amount
            FROM advance_payments
            WHERE from_user_id = ?
              AND from_user_type = ?
              AND status IN ('active','partially_adjusted')
              AND balance_amount > 0
            ORDER BY payment_date ASC, id ASC
        ";

        $stmt = $db->prepare($adv_sql);
        $stmt->bind_param("ss", $customer_id, $customer_type);
        $stmt->execute();
        $advances = $stmt->get_result();

        while ($row = $advances->fetch_assoc()) {

            if ($remaining <= 0) break;

            $advance_id = $row['id'];

            // 🔹 Get CURRENT balance from DB (important)
            $bal_sql = "
                SELECT 
                    amount,
                    COALESCE(SUM(apa.adjusted_amount),0) AS used
                FROM advance_payments ap
                LEFT JOIN advance_payment_adjustments apa
                    ON ap.id = apa.advance_payment_id
                WHERE ap.id = ?
                GROUP BY ap.id
            ";

            $b = $db->prepare($bal_sql);
            $b->bind_param("i", $advance_id);
            $b->execute();
            $bal = $b->get_result()->fetch_assoc();

            $available = $bal['amount'] - $bal['used'];
            if ($available <= 0) continue;

            $apply = min($available, $remaining);

            // ✅ INSERT adjustment (ONLY ONCE)
            $ins = $db->prepare("
                INSERT INTO advance_payment_adjustments
                (advance_payment_id, invoice_id, invoice_number, adjusted_amount, adjustment_date, adjustment_type)
                VALUES (?, ?, ?, ?, CURDATE(), 'invoice')
            ");
            $ins->bind_param("iisd", $advance_id, $invoice_id, $invoice_number, $apply);
            $ins->execute();

            // ✅ LOG reconciliation
            $log = $db->prepare("
                INSERT INTO advance_payment_reconciliation_invoice_log
                (execution_id, advance_payment_id, invoice_id, applied_amount)
                VALUES (?, ?, ?, ?)
            ");
            $log->bind_param("siid", $execution_id, $advance_id, $invoice_id, $apply);
            $log->execute();

            $remaining -= $apply;
        }

        // 🔹 Final invoice status
        if ($remaining == 0) {
            $results['successful'][] = [
                'invoice_id' => $invoice_id,
                'invoice_amount' => $invoice_amount
            ];
        } else {
            $results['insufficient_balance'][] = [
                'invoice_id' => $invoice_id,
                'remaining' => $remaining
            ];
        }
    }

    // 🔒 FINAL SYNC (this fixes EVERYTHING)
    $db->query("
        UPDATE advance_payments ap
        SET
            adjusted_amount = (
                SELECT COALESCE(SUM(adjusted_amount),0)
                FROM advance_payment_adjustments
                WHERE advance_payment_id = ap.id
            ),
            balance_amount = ap.amount - (
                SELECT COALESCE(SUM(adjusted_amount),0)
                FROM advance_payment_adjustments
                WHERE advance_payment_id = ap.id
            ),
            status = CASE
                WHEN ap.amount = (
                    SELECT COALESCE(SUM(adjusted_amount),0)
                    FROM advance_payment_adjustments
                    WHERE advance_payment_id = ap.id
                ) THEN 'fully_adjusted'
                WHEN (
                    SELECT COALESCE(SUM(adjusted_amount),0)
                    FROM advance_payment_adjustments
                    WHERE advance_payment_id = ap.id
                ) > 0 THEN 'partially_adjusted'
                ELSE 'active'
            END
    ");

    return $results;
}



/**
 * SINGLE SOURCE OF TRUTH ENGINE
 * Dry Run  => $commit = false
 * Execute  => $commit = true
 */
function processInvoices(
    mysqli $db,
    array $invoices,
    string $execution_id,
    bool $commit
) {
    $results = [
        'success' => [],
        'partial' => [],
        'insufficient' => [],
        'failed' => []
    ];

    // FIFO – oldest invoice first
    usort($invoices, fn($a, $b) =>
        strtotime($a['invoice_date']) <=> strtotime($b['invoice_date'])
    );

    $db->begin_transaction();

    try {

        foreach ($invoices as $inv) {

            // Skip already converted invoices
            foreach ($inv['receipts'] as $r) {
                if ($r['receipt_method'] === 'Advance Payment') {
                    continue 2;
                }
            }

            $invoiceAmount = (float)$inv['invoice_amount'];

            // Fetch FIFO advance rows
            $stmt = $db->prepare("
                SELECT id, balance_amount
                FROM advance_payments
                WHERE from_user_id = ?
                  AND from_user_type = ?
                  AND balance_amount > 0
                  AND deleted_at IS NULL
                ORDER BY payment_date ASC, id ASC
                FOR UPDATE
            ");
            $stmt->bind_param("ss", $inv['customer_id'], $inv['customer_type']);
            $stmt->execute();
            $advances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $totalBalance = array_sum(array_column($advances, 'balance_amount'));

            if ($totalBalance <= 0) {
                $results['insufficient'][] = $inv;
                continue;
            }

            $amountToAdjust = min($invoiceAmount, $totalBalance);
            $remaining = $amountToAdjust;
            $balanceBefore = $totalBalance;

            foreach ($advances as $adv) {
                if ($remaining <= 0) break;

                $deduct = min($remaining, (float)$adv['balance_amount']);
                $newBalance = $adv['balance_amount'] - $deduct;

                if ($commit) {
                    // Update wallet
                    $upd = $db->prepare("
                        UPDATE advance_payments
                        SET balance_amount = ?,
                            adjusted_amount = adjusted_amount + ?,
                            status = CASE
                                WHEN ? = 0 THEN 'fully_adjusted'
                                ELSE 'partially_adjusted'
                            END,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $upd->bind_param("dddi", $newBalance, $deduct, $newBalance, $adv['id']);
                    $upd->execute();
                    $upd->close();

                    // Ledger entry (DASHBOARD SOURCE)
                    $adj = $db->prepare("
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

                    $balanceAfter = $balanceBefore - $deduct;
                    $remarks = 'Advance reconciliation';

                    $adj->bind_param(
                        "issdsddsss",
                        $adv['id'],
                        $inv['inv_id'],
                        $inv['inv_number'],
                        $deduct,
                        $inv['invoice_date'],
                        $balanceBefore,
                        $balanceAfter,
                        $_SESSION['LOGIN_USER_ID'],
                        $_SESSION['LOGIN_USER_TYPE'],
                        $remarks
                    );
                    $adj->execute();
                    $adj->close();
                }

                $remaining -= $deduct;
                $balanceBefore -= $deduct;
            }

            // Receipt conversion (NO DOUBLE MONEY)
            if ($commit && !empty($inv['receipts'])) {
                $r = $inv['receipts'][0];

                $stmt = $db->prepare("
                    UPDATE receipt
                    SET received = ?,
                        receipt_method = 'Advance Payment',
                        receipt_remarks = ?
                    WHERE id = ?
                ");
                $remark = $amountToAdjust < $invoiceAmount
                    ? 'Partial advance reconciliation'
                    : 'Full advance reconciliation';

                $stmt->bind_param("dsi", $amountToAdjust, $remark, $r['id']);
                $stmt->execute();
                $stmt->close();
            }

            // Reconciliation log (audit)
            if ($commit) {
                logInvoiceProcess(
                    $db,
                    $execution_id,
                    [
                        'invoice_id' => $inv['inv_id'],
                        'invoice_number' => $inv['inv_number'],
                        'customer_id' => $inv['customer_id'],
                        'customer_type' => $inv['customer_type'],
                        'customer_name' => $inv['customer_name'],
                        'invoice_amount' => $invoiceAmount,
                        'balance_before' => $totalBalance,
                        'balance_after' => $totalBalance - $amountToAdjust,
                        'receipts_updated' => 1,
                        'old_receipt_data' => json_encode($inv['receipts'])
                    ],
                    $amountToAdjust < $invoiceAmount ? 'partial' : 'success'
                );
            }

            if ($amountToAdjust < $invoiceAmount) {
                $results['partial'][] = $inv;
            } else {
                $results['success'][] = $inv;
            }
        }

        $commit ? $db->commit() : $db->rollback();
        return $results;

    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}



/**
 * CORE ENGINE
 * - Used by BOTH Dry Run and Execution
 * - Dry Run  => rollback
 * - Execution => commit
 */



/**
 * ✅ FIXED: Single source of truth - only insert adjustments, sync balance via query
 */
function processSingleInvoice(mysqli $db_conn, array $invoice, string $execution_id)
{
    // Skip already processed invoices
    foreach ($invoice['receipts'] as $r) {
        if ($r['receipt_method'] === 'Advance Payment') {
            throw new Exception('Already processed');
        }
    }

    $invoiceAmount = (float)$invoice['invoice_amount'];

    // 🔎 Get available advance payments (FIFO - oldest first)
    $stmt = $db_conn->prepare("
        SELECT id, amount, adjusted_amount, balance_amount
        FROM advance_payments
        WHERE from_user_id = ?
          AND from_user_type = ?
          AND status IN ('active', 'partially_adjusted')
          AND balance_amount > 0
          AND deleted_at IS NULL
        ORDER BY payment_date ASC, id ASC
        FOR UPDATE
    ");
    $stmt->bind_param("ss", $invoice['customer_id'], $invoice['customer_type']);
    $stmt->execute();
    $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$payments) {
        return ['status' => 'insufficient_balance'];
    }

    $totalBalance = array_sum(array_column($payments, 'balance_amount'));

    if ($totalBalance <= 0) {
        return ['status' => 'insufficient_balance'];
    }

    $amountToAdjust = min($totalBalance, $invoiceAmount);
    $isPartial = $amountToAdjust < $invoiceAmount;

    $remaining = $amountToAdjust;

    // 🔁 FIFO deduction - ONLY insert adjustments
    foreach ($payments as $pay) {
        if ($remaining <= 0) break;

        $availableInThisPayment = (float)$pay['balance_amount'];
        $deduct = min($remaining, $availableInThisPayment);

        // ✅ ONLY INSERT ADJUSTMENT - Don't touch balance_amount directly
        $adj = $db_conn->prepare("
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
            ) VALUES (?, ?, ?, ?, ?, 'invoice', ?, ?, ?)
        ");

        $remarks = 'Advance reconciliation';
        $today = date('Y-m-d');
        $user_id = $_SESSION['LOGIN_USER_ID'] ?? 'system';
        $user_type = $_SESSION['LOGIN_USER_TYPE'] ?? 'admin';

        $adj->bind_param(
            "iisdsiss",
            $pay['id'],
            $invoice['inv_id'],
            $invoice['inv_number'],
            $deduct,
            $today,
            $user_id,
            $user_type,
            $remarks
        );
        $adj->execute();
        $adj->close();

        $remaining -= $deduct;
    }

    // ✅ NOW sync all advance_payments for this customer (prevents double deduction)
    $syncStmt = $db_conn->prepare("
        UPDATE advance_payments ap
        SET 
            adjusted_amount = (
                SELECT COALESCE(SUM(adjusted_amount), 0)
                FROM advance_payment_adjustments
                WHERE advance_payment_id = ap.id
            ),
            balance_amount = ap.amount - (
                SELECT COALESCE(SUM(adjusted_amount), 0)
                FROM advance_payment_adjustments
                WHERE advance_payment_id = ap.id
            ),
            status = CASE
                WHEN ap.amount <= (
                    SELECT COALESCE(SUM(adjusted_amount), 0)
                    FROM advance_payment_adjustments
                    WHERE advance_payment_id = ap.id
                ) THEN 'fully_adjusted'
                WHEN (
                    SELECT COALESCE(SUM(adjusted_amount), 0)
                    FROM advance_payment_adjustments
                    WHERE advance_payment_id = ap.id
                ) > 0 THEN 'partially_adjusted'
                ELSE 'active'
            END,
            updated_at = NOW()
        WHERE from_user_id = ? 
          AND from_user_type = ?
          AND deleted_at IS NULL
    ");
    $syncStmt->bind_param("ss", $invoice['customer_id'], $invoice['customer_type']);
    $syncStmt->execute();
    $syncStmt->close();

    // 🧾 Update receipt to reflect advance payment
    if (!empty($invoice['receipts'])) {
        $r = $invoice['receipts'][0];

        $remark = $isPartial
            ? 'Partial advance reconciliation'
            : 'Full advance reconciliation';

        $receiptStmt = $db_conn->prepare("
            UPDATE receipt
            SET received = ?,
                receipt_method = 'Advance Payment',
                receipt_remarks = ?
            WHERE id = ?
        ");
        $receiptStmt->bind_param("dsi", $amountToAdjust, $remark, $r['id']);
        $receiptStmt->execute();
        $receiptStmt->close();
    }

    // 🧾 Log this invoice processing
    logInvoiceProcess(
        $db_conn,
        $execution_id,
        [
            'invoice_id' => $invoice['inv_id'],
            'invoice_number' => $invoice['inv_number'],
            'customer_id' => $invoice['customer_id'],
            'customer_type' => $invoice['customer_type'],
            'customer_name' => $invoice['customer_name'],
            'invoice_amount' => $invoiceAmount,
            'balance_before' => $totalBalance,
            'balance_after' => $totalBalance - $amountToAdjust,
            'receipts_updated' => 1,
            'old_receipt_data' => json_encode($invoice['receipts'])
        ],
        $isPartial ? 'partial' : 'success'
    );

    // ✅ Return structured data for UI
    return [
        'status' => $isPartial ? 'partial' : 'success',
        'data' => [
            'inv_number' => $invoice['inv_number'],
            'customer_name' => $invoice['customer_name'],
            'customer_type' => $invoice['customer_type'],
            'invoice_amount' => $invoiceAmount,
            'amount_paid' => $amountToAdjust,
            'remaining_amount' => $invoiceAmount - $amountToAdjust,
            'balance_before' => $totalBalance,
            'balance_after' => $totalBalance - $amountToAdjust,
            'current_balance' => $totalBalance,
            'receipt_count' => count($invoice['receipts'])
        ]
    ];
}


/**
 * ✅ FIXED: Properly return results for UI display
 */
function processInvoicesCore(mysqli $db_conn, array $invoices, string $execution_id, bool $commit = false)
{
    $results = [
        'successful' => [],
        'partial' => [],
        'failed' => [],
        'insufficient_balance' => []
    ];

    // FIFO: oldest invoices first
    usort($invoices, function ($a, $b) {
        return strtotime($a['invoice_date']) <=> strtotime($b['invoice_date']);
    });

    // 🔒 ONE transaction for everything
    $db_conn->begin_transaction();

    try {
        foreach ($invoices as $invoice) {
            try {
                $outcome = processSingleInvoice($db_conn, $invoice, $execution_id);

                if ($outcome['status'] === 'success') {
                    $results['successful'][] = $outcome['data'];
                } elseif ($outcome['status'] === 'partial') {
                    $results['partial'][] = $outcome['data'];
                } elseif ($outcome['status'] === 'insufficient_balance') {
                    $results['insufficient_balance'][] = $invoice;
                }

            } catch (Exception $e) {
                $results['failed'][] = [
                    'invoice' => $invoice,
                    'error' => $e->getMessage()
                ];
            }
        }

        // ✅ EXECUTION vs DRY RUN
        if ($commit) {
            $db_conn->commit();
        } else {
            $db_conn->rollback(); // DRY RUN
        }

        return $results;

    } catch (Exception $e) {
        $db_conn->rollback();
        throw $e;
    }
}




/**
 * Rollback execution
 */
function rollbackExecution($db_conn, $execution_id) {
    $results = [
        'successful' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    // Get all processed invoices from this execution
    $stmt = $db_conn->prepare("
        SELECT * FROM advance_payment_reconciliation_invoice_log
        WHERE execution_id = ? AND status = 'success'
    ");
    $stmt->bind_param("s", $execution_id);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($logs as $log) {
        $db_conn->begin_transaction();
        
        try {
            // Restore advance payment balance
            $restoreResult = restoreAdvancePaymentOnInvoiceEdit(
                $db_conn,
                $log['invoice_id'],
                $log['invoice_number'],
                date('Y-m-d'),
                "Rollback of reconciliation script execution: {$execution_id}",
                '', // company_id - will be fetched internally
                ''  // company_type - will be fetched internally
            );
            
            if (!$restoreResult['success']) {
                throw new Exception("Failed to restore balance: " . $restoreResult['message']);
            }
            
            // Restore original receipt methods
            $old_receipts = json_decode($log['old_receipt_data'], true);
            if ($old_receipts && is_array($old_receipts)) {
                foreach ($old_receipts as $receipt) {
                    $stmt = $db_conn->prepare("
                        UPDATE receipt 
                        SET receipt_method = ?,
                            receipt_remarks = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ssi", $receipt['receipt_method'], $receipt['receipt_remarks'], $receipt['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $db_conn->commit();
            $results['successful']++;
            
        } catch (Exception $e) {
            $db_conn->rollback();
            $results['failed']++;
            $results['errors'][] = [
                'invoice' => $log['invoice_number'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $results;
}

// ============================================================================
// INITIALIZE TABLES
// ============================================================================

createExecutionLogTable($db_conn);
createInvoiceLogTable($db_conn);

// ============================================================================
// HANDLE FORM SUBMISSION
// ============================================================================

$execution_results = null;
$dry_run_results = null;
$rollback_results = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $mode = $_POST['mode'] ?? '';
        $date_from = $_POST['date_from'] ?? '';
        $date_to = $_POST['date_to'] ?? '';
        $execution_id_for_rollback = $_POST['execution_id_rollback'] ?? '';
        
        if (empty($mode)) {
            throw new Exception("Please select an operation mode");
        }
        
        if ($mode === 'dry_run' && $date_from && $date_to) {

            // Fetch invoices only (read-only)
            $invoices = fetchEligibleInvoices($db_conn, $date_from, $date_to);
        
            // IMPORTANT:
            // Dry run UI already simulates FIFO in PHP
            // DO NOT call processInvoices() here
            // DO NOT generate execution_id
            // DO NOT touch DB
        
            $dry_run_results = [
                'invoices'  => $invoices,
                'date_from' => $date_from,
                'date_to'   => $date_to
            ];
        }



        elseif ($mode === 'execution' && $date_from && $date_to) {
    // Generate execution ID
    $execution_id = 'RECON_' . date('YmdHis') . '_' . rand(1000, 9999);

    // Log execution start
    logExecutionStart(
        $db_conn,
        $execution_id,
        'execution',
        $date_from,
        $date_to,
        $_SESSION['LOGIN_USER_ID'] ?? 'Admin'
    );

    // Fetch eligible invoices
    $invoices = fetchEligibleInvoices($db_conn, $date_from, $date_to);

    // ✅ Process with commit = true
    $results = processInvoicesCore(
        $db_conn,
        $invoices,
        $execution_id,
        true  // COMMIT
    );

    // Calculate stats
    $totalInvoices = count($invoices);
    $successful = count($results['successful']);
    $partial = count($results['partial']);
    $failed = count($results['failed']) + count($results['insufficient_balance']);

    $totalAmount = array_sum(array_column($results['successful'], 'invoice_amount'));
    $partialAmount = array_sum(array_column($results['partial'], 'amount_paid'));

    // Update execution log
    logExecutionComplete(
        $db_conn,
        $execution_id,
        $totalInvoices,
        $successful + $partial,
        $failed,
        $totalAmount + $partialAmount
    );

    // ✅ CRITICAL: Set results for UI display
    $execution_results = [
        'execution_id' => $execution_id,
        'results' => $results,
        'date_from' => $date_from,
        'date_to' => $date_to
    ];
}


 
        elseif ($mode === 'rollback' && $execution_id_for_rollback) {
            // Rollback mode
            $rollback_results = rollbackExecution($db_conn, $execution_id_for_rollback);
            $rollback_results['execution_id'] = $execution_id_for_rollback;
        } else {
            throw new Exception("Missing required parameters for selected mode");
        }
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        error_log("Reconciliation Script Error: " . $e->getMessage() . " | " . $e->getTraceAsString());
    }
}

// Fetch recent executions for rollback dropdown
$recent_executions = [];
$stmt = $db_conn->prepare("
    SELECT execution_id, execution_date, mode, date_from, date_to, 
           total_invoices, successful_invoices, failed_invoices, total_amount
    FROM advance_payment_reconciliation_log
    WHERE mode = 'execution' AND status = 'completed'
    ORDER BY execution_date DESC
    LIMIT 20
");
$stmt->execute();
$recent_executions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance Payment Reconciliation Script</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.1);
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .card-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #475569;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        thead {
            background: #f8fafc;
        }
        
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        tbody tr:hover {
            background: #f8fafc;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-value.success {
            color: #10b981;
        }
        
        .stat-value.danger {
            color: #ef4444;
        }
        
        .stat-value.warning {
            color: #f59e0b;
        }
        
        .mode-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .mode-card {
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .mode-card:hover {
            border-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }
        
        .mode-card.active {
            border-color: #2563eb;
            background: #eff6ff;
        }
        
        .mode-icon {
            font-size: 36px;
            margin-bottom: 10px;
            color: #2563eb;
        }
        
        .mode-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .mode-desc {
            font-size: 12px;
            color: #64748b;
        }
        
        .invoice-row-success {
            background: #f0fdf4 !important;
        }
        
        .invoice-row-failed {
            background: #fef2f2 !important;
        }
        
        .invoice-row-insufficient {
            background: #fffbeb !important;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .mode-selector {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="material-icons" style="font-size: 36px;">sync_alt</i>
                Advance Payment Reconciliation Script
            </h1>
            <p>Convert fully paid invoices to use advance payment system retrospectively</p>
        </div>

        <!-- Error Message Display -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="material-icons">error</i>
                <div>
                    <strong>Error occurred during execution:</strong>
                    <br/><?= htmlspecialchars($error_message) ?>
                    <br/><br/>
                    <small>Check your error logs for more details.</small>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mode Selection Form -->
        <div class="card">
            <div class="card-header">
                <i class="material-icons">settings</i>
                Select Operation Mode
            </div>
            
            <form method="POST" id="mainForm">
                <div class="mode-selector">
                    <div class="mode-card" onclick="selectMode('dry_run')">
                        <div class="mode-icon">
                            <i class="material-icons" style="font-size: 48px;">visibility</i>
                        </div>
                        <div class="mode-title">Dry Run</div>
                        <div class="mode-desc">Preview changes without executing</div>
                    </div>
                    
                    <div class="mode-card" onclick="selectMode('execution')">
                        <div class="mode-icon">
                            <i class="material-icons" style="font-size: 48px;">play_circle</i>
                        </div>
                        <div class="mode-title">Execution</div>
                        <div class="mode-desc">Process and update invoices</div>
                    </div>
                    
                    <div class="mode-card" onclick="selectMode('rollback')">
                        <div class="mode-icon">
                            <i class="material-icons" style="font-size: 48px;">undo</i>
                        </div>
                        <div class="mode-title">Rollback</div>
                        <div class="mode-desc">Revert previous execution</div>
                    </div>
                </div>
                
                <input type="hidden" name="mode" id="modeInput" value="">
                
                <!-- Date Range Selection (for Dry Run and Execution) -->
                <div id="dateRangeSection" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" id="dateFrom" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" id="dateTo" class="form-control">
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="material-icons">play_arrow</i>
                            <span id="btnText">Run</span>
                        </button>
                    </div>
                </div>
                
                <!-- Rollback Execution Selection -->
                <div id="rollbackSection" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Select Execution to Rollback</label>
                        <select name="execution_id_rollback" id="rollbackSelect" class="form-control">
                            <option value="">-- Select Execution --</option>
                            <?php foreach ($recent_executions as $exec): ?>
                                <option value="<?= htmlspecialchars($exec['execution_id']) ?>">
                                    <?= htmlspecialchars($exec['execution_id']) ?> - 
                                    <?= date('d/M/Y H:i', strtotime($exec['execution_date'])) ?> - 
                                    <?= $exec['successful_invoices'] ?> invoices - 
                                    ₹<?= inr_format($exec['total_amount'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to rollback this execution? This will restore advance balances and revert receipt methods.')">
                            <i class="material-icons">undo</i>
                            Rollback Execution
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dry Run Results -->
        <!-- ✅ UPDATED DRY RUN SECTION - Replace around line 862-925 -->

<?php if ($dry_run_results): ?>
    <?php
    $invoices = $dry_run_results['invoices'];
    $total_invoices = count($invoices);
    
    // ✅ MATCH EXECUTION LOGIC: Sort by date first (FIFO)
    usort($invoices, function($a, $b) {
        return strtotime($a['invoice_date']) - strtotime($b['invoice_date']);
    });
    
    // Group by customer and simulate FIFO processing
    $customer_summary = [];
    $customer_balances = [];  // Track running balance
    $total_amount_all = 0;
    $sufficient_count = 0;
    $partial_count = 0;
    $insufficient_count = 0;
    
    foreach ($invoices as $inv) {
        if ($inv['already_processed']) continue;
        
        $key = $inv['customer_id'];
        $invoice_amt = floatval($inv['invoice_amount']);
        
        // Initialize customer if first time
        if (!isset($customer_summary[$key])) {
            $customer_summary[$key] = [
                'name' => $inv['customer_name'],
                'type' => $inv['customer_type'],
                'balance_before' => $inv['current_balance'],
                'total_invoice_amount' => 0,
                'invoices' => [],
                'fully_paid' => 0,
                'partially_paid' => 0,
                'unpaid' => 0
            ];
            $customer_balances[$key] = floatval($inv['current_balance']);
        }
        
        // ✅ Simulate FIFO: Check current running balance
        $current_balance = $customer_balances[$key];
        
        // Calculate payment status
        if ($current_balance >= $invoice_amt) {
            // Full payment possible
            $customer_summary[$key]['fully_paid']++;
            $customer_balances[$key] -= $invoice_amt;
            $payment_status = 'full';
            $amount_paid = $invoice_amt;
            $remaining = 0;
        } elseif ($current_balance > 0) {
            // Partial payment
            $customer_summary[$key]['partially_paid']++;
            $amount_paid = $current_balance;
            $remaining = $invoice_amt - $current_balance;
            $customer_balances[$key] = 0;
            $payment_status = 'partial';
        } else {
            // No payment possible
            $customer_summary[$key]['unpaid']++;
            $payment_status = 'unpaid';
            $amount_paid = 0;
            $remaining = $invoice_amt;
        }
        
        $customer_summary[$key]['total_invoice_amount'] += $invoice_amt;
        $customer_summary[$key]['invoices'][] = array_merge($inv, [
            'payment_status' => $payment_status,
            'amount_paid' => $amount_paid,
            'remaining' => $remaining
        ]);
        
        $total_amount_all += $invoice_amt;
    }
    
    // Calculate customer-level status
    foreach ($customer_summary as $key => &$customer) {
        $customer['balance_after'] = $customer_balances[$key];
        
        if ($customer['unpaid'] == 0 && $customer['partially_paid'] == 0) {
            // All fully paid
            $customer['status'] = 'sufficient';
            $sufficient_count++;
        } elseif ($customer['unpaid'] > 0 && $customer['fully_paid'] == 0 && $customer['partially_paid'] == 0) {
            // All unpaid
            $customer['status'] = 'insufficient';
            $insufficient_count++;
        } else {
            // Mix of paid/partial/unpaid
            $customer['status'] = 'partial';
            $partial_count++;
        }
    }
    ?>
    
    <div class="card">
        <div class="card-header">
            <i class="material-icons">assessment</i>
            Dry Run Results (<?= $dry_run_results['date_from'] ?> to <?= $dry_run_results['date_to'] ?>)
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Invoices</div>
                <div class="stat-value"><?= $total_invoices ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Amount</div>
                <div class="stat-value">₹<?= inr_format($total_amount_all, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">All Invoices Paid</div>
                <div class="stat-value success"><?= $sufficient_count ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Has Partial/Unpaid</div>
                <div class="stat-value warning"><?= $partial_count + $insufficient_count ?></div>
            </div>
        </div>
        
        <div class="alert alert-info">
            <i class="material-icons">info</i>
            <div>
                <strong>Dry Run Mode:</strong> This preview simulates FIFO processing. 
                Invoices are processed in date order (oldest first), using available advance balance.
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer Name</th>
                        <th>Type</th>
                        <th>Initial Balance</th>
                        <th>Total Invoice Amount</th>
                        <th>Final Balance</th>
                        <th>Invoice Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($customer_summary as $customer): ?>
                        <tr class="<?= 
                            $customer['status'] === 'sufficient' ? 'invoice-row-success' : 
                            ($customer['status'] === 'insufficient' ? 'invoice-row-insufficient' : '') 
                        ?>">
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($customer['name']) ?></strong></td>
                            <td>
                                <span class="badge badge-info">
                                    <?= ucwords(str_replace('_', ' ', $customer['type'])) ?>
                                </span>
                            </td>
                            <td>₹<?= inr_format($customer['balance_before'], 2) ?></td>
                            <td><strong>₹<?= inr_format($customer['total_invoice_amount'], 2) ?></strong></td>
                            <td>₹<?= inr_format($customer['balance_after'], 2) ?></td>
                            <td>
                                <?php if ($customer['fully_paid'] > 0): ?>
                                    <span class="badge badge-success">✓ <?= $customer['fully_paid'] ?> Fully Paid</span>
                                <?php endif; ?>
                                <?php if ($customer['partially_paid'] > 0): ?>
                                    <span class="badge badge-warning">⚡ <?= $customer['partially_paid'] ?> Partial</span>
                                <?php endif; ?>
                                <?php if ($customer['unpaid'] > 0): ?>
                                    <span class="badge badge-danger">✗ <?= $customer['unpaid'] ?> Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <details>
                                    <summary style="cursor: pointer; color: #2563eb;">
                                        <?= count($customer['invoices']) ?> invoices
                                    </summary>
                                    <table style="margin-top: 8px; width: 100%; font-size: 12px;">
                                        <tr style="background: #f8fafc; font-weight: 600;">
                                            <td style="padding: 5px;">Invoice</td>
                                            <td style="padding: 5px;">Amount</td>
                                            <td style="padding: 5px;">Paid</td>
                                            <td style="padding: 5px;">Remaining</td>
                                            <td style="padding: 5px;">Status</td>
                                        </tr>
                                        <?php foreach ($customer['invoices'] as $inv): ?>
                                            <tr style="background: <?= 
                                                $inv['payment_status'] === 'full' ? '#f0fdf4' : 
                                                ($inv['payment_status'] === 'partial' ? '#fffbeb' : '#fef2f2') 
                                            ?>">
                                                <td style="padding: 5px;"><?= htmlspecialchars($inv['inv_number']) ?></td>
                                                <td style="padding: 5px;">₹<?= inr_format($inv['invoice_amount'], 2) ?></td>
                                                <td style="padding: 5px; color: #10b981; font-weight: 600;">
                                                    ₹<?= inr_format($inv['amount_paid'], 2) ?>
                                                </td>
                                                <td style="padding: 5px; color: #ef4444;">
                                                    ₹<?= inr_format($inv['remaining'], 2) ?>
                                                </td>
                                                <td style="padding: 5px;">
                                                    <?php if ($inv['payment_status'] === 'full'): ?>
                                                        ✅ Full
                                                    <?php elseif ($inv['payment_status'] === 'partial'): ?>
                                                        ⚡ Partial
                                                    <?php else: ?>
                                                        ❌ Unpaid
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($insufficient_count > 0 || $partial_count > 0): ?>
            <div class="alert alert-warning" style="margin-top: 20px;">
                <i class="material-icons">info</i>
                <div>
                    <strong>Note:</strong> 
                    <?= $sufficient_count ?> customer(s) have sufficient balance to pay all invoices fully.
                    <?= $partial_count + $insufficient_count ?> customer(s) have insufficient balance and will have partial or unpaid invoices.
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

        <!-- Execution Results -->
        <?php if ($execution_results): ?>
            <?php
                $results = $execution_results['results'];
                $successful = $results['successful'];
                $partial = $results['partial'] ?? [];  // ✅ NEW
                $failed = $results['failed'];
                $insufficient = $results['insufficient_balance'];
                
                $total_successful = count($successful);
                $total_partial = count($partial);  // ✅ NEW
                $total_failed = count($failed) + count($insufficient);
                $total_amount = array_sum(array_column($successful, 'invoice_amount'));
                $total_partial_amount = array_sum(array_column($partial, 'amount_paid'));  // ✅ NEW
                ?>
            
            <div class="card">
                <div class="card-header">
                    <i class="material-icons">check_circle</i>
                    Execution Results
                </div>
                
                <div class="alert alert-success">
                    <i class="material-icons">done</i>
                    <div>
                        <strong>Execution ID:</strong> <?= htmlspecialchars($execution_results['execution_id']) ?>
                        <br/>
                        <strong>Date Range:</strong> <?= htmlspecialchars($execution_results['date_from']) ?> to <?= htmlspecialchars($execution_results['date_to']) ?>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Fully Paid</div>
                        <div class="stat-value success"><?= $total_successful ?></div>
                    </div>
                    
                    <!-- ✅ NEW STAT -->
                    <div class="stat-card">
                        <div class="stat-label">Partially Paid</div>
                        <div class="stat-value warning"><?= $total_partial ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Failed</div>
                        <div class="stat-value danger"><?= $total_failed ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Total Processed</div>
                        <div class="stat-value">₹<?= inr_format($total_amount + $total_partial_amount, 2) ?></div>
                    </div>
                </div>
                
                <?php if ($total_successful > 0): ?>
                    <h3 style="margin: 25px 0 15px; color: #10b981;">✓ Successfully Processed (<?= $total_successful ?>)</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Invoice Number</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Balance Before</th>
                                    <th>Balance After</th>
                                    <th>Receipts Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($successful as $inv): ?>
                                    <?php
                                    $balance_before = floatval($inv['current_balance']);
                                    $balance_after = $balance_before - floatval($inv['invoice_amount']);
                                    ?>
                                    <tr class="invoice-row-success">
                                        <td><?= $i++ ?></td>
                                        <td><strong><?= htmlspecialchars($inv['inv_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= ucwords(str_replace('_', ' ', $inv['customer_type'])) ?>
                                            </span>
                                        </td>
                                        <td>₹<?= inr_format($inv['invoice_amount'], 2) ?></td>
                                        <td>₹<?= inr_format($balance_before, 2) ?></td>
                                        <td>₹<?= inr_format($balance_after, 2) ?></td>
                                        <td><?= $inv['receipt_count'] ?> receipts</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <?php if (count($results['partial']) > 0): ?>
                    <h3 style="margin: 25px 0 15px; color: #f59e0b;">⚡ Partially Paid (<?= count($results['partial']) ?>)</h3>
                    <div class="alert alert-warning">
                        <i class="material-icons">info</i>
                        <div>
                            <strong>Info:</strong> These invoices were partially paid using available advance balance. 
                            Remaining amounts need to be collected separately.
                        </div>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Invoice Number</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Invoice Amount</th>
                                    <th>Amount Paid</th>
                                    <th>Remaining</th>
                                    <th>Balance Before</th>
                                    <th>Balance After</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($results['partial'] as $inv): ?>
                                    <tr style="background: #fffbeb !important;">
                                        <td><?= $i++ ?></td>
                                        <td><strong><?= htmlspecialchars($inv['inv_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= ucwords(str_replace('_', ' ', $inv['customer_type'])) ?>
                                            </span>
                                        </td>
                                        <td>₹<?= inr_format($inv['invoice_amount'], 2) ?></td>
                                        <td style="color: #10b981; font-weight: 600;">₹<?= inr_format($inv['amount_paid'], 2) ?></td>
                                        <td style="color: #ef4444; font-weight: 600;">₹<?= inr_format($inv['remaining_amount'], 2) ?></td>
                                        <td>₹<?= inr_format($inv['balance_before'], 2) ?></td>
                                        <td>₹<?= inr_format($inv['balance_after'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?> 
                
                <?php if (count($insufficient) > 0): ?>
                    <h3 style="margin: 25px 0 15px; color: #f59e0b;">⚠ Insufficient Balance (<?= count($insufficient) ?>)</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Invoice Number</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Required Amount</th>
                                    <th>Available Balance</th>
                                    <th>Shortfall</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($insufficient as $inv): ?>
                                    <?php
                                    $shortfall = floatval($inv['invoice_amount']) - floatval($inv['current_balance']);
                                    ?>
                                    <tr class="invoice-row-insufficient">
                                        <td><?= $i++ ?></td>
                                        <td><strong><?= htmlspecialchars($inv['inv_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($inv['customer_name']) ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?= ucwords(str_replace('_', ' ', $inv['customer_type'])) ?>
                                            </span>
                                        </td>
                                        <td>₹<?= inr_format($inv['invoice_amount'], 2) ?></td>
                                        <td>₹<?= inr_format($inv['current_balance'], 2) ?></td>
                                        <td><strong style="color: #ef4444;">-₹<?= inr_format($shortfall, 2) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <?php if (count($failed) > 0): ?>
                    <h3 style="margin: 25px 0 15px; color: #ef4444;">✗ Failed (<?= count($failed) ?>)</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Invoice Number</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($failed as $fail): ?>
                                    <tr class="invoice-row-failed">
                                        <td><?= $i++ ?></td>
                                        <td><strong><?= htmlspecialchars($fail['invoice']['inv_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($fail['invoice']['customer_name']) ?></td>
                                        <td>₹<?= inr_format($fail['invoice']['invoice_amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($fail['error']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Rollback Results -->
        <?php if ($rollback_results): ?>
            <div class="card">
                <div class="card-header">
                    <i class="material-icons">undo</i>
                    Rollback Results
                </div>
                
                <div class="alert alert-<?= $rollback_results['successful'] > 0 ? 'success' : 'danger' ?>">
                    <i class="material-icons"><?= $rollback_results['successful'] > 0 ? 'check_circle' : 'error' ?></i>
                    <div>
                        <strong>Execution ID:</strong> <?= htmlspecialchars($rollback_results['execution_id']) ?>
                        <br/>
                        <strong>Successful:</strong> <?= intval($rollback_results['successful']) ?> invoices
                        <br/>
                        <strong>Failed:</strong> <?= intval($rollback_results['failed']) ?> invoices
                    </div>
                </div>
                
                <?php if ($rollback_results['failed'] > 0): ?>
                    <div class="alert alert-danger">
                        <i class="material-icons">error</i>
                        <div>
                            <strong>Errors occurred during rollback:</strong>
                            <ul style="margin-top: 10px;">
                                <?php foreach ($rollback_results['errors'] as $error): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($error['invoice']) ?>:</strong> 
                                        <?= htmlspecialchars($error['error']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function selectMode(mode) {
            // Update hidden input
            document.getElementById('modeInput').value = mode;
            
            // Update active state
            document.querySelectorAll('.mode-card').forEach(card => {
                card.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Get form elements
            const dateRangeSection = document.getElementById('dateRangeSection');
            const rollbackSection = document.getElementById('rollbackSection');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            const rollbackSelect = document.getElementById('rollbackSelect');
            
            // Reset all required attributes
            dateFrom.removeAttribute('required');
            dateTo.removeAttribute('required');
            rollbackSelect.removeAttribute('required');
            
            // Show/hide relevant sections and set required attributes
            if (mode === 'rollback') {
                dateRangeSection.style.display = 'none';
                rollbackSection.style.display = 'block';
                rollbackSelect.setAttribute('required', 'required');
            } else {
                dateRangeSection.style.display = 'block';
                rollbackSection.style.display = 'none';
                dateFrom.setAttribute('required', 'required');
                dateTo.setAttribute('required', 'required');
                
                // Update button text
                if (mode === 'dry_run') {
                    btnText.textContent = 'Run Dry Run';
                    submitBtn.className = 'btn btn-primary';
                } else if (mode === 'execution') {
                    btnText.textContent = 'Execute';
                    submitBtn.className = 'btn btn-success';
                }
            }
        }
    </script>
</body>
</html>