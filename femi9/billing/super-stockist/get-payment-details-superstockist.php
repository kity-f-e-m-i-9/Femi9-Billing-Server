<?php
/**
 * Get Payment Details - Super Stockist Version
 * Femi9 Billing Application
 * 
 * Description: Returns detailed information about a specific advance payment
 * Security: SQL injection prevention, XSS protection, authorization check
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
 * @param array|null $payment Payment data
 * @param string $message Response message
 * @return void
 */
function send_response(bool $success, ?array $payment = null, string $message = ''): void {
    echo json_encode([
        'success' => $success,
        'payment' => $payment,
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
    $log = date('Y-m-d H:i:s') . " - get-payment-details-superstockist.php - " . $message;
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
    
    // Get payment ID
    $payment_id = intval($_POST['payment_id'] ?? 0);
    
    if ($payment_id <= 0) {
        throw new Exception('Invalid payment ID');
    }
    
    // Fetch payment details with authorization check
    // Only show payment if it belongs to the logged-in Super Stockist (as receiver)
    $stmt_payment = $db_conn->prepare(
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
         WHERE id = ?
           AND to_user_id = ?
           AND to_user_type = 'super_stockiest'
         LIMIT 1"
    );
    
    if (!$stmt_payment) {
        throw new Exception('Database prepare failed: ' . $db_conn->error);
    }
    
    $stmt_payment->bind_param('is', $payment_id, $logged_user_id);
    
    if (!$stmt_payment->execute()) {
        $stmt_payment->close();
        throw new Exception('Failed to fetch payment: ' . $stmt_payment->error);
    }
    
    $result = $stmt_payment->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_payment->close();
        throw new Exception('Payment not found or you do not have access to view it');
    }
    
    $row = $result->fetch_assoc();
    $stmt_payment->close();
    
    // Prepare response
    $payment = [
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
    
    send_response(true, $payment, 'Payment details retrieved successfully');
    
} catch (Exception $e) {
    log_error("Error: " . $e->getMessage(), [
        'user_id' => $_SESSION['LOGIN_USER_ID'] ?? 'unknown',
        'payment_id' => $payment_id ?? 0
    ]);
    
    send_response(false, null, $e->getMessage());
}
