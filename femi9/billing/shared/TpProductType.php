<?php
/**
 * TpProductType — canonical Napkin/Diaper classification for TP purchase
 * orders, invoices, and advance-payment wallets.
 *
 * products.category is enum('napkin','diaper'), but historic/legacy Napkin
 * products carry category IS NULL rather than the literal string 'napkin'
 * (only Diaper products have always been explicitly tagged) — so the match
 * pattern everywhere in this codebase is COALESCE(category,'') != 'diaper'
 * for Napkin, category = 'diaper' for Diaper. This file is the single home
 * for that expression so every picker/report/validation site reuses it
 * instead of re-typing it.
 */

/** SQL fragment matching a given product_type against products.category. */
function tpProductTypeSqlFilter(string $type, string $alias = 'p'): string
{
    return $type === 'diaper'
        ? "{$alias}.category = 'diaper'"
        : "COALESCE({$alias}.category, '') != 'diaper'";
}

/**
 * Never trust a client-submitted product_type on its own — whitelists to
 * napkin|diaper, defaulting to napkin for anything else (missing, empty,
 * tampered).
 */
function tpResolveProductType(?string $requested): string
{
    return $requested === 'diaper' ? 'diaper' : 'napkin';
}

/**
 * Classifies a cart of product ids in one query — backs every server-side
 * "all lines match the declared type" guard (a client-side picker filter is
 * UX only, this is the actual enforcement).
 *
 * @param int[] $productIds
 * @return array{type:?string,mixed:bool} type is 'napkin'/'diaper' when the
 *   cart is pure, null when $productIds is empty. mixed=true means the cart
 *   contains both — the caller must reject, not guess.
 */
function tpProductTypeOfProducts(mysqli $db, array $productIds): array
{
    $productIds = array_values(array_unique(array_map('intval', $productIds)));
    if (empty($productIds)) {
        return ['type' => null, 'mixed' => false];
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));
    $stmt = $db->prepare("SELECT DISTINCT category FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $hasNapkin = false;
    $hasDiaper = false;
    foreach ($rows as $row) {
        if (($row['category'] ?? null) === 'diaper') $hasDiaper = true;
        else $hasNapkin = true;
    }

    if ($hasNapkin && $hasDiaper) return ['type' => null, 'mixed' => true];
    if ($hasDiaper) return ['type' => 'diaper', 'mixed' => false];
    if ($hasNapkin) return ['type' => 'napkin', 'mixed' => false];
    return ['type' => null, 'mixed' => false];
}

/** Display label for a product_type value. */
function tpProductTypeLabel(string $type): string
{
    return $type === 'diaper' ? 'Lumi Diaper' : 'Napkin';
}

/** Badge colour pair [background, text] for a product_type value — reused across every portal's PO/invoice badge. */
function tpProductTypeBadgeColors(string $type): array
{
    return $type === 'diaper'
        ? ['#ede9fe', '#6d28d9']
        : ['#dcfce7', '#15803d'];
}

/**
 * Self-migrating column guard for the two advance-payment wallet tables —
 * see db_migrations/2026_08_18_tp_napkin_diaper_advance_wallet.php. Called
 * from every file that reads/writes tp_advance_payments or
 * tp_advance_payment_submissions, same as this codebase's existing
 * per-file SHOW COLUMNS/ALTER TABLE pattern elsewhere, just centralized
 * here since so many files touch these two tables.
 */
function tpEnsureAdvanceWalletColumns(mysqli $db): void
{
    $col1 = $db->query("SHOW COLUMNS FROM tp_advance_payments LIKE 'product_type'");
    if ($col1 && $col1->num_rows === 0) {
        $db->query("ALTER TABLE tp_advance_payments ADD COLUMN product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin' AFTER territory_partner_id");
        $db->query("ALTER TABLE tp_advance_payments ADD KEY idx_tpap_ptype (territory_partner_id, product_type)");
    }
    $col2 = $db->query("SHOW COLUMNS FROM tp_advance_payment_submissions LIKE 'product_type'");
    if ($col2 && $col2->num_rows === 0) {
        $db->query("ALTER TABLE tp_advance_payment_submissions ADD COLUMN product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin' AFTER territory_partner_id");
    }
    // Tracks whether a company reviewer has explicitly confirmed this row's
    // type — used by company/review-mixed-advance-payments.php to stop
    // re-surfacing a "Mixed" (funded both Napkin and Diaper historically)
    // row once someone has looked at it and picked a side, instead of
    // showing the same row forever just because its funding history stays
    // mixed.
    $col3 = $db->query("SHOW COLUMNS FROM tp_advance_payments LIKE 'product_type_reviewed'");
    if ($col3 && $col3->num_rows === 0) {
        $db->query("ALTER TABLE tp_advance_payments ADD COLUMN product_type_reviewed TINYINT(1) NOT NULL DEFAULT 0 AFTER product_type");
    }
}

/**
 * Traces one advance-payment row's real funding history via
 * tp_invoice_advance_log -> tp_invoice_items -> products.category — same
 * per-row classification logic as the original backfill script
 * (db_migrations/2026_08_18_tp_napkin_diaper_advance_wallet_backfill.php),
 * exposed here so the mixed-payment review tool can recompute it live
 * (funding history can grow after the original backfill ran).
 *
 * @return array{type:?string,mixed:bool} type is 'napkin'/'diaper' when the
 *   row's entire funding history is one type, null when it has none yet.
 *   mixed=true means it funded both — the case this whole function exists for.
 */
function tpAdvancePaymentFundingHistory(mysqli $db, int $advanceId): array
{
    $stmt = $db->prepare("
        SELECT DISTINCT CASE WHEN p.category = 'diaper' THEN 'diaper' ELSE 'napkin' END AS derived_type
        FROM tp_invoice_advance_log l
        JOIN tp_invoice_items tii ON tii.tp_invoice_id = l.tp_invoice_id
        JOIN products p ON p.id = tii.product_id
        WHERE l.tp_advance_id = ?
    ");
    $stmt->bind_param('i', $advanceId);
    $stmt->execute();
    $types = array_unique(array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'derived_type'));
    $stmt->close();

    if (count($types) === 0) return ['type' => null, 'mixed' => false];
    if (count($types) === 1) return ['type' => $types[0], 'mixed' => false];
    return ['type' => null, 'mixed' => true];
}
