<?php
/**
 * receipt-stock-reversal.php
 * Femi9 Billing Application
 *
 * Provides two symmetrical stock management functions:
 *
 *   applyStockForInvoice()   — forward:  apply stock when receipt is created
 *                              Seller: closing ↓, sales ↑
 *                              Buyer:  input ↑, closing ↑
 *
 *   reverseStockForInvoice() — inverse:  undo stock when receipt is deleted
 *                              Seller: closing ↑, sales ↓
 *                              Buyer:  input ↓, closing ↓
 *
 * WHEN EACH IS CALLED
 * ───────────────────
 * applyStockForInvoice():
 *   Called from add-receipt.php after a successful advance_product receipt
 *   insert (SS / Stockist paying invoice amount via advance balance).
 *   Guard: only runs when this is the FIRST advance_product receipt for the
 *   invoice — prevents double-application if multiple partial receipts exist.
 *
 * reverseStockForInvoice():
 *   Called from add-receipt.php when an advance_product or regular receipt is
 *   deleted, before the invoice is edited and resubmitted.
 *   courier_charge receipts never trigger stock movement.
 *
 * IMPORTANT: invoice-stock-update.php (included from user-invoice-submit.php)
 * already applies stock for Distributors at submission time. For SS/Stockist
 * the advance deduction happens at receipt creation (this page), so stock is
 * applied here instead.
 *
 * @version 1.1
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────
// SHARED CONSTANTS
// ─────────────────────────────────────────────────────────────────────────────
// Buyer types that maintain their own stock ledger
const STOCK_MAINTAINING_TYPES = ['candf', 'super_stockiest', 'stockiest', 'super_distributor', 'distributor'];

// ─────────────────────────────────────────────────────────────────────────────
// INTERNAL HELPER
// ─────────────────────────────────────────────────────────────────────────────
/**
 * Fetch invoice header + items and resolve company/customer IDs.
 *
 * Returns ['inv' => [...], 'items' => [[pr_id, qty], ...]]
 * or      ['error' => 'message string']
 */
function _fetchInvoiceForStock(
    mysqli $db_conn,
    string $inv_id,
    bool   $is_customer_invoice
): array {
    $invoice_table       = $is_customer_invoice ? 'invoice'       : 'user_invoice';
    $invoice_items_table = $is_customer_invoice ? 'invoice_items' : 'user_invoice_items';

    // ── Invoice header ────────────────────────────────────────────────────────
    $inv_sql = $is_customer_invoice
        ? "SELECT user_id, user_type, customer_id FROM $invoice_table WHERE inv_id = ?"
        : "SELECT from_user_id, from_user_type, to_user_id, to_user_type FROM $invoice_table WHERE inv_id = ?";

    $s = $db_conn->prepare($inv_sql);
    if (!$s) return ['error' => 'Prepare invoice query failed: ' . $db_conn->error];

    $s->bind_param('s', $inv_id);
    $s->execute();
    $inv_row = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$inv_row) return ['error' => "Invoice not found: $inv_id"];

    if ($is_customer_invoice) {
        $company_id    = $inv_row['user_id'];
        $company_type  = $inv_row['user_type'];
        $customer_id   = $inv_row['customer_id'];
        $customer_type = 'customer';
    } else {
        $company_id    = $inv_row['from_user_id'];
        $company_type  = $inv_row['from_user_type'];
        $customer_id   = $inv_row['to_user_id'];
        $customer_type = $inv_row['to_user_type'];
    }

    // ── Invoice items ─────────────────────────────────────────────────────────
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
// PUBLIC FUNCTION 1: applyStockForInvoice
// ─────────────────────────────────────────────────────────────────────────────
/**
 * Apply stock for every item on an invoice (forward direction).
 *
 * Seller  : closing_qty ↓  |  sales_qty ↑
 * Buyer   : input_qty   ↑  |  closing_qty ↑   (only if buyer maintains stock)
 *
 * GUARD: This function first checks whether stock has already been applied for
 * this invoice by counting existing advance_product/regular receipts (excluding
 * the one just inserted whose $new_receipt_id is passed in). If any prior
 * receipt of those types exists, stock was already applied and this call is a
 * no-op — returning success:true with message 'already_applied'.
 *
 * This prevents double-application when a second partial advance receipt is
 * added to the same invoice.
 *
 * @param mysqli  $db_conn             Active database connection
 * @param string  $inv_id              Invoice ID
 * @param bool    $is_customer_invoice true  → invoice/invoice_items tables
 *                                     false → user_invoice/user_invoice_items
 * @param string  $new_receipt_id      receiptid just inserted (excluded from guard check)
 *
 * @return array{success: bool, message: string, items_applied: int}
 */
