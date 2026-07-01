<?php
/**
 * Get Available Advance Balance - AJAX Endpoint
 * Femi9 Billing Application
 * 
 * SCOPE: ONLY Super Stockist and Stockist
 * Returns empty for Distributor & Super Distributor
 * 
 * @author Femi9 Development Team
 * @version 2.0 - Super Stockist & Stockist Only
 * @date 2025-12-30
 */

header('Content-Type: application/json');

session_start();

include("checksession.php");
include("config.php");

$response = [
    'success' => false,
    'available_balance' => 0,
    'advance_payments' => [],
    'is_mandatory' => false,
    'message' => ''
];

try {
    // Check database connection
    if (!isset($db_conn) || !$db_conn) {
        throw new Exception('Database connection failed');
    }

    // Check if user is logged in
    if (!isset($_SESSION['LOGIN_USER_ID']) || !isset($_SESSION['LOGIN_USER_TYPE'])) {
        throw new Exception('Please login first');
    }

    $logged_user_id = $_SESSION['LOGIN_USER_ID'];
    $logged_user_type = $_SESSION['LOGIN_USER_TYPE'];

    // Get parameters
    $customer_id = trim($_POST['customer_id'] ?? '');
    $customer_type = trim($_POST['customer_type'] ?? '');
    $godown_id = trim($_POST['godown_id'] ?? '');

    if (empty($customer_id)) {
        throw new Exception('Customer ID is required');
    }

    if (empty($customer_type)) {
        throw new Exception('Customer type is required');
    }

    // Check if advance payment is mandatory for this user type
    $mandatory_types = ['super_stockiest', 'stockiest'];
    $is_mandatory = in_array($customer_type, $mandatory_types, true);
    
    $response['is_mandatory'] = $is_mandatory;
    $response['customer_type'] = $customer_type;

    // If NOT mandatory (Distributor, Super Distributor), return empty
    if (!$is_mandatory) {
        $response['success'] = true;
        $response['message'] = 'Advance payment not applicable for ' . ucwords(str_replace('_', ' ', $customer_type));
        echo json_encode($response);
        exit;
    }

    // ONLY for Super Stockist and Stockist - get advance payments
    $where_conditions = [
        "deleted_at IS NULL",
        "balance_amount > 0",
        "from_user_id = '" . $db_conn->real_escape_string($customer_id) . "'",
        "from_user_type = '" . $db_conn->real_escape_string($customer_type) . "'"
    ];

    // Filter by godown_id (company)
    if ($logged_user_type === 'company' && !empty($godown_id)) {
        $where_conditions[] = "to_user_id = '" . $db_conn->real_escape_string($godown_id) . "'";
    } else {
        $where_conditions[] = "to_user_id = '" . $db_conn->real_escape_string($logged_user_id) . "'";
    }

    $where_sql = implode(" AND ", $where_conditions);

    $query = "SELECT 
                id,
                amount,
                balance_amount,
                adjusted_amount,
                payment_date,
                payment_mode,
                reference_number,
                status
              FROM advance_payments 
              WHERE $where_sql
              ORDER BY payment_date ASC"; // Oldest first (FIFO)

    $result = $db_conn->query($query);

    if (!$result) {
        throw new Exception('Query failed: ' . $db_conn->error);
    }

    $advance_payments = [];
    $total_available = 0;

    while ($row = $result->fetch_assoc()) {
        $payment_data = [
            'id' => $row['id'],
            'amount' => floatval($row['amount']),
            'balance_amount' => floatval($row['balance_amount']),
            'adjusted_amount' => floatval($row['adjusted_amount']),
            'payment_date' => $row['payment_date'],
            'payment_mode' => htmlspecialchars($row['payment_mode'], ENT_QUOTES, 'UTF-8'),
            'reference_number' => htmlspecialchars($row['reference_number'] ?? '', ENT_QUOTES, 'UTF-8'),
            'status' => htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8')
        ];
        
        $advance_payments[] = $payment_data;
        $total_available += floatval($row['balance_amount']);
    }

    $response['success'] = true;
    $response['available_balance'] = $total_available;
    $response['advance_payments'] = $advance_payments;
    $response['message'] = count($advance_payments) . ' advance payment(s) found with total balance ₹' . 
                          number_format($total_available, 2);

} catch (Exception $e) {
    error_log("get-available-advance-balance.php Error: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
