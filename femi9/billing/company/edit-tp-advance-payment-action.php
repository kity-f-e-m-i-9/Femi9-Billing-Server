<?php
/**
 * Edit TP Advance Payment Action Handler
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
require_once("include/GodownAccess.php");

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = [
    'success' => false,
    'message' => ''
];

try {

    if (!isset($_SESSION['LOGIN_USER_ID'])) {
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

    $payment_id       = intval($_POST['payment_id'] ?? 0);
    $amount           = floatval($_POST['amount'] ?? 0);
    $payment_date     = trim($_POST['payment_date'] ?? '');
    $payment_mode     = trim($_POST['payment_mode'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $bank_name        = trim($_POST['bank_name'] ?? '');
    $adjusted_amount  = floatval($_POST['adjusted_amount'] ?? 0);
    $remarks          = trim($_POST['remarks'] ?? '');

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

    $stmt_check = $db_conn->prepare("
        SELECT company_id
        FROM tp_advance_payments
        WHERE id = ?
        LIMIT 1
    ");
    $stmt_check->bind_param("i", $payment_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Payment record not found');
    }

    $existing = $result->fetch_assoc();
    $stmt_check->close();

    if ($existing['company_id'] && !is_godown_allowed($db_conn, (int)$existing['company_id'])) {
        throw new Exception('Unauthorized access');
    }

    $balance_amount = $amount - $adjusted_amount;

    if ($balance_amount == 0 && $amount > 0) {
        $status = 'fully_adjusted';
    } elseif ($adjusted_amount > 0 && $balance_amount > 0) {
        $status = 'partially_adjusted';
    } else {
        $status = 'active';
    }

    $reference_number = htmlspecialchars($reference_number, ENT_QUOTES, 'UTF-8');
    $bank_name        = htmlspecialchars($bank_name, ENT_QUOTES, 'UTF-8');
    $remarks          = htmlspecialchars($remarks, ENT_QUOTES, 'UTF-8');

    mysqli_begin_transaction($db_conn);

    $stmt_update = $db_conn->prepare("
        UPDATE tp_advance_payments
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
            updated_at = NOW()
        WHERE id = ?
    ");

    if (!$stmt_update) {
        throw new Exception('Prepare failed: ' . $db_conn->error);
    }

    $stmt_update->bind_param(
        "dssssddssi",
        $amount,
        $payment_date,
        $payment_mode,
        $reference_number,
        $bank_name,
        $adjusted_amount,
        $balance_amount,
        $status,
        $remarks,
        $payment_id
    );

    if (!$stmt_update->execute()) {
        throw new Exception('Update failed: ' . $stmt_update->error);
    }

    $stmt_update->close();
    mysqli_commit($db_conn);

    error_log("TP advance payment updated | ID: $payment_id | Status: $status");

    $response['success'] = true;
    $response['message'] = 'Payment updated successfully';

} catch (Exception $e) {

    if (isset($db_conn)) {
        mysqli_rollback($db_conn);
    }

    error_log("Edit TP advance payment error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
