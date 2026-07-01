<?php
/**
 * Advance Payment Action Handler
 * Femi9 Billing Application
 * 
 * Description: Processes advance payment form submission with validation and security
 * Security: CSRF protection, SQL injection prevention, XSS protection, input validation
 * 
 * @author Femi9 Development Team
 * @version 2.0
 * @date 2025-12-29
 */

session_start();

include("checksession.php");
include("config.php");

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);

/**
 * Sanitize input to prevent XSS
 * 
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitize_input(string $data): string {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate decimal amount
 * 
 * @param mixed $amount Amount to validate
 * @return bool True if valid
 */
function validate_amount($amount): bool {
    return is_numeric($amount) && $amount > 0 && $amount <= 99999999.99;
}

/**
 * Log error for debugging
 * 
 * @param string $message Error message
 * @param array $context Additional context
 */
function log_error(string $message, array $context = []): void {
    $log_message = date('Y-m-d H:i:s') . " - " . $message;
    if (!empty($context)) {
        $log_message .= " | Context: " . json_encode($context);
    }
    error_log($log_message);
}

// Check if user is logged in
if (!isset($_SESSION['LOGIN_USER_ID']) || !isset($_SESSION['LOGIN_USER_TYPE'])) {
    header("Location: login.php?error=" . urlencode("Please login first"));
    exit;
}

// Get logged-in user details
$logged_user_id = $_SESSION['LOGIN_USER_ID'];
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'];

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add-advance-payment.php?error=" . urlencode("Invalid request method"));
    exit;
}

// Verify form submission
if (!isset($_POST['add_advance_payment'])) {
    header("Location: add-advance-payment.php?error=" . urlencode("Invalid form submission"));
    exit;
}

// CSRF Token Validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    log_error("CSRF token validation failed", [
        'session_token' => $_SESSION['csrf_token'] ?? 'none',
        'post_token' => $_POST['csrf_token'] ?? 'none'
    ]);
    header("Location: add-advance-payment.php?error=" . urlencode("Security validation failed"));
    exit;
}

