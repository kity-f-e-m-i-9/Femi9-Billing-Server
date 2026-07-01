<?php
/**
 * Invoice Stock Update Handler — StockService edition.
 *
 * Routes all stock writes through StockService so every change is:
 *   • wrapped in a single transaction
 *   • locked with SELECT … FOR UPDATE
 *   • recorded in stock_ledger (audit trail)
 *
 * Called by user-invoice-submit.php after define('INVOICE_STOCK_UPDATE_INCLUDED', true).
 * Variables inherited from the calling scope:
 *   $invoice_id, $db_conn, $is_customer_invoice (bool)
 */

if (!defined('INVOICE_STOCK_UPDATE_INCLUDED')) {
    die('Direct access not permitted');
}

require_once(__DIR__ . '/include/StockService.php');

error_log("=== STOCK UPDATE (StockService) START === Invoice: $invoice_id");

// Resolve invoice table and seller/buyer identity
if ($is_customer_invoice) {
    $stmt = $db_conn->prepare("SELECT * FROM invoice WHERE inv_id = ?");
    $stmt->bind_param('s', $invoice_id);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inv) throw new Exception("Invoice not found: $invoice_id");

    $company_type  = $inv['user_type'];
    $company_id    = $inv['user_id'];
    $customer_type = 'customer';
    $customer_id   = (string) $inv['customer_id'];
    $invoice_date  = $inv['date'];

    $stmt = $db_conn->prepare(
        "SELECT pr_id, qty FROM invoice_items WHERE inv_id = ? AND deleted_at IS NULL"
    );
    $stmt->bind_param('s', $invoice_id);
} else {
    $stmt = $db_conn->prepare("SELECT * FROM user_invoice WHERE inv_id = ?");
    $stmt->bind_param('s', $invoice_id);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inv) throw new Exception("User invoice not found: $invoice_id");

    $company_type  = $inv['from_user_type'];
    $company_id    = $inv['from_user_id'];
    $customer_type = $inv['to_user_type'];
    $customer_id   = $inv['to_user_id'];
    $invoice_date  = $inv['date'];

    $stmt = $db_conn->prepare(
        "SELECT pr_id, qty FROM user_invoice_items
          WHERE inv_id = ? AND from_user_type = ? AND from_user_id = ?
            AND to_user_type = ? AND to_user_id = ? AND deleted_at IS NULL"
    );
    $stmt->bind_param('sssss', $invoice_id, $company_type, $company_id, $customer_type, $customer_id);
}

$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($items)) {
    error_log("WARNING: No items found for invoice $invoice_id — stock update skipped");
    return;
}

$refType     = $is_customer_invoice ? 'invoice' : 'user_invoice';
$createdBy   = $_SESSION['LOGIN_USER'] ?? 'system';
$stockService = new StockService($db_conn);

// Idempotency guard: skip if already applied
if ($stockService->hasLedgerEntry($refType, $invoice_id)) {
    error_log("INFO: Stock already applied for $refType $invoice_id — skipping");
    return;
}

// If the caller already owns a transaction (e.g. user-invoice-submit.php wraps
// everything in one outer tx), skip begin/commit here to avoid an implicit commit
// of the outer transaction. Otherwise manage our own transaction.
$ownTransaction = empty($invoice_stock_external_txn);

if ($ownTransaction) {
    $db_conn->begin_transaction();
}

try {
    foreach ($items as $item) {
        $prId = (int) $item['pr_id'];
        $qty  = (int) $item['qty'];

        error_log("Processing product=$prId qty=$qty seller=$company_type/$company_id buyer=$customer_type/$customer_id");

        // Deduct from seller
        $stockService->deduct(
            $prId, $company_type, $company_id, $qty,
            $refType, $invoice_id, $createdBy,
            true // always tell StockService the tx is external
        );

        // Credit buyer only if they maintain their own stock ledger
        if (in_array($customer_type, StockService::STOCK_MAINTAINING_TYPES, true)) {
            $stockService->credit(
                $prId, $customer_type, $customer_id, $qty,
                $refType, $invoice_id, $createdBy,
                true // always tell StockService the tx is external
            );
        }
    }

    if ($ownTransaction) {
        $db_conn->commit();
    }
    error_log("=== STOCK UPDATE COMPLETED === " . count($items) . " items processed");

} catch (\Throwable $e) {
    if ($ownTransaction) {
        $db_conn->rollback();
    }
    error_log("CRITICAL: Stock update FAILED for $invoice_id — " . $e->getMessage());
    throw new Exception("Stock update failed: " . $e->getMessage());
}
?>
