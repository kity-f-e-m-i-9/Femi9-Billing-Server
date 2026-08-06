<?php
/**
 * Bridges Neksomo's piece/pack purchase pool (neksomo_purchase_items — never
 * touches the standard `stock` table) to the mapped company product's real
 * `stock` row at the NEKSOMO HYGIENE INDUSTRIES godown, so that StockService
 * (internal transfer, invoices, OT sales, demo/free/damage — every consumer)
 * can transact against it like any other company stock.
 *
 * One neksomo_product_id can map to several company pack-size SKUs (e.g.
 * 330mm pieces -> 3pc/6pc/9pc packs) that all draw from the *same* physical
 * pool, so "available" for one sibling depends on what's already been drawn
 * for the others — hence a shared conversion ledger, not a per-SKU balance.
 */

require_once __DIR__ . '/NeksomoProductMapping.php';
require_once __DIR__ . '/NeksomoStockHelper.php';

function ensure_neksomo_stock_conversions_table($db_conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS neksomo_stock_conversions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            neksomo_product_id INT NOT NULL,
            company_product_id INT NOT NULL,
            qty_neksomo_unit INT NOT NULL,
            qty_company_packs INT NOT NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_nsc_neksomo (neksomo_product_id),
            KEY idx_nsc_company (company_product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

// NEKSOMO HYGIENE INDUSTRIES godown id, memoized per-request.
function get_neksomo_godown_id($db_conn) {
    static $id = null;
    if ($id === null) {
        $res = $db_conn->query("SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1");
        $row = $res ? $res->fetch_assoc() : null;
        $id  = $row ? (int)$row['id'] : 0;
    }
    return $id;
}

// Reverse lookup: which neksomo product (if any) is $companyProductId mapped
// from, plus enough of that product's own row to compute pool conversion
// ratios. Null if this company product isn't mapped to anything.
// Memoized per-request — called on every relevant StockService read/write.
function get_neksomo_source_for_company_product($db_conn, $companyProductId) {
    static $cache = [];
    if (array_key_exists($companyProductId, $cache)) return $cache[$companyProductId];

    ensure_neksomo_product_mapping_table($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT np.id AS neksomo_product_id, np.unit_type, np.pieces_per_pack AS neksomo_pieces_per_pack
         FROM neksomo_product_mapping m
         JOIN products np ON np.id = m.neksomo_product_id
         WHERE m.company_product_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $companyProductId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $cache[$companyProductId] = ($row ?: null);
}

// Total qty (in the neksomo product's own native unit — pieces, or packs for
// unit_type='pack' products) already drawn out of its pool into mapped
// company-product stock so far. Optionally as-of a date (inclusive).
function get_neksomo_converted_qty($db_conn, $neksomoProductId, $toDate = '') {
    ensure_neksomo_stock_conversions_table($db_conn);
    $dateClause = $toDate !== ''
        ? "AND created_at <= '" . mysqli_real_escape_string($db_conn, $toDate) . " 23:59:59'"
        : '';
    $stmt = $db_conn->prepare(
        "SELECT COALESCE(SUM(qty_neksomo_unit), 0) AS q
         FROM neksomo_stock_conversions
         WHERE neksomo_product_id = ? $dateClause"
    );
    $stmt->bind_param('i', $neksomoProductId);
    $stmt->execute();
    $qty = (int)($stmt->get_result()->fetch_assoc()['q'] ?? 0);
    $stmt->close();
    return $qty;
}

// How many packs of $companyProductId can currently be drawn from its shared
// Neksomo pool: purchased - LLP/Healthcare sold - already converted, all-time,
// converted into the company product's own pack unit. 0 if not mapped or the
// pool is exhausted.
function get_neksomo_pool_available_packs($db_conn, $companyProductId) {
    $src = get_neksomo_source_for_company_product($db_conn, $companyProductId);
    if (!$src) return 0;

    $nid    = (int)$src['neksomo_product_id'];
    $isPack = $src['unit_type'] === 'pack';

    $purchasedRow = $db_conn->query(
        "SELECT COALESCE(SUM(quantity_pieces), 0) AS pieces, COALESCE(SUM(quantity_packs), 0) AS packs
         FROM neksomo_purchase_items WHERE product_id = $nid"
    )->fetch_assoc();
    $purchased = $isPack ? (int)$purchasedRow['packs'] : (int)$purchasedRow['pieces'];

    $soldMap = $isPack
        ? get_neksomo_packs_sold_via_llp_healthcare($db_conn, '', '')
        : get_neksomo_pieces_sold_via_llp_healthcare($db_conn, '', '');
    $sold = $soldMap[$nid] ?? 0;

    $converted = get_neksomo_converted_qty($db_conn, $nid);

    $poolRemaining = $purchased - $sold - $converted; // native unit
    if ($poolRemaining <= 0) return 0;

    if ($isPack) return $poolRemaining; // 1:1, no conversion ratio

    $ratioRow = $db_conn->query("SELECT pieces_per_pack FROM products WHERE id = " . (int)$companyProductId)->fetch_assoc();
    $ratio    = max(1, (int)($ratioRow['pieces_per_pack'] ?? 1));
    return intdiv($poolRemaining, $ratio);
}

// Display-only sibling of get_neksomo_pool_available_packs(): purchased -
// LLP/Healthcare sold, all-time, in $companyProductId's own pack unit —
// deliberately NOT subtracting already-converted quantity. Already-converted
// stock hasn't left Neksomo's purchased pool, it's simply sitting in a
// different bucket (a company product's real `stock` row); subtracting it
// here would double-penalize the same goods once for being converted and
// again for having been converted (same fix as neksomo-purchase-stock.php's
// Closing Stock). Never call this from write-path conversion logic — that
// must keep using get_neksomo_pool_available_packs(), which correctly
// subtracts already-converted amounts to avoid drawing the same pool twice.
function get_neksomo_pool_purchased_minus_sold_packs($db_conn, $companyProductId) {
    $src = get_neksomo_source_for_company_product($db_conn, $companyProductId);
    if (!$src) return 0;

    $nid    = (int)$src['neksomo_product_id'];
    $isPack = $src['unit_type'] === 'pack';

    $purchasedRow = $db_conn->query(
        "SELECT COALESCE(SUM(quantity_pieces), 0) AS pieces, COALESCE(SUM(quantity_packs), 0) AS packs
         FROM neksomo_purchase_items WHERE product_id = $nid"
    )->fetch_assoc();
    $purchased = $isPack ? (int)$purchasedRow['packs'] : (int)$purchasedRow['pieces'];

    $soldMap = $isPack
        ? get_neksomo_packs_sold_via_llp_healthcare($db_conn, '', '')
        : get_neksomo_pieces_sold_via_llp_healthcare($db_conn, '', '');
    $sold = $soldMap[$nid] ?? 0;

    $remaining = $purchased - $sold; // native unit
    if ($remaining <= 0) return 0;

    if ($isPack) return $remaining; // 1:1, no conversion ratio

    $ratioRow = $db_conn->query("SELECT pieces_per_pack FROM products WHERE id = " . (int)$companyProductId)->fetch_assoc();
    $ratio    = max(1, (int)($ratioRow['pieces_per_pack'] ?? 1));
    return intdiv($remaining, $ratio);
}

// Records a draw of $qtyCompanyPacks (in the company product's own pack unit)
// out of the shared Neksomo pool. Does NOT touch the `stock` table — callers
// must separately credit that via StockService; this only books the pool draw.
function record_neksomo_stock_conversion($db_conn, $companyProductId, $qtyCompanyPacks, $createdBy) {
    $src = get_neksomo_source_for_company_product($db_conn, $companyProductId);
    if (!$src) return false;

    ensure_neksomo_stock_conversions_table($db_conn);

    $isPack = $src['unit_type'] === 'pack';
    if ($isPack) {
        $ratio = 1;
    } else {
        $ratioRow = $db_conn->query("SELECT pieces_per_pack FROM products WHERE id = " . (int)$companyProductId)->fetch_assoc();
        $ratio    = max(1, (int)($ratioRow['pieces_per_pack'] ?? 1));
    }
    $qtyNeksomoUnit = $qtyCompanyPacks * $ratio;
    $neksomoProductId = (int)$src['neksomo_product_id'];

    $stmt = $db_conn->prepare(
        "INSERT INTO neksomo_stock_conversions
            (neksomo_product_id, company_product_id, qty_neksomo_unit, qty_company_packs, created_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iiiis', $neksomoProductId, $companyProductId, $qtyNeksomoUnit, $qtyCompanyPacks, $createdBy);
    $stmt->execute();
    $stmt->close();
    return true;
}
