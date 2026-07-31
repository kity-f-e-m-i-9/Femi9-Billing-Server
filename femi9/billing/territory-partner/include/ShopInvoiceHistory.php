<?php
// Immutable change log for shop invoices (user_invoice/user_invoice_items,
// to_user_type='shop') — follows this codebase's existing ledger convention
// (stock_ledger, territory_partner_stock_ledger, remapping_audit_log).
// Logs for every shop invoice regardless of origin; the History view is what
// decides where it's actually surfaced.

function logShopInvoiceChange(
    mysqli $db_conn,
    string $inv_id,
    ?int $pr_id,
    string $change_type,
    ?int $qty_before,
    ?int $qty_after,
    string $changed_by_user_type,
    string $changed_by_user_id,
    ?string $note = null
): void {
    $stmt = $db_conn->prepare(
        "INSERT INTO shop_invoice_change_log
         (inv_id, pr_id, change_type, qty_before, qty_after, changed_by_user_type, changed_by_user_id, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sisiisss', $inv_id, $pr_id, $change_type, $qty_before, $qty_after, $changed_by_user_type, $changed_by_user_id, $note);
    $stmt->execute();
    $stmt->close();
}
