<?php
// Immutable change log for shop invoices (user_invoice/user_invoice_items,
// to_user_type='shop') — follows this codebase's existing ledger convention
// (stock_ledger, territory_partner_stock_ledger, remapping_audit_log).
// Logs for every shop invoice regardless of origin; the History view is what
// decides where it's actually surfaced.

// amount_before/amount_after — the line's own ₹ total (user_invoice_items.
// total, GST-inclusive, same basis as the invoice's own displayed "Invoice
// Amount") immediately before/after this change. Added self-migrating
// (2026-11-21, user-requested "Removed/Deleted Amount" on Manage Invoice +
// both dashboards) — only qty was tracked before, so there was no way to
// show how much ₹ value a Remove/qty-reduction/Void actually took off an
// invoice. Only populated going forward; existing historical rows keep
// these NULL (no retroactive backfill — the per-unit rate at the time of an
// old edit isn't reliably recoverable).
function ensureShopInvoiceChangeLogAmountColumns(mysqli $db_conn): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $res = $db_conn->query("SHOW COLUMNS FROM shop_invoice_change_log LIKE 'amount_before'");
    if ($res && $res->num_rows === 0) {
        $db_conn->query("ALTER TABLE shop_invoice_change_log ADD COLUMN amount_before DECIMAL(12,2) NULL AFTER qty_after");
        $db_conn->query("ALTER TABLE shop_invoice_change_log ADD COLUMN amount_after DECIMAL(12,2) NULL AFTER amount_before");
    }
}

function logShopInvoiceChange(
    mysqli $db_conn,
    string $inv_id,
    ?int $pr_id,
    string $change_type,
    ?int $qty_before,
    ?int $qty_after,
    string $changed_by_user_type,
    string $changed_by_user_id,
    ?string $note = null,
    ?float $amount_before = null,
    ?float $amount_after = null
): void {
    ensureShopInvoiceChangeLogAmountColumns($db_conn);
    $stmt = $db_conn->prepare(
        "INSERT INTO shop_invoice_change_log
         (inv_id, pr_id, change_type, qty_before, qty_after, amount_before, amount_after, changed_by_user_type, changed_by_user_id, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sisiiddsss', $inv_id, $pr_id, $change_type, $qty_before, $qty_after, $amount_before, $amount_after, $changed_by_user_type, $changed_by_user_id, $note);
    $stmt->execute();
    $stmt->close();
}

// Sum of ₹ value actually taken OFF a set of invoices via Remove / qty
// reduction / Void — i.e. amount_before - amount_after wherever that's a
// positive drop, for change_type IN ('removed','qty_changed','voided').
// qty_changed also covers a qty INCREASE (amount_after > amount_before),
// which must never count as a negative "removal" here, so each row is
// floored at 0 before summing. Returns 0 for any invoice with no amount-
// tagged history rows yet (old edits, logged before this column existed).
function getShopInvoiceRemovedAmountByInvId(mysqli $db_conn, array $invIds): array {
    $out = [];
    if (empty($invIds)) return $out;
    ensureShopInvoiceChangeLogAmountColumns($db_conn);
    $idList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string($v), $invIds)) . "'";
    $res = $db_conn->query("
        SELECT inv_id, SUM(GREATEST(COALESCE(amount_before,0) - COALESCE(amount_after,0), 0)) AS removed
        FROM shop_invoice_change_log
        WHERE inv_id IN ($idList) AND change_type IN ('removed','qty_changed','voided')
        GROUP BY inv_id
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) { $out[$r['inv_id']] = (float)$r['removed']; }
    }
    return $out;
}

// Same ₹-removed sum as getShopInvoiceRemovedAmountByInvId(), but keyed by
// WHO made the change (shop_invoice_change_log.changed_by_user_id) instead
// of a pre-fetched list of still-existing invoice ids. 2026-11-23,
// user-reported: a TP who removed every line of an invoice and then deleted
// the now-empty invoice itself (delinvoice.php) saw their dashboard's
// "Shop Invoice Removed Amount" card silently drop back to ₹0 — the invoice
// no longer existing in user_invoice meant the invIds-based version above
// never even looked up its change-log rows, even though that history is
// still sitting right there. This version is immune to the invoice itself
// later being deleted, since it never depends on user_invoice at all.
// Returns [changed_by_user_id => removed_amount].
function getShopInvoiceRemovedAmountByUser(mysqli $db_conn, string $changedByUserType, array $changedByUserIds, ?string $fromDate = null, ?string $toDate = null): array {
    $out = [];
    if (empty($changedByUserIds)) return $out;
    ensureShopInvoiceChangeLogAmountColumns($db_conn);
    $idList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string((string)$v), $changedByUserIds)) . "'";
    $typeEsc = $db_conn->real_escape_string($changedByUserType);
    $dateClause = '';
    if ($fromDate !== null && $toDate !== null) {
        $dateClause = " AND DATE(created_at) BETWEEN '" . $db_conn->real_escape_string($fromDate) . "' AND '" . $db_conn->real_escape_string($toDate) . "'";
    }
    $res = $db_conn->query("
        SELECT changed_by_user_id, SUM(GREATEST(COALESCE(amount_before,0) - COALESCE(amount_after,0), 0)) AS removed
        FROM shop_invoice_change_log
        WHERE changed_by_user_type = '$typeEsc' AND changed_by_user_id IN ($idList)
          AND change_type IN ('removed','qty_changed','voided')$dateClause
        GROUP BY changed_by_user_id
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) { $out[$r['changed_by_user_id']] = (float)$r['removed']; }
    }
    return $out;
}

