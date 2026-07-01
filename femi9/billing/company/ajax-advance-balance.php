<?php
/**
 * AJAX Endpoint: Get Advance Payment Balance
 * Femi9 Billing Application
 * 
 * Real-time balance checking for invoice creation
 * 
 * @version 1.0
 * @date 2025-12-31
 */

declare(strict_types=1);

// Start session and include dependencies
session_start();
require_once("config.php");
require_once("advance-payment-functions.php");

// Set JSON header
header('Content-Type: application/json');

// Enable CORS if needed (adjust for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * Send JSON response and exit
 * 
 * @param bool $success Success status
 * @param string $message Message
 * @param array $data Additional data
 * @return void
 */
function sendJsonResponse(bool $success, string $message = '', array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    exit();
}

/**
 * Validate required parameters
 * 
 * @param array $params Parameters to validate
 * @return bool
 */
function validateParams(array $params): bool
{
    foreach ($params as $param) {
        if (!isset($_REQUEST[$param]) || empty($_REQUEST[$param])) {
            return false;
        }
    }
    return true;
}

// =============================================================================
// MAIN AJAX HANDLER
// =============================================================================

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, 'Unauthorized access. Please login.');
    }

    // Get action parameter
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        
        // Get available balance
        case 'get_balance':
            if (!validateParams(['customer_id', 'customer_type', 'to_user_id'])) {
                sendJsonResponse(false, 'Missing required parameters');
            }

            $customerId = htmlspecialchars(trim($_REQUEST['customer_id']), ENT_QUOTES, 'UTF-8');
            $customerType = htmlspecialchars(trim($_REQUEST['customer_type']), ENT_QUOTES, 'UTF-8');
            $toUserId = htmlspecialchars(trim($_REQUEST['to_user_id']), ENT_QUOTES, 'UTF-8');

            // Check if advance payment is mandatory
            $isMandatory = isAdvancePaymentMandatory($customerType);

            if (!$isMandatory) {
                sendJsonResponse(true, 'Advance payment not required for this user type', [
                    'is_mandatory' => false,
                    'available_balance' => 0,
                    'formatted_balance' => '₹0.00',
                    'user_type_label' => ucwords(str_replace('_', ' ', $customerType))
                ]);
            }

            // Get available balance
            $balance = getAvailableAdvanceBalance($db_conn, $customerId, $customerType, $toUserId);
            
            // Get summary
            $summary = getAdvancePaymentSummary($db_conn, $customerId, $customerType, $toUserId);

            sendJsonResponse(true, 'Balance retrieved successfully', [
                'is_mandatory' => true,
                'available_balance' => $balance,
                'formatted_balance' => '₹' . number_format($balance, 2),
                'summary' => [
                    'total_paid' => $summary['total_paid'],
                    'formatted_total_paid' => '₹' . number_format($summary['total_paid'], 2),
                    'total_adjusted' => $summary['total_adjusted'],
                    'formatted_total_adjusted' => '₹' . number_format($summary['total_adjusted'], 2),
                    'payment_count' => $summary['payment_count']
                ],
                'user_type_label' => ucwords(str_replace('_', ' ', $customerType))
            ]);
            break;

        // Validate invoice amount against balance
        case 'validate_invoice':
            if (!validateParams(['customer_id', 'customer_type', 'to_user_id', 'invoice_amount'])) {
                sendJsonResponse(false, 'Missing required parameters');
            }

            $customerId = htmlspecialchars(trim($_REQUEST['customer_id']), ENT_QUOTES, 'UTF-8');
            $customerType = htmlspecialchars(trim($_REQUEST['customer_type']), ENT_QUOTES, 'UTF-8');
            $toUserId = htmlspecialchars(trim($_REQUEST['to_user_id']), ENT_QUOTES, 'UTF-8');
            $invoiceAmount = floatval($_REQUEST['invoice_amount']);

            // Validate
            $validation = validateAdvanceBalanceForInvoice(
                $db_conn,
                $customerId,
                $customerType,
                $invoiceAmount,
                $toUserId
            );

            $responseData = [
                'can_create' => $validation['can_create'],
                'is_mandatory' => $validation['is_mandatory'],
                'available_balance' => $validation['available_balance'],
                'formatted_balance' => '₹' . number_format($validation['available_balance'], 2),
                'required_amount' => $invoiceAmount,
                'formatted_required' => '₹' . number_format($invoiceAmount, 2),
                'user_type_label' => ucwords(str_replace('_', ' ', $customerType))
            ];

            if (!$validation['can_create'] && $validation['is_mandatory']) {
                $shortage = $invoiceAmount - $validation['available_balance'];
                $responseData['shortage'] = $shortage;
                $responseData['formatted_shortage'] = '₹' . number_format($shortage, 2);
            }

            sendJsonResponse(
                $validation['can_create'],
                $validation['message'],
                $responseData
            );
            break;

        // Get advance payment details
        case 'get_payment_details':
            if (!validateParams(['customer_id', 'customer_type', 'to_user_id'])) {
                sendJsonResponse(false, 'Missing required parameters');
            }

            $customerId = htmlspecialchars(trim($_REQUEST['customer_id']), ENT_QUOTES, 'UTF-8');
            $customerType = htmlspecialchars(trim($_REQUEST['customer_type']), ENT_QUOTES, 'UTF-8');
            $toUserId = htmlspecialchars(trim($_REQUEST['to_user_id']), ENT_QUOTES, 'UTF-8');

            if (!isAdvancePaymentMandatory($customerType)) {
                sendJsonResponse(false, 'Advance payment not applicable for this user type');
            }

            // Get payment records
            $stmt = $db_conn->prepare(
                "SELECT 
                    id,
                    payment_date,
                    amount,
                    balance_amount,
                    adjusted_amount,
                    payment_mode,
                    status,
                    reference_number
                FROM advance_payments
                WHERE deleted_at IS NULL
                  AND from_user_id = ?
                  AND from_user_type = ?
                  AND to_user_id = ?
                ORDER BY payment_date DESC, id DESC
                LIMIT 10"
            );

            $stmt->bind_param("sss", $customerId, $customerType, $toUserId);
            $stmt->execute();
            $result = $stmt->get_result();

            $payments = [];
            while ($row = $result->fetch_assoc()) {
                $payments[] = [
                    'id' => $row['id'],
                    'payment_date' => date('d-M-Y', strtotime($row['payment_date'])),
                    'amount' => floatval($row['amount']),
                    'formatted_amount' => '₹' . number_format($row['amount'], 2),
                    'balance_amount' => floatval($row['balance_amount']),
                    'formatted_balance' => '₹' . number_format($row['balance_amount'], 2),
                    'adjusted_amount' => floatval($row['adjusted_amount']),
                    'formatted_adjusted' => '₹' . number_format($row['adjusted_amount'], 2),
                    'payment_mode' => $row['payment_mode'],
                    'status' => $row['status'],
                    'reference_number' => $row['reference_number']
                ];
            }

            $stmt->close();

            sendJsonResponse(true, 'Payment details retrieved', [
                'payments' => $payments,
                'count' => count($payments)
            ]);
            break;

        default:
            sendJsonResponse(false, 'Invalid action specified');
    }

} catch (Exception $e) {
    error_log("AJAX Error: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage());
}
