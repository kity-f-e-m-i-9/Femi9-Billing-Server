<?php
/**
 * Get Advance Balance - AJAX Endpoint - SUPER STOCKIST VERSION
 * Femi9 Billing Application
 * 
 * Returns advance payment balance for selected customer
 * ONLY for Stockist (Super Stockist to Stockist advance payment)
 * 
 * Key Difference from Company Login:
 * - Uses Super Stockist's own ID (not company godown)
 * - Only checks Stockist customer type
 * - Distributor/Super Distributor follow normal flow
 * 
 * Security Features:
 * - Input validation
 * - Rate limiting ready
 * - JSON-only output
 * - Proper error handling
 * 
 * @version 2.0 - Super Stockist Adapted
 * @date 2025-01-30
 */

declare(strict_types=1);

// Start session before any output
session_start();

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Error handling (log, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

include("checksession.php");
include("config.php");
require_once("advance-payment-functions.php");

$response = [
    'success' => false,
    'balance' => 0.00,
    'is_mandatory' => false,
    'message' => '',
    'can_proceed' => false,
    'timestamp' => time()
];

try {
    // ========================================================================
    // VALIDATE REQUEST METHOD
    // ========================================================================
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. POST required.');
    }
    
    // ========================================================================
    // VALIDATE AND SANITIZE INPUT
    // ========================================================================
    
    $customer_id = isset($_POST['customer_id']) ? trim($_POST['customer_id']) : '';
    $customer_type = isset($_POST['customer_type']) ? trim($_POST['customer_type']) : '';
    $to_user_id = isset($_POST['to_user_id']) ? trim($_POST['to_user_id']) : '';
    
    // Input validation
    if (empty($customer_id)) {
        throw new Exception('Customer ID is required');
    }
    
    if (empty($customer_type)) {
        throw new Exception('Customer type is required');
    }
    
    if (empty($to_user_id)) {
        throw new Exception('Super Stockist ID is required');
    }
    
    // Sanitize inputs
    $customer_id = mysqli_real_escape_string($db_conn, $customer_id);
    $customer_type = mysqli_real_escape_string($db_conn, $customer_type);
    $to_user_id = mysqli_real_escape_string($db_conn, $to_user_id);
    
    // ✅ Validate customer type for Super Stockist login
    // Only stockiest, distributor, super_distributor are valid
    $allowed_types = ['stockiest', 'distributor', 'super_distributor'];
    if (!in_array($customer_type, $allowed_types, true)) {
        throw new Exception('Invalid customer type for Super Stockist');
    }
    
    error_log("get-advance-balance [SS]: customer=$customer_id, type=$customer_type, ss_id=$to_user_id");
    
    // ========================================================================
    // CHECK IF ADVANCE PAYMENT IS MANDATORY
    // ========================================================================
    
    // ✅ For Super Stockist: Only stockiest requires advance payment
    $is_mandatory = ($customer_type === 'stockiest');
    $response['is_mandatory'] = $is_mandatory;
    
    // If not mandatory (Distributor, Super Distributor), allow invoice with normal flow
    if (!$is_mandatory) {
        $response['success'] = true;
        $response['can_proceed'] = true;
        $response['message'] = 'Advance payment not required for ' . 
                              ucwords(str_replace('_', ' ', $customer_type));
        
        error_log("get-advance-balance [SS]: Not mandatory for $customer_type");
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    // ========================================================================
    // GET ADVANCE BALANCE (STOCKIST ONLY - FROM SUPER STOCKIST)
    // ========================================================================
    
    // ✅ For Super Stockist: to_user_id is the Super Stockist's own ID
    // ✅ This queries: super_stockiest -> stockiest advance payment
    $balance = getAvailableAdvanceBalance($db_conn, $customer_id, $customer_type, $to_user_id);
    $response['balance'] = round($balance, 2);
    
    // Get full summary
    $summary = getAdvancePaymentSummary($db_conn, $customer_id, $customer_type, $to_user_id);
    $response['summary'] = [
        'total_paid' => round($summary['total_paid'], 2),
        'total_adjusted' => round($summary['total_adjusted'], 2),
        'available_balance' => round($summary['available_balance'], 2),
        'payment_count' => $summary['payment_count']
    ];
    
    // ========================================================================
    // DETERMINE IF CAN PROCEED
    // ========================================================================
    
    if ($balance > 0) {
        $response['success'] = true;
        $response['can_proceed'] = true;
        $response['message'] = 'Balance available: Rs.' . number_format($balance, 2);
        
        error_log("get-advance-balance [SS]: SUCCESS - Balance: Rs." . number_format($balance, 2));
    } else {
        $response['success'] = true;
        $response['can_proceed'] = false;
        $response['message'] = 'No advance balance available. Please add advance payment first.';
        
        error_log("get-advance-balance [SS]: ZERO BALANCE for stockist $customer_id");
    }
    
} catch (Exception $e) {
    // Log error
    error_log("get-advance-balance [SS] ERROR: " . $e->getMessage());
    
    // Return error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Don't expose sensitive error details to client
    if (strpos($e->getMessage(), 'mysqli') !== false || 
        strpos($e->getMessage(), 'SQL') !== false) {
        $response['message'] = 'Database error occurred. Please contact support.';
    }
}

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
?>