// Shop Invoice Return Amount, keyed by the receiving TP (to_usertype=
// 'territory_partner') — same "invoice-existence-independent" fix as
// getShopInvoiceRemovedAmountByUser() above, and for the identical reason:
// user_return_stock_items.invnumber IN (<still-existing shop invoice ids>)
// silently drops a return the moment its originating invoice is later
// deleted (TP removes every line, then deletes the now-empty invoice —
// delinvoice.php's own guard only requires 0 remaining items, a completed
// Return doesn't block it). from_usertype='shop' distinguishes a shop
// invoice's return from a plain customer invoice's return (both land in
// this same table, told apart only by the original invoice's buyer type —
// see cnote_finish.php). Returns [to_userid => return_amount].
function getShopInvoiceReturnAmountByUser(mysqli $db_conn, array $tpIds, ?string $fromDate = null, ?string $toDate = null): array {
    $out = [];
    if (empty($tpIds)) return $out;
    $idList = "'" . implode("','", array_map('intval', $tpIds)) . "'";
    $dateClause = '';
    if ($fromDate !== null && $toDate !== null) {
        $dateClause = " AND DATE(date) BETWEEN '" . $db_conn->real_escape_string($fromDate) . "' AND '" . $db_conn->real_escape_string($toDate) . "'";
    }
    $res = $db_conn->query("
        SELECT to_userid, SUM(subtotal) AS amt
        FROM user_return_stock_items
        WHERE to_usertype = 'territory_partner' AND to_userid IN ($idList) AND from_usertype = 'shop'
          AND (deleted_at IS NULL)$dateClause
        GROUP BY to_userid
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) { $out[(int)$r['to_userid']] = (float)$r['amt']; }
    }
    return $out;
}

// Per-invoice breakdown behind getShopInvoiceReturnAmountByUser() — which
// TP, which invoice number, how much — for the "TP Return Amount" card's
// hover tooltip on marketing/dashboard.php. Returns one row per (tp_id,
// invnumber) pair, amounts for the same invoice/TP already summed together.
// 2026-11-24, user-requested ("return aprm remove ethu pannalum hover
// pannalum yaaru nu theiryanum entha invoice number nu theriyanum").
function getShopInvoiceReturnDetailByUser(mysqli $db_conn, array $tpIds, ?string $fromDate = null, ?string $toDate = null): array {
    $out = [];
    if (empty($tpIds)) return $out;
    $idList = "'" . implode("','", array_map('intval', $tpIds)) . "'";
    $dateClause = '';
    if ($fromDate !== null && $toDate !== null) {
        $dateClause = " AND DATE(date) BETWEEN '" . $db_conn->real_escape_string($fromDate) . "' AND '" . $db_conn->real_escape_string($toDate) . "'";
    }
    $res = $db_conn->query("
        SELECT to_userid, invnumber, SUM(subtotal) AS amt
        FROM user_return_stock_items
        WHERE to_usertype = 'territory_partner' AND to_userid IN ($idList) AND from_usertype = 'shop'
          AND (deleted_at IS NULL)$dateClause
        GROUP BY to_userid, invnumber
        ORDER BY amt DESC
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[] = ['tp_id' => (int)$r['to_userid'], 'inv_number' => $r['invnumber'], 'amount' => (float)$r['amt']];
        }
    }
    return $out;
}

// Per-invoice breakdown behind getShopInvoiceRemovedAmountByUser() — which
// TP, which invoice, how much. shop_invoice_change_log only carries inv_id
// (not inv_number), and that invoice may since have been deleted (see this
// function's sibling's own comment above), so the invoice number is
// resolved via a LEFT JOIN and falls back to the raw inv_id string (with a
// "(deleted)" marker) when the invoice itself no longer exists.
// 2026-11-24, user-requested.
function getShopInvoiceRemovedDetailByUser(mysqli $db_conn, string $changedByUserType, array $changedByUserIds, ?string $fromDate = null, ?string $toDate = null): array {
    $out = [];
    if (empty($changedByUserIds)) return $out;
    ensureShopInvoiceChangeLogAmountColumns($db_conn);
    $idList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string((string)$v), $changedByUserIds)) . "'";
    $typeEsc = $db_conn->real_escape_string($changedByUserType);
    $dateClause = '';
    if ($fromDate !== null && $toDate !== null) {
        $dateClause = " AND DATE(l.created_at) BETWEEN '" . $db_conn->real_escape_string($fromDate) . "' AND '" . $db_conn->real_escape_string($toDate) . "'";
    }
    $res = $db_conn->query("
        SELECT l.changed_by_user_id, l.inv_id, ui.inv_number,
               SUM(GREATEST(COALESCE(l.amount_before,0) - COALESCE(l.amount_after,0), 0)) AS removed
        FROM shop_invoice_change_log l
        LEFT JOIN user_invoice ui ON ui.inv_id = l.inv_id
        WHERE l.changed_by_user_type = '$typeEsc' AND l.changed_by_user_id IN ($idList)
          AND l.change_type IN ('removed','qty_changed','voided')$dateClause
        GROUP BY l.changed_by_user_id, l.inv_id, ui.inv_number
        HAVING removed > 0
        ORDER BY removed DESC
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[] = [
                'tp_id'      => $r['changed_by_user_id'],
                'inv_number' => $r['inv_number'] ?: ($r['inv_id'] . ' (deleted)'),
                'amount'     => (float)$r['removed'],
            ];
        }
    }
    return $out;
}
