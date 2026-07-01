<?php
/**
 * Get Advance Payments - Super Stockist Version
 * Femi9 Billing Application
 * 
 * Description: Returns advance payments received by the logged-in Super Stockist
 * Security: SQL injection prevention, XSS protection, session validation
 * 
 * @author Femi9 Development Team
 * @version 6.0 - Super Stockist specific implementation
 * @date 2025-01-02
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

session_start();

require_once 'checksession.php';
require_once 'config.php';

/**
 * Send JSON response and exit
 * 
 * @param bool $success Success status
 * @param array $payments Payment data
 * @param array $statistics Statistics data
 * @param string $message Response message
 * @return void
 */
function send_response(bool $success, array $payments = [], array $statistics = [], string $message = ''): void {
    echo json_encode([
        'success' => $success,
        'payments' => $payments,
        'statistics' => $statistics,
        'message' => $message
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log error with context
 * 
 * @param string $message Error message
 * @param array $context Additional context
 * @return void
 */
function log_error(string $message, array $context = []): void {
    $log = date('Y-m-d H:i:s') . " - get-advance-payments-superstockist.php - " . $message;
    if (!empty($context)) {
        $log .= " | " . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    error_log($log);
}

try {
    // Validate database connection
    if (!isset($db_conn) || !$db_conn instanceof mysqli || $db_conn->connect_errno) {
        throw new Exception('Database connection failed');
    }
    
    // Validate session
    if (!isset($_SESSION['LOGIN_USER_ID'], $_SESSION['LOGIN_USER_TYPE'])) {
        throw new Exception('Please login first');
    }
    
    $logged_user_id = trim($_SESSION['LOGIN_USER_ID']);
    $logged_user_type = trim($_SESSION['LOGIN_USER_TYPE']);
    
    // Verify user type is super_stockiest
    if ($logged_user_type !== 'super_stockiest') {
        throw new Exception('Access denied. Super Stockist login required');
    }
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Verify Super Stockist exists and is active
    $stmt_verify = $db_conn->prepare(
        "SELECT temp_id, name, account_status, deleted_at 
         FROM super_stockiest 
         WHERE temp_id = ? 
         LIMIT 1"
    );
    
    if (!$stmt_verify) {
        throw new Exception('Database prepare failed: ' . $db_conn->error);
    }
    
    $stmt_verify->bind_param('s', $logged_user_id);
    
    if (!$stmt_verify->execute()) {
        $stmt_verify->close();
        throw new Exception('Failed to verify user: ' . $stmt_verify->error);
    }
    
    $result_verify = $stmt_verify->get_result();
    
    if ($result_verify->num_rows === 0) {
        $stmt_verify->close();
        throw new Exception('Super Stockist not found');
    }
    
    $super_stockist = $result_verify->fetch_assoc();
    $stmt_verify->close();
    
    if ($super_stockist['account_status'] !== 'active') {
        throw new Exception('Your account is not active');
    }
    
    if ($super_stockist['deleted_at'] !== null) {
        throw new Exception('Your account has been deleted');
    }
    
    // Fetch advance payments received by this Super Stockist
    // Optimized query: Show payments where TO user is the logged-in Super Stockist
    $stmt_payments = $db_conn->prepare(
        "SELECT 
            id,
            from_user_id,
            from_user_type,
            from_user_name,
            to_user_id,
            to_user_type,
            to_user_name,
            amount,
            payment_date,
            payment_mode,
            reference_number,
            bank_name,
            adjusted_amount,
            balance_amount,
            status,
            remarks,
            created_by_user_id,
            created_by_user_type,
            created_at,
            updated_at
         FROM advance_payments
         WHERE to_user_id = ?
           AND to_user_type = 'super_stockiest'
           AND from_user_type = 'stockiest'
         ORDER BY id DESC
         LIMIT 1000"
    );
    
    if (!$stmt_payments) {
        throw new Exception('Database prepare failed: ' . $db_conn->error);
    }
    
    $stmt_payments->bind_param('s', $logged_user_id);
    
    if (!$stmt_payments->execute()) {
        $stmt_payments->close();
        throw new Exception('Failed to fetch payments: ' . $stmt_payments->error);
    }
    
    $result_payments = $stmt_payments->get_result();
    $payments = [];
    
    // Statistics variables
    $total_count = 0;
    $total_amount = 0.00;
    $total_adjusted = 0.00;
    $total_balance = 0.00;
    
    while ($row = $result_payments->fetch_assoc()) {
        $payments[] = [
            'id' => $row['id'],
            'from_user_id' => htmlspecialchars($row['from_user_id'], ENT_QUOTES, 'UTF-8'),
            'from_user_type' => htmlspecialchars($row['from_user_type'], ENT_QUOTES, 'UTF-8'),
            'from_user_name' => htmlspecialchars($row['from_user_name'], ENT_QUOTES, 'UTF-8'),
            'to_user_id' => htmlspecialchars($row['to_user_id'], ENT_QUOTES, 'UTF-8'),
            'to_user_type' => htmlspecialchars($row['to_user_type'], ENT_QUOTES, 'UTF-8'),
            'to_user_name' => htmlspecialchars($row['to_user_name'], ENT_QUOTES, 'UTF-8'),
            'amount' => number_format((float)$row['amount'], 2, '.', ''),
            'payment_date' => $row['payment_date'],
            'payment_mode' => htmlspecialchars($row['payment_mode'], ENT_QUOTES, 'UTF-8'),
            'reference_number' => htmlspecialchars($row['reference_number'] ?? '', ENT_QUOTES, 'UTF-8'),
            'bank_name' => htmlspecialchars($row['bank_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'adjusted_amount' => number_format((float)$row['adjusted_amount'], 2, '.', ''),
            'balance_amount' => number_format((float)$row['balance_amount'], 2, '.', ''),
            'status' => $row['status'],
            'remarks' => htmlspecialchars($row['remarks'] ?? '', ENT_QUOTES, 'UTF-8'),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
        
        // Calculate statistics
        $total_count++;
        $total_amount += (float)$row['amount'];
        $total_adjusted += (float)$row['adjusted_amount'];
        $total_balance += (float)$row['balance_amount'];
    }
    
    $stmt_payments->close();
    
    // Prepare statistics
    $statistics = [
        'total_count' => $total_count,
        'total_amount' => number_format($total_amount, 2, '.', ''),
        'total_adjusted' => number_format($total_adjusted, 2, '.', ''),
        'total_balance' => number_format($total_balance, 2, '.', '')
    ];
    
    // Log successful fetch
    log_error("Advance payments fetched successfully", [
        'super_stockist_id' => $logged_user_id,
        'count' => $total_count
    ]);
    
    send_response(true, $payments, $statistics, $total_count . ' payment(s) found');
    
} catch (Exception $e) {
    log_error("Error: " . $e->getMessage(), [
        'user_id' => $_SESSION['LOGIN_USER_ID'] ?? 'unknown',
        'user_type' => $_SESSION['LOGIN_USER_TYPE'] ?? 'unknown'
    ]);
    
    send_response(false, [], [], $e->getMessage());
}
