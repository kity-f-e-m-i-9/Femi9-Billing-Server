<?php
/**
 * receipt-stock-reversal.php
 *
 * Provides two symmetrical stock management functions used by add-receipt.php:
 *
 *   applyStockForInvoice()    — forward:  apply stock when receipt is created
 *   reverseStockForInvoice()  — inverse:  undo stock when receipt is deleted
 *
 * v2.0 — migrated to StockService
 * ─────────────────────────────────
 * Both functions now route all stock writes through StockService so that:
 *   • every change is recorded in stock_ledger (full audit trail)
 *   • SELECT FOR UPDATE prevents race conditions
 *   • reverseAll() (called by delinvoice.php) can find and undo the entries
 *   • the CHECK(closing_qty >= 0) constraint is respected
 *
 * Guard strategy
 * ──────────────
 * applyStockForInvoice() uses a two-layer guard:
 *   Layer 1 — StockService::hasLedgerEntry() — catches invoices applied via the
 *             new StockService path (ledger entries present).
 *   Layer 2 — prior-receipt check — catches legacy invoices applied via the old
 *             direct-SQL path (no ledger entries, but receipt rows exist).
 * This ensures idempotency for both new and pre-migration invoices.
 *
 * reverseStockForInvoice() delegates entirely to StockService::reverseAll()
 * which is already idempotent and writes reverse_deduct / reverse_credit entries.
 */

declare(strict_types=1);

require_once __DIR__ . '/include/StockService.php';

// ─────────────────────────────────────────────────────────────────────────────
// INTERNAL HELPER — fetch invoice header + items
// ─────────────────────────────────────────────────────────────────────────────
function _fetchInvoiceForStock(
    mysqli $db_conn,
    string $inv_id,
    bool   $is_customer_invoice
): array {
    $invoice_table       = $is_customer_invoice ? 'invoice'       : 'user_invoice';
    $invoice_items_table = $is_customer_invoice ? 'invoice_items' : 'user_invoice_items';

    $inv_sql = $is_customer_invoice
        ? "SELECT user_id AS company_id, user_type AS company_type,
                  customer_id AS customer_id, 'customer' AS customer_type
             FROM $invoice_table WHERE inv_id = ?"
        : "SELECT from_user_id AS company_id, from_user_type AS company_type,
                  to_user_id   AS customer_id, to_user_type  AS customer_type
             FROM $invoice_table WHERE inv_id = ?";

    $s = $db_conn->prepare($inv_sql);
    if (!$s) return ['error' => 'Prepare invoice query failed: ' . $db_conn->error];
    $s->bind_param('s', $inv_id);
    $s->execute();
    $inv_row = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$inv_row) return ['error' => "Invoice not found: $inv_id"];

    $company_id    = $inv_row['company_id'];
    $company_type  = $inv_row['company_type'];
    $customer_id   = $inv_row['customer_id'];
    $customer_type = $inv_row['customer_type'];

    if ($is_customer_invoice) {
        $si = $db_conn->prepare(
            "SELECT pr_id, qty FROM $invoice_items_table WHERE inv_id = ?"
        );
        if (!$si) return ['error' => 'Prepare items query failed: ' . $db_conn->error];
        $si->bind_param('s', $inv_id);
    } else {
        $si = $db_conn->prepare(
            "SELECT pr_id, qty FROM $invoice_items_table
              WHERE inv_id = ?
                AND from_user_type = ? AND from_user_id = ?
                AND to_user_type   = ? AND to_user_id   = ?"
        );
        if (!$si) return ['error' => 'Prepare items query failed: ' . $db_conn->error];
        $si->bind_param('sssss', $inv_id, $company_type, $company_id, $customer_type, $customer_id);
    }

    $si->execute();
    $items = $si->get_result()->fetch_all(MYSQLI_ASSOC);
    $si->close();

    return [
        'inv' => [
            'company_id'    => $company_id,
            'company_type'  => $company_type,
            'customer_id'   => $customer_id,
            'customer_type' => $customer_type,
        ],
        'items' => $items,
    ];
}


// ─────────────────────────────────────────────────────────────────────────────
// PUBLIC FUNCTION 1 — applyStockForInvoice
// ─────────────────────────────────────────────────────────────────────────────
/**
 * Apply stock for every item on an invoice (forward direction).
 *
 * Seller : closing_qty ↓, sales_qty ↑
 * Buyer  : input_qty ↑,   closing_qty ↑  (only if buyer maintains stock)
 *
 * Two-layer idempotency guard prevents double-application:
 *   1. Ledger check  — skips if StockService already applied this invoice
 *   2. Receipt check — skips if a prior advance_product/regular receipt exists
 *                      (covers pre-migration invoices with no ledger entries)
 */
