<?php
// A shop auto-created by marketing/include/OrderTpBridge.php for a genuinely
// new DM-order shop (no dedupe match against this TP's existing shops) is
// written to the `shop` table immediately — needed so tp_orders.shop_id has
// somewhere real to point at — but must NOT show up anywhere as one of the
// TP's own shops (Manage Shop, the Invoice-Shop dropdown, the "pick a
// different shop" search) until the TP has actually reviewed/corrected its
// DM-typed details and saved, via order-confirm-shop.php -> shop-edit.php ->
// shop-edit-action.php. 2026-12-01, user-requested ("invoice button click
// panni yes review kudutha, antha kudutha mattum antha shop add kattum,
// edhavadhu changes pannitu save pannaa apram tp oda manage shop la
// varattum").
//
// `shop.pending_review` (self-migrating) marks exactly that in-between
// state: 1 = bridged but not yet reviewed/saved by the TP, 0 (or NULL, for
// every pre-existing row) = a normal, visible shop. shop-edit-action.php
// clears it back to 0 on every save, not just the order-continuation path —
// harmless no-op for an already-0 row, and covers the flow correctly
// regardless of how the TP reached that save.
function ensureShopPendingReviewColumn($db_conn): void {
    static $checked = false;
    if ($checked) { return; }
    $checked = true;
    $res = $db_conn->query("SHOW COLUMNS FROM shop LIKE 'pending_review'");
    if ($res && $res->num_rows === 0) {
        $db_conn->query("ALTER TABLE shop ADD COLUMN pending_review TINYINT(1) NOT NULL DEFAULT 0");
    }
}
