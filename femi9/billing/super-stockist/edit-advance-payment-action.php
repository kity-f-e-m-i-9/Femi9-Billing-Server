<?php
/**
 * Edit Advance Payment Action Handler (Super Stockist)
 * Femi9 Billing Application
 *
 * Mirrors company/edit-advance-payment-action.php, scoped so a super
 * stockist can only update payments where they are the receiver —
 * otherwise any logged-in super stockist could edit any payment
 * system-wide just by posting an arbitrary payment_id.
 *
 * Auto-calculates status based on adjusted & balance amount
 *
 * Status Rules:
 * - balance_amount = 0                  → fully_adjusted
 * - adjusted_amount > 0 & balance > 0   → partially_adjusted
 * - else                                → active
 */

header('Content-Type: application/json');

session_start();

include("checksession.php");
include("config.php");

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = [
    'success' => false,
    'message' => ''
];

try {

    /* ---------------------------
       AUTH & REQUEST VALIDATION
    ---------------------------- */

    if (!isset($_SESSION['LOGIN_USER_ID'], $_SESSION['LOGIN_USER_TYPE'])) {
        throw new Exception('Please login first');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (
        !isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']
    ) {
        throw new Exception('Security validation failed');
    }

    $logged_user_id   = $_SESSION['LOGIN_USER_ID'];
    $logged_user_type = $_SESSION['LOGIN_USER_TYPE'];

    /* ---------------------------
       INPUT SANITIZATION
    ---------------------------- */

    $payment_id       = intval($_POST['payment_id'] ?? 0);
    $amount           = floatval($_POST['amount'] ?? 0);
    $payment_date     = trim($_POST['payment_date'] ?? '');
    $payment_mode     = trim($_POST['payment_mode'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $bank_name        = trim($_POST['bank_name'] ?? '');
    $adjusted_amount  = floatval($_POST['adjusted_amount'] ?? 0);
    $remarks          = trim($_POST['remarks'] ?? '');

    /* ---------------------------
       BASIC VALIDATIONS
    ---------------------------- */

    if ($payment_id <= 0) {
        throw new Exception('Invalid payment ID');
    }

    if ($amount <= 0 || $amount > 99999999.99) {
        throw new Exception('Invalid payment amount');
    }

    if (empty($payment_date)) {
        throw new Exception('Payment date is required');
    }

    $date_obj = DateTime::createFromFormat('Y-m-d', $payment_date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $payment_date) {
        throw new Exception('Invalid payment date format');
    }

    if ($date_obj > new DateTime()) {
        throw new Exception('Payment date cannot be in the future');
    }

    $allowed_modes = [
        'Cash', 'Bank Transfer', 'Cheque', 'UPI',
        'NEFT', 'RTGS', 'IMPS', 'Demand Draft', 'Other'
    ];

    if (empty($payment_mode) || !in_array($payment_mode, $allowed_modes, true)) {
        throw new Exception('Invalid payment mode');
    }

    if ($adjusted_amount < 0 || $adjusted_amount > $amount) {
        throw new Exception('Invalid adjusted amount');
    }

    /* ---------------------------
       RECORD EXISTENCE + OWNERSHIP CHECK
    ---------------------------- */

    $stmt_check = $db_conn->prepare("
        SELECT status
        FROM advance_payments
        WHERE id = ? AND to_user_id = ? AND to_user_type = 'super_stockiest' AND deleted_at IS NULL
        LIMIT 1
    ");

    $stmt_check->bind_param("is", $payment_id, $logged_user_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Payment record not found');
    }

    $current_status = $result->fetch_assoc()['status'];
    $stmt_check->close();



    /* ---------------------------
       AUTO CALCULATIONS
    ---------------------------- */

    $balance_amount = $amount - $adjusted_amount;

    if ($current_status === 'cancelled') {
        $status = 'cancelled';
    } else {
        if ($balance_amount == 0 && $amount > 0) {
            $status = 'fully_adjusted';
        } elseif ($adjusted_amount > 0 && $balance_amount > 0) {
            $status = 'partially_adjusted';
        } else {
            $status = 'active';
        }
    }



    /* ---------------------------
       XSS SAFE STRINGS
    ---------------------------- */

    $reference_number = htmlspecialchars($reference_number, ENT_QUOTES, 'UTF-8');
    $bank_name        = htmlspecialchars($bank_name, ENT_QUOTES, 'UTF-8');
    $remarks          = htmlspecialchars($remarks, ENT_QUOTES, 'UTF-8');


    /* ---------------------------
       UPDATE TRANSACTION
    ---------------------------- */

    mysqli_begin_transaction($db_conn);

    $stmt_update = $db_conn->prepare("
        UPDATE advance_payments
        SET
            amount = ?,
            payment_date = ?,
            payment_mode = ?,
            reference_number = ?,
            bank_name = ?,
            adjusted_amount = ?,
            balance_amount = ?,
            status = ?,
            remarks = ?,
            updated_by_user_id = ?,
            updated_by_user_type = ?,
            updated_at = NOW()
        WHERE id = ? AND to_user_id = ? AND to_user_type = 'super_stockiest' AND deleted_at IS NULL
    ");

    if (!$stmt_update) {
        throw new Exception('Prepare failed: ' . $db_conn->error);
    }

    $stmt_update->bind_param(
        "dssssddssssis",
        $amount,              // d - decimal
        $payment_date,        // s - string
        $payment_mode,        // s - string
        $reference_number,    // s - string
        $bank_name,           // s - string
        $adjusted_amount,     // d - decimal
        $balance_amount,      // d - decimal
        $status,              // s - string
        $remarks,             // s - string
        $logged_user_id,      // s - string
        $logged_user_type,    // s - string
        $payment_id,          // i - integer
        $logged_user_id       // s - string (ownership re-check in WHERE)
    );

    if (!$stmt_update->execute()) {
        throw new Exception('Update failed: ' . $stmt_update->error);
    }

    if ($stmt_update->affected_rows === 0) {
        throw new Exception('No changes made or record not found');
    }

    $stmt_update->close();
    mysqli_commit($db_conn);

    error_log("Advance payment updated (super stockist) | ID: $payment_id | Status: $status");

    $response['success'] = true;
    $response['message'] = 'Payment updated successfully';

} catch (Exception $e) {

    if (isset($db_conn)) {
        mysqli_rollback($db_conn);
    }

    error_log("Edit advance payment error (super stockist): " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