function applyStockForInvoice(
    mysqli $db_conn,
    string $inv_id,
    bool   $is_customer_invoice,
    string $new_receipt_id = ''
): array {

    $refType      = $is_customer_invoice ? 'invoice' : 'user_invoice';
    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    // ── Guard layer 1: ledger-based (new path) ────────────────────────────────
    if ($stockService->hasLedgerEntry($refType, $inv_id)) {
        error_log("STOCK APPLY: Already in ledger — skipping for inv=$inv_id");
        return ['success' => true, 'message' => 'already_applied', 'items_applied' => 0];
    }

    // ── Guard layer 2: receipt-based (legacy path, no ledger) ────────────────
    $guard = $db_conn->prepare(
        "SELECT COUNT(*) FROM receipt
          WHERE inv_id = ?
            AND payment_type IN ('advance_product','regular')
            AND receiptid != ?"
    );
    if ($guard) {
        $guard->bind_param('ss', $inv_id, $new_receipt_id);
        $guard->execute();
        [$prior_count] = $guard->get_result()->fetch_row();
        $guard->close();

        if ((int)$prior_count > 0) {
            error_log("STOCK APPLY: Prior receipt guard — skipping for inv=$inv_id");
            return ['success' => true, 'message' => 'already_applied', 'items_applied' => 0];
        }
    }

    // ── Fetch invoice + items ─────────────────────────────────────────────────
    $data = _fetchInvoiceForStock($db_conn, $inv_id, $is_customer_invoice);
    if (isset($data['error'])) {
        return ['success' => false, 'message' => $data['error'], 'items_applied' => 0];
    }

    $inv   = $data['inv'];
    $items = $data['items'];

    if (empty($items)) {
        return ['success' => true, 'message' => 'No items to apply', 'items_applied' => 0];
    }

    $company_id    = $inv['company_id'];
    $company_type  = $inv['company_type'];
    $customer_id   = $inv['customer_id'];
    $customer_type = $inv['customer_type'];

    // ── Apply stock via StockService (transaction-wrapped, ledger-written) ────
    $db_conn->begin_transaction();
    $items_applied = 0;

    try {
        foreach ($items as $item) {
            $prId = (int)   $item['pr_id'];
            $qty  = (int)   $item['qty'];

            // Deduct from seller
            $stockService->deduct(
                $prId, $company_type, $company_id, $qty,
                $refType, $inv_id, $createdBy,
                true // external transaction
            );

            // Credit buyer if they maintain stock
            if (in_array($customer_type, StockService::STOCK_MAINTAINING_TYPES, true)) {
                $stockService->credit(
                    $prId, $customer_type, $customer_id, $qty,
                    $refType, $inv_id, $createdBy,
                    true // external transaction
                );
            }

            $items_applied++;
        }

        $db_conn->commit();
        error_log("STOCK APPLY COMPLETE: inv=$inv_id items=$items_applied");

        return [
            'success'       => true,
            'message'       => "Stock applied for $items_applied item(s)",
            'items_applied' => $items_applied,
        ];

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("STOCK APPLY FAILED: inv=$inv_id — " . $e->getMessage());

        return [
            'success'       => false,
            'message'       => $e->getMessage(),
            'items_applied' => 0,
        ];
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PUBLIC FUNCTION 2 — reverseStockForInvoice
// ─────────────────────────────────────────────────────────────────────────────
/**
 * Reverse stock movements for an invoice (inverse direction).
 *
 * Delegates entirely to StockService::reverseAll() which:
 *   • reads stock_ledger for this invoice's deduct/credit entries
 *   • skips entries already reversed (idempotent)
 *   • uses SELECT FOR UPDATE to prevent race conditions
 *   • writes reverse_deduct / reverse_credit ledger entries
 *
 * For pre-migration invoices that have no ledger entries, reverseAll() returns
 * 0 (nothing to reverse). The reconciliation tool detects any resulting drift.
 */
function reverseStockForInvoice(
    mysqli $db_conn,
    string $inv_id,
    bool   $is_customer_invoice
): array {

    $refType      = $is_customer_invoice ? 'invoice' : 'user_invoice';
    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    try {
        $reversed = $stockService->reverseAll($refType, $inv_id, $createdBy);

        error_log("STOCK REVERSAL COMPLETE: inv=$inv_id entries_reversed=$reversed");

        return [
            'success'        => true,
            'message'        => "Stock reversed for $reversed ledger entry/entries",
            'items_reversed' => $reversed,
        ];

    } catch (\Throwable $e) {
        error_log("STOCK REVERSAL FAILED: inv=$inv_id — " . $e->getMessage());

        return [
            'success'        => false,
            'message'        => $e->getMessage(),
            'items_reversed' => 0,
        ];
    }
}