function applyStockForInvoice(
    mysqli $db_conn,
    string $inv_id,
    bool   $is_customer_invoice,
    string $new_receipt_id = ''
): array {

    // ── Guard: skip if stock already applied (prior non-courier receipt exists) ─
    // We look for advance_product or regular receipts EXCLUDING the one we just
    // inserted. If any exist, stock was already applied at a previous submission.
    $guard = $db_conn->prepare(
        "SELECT COUNT(*) FROM receipt
         WHERE inv_id = ?
           AND payment_type IN ('advance_product', 'regular')
           AND receiptid != ?"
    );
    if (!$guard) {
        error_log("STOCK APPLY GUARD: Prepare failed — " . $db_conn->error);
        // Fail open: proceed with applying stock rather than silently skipping
    } else {
        $guard->bind_param('ss', $inv_id, $new_receipt_id);
        $guard->execute();
        [$prior_count] = $guard->get_result()->fetch_row();
        $guard->close();

        if ((int)$prior_count > 0) {
            error_log("STOCK APPLY: Already applied (prior receipts=$prior_count) — skipping for inv=$inv_id");
            return [
                'success'       => true,
                'message'       => 'already_applied',
                'items_applied' => 0,
            ];
        }
    }

    // ── Fetch invoice data ────────────────────────────────────────────────────
    $data = _fetchInvoiceForStock($db_conn, $inv_id, $is_customer_invoice);
    if (isset($data['error'])) {
        return ['success' => false, 'message' => $data['error'], 'items_applied' => 0];
    }

    $inv   = $data['inv'];
    $items = $data['items'];

    if (empty($items)) {
        error_log("STOCK APPLY: No items found for invoice $inv_id — skipping");
        return ['success' => true, 'message' => 'No items to apply', 'items_applied' => 0];
    }

    $company_id    = $inv['company_id'];
    $company_type  = $inv['company_type'];
    $customer_id   = $inv['customer_id'];
    $customer_type = $inv['customer_type'];

    $buyer_maintains_stock = in_array($customer_type, STOCK_MAINTAINING_TYPES, true);

    // ── Prepare statements once (outside the item loop) ──────────────────────
    $s_get_seller = $db_conn->prepare(
        "SELECT sales_qty, closing_qty FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ?"
    );
    $s_upd_seller = $db_conn->prepare(
        "UPDATE stock
         SET sales_qty   = ?,
             closing_qty = ?,
             updated_at  = NOW()
         WHERE product_id = ? AND user_type = ? AND user_id = ?"
    );
    $s_get_buyer = $buyer_maintains_stock ? $db_conn->prepare(
        "SELECT input_qty, closing_qty FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ?"
    ) : null;
    $s_upd_buyer = $buyer_maintains_stock ? $db_conn->prepare(
        "UPDATE stock
         SET input_qty   = ?,
             closing_qty = ?,
             updated_at  = NOW()
         WHERE product_id = ? AND user_type = ? AND user_id = ?"
    ) : null;

    foreach (array_filter([$s_get_seller, $s_upd_seller, $s_get_buyer, $s_upd_buyer]) as $chk) {
        if (!$chk) {
            return ['success' => false, 'message' => 'Prepare stock statement failed: ' . $db_conn->error, 'items_applied' => 0];
        }
    }

    // ── Transaction ───────────────────────────────────────────────────────────
    $db_conn->begin_transaction();
    $items_applied = 0;

    try {
        foreach ($items as $item) {
            $pr_id = (string) $item['pr_id'];
            $qty   = floatval($item['qty']);

            error_log("STOCK APPLY: inv=$inv_id product=$pr_id qty=$qty");

            // ── Seller ────────────────────────────────────────────────────────
            $s_get_seller->bind_param('sss', $pr_id, $company_type, $company_id);
            $s_get_seller->execute();
            $seller_row = $s_get_seller->get_result()->fetch_assoc();

            if (!$seller_row) {
                error_log("STOCK APPLY WARNING: seller stock not found — product=$pr_id user=$company_id type=$company_type");
                continue; // Skip item, don't abort entire operation
            }

            // Forward: closing ↓, sales ↑
            $new_sales_qty   = floatval($seller_row['sales_qty'])   + $qty;
            $new_closing_qty = max(0.0, floatval($seller_row['closing_qty']) - $qty);

            $s_upd_seller->bind_param('ddsss', $new_sales_qty, $new_closing_qty, $pr_id, $company_type, $company_id);
            if (!$s_upd_seller->execute()) {
                throw new RuntimeException("Failed to apply seller stock: product=$pr_id — " . $s_upd_seller->error);
            }

            error_log("STOCK APPLY: seller product=$pr_id sales=$new_sales_qty closing=$new_closing_qty ✓");

            // ── Buyer ─────────────────────────────────────────────────────────
            if (!$buyer_maintains_stock) {
                $items_applied++;
                continue;
            }

            $s_get_buyer->bind_param('sss', $pr_id, $customer_type, $customer_id);
            $s_get_buyer->execute();
            $buyer_row = $s_get_buyer->get_result()->fetch_assoc();

            if (!$buyer_row) {
                // Buyer stock record doesn't exist yet — INSERT it
                error_log("STOCK APPLY INFO: buyer stock not found — will create — product=$pr_id user=$customer_id type=$customer_type");

                $s_ins_buyer = $db_conn->prepare(
                    "INSERT INTO stock (product_id, user_type, user_id, input_qty, closing_qty, sales_qty, updated_at)
                     VALUES (?, ?, ?, ?, ?, 0, NOW())
                     ON DUPLICATE KEY UPDATE
                         input_qty   = input_qty   + VALUES(input_qty),
                         closing_qty = closing_qty + VALUES(closing_qty),
                         updated_at  = NOW()"
                );
                if ($s_ins_buyer) {
                    $s_ins_buyer->bind_param('ssddd', $pr_id, $customer_type, $customer_id, $qty, $qty);
                    $s_ins_buyer->execute();
                    $s_ins_buyer->close();
                }
                $items_applied++;
                continue;
            }

            // Forward: input ↑, closing ↑
            $new_buyer_input   = floatval($buyer_row['input_qty'])   + $qty;
            $new_buyer_closing = floatval($buyer_row['closing_qty']) + $qty;

            $s_upd_buyer->bind_param('ddsss', $new_buyer_input, $new_buyer_closing, $pr_id, $customer_type, $customer_id);
            if (!$s_upd_buyer->execute()) {
                throw new RuntimeException("Failed to apply buyer stock: product=$pr_id — " . $s_upd_buyer->error);
            }

            error_log("STOCK APPLY: buyer product=$pr_id input=$new_buyer_input closing=$new_buyer_closing ✓");

            $items_applied++;
        }

        $db_conn->commit();
        error_log("STOCK APPLY COMPLETE: inv=$inv_id items=$items_applied");

        return [
            'success'       => true,
            'message'       => "Stock applied for $items_applied item(s)",
            'items_applied' => $items_applied,
        ];

    } catch (Exception $e) {
        $db_conn->rollback();
        error_log("STOCK APPLY FAILED: inv=$inv_id — " . $e->getMessage());

        return [
            'success'       => false,
            'message'       => $e->getMessage(),
            'items_applied' => 0,
        ];

    } finally {
        foreach (array_filter([$s_get_seller, $s_upd_seller, $s_get_buyer, $s_upd_buyer]) as $st) {
            if ($st instanceof mysqli_stmt) $st->close();
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PUBLIC FUNCTION 2: reverseStockForInvoice
// ─────────────────────────────────────────────────────────────────────────────
/**
 * Reverse stock movements for every item on an invoice (inverse direction).
 *
 * Seller  : closing_qty ↑  |  sales_qty ↓
 * Buyer   : input_qty   ↓  |  closing_qty ↓   (only if buyer maintains stock)
 *
 * Runs inside its own transaction. Allows closing_qty to go negative on the
 * buyer side intentionally — the caller's edit+resubmit cycle will correct it.
 *
 * @param mysqli  $db_conn             Active database connection
 * @param string  $inv_id              Invoice ID (inv_id)
 * @param bool    $is_customer_invoice true → invoice/invoice_items tables
 *                                     false → user_invoice/user_invoice_items
 *
 * @return array{success: bool, message: string, items_reversed: int}
 */
function reverseStockForInvoice(
    mysqli $db_conn,
    string $inv_id,
    bool   $is_customer_invoice
): array {

    // ── Fetch invoice data ────────────────────────────────────────────────────
    $data = _fetchInvoiceForStock($db_conn, $inv_id, $is_customer_invoice);
    if (isset($data['error'])) {
        return ['success' => false, 'message' => $data['error'], 'items_reversed' => 0];
    }

    $inv   = $data['inv'];
    $items = $data['items'];

    if (empty($items)) {
        error_log("STOCK REVERSAL: No items found for invoice $inv_id — skipping");
        return ['success' => true, 'message' => 'No items to reverse', 'items_reversed' => 0];
    }

    $company_id    = $inv['company_id'];
    $company_type  = $inv['company_type'];
    $customer_id   = $inv['customer_id'];
    $customer_type = $inv['customer_type'];

    $buyer_maintains_stock = in_array($customer_type, STOCK_MAINTAINING_TYPES, true);

    // ── Prepare statements once (outside the item loop) ──────────────────────
    $s_get_seller = $db_conn->prepare(
        "SELECT sales_qty, closing_qty FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ?"
    );
    $s_upd_seller = $db_conn->prepare(
        "UPDATE stock
         SET sales_qty   = ?,
             closing_qty = ?,
             updated_at  = NOW()
         WHERE product_id = ? AND user_type = ? AND user_id = ?"
    );
    $s_get_buyer = $buyer_maintains_stock ? $db_conn->prepare(
        "SELECT input_qty, closing_qty FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ?"
    ) : null;
    $s_upd_buyer = $buyer_maintains_stock ? $db_conn->prepare(
        "UPDATE stock
         SET input_qty   = ?,
             closing_qty = ?,
             updated_at  = NOW()
         WHERE product_id = ? AND user_type = ? AND user_id = ?"
    ) : null;

    foreach (array_filter([$s_get_seller, $s_upd_seller, $s_get_buyer, $s_upd_buyer]) as $chk) {
        if (!$chk) {
            return ['success' => false, 'message' => 'Prepare stock statement failed: ' . $db_conn->error, 'items_reversed' => 0];
        }
    }

    // ── Transaction ───────────────────────────────────────────────────────────
    $db_conn->begin_transaction();
    $items_reversed = 0;

    try {
        foreach ($items as $item) {
            $pr_id = (string) $item['pr_id'];
            $qty   = floatval($item['qty']);

            error_log("STOCK REVERSAL: inv=$inv_id product=$pr_id qty=$qty");

            // ── Seller ────────────────────────────────────────────────────────
            $s_get_seller->bind_param('sss', $pr_id, $company_type, $company_id);
            $s_get_seller->execute();
            $seller_row = $s_get_seller->get_result()->fetch_assoc();

            if (!$seller_row) {
                error_log("STOCK REVERSAL WARNING: seller stock not found — product=$pr_id user=$company_id type=$company_type");
                continue;
            }

            // Reverse: closing ↑, sales ↓
            $rev_sales_qty   = max(0.0, floatval($seller_row['sales_qty'])   - $qty);
            $rev_closing_qty = floatval($seller_row['closing_qty']) + $qty;

            $s_upd_seller->bind_param('ddsss', $rev_sales_qty, $rev_closing_qty, $pr_id, $company_type, $company_id);
            if (!$s_upd_seller->execute()) {
                throw new RuntimeException("Failed to reverse seller stock: product=$pr_id — " . $s_upd_seller->error);
            }

            error_log("STOCK REVERSAL: seller product=$pr_id sales=$rev_sales_qty closing=$rev_closing_qty ✓");

            // ── Buyer ─────────────────────────────────────────────────────────
            if (!$buyer_maintains_stock) {
                $items_reversed++;
                continue;
            }

            $s_get_buyer->bind_param('sss', $pr_id, $customer_type, $customer_id);
            $s_get_buyer->execute();
            $buyer_row = $s_get_buyer->get_result()->fetch_assoc();

            if (!$buyer_row) {
                error_log("STOCK REVERSAL INFO: buyer stock not found — product=$pr_id user=$customer_id type=$customer_type");
                $items_reversed++;
                continue;
            }

            // Reverse: input ↓, closing ↓ (allow negative — edit cycle corrects it)
            $rev_buyer_input   = floatval($buyer_row['input_qty'])   - $qty;
            $rev_buyer_closing = floatval($buyer_row['closing_qty']) - $qty;

            $s_upd_buyer->bind_param('ddsss', $rev_buyer_input, $rev_buyer_closing, $pr_id, $customer_type, $customer_id);
            if (!$s_upd_buyer->execute()) {
                throw new RuntimeException("Failed to reverse buyer stock: product=$pr_id — " . $s_upd_buyer->error);
            }

            error_log("STOCK REVERSAL: buyer product=$pr_id input=$rev_buyer_input closing=$rev_buyer_closing ✓");

            $items_reversed++;
        }

        $db_conn->commit();
        error_log("STOCK REVERSAL COMPLETE: inv=$inv_id items=$items_reversed");

        return [
            'success'        => true,
            'message'        => "Stock reversed for $items_reversed item(s)",
            'items_reversed' => $items_reversed,
        ];

    } catch (Exception $e) {
        $db_conn->rollback();
        error_log("STOCK REVERSAL FAILED: inv=$inv_id — " . $e->getMessage());

        return [
            'success'        => false,
            'message'        => $e->getMessage(),
            'items_reversed' => 0,
        ];

    } finally {
        foreach (array_filter([$s_get_seller, $s_upd_seller, $s_get_buyer, $s_upd_buyer]) as $st) {
            if ($st instanceof mysqli_stmt) $st->close();
        }
    }
}
