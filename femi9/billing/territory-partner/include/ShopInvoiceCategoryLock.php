<?php
// A shop invoice must be all-Napkin or all-Diaper, never mixed, going
// forward — the TP picks the Product Type once (card chooser, same pattern
// as add-purchase-order.php's Napkin/Diaper cards) before the first product
// is added, and it stays locked for the rest of that invoice. Existing
// legacy invoices that already mix both categories (created before this
// lock existed) are left alone — getShopInvoiceLockedCategory() returns
// 'mixed' for those, which both shop-invoice-add.php and
// shop-invoice-action.php treat as "no restriction, same as before" so a TP
// can still finish adding lines to one of those old invoices.
// 2026-11-23, user-requested ("napkin ahh click panni oru product add
// pannitalum diaper ku change panna mudiya kudathu").
//
// Returns: 'napkin' | 'diaper' | 'mixed' (2+ distinct categories already on
// this invoice — legacy, unrestricted) | null (no items yet — free choice).
function getShopInvoiceLockedCategory(mysqli $db_conn, string $invId): ?string {
    if ($invId === '') { return null; }
    $stmt = $db_conn->prepare("
        SELECT DISTINCT p.category
        FROM user_invoice_items uii
        JOIN products p ON p.id = uii.pr_id
        WHERE uii.inv_id = ?
    ");
    $stmt->bind_param('s', $invId);
    $stmt->execute();
    $cats = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        if ($r['category'] !== null && $r['category'] !== '') { $cats[$r['category']] = true; }
    }
    $stmt->close();
    if (count($cats) === 0) { return null; }
    if (count($cats) > 1) { return 'mixed'; }
    return array_key_first($cats);
}
