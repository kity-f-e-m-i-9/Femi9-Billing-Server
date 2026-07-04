<?php
/**
 * Delete TP Advance Payment Action - Soft Delete
 */

declare(strict_types=1);

header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Content-Type: application/json; charset=utf-8");

session_start();

require_once("checksession.php");
require_once("config.php");
require_once("include/GodownAccess.php");

date_default_timezone_set("Asia/Kolkata");

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (empty($_SESSION['LOGIN_USER_ID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$payment_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$payment_id || $payment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit;
}

// Self-migrating: ensure deleted_at column exists for TP advance payment soft-delete.
$_tapDelCol = $db_conn->query("SHOW COLUMNS FROM tp_advance_payments LIKE 'deleted_at'");
if ($_tapDelCol && $_tapDelCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_advance_payments ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER status");
}

$verify_stmt = $db_conn->prepare("
    SELECT id, company_id, amount, status
    FROM tp_advance_payments
    WHERE id = ? AND deleted_at IS NULL
");
$verify_stmt->bind_param("i", $payment_id);
$verify_stmt->execute();
$payment = $verify_stmt->get_result()->fetch_assoc();
$verify_stmt->close();

if (!$payment) {
    echo json_encode(['success' => false, 'message' => 'Payment not found or already deleted']);
    exit;
}

if ($payment['company_id'] && !is_godown_allowed($db_conn, (int)$payment['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($payment['status'] === 'partially_adjusted' || $payment['status'] === 'fully_adjusted') {
    echo json_encode([
        'success' => false,
        'message' => 'Cannot delete payment that has been adjusted. Please remove adjustments first.'
    ]);
    exit;
}

$delete_stmt = $db_conn->prepare("
    UPDATE tp_advance_payments
    SET deleted_at = NOW(), updated_at = NOW()
    WHERE id = ? AND deleted_at IS NULL
");
$delete_stmt->bind_param("i", $payment_id);

if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
    error_log("TP advance payment deleted - ID: {$payment_id}, Amount: {$payment['amount']}, User: {$_SESSION['LOGIN_USER_ID']}");
    echo json_encode(['success' => true, 'message' => 'Payment entry deleted successfully']);
} else {
    error_log("TP advance payment delete failed - ID: {$payment_id}, Error: " . $delete_stmt->error);
    echo json_encode(['success' => false, 'message' => 'Failed to delete payment entry']);
}

$delete_stmt->close();
