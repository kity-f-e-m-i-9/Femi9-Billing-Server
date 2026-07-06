<?php
/**
 * AJAX Endpoint: Get Invoice Total
 * Femi9 Billing Application
 * 
 * Returns current total of an invoice
 * 
 * @version 1.0
 * @date 2025-12-31
 */

declare(strict_types=1);

session_start();
require_once("config.php");

header('Content-Type: application/json');

/**
 * Send JSON response
 * 
 * @param bool $success Success status
 * @param string $message Message
 * @param array $data Additional data
 * @return void
 */
function sendResponse(bool $success, string $message = '', array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ] + $data);
    exit();
}

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Unauthorized access');
    }

    // Get invoice ID
    $invoiceId = $_REQUEST['invoice_id'] ?? '';
    
    if (empty($invoiceId)) {
        sendResponse(false, 'Invoice ID is required');
    }

    // Sanitize input
    $invoiceId = htmlspecialchars(trim($invoiceId), ENT_QUOTES, 'UTF-8');

    // Get invoice total using prepared statement
    $stmt = $db_conn->prepare("
        SELECT 
            SUM(total) as total_amount,
            COUNT(*) as item_count
        FROM user_invoice_items 
        WHERE inv_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Database error: " . $db_conn->error);
    }

    $stmt->bind_param("s", $invoiceId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = floatval($result['total_amount'] ?? 0);
    $itemCount = intval($result['item_count'] ?? 0);

    sendResponse(true, 'Invoice total retrieved', [
        'total' => $total,
        'formatted_total' => '₹' . inr_format($total, 2),
        'item_count' => $itemCount
    ]);

} catch (Exception $e) {
    error_log("Get invoice total error: " . $e->getMessage());
    sendResponse(false, 'Error: ' . $e->getMessage());
}
