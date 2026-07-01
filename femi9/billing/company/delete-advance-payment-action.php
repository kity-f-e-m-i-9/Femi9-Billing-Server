<?php
/**
 * Delete Advance Payment Action - Soft Delete
 * Femi9 Billing Application
 * 
 * @author Femi9 Development Team
 * @version 1.0
 * @date 2025-01-22
 */

declare(strict_types=1);

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Content-Type: application/json; charset=utf-8");

require_once("checksession.php");
require_once("config.php");

date_default_timezone_set("Asia/Kolkata");

// Production error handling
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/advance-payments-errors.log');

// ============================================================================
// SESSION & SECURITY CHECK
// ============================================================================

$logged_user_id = $_SESSION['LOGIN_USER_ID'] ?? '';
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';

if (empty($logged_user_id) || empty($logged_user_type)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// CSRF Token Validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// ============================================================================
// INPUT VALIDATION
// ============================================================================

$payment_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$payment_id || $payment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit;
}

// ============================================================================
// DATABASE CONNECTION CHECK
// ============================================================================

if (!$db_conn) {
    error_log("Database connection failed in delete-advance-payment-action.php");
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

mysqli_set_charset($db_conn, 'utf8mb4');

// ============================================================================
// VERIFY PAYMENT EXISTS AND CHECK PERMISSIONS
// ============================================================================

$verify_query = "SELECT id, from_user_id, to_user_id, amount, balance_amount, status 
                 FROM advance_payments 
                 WHERE id = ? AND deleted_at IS NULL";

$verify_stmt = $db_conn->prepare($verify_query);
if (!$verify_stmt) {
    error_log("Prepare failed in delete verification: " . $db_conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$verify_stmt->bind_param("i", $payment_id);
$verify_stmt->execute();
$result = $verify_stmt->get_result();
$payment = $result->fetch_assoc();
$verify_stmt->close();

if (!$payment) {
    echo json_encode(['success' => false, 'message' => 'Payment not found or already deleted']);
    exit;
}

// Check permissions - only company or involved parties can delete
if ($logged_user_type !== 'company') {
    if ($payment['from_user_id'] !== $logged_user_id && $payment['to_user_id'] !== $logged_user_id) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this payment']);
        exit;
    }
}

// Check if payment has been adjusted
if ($payment['status'] === 'partially_adjusted' || $payment['status'] === 'fully_adjusted') {
    echo json_encode([
        'success' => false, 
        'message' => 'Cannot delete payment that has been adjusted. Please remove adjustments first.'
    ]);
    exit;
}

// ============================================================================
// PERFORM SOFT DELETE
// ============================================================================

$delete_query = "UPDATE advance_payments 
                 SET deleted_at = NOW(), 
                     updated_at = NOW(),
                     updated_by_user_id = ?,
                     updated_by_user_type = ?
                 WHERE id = ? AND deleted_at IS NULL";

$delete_stmt = $db_conn->prepare($delete_query);
if (!$delete_stmt) {
    error_log("Prepare failed in delete action: " . $db_conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error during deletion']);
    exit;
}

$delete_stmt->bind_param("ssi", $logged_user_id, $logged_user_type, $payment_id);

if ($delete_stmt->execute()) {
    if ($delete_stmt->affected_rows > 0) {
        // Log the deletion
        error_log("Advance payment deleted - ID: {$payment_id}, Amount: {$payment['amount']}, User: {$logged_user_id}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Payment entry deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Payment not found or already deleted'
        ]);
    }
} else {
    error_log("Delete execution failed: " . $delete_stmt->error);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to delete payment entry'
    ]);
}

$delete_stmt->close();
$db_conn->close();
?>