// Collect and sanitize input data
$company_id = intval($_POST['company_id'] ?? 0);
$from_user_id = sanitize_input($_POST['from_user_id'] ?? '');
$from_user_type = sanitize_input($_POST['from_user_type'] ?? '');
$from_user_name = sanitize_input($_POST['from_user_name'] ?? '');
$to_user_id = sanitize_input($_POST['to_user_id'] ?? '');
$to_user_type = sanitize_input($_POST['to_user_type'] ?? '');
$to_user_name = sanitize_input($_POST['to_user_name'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$payment_date = sanitize_input($_POST['payment_date'] ?? '');
$payment_mode = sanitize_input($_POST['payment_mode'] ?? '');
$reference_number = sanitize_input($_POST['reference_number'] ?? '');
$bank_name = sanitize_input($_POST['bank_name'] ?? '');
$remarks = sanitize_input($_POST['remarks'] ?? '');

// Validation array
$errors = [];

// Validate company ID (only for non-Super Stockist users)
if ($logged_user_type !== 'super_stockiest' && $company_id <= 0) {
    $errors[] = "Invalid company selection";
}

// Validate payer information
if (empty($from_user_id)) {
    $errors[] = "Payer ID is required";
}

if (empty($from_user_type)) {
    $errors[] = "Payer type is required";
}

$allowed_from_types = ['super_stockiest', 'stockiest', 'distributor', 'super_distributor', 'c_and_f'];
if (!in_array($from_user_type, $allowed_from_types, true)) {
    $errors[] = "Invalid payer type";
}

if (empty($from_user_name)) {
    $errors[] = "Payer name is required";
}

// Validate receiver information
if (empty($to_user_id)) {
    $errors[] = "Receiver ID is required";
}

if (empty($to_user_type)) {
    $errors[] = "Receiver type is required";
}

if (empty($to_user_name)) {
    $errors[] = "Receiver name is required";
}

// Validate amount
if (!validate_amount($amount)) {
    $errors[] = "Invalid amount. Must be between ₹1 and ₹99,999,999.99";
}

// Validate payment date
if (empty($payment_date)) {
    $errors[] = "Payment date is required";
} else {
    $date_obj = DateTime::createFromFormat('Y-m-d', $payment_date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $payment_date) {
        $errors[] = "Invalid payment date format";
    } elseif ($date_obj > new DateTime()) {
        $errors[] = "Payment date cannot be in the future";
    }
}

// Validate payment mode
$allowed_payment_modes = ['Cash', 'Bank Transfer', 'Cheque', 'UPI', 'NEFT', 'RTGS', 'IMPS', 'Demand Draft', 'Other'];
if (empty($payment_mode)) {
    $errors[] = "Payment mode is required";
} elseif (!in_array($payment_mode, $allowed_payment_modes, true)) {
    $errors[] = "Invalid payment mode";
}

// Validate payer and receiver are not the same
if ($from_user_id === $to_user_id && $from_user_type === $to_user_type) {
    $errors[] = "Payer and receiver cannot be the same";
}

// Additional validation for Super Stockist: Verify payer type is stockiest
if ($logged_user_type === 'super_stockiest' && $from_user_type !== 'stockiest') {
    $errors[] = "Super Stockist can only receive payments from Stockists";
}

// If there are validation errors, redirect back
if (!empty($errors)) {
    $error_message = implode(", ", $errors);
    log_error("Validation failed", ['errors' => $errors, 'post_data' => $_POST]);
    header("Location: add-advance-payment.php?error=" . urlencode($error_message));
    exit;
}

// Begin database transaction
mysqli_begin_transaction($db_conn);

try {
    // Verify payer exists in their respective table
    $table_map = [
        'super_stockiest' => 'super_stockiest',
        'stockiest' => 'stockiest',
        'distributor' => 'distributor',
        'super_distributor' => 'super_distributor',
        'c_and_f' => 'c_and_f'
    ];

    $from_table = $table_map[$from_user_type] ?? null;
    if (!$from_table) {
        throw new Exception("Invalid payer type table mapping");
    }

    $stmt_verify_payer = $db_conn->prepare(
        "SELECT temp_id, name FROM $from_table WHERE temp_id = ? AND deleted_at IS NULL LIMIT 1"
    );
    
    if (!$stmt_verify_payer) {
        throw new Exception("Database error: " . $db_conn->error);
    }

    $stmt_verify_payer->bind_param("s", $from_user_id);
    $stmt_verify_payer->execute();
    $result_payer = $stmt_verify_payer->get_result();

    if ($result_payer->num_rows === 0) {
        throw new Exception("Payer not found in database");
    }

    $payer_data = $result_payer->fetch_assoc();
    $stmt_verify_payer->close();

    // Authorization check for Super Stockist
    if ($logged_user_type === 'super_stockiest' && $from_user_type === 'stockiest') {
        // Verify the stockist belongs to this Super Stockist
        $stmt_auth = $db_conn->prepare(
            "SELECT temp_id FROM stockiest 
             WHERE temp_id = ? 
             AND deleted_at IS NULL 
             AND (ss_id = ? OR (onboard_userID = ? AND onboard_userTYPE = 'super_stockiest'))
             LIMIT 1"
        );
        
        if (!$stmt_auth) {
            throw new Exception("Authorization check failed: " . $db_conn->error);
        }
        
        $stmt_auth->bind_param("sss", $from_user_id, $logged_user_id, $logged_user_id);
        $stmt_auth->execute();
        $result_auth = $stmt_auth->get_result();
        
        if ($result_auth->num_rows === 0) {
            $stmt_auth->close();
            throw new Exception("Unauthorized: This stockist does not belong to you");
        }
        
        $stmt_auth->close();
    }

    // Calculate initial balance (same as amount since no adjustment yet)
    $balance_amount = $amount;
    $adjusted_amount = 0.00;
    $status = 'active';

    // Insert advance payment record
    $stmt_insert = $db_conn->prepare("
        INSERT INTO advance_payments (
            from_user_id, from_user_type, from_user_name,
            to_user_id, to_user_type, to_user_name,
            amount, payment_date, payment_mode, reference_number, bank_name,
            adjusted_amount, balance_amount, status, remarks,
            created_by_user_id, created_by_user_type,
            created_at, updated_at
        ) VALUES (
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            NOW(), NOW()
        )
    ");

    if (!$stmt_insert) {
        throw new Exception("Database error: " . $db_conn->error);
    }

    $stmt_insert->bind_param(
        "ssssssdssssddssss",
        $from_user_id, $from_user_type, $from_user_name,
        $to_user_id, $to_user_type, $to_user_name,
        $amount, $payment_date, $payment_mode, $reference_number, $bank_name,
        $adjusted_amount, $balance_amount, $status, $remarks,
        $to_user_id, $to_user_type
    );

    if (!$stmt_insert->execute()) {
        throw new Exception("Failed to insert payment record: " . $stmt_insert->error);
    }

    $inserted_id = $db_conn->insert_id;
    $stmt_insert->close();

    // Commit transaction
    mysqli_commit($db_conn);

    // Log success
    error_log("Advance payment added successfully - ID: $inserted_id, Amount: ₹$amount, From: $from_user_name ($from_user_type), To: $to_user_name ($to_user_type)");

    // Regenerate CSRF token for next form submission
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Redirect with success message
    header("Location: add-advance-payment.php?success=1");
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($db_conn);
    
    log_error("Failed to add advance payment", [
        'error' => $e->getMessage(),
        'from_user' => "$from_user_name ($from_user_type)",
        'to_user' => "$to_user_name ($to_user_type)",
        'amount' => $amount
    ]);

    header("Location: add-advance-payment.php?error=" . urlencode("Failed to record payment: " . $e->getMessage()));
    exit;
}
?>