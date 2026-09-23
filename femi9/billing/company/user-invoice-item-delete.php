<?php
/**
 * Delete Invoice Item
 * Femi9 Billing Application
 * 
 * Removes a product from an invoice and restores stock
 * 
 * @version 1.0
 * @date 2026-01-02
 */

session_start();
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

date_default_timezone_set("Asia/Kolkata");
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get parameters
$item_id = intval($_GET['item_id'] ?? 0);
$inv_id = base64_decode($_GET['inv_id'] ?? '');
$invuser = $_GET['invuser'] ?? '';
$godown_id = intval($_GET['gid'] ?? 0);

// Validate inputs
if ($item_id === 0 || empty($inv_id)) {
    $_SESSION['errorMessage'] = "Invalid request";
    header("Location: user-manage-invoice.php");
    exit;
}

// Get item details before deletion
$stmt_item = $db_conn->prepare("
    SELECT * FROM user_invoice_items 
    WHERE id = ? AND inv_id = ?
");
$stmt_item->bind_param("is", $item_id, $inv_id);
$stmt_item->execute();
$item_data = $stmt_item->get_result()->fetch_assoc();
$stmt_item->close();

if (!$item_data) {
    $_SESSION['errorMessage'] = "Item not found";
    header("Location: user-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&invuser={$invuser}&gid={$godown_id}");
    exit;
}

$pr_id = $item_data['pr_id'];
$qty = $item_data['qty'];
$from_user_type = $item_data['from_user_type'];
$from_user_id = $item_data['from_user_id'];
$to_user_type = $item_data['to_user_type'];
$to_user_id = $item_data['to_user_id'];

// Which physical warehouse the seller-side deduction came from — same
// column invoice-stock-update.php reads at creation time. Reading it here
// keeps this reversal scoped to the exact row that was actually deducted.
$stmt_wh = $db_conn->prepare("SELECT warehouse_id FROM user_invoice WHERE inv_id = ? LIMIT 1");
$stmt_wh->bind_param("s", $inv_id);
$stmt_wh->execute();
$whRow = $stmt_wh->get_result()->fetch_assoc();
$stmt_wh->close();
$warehouseId = ($whRow && $whRow['warehouse_id'] !== null) ? (int) $whRow['warehouse_id'] : null;

// Begin transaction
$db_conn->begin_transaction();

try {
    // Delete the item
    $stmt_delete = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
    $stmt_delete->bind_param("i", $item_id);
    $stmt_delete->execute();
    $stmt_delete->close();

    // Reverse stock only if it was already applied (ledger guard prevents double-restore)
    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    if ($stockService->hasLedgerEntry('user_invoice', $inv_id)) {
        // Restore seller stock: closing_qty ↑, sales_qty ↓ — scoped to the
        // same warehouse the original deduction targeted (only applies when
        // the seller is company; other seller types have no warehouse concept).
        $stockService->reverseDeduct(
            $pr_id, $from_user_type, $from_user_id, $qty,
            'user_invoice', $inv_id, $createdBy,
            true, // externalTransaction
            $from_user_type === 'company' ? $warehouseId : null
        );

        // Remove buyer stock if buyer maintains inventory: closing_qty ↓, input_qty ↓
        if (in_array($to_user_type, StockService::STOCK_MAINTAINING_TYPES, true)) {
            $stockService->reverseCredit(
                $pr_id, $to_user_type, $to_user_id, $qty,
                'user_invoice', $inv_id, $createdBy,
                true // externalTransaction
            );
        }
    }
    
    // Check if invoice has any remaining items
    $stmt_check_items = $db_conn->prepare("SELECT COUNT(*) as item_count FROM user_invoice_items WHERE inv_id = ?");
    $stmt_check_items->bind_param("s", $inv_id);
    $stmt_check_items->execute();
    $result_check = $stmt_check_items->get_result()->fetch_assoc();
    $stmt_check_items->close();
    
    // If no items remain, delete the invoice header
    if ($result_check['item_count'] == 0) {
        $stmt_delete_invoice = $db_conn->prepare("DELETE FROM user_invoice WHERE inv_id = ?");
        $stmt_delete_invoice->bind_param("s", $inv_id);
        $stmt_delete_invoice->execute();
        $stmt_delete_invoice->close();
        
        $db_conn->commit();
        
        $_SESSION['successMessage'] = "Last item removed. Invoice cancelled.";
        header("Location: user-invoice-add.php?invuser={$invuser}&gid={$godown_id}");
        exit;
    }
    
    $db_conn->commit();
    
    $_SESSION['successMessage'] = "Item removed successfully";
    header("Location: user-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&invuser={$invuser}&gid={$godown_id}");
    exit;
    
} catch (Exception $e) {
    $db_conn->rollback();
    
    $_SESSION['errorMessage'] = "Failed to delete item: " . $e->getMessage();
    header("Location: user-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . "&invuser={$invuser}&gid={$godown_id}");
    exit;
}
?>