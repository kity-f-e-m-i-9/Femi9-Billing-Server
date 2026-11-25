<?php
// "TP Invoice Amount" — Napkin-only, district-scoped. A TP's shop invoice
// only counts toward the district it was actually billed to, not toward
// every district that TP happens to cover — and only the Napkin-category
// lines, same as every other Target/Achieved figure on this dashboard being
// Napkin-only. Both gaps meant a TP whose Firka assignments span two
// different DMs' districts had their WHOLE invoice total (Napkin + Diaper)
// counted on both DMs' dashboards. 2026-11-20, user-reported.
//
// Which district: COALESCE(ui.invoice_district_id, s.district_id) — prefers
// the District the TP actually picked on the Invoice - Shop form itself
// (territory-partner/shop-invoice-action.php, 2026-11-21 onward) over the
// shop's own stored district_id, which is confirmed stale for a large share
// of the TP base (133 of 195 active TPs have shops whose district_id no
// longer matches any of their currently-assigned districts — a TP
// reassigned to a different Firka keeps her old shops' old district_id).
// Older invoices (before this column existed) have invoice_district_id
// NULL and fall back to shop.district_id as before. 2026-11-21, user-
// reported (a Namakkal-billed invoice was attributing to the Erode DM
// because the shop's own district_id was still stuck at Erode).
//
// Query shape matters here: `shop` has no index at all beyond its PRIMARY
// (id) — not even on temp_id, which the rest of this app already joins on
// everywhere. Letting the optimizer pick its own join order made it drive
// off a full scan of `products` x `shop` (joined via buffer) before ever
// touching user_invoice's own indexes, which measured ~3s for an org-wide
// (ASM/SM-level) call with ~200 TPs / ~40 districts. STRAIGHT_JOIN forces
// the join to start from user_invoice (which DOES have a usable index for
// to_user_type/from_user_type/date) and walk out to shop via the new
// idx_shop_temp_id below — same query measured ~80ms after that. Confirmed
// via EXPLAIN + timing on local DB, 2026-11-20.
function ensureTpInvoiceAmountIndexes($db_conn): void {
    static $checked = false;
    if ($checked) { return; }
    $checked = true;
    $chk = $db_conn->query("SHOW INDEX FROM shop WHERE Key_name='idx_shop_temp_id'");
    if ($chk && $chk->num_rows === 0) {
        $db_conn->query("ALTER TABLE shop ADD INDEX idx_shop_temp_id (temp_id)");
    }
    $chk2 = $db_conn->query("SHOW INDEX FROM shop WHERE Key_name='idx_shop_district_id'");
    if ($chk2 && $chk2->num_rows === 0) {
        $db_conn->query("ALTER TABLE shop ADD INDEX idx_shop_district_id (district_id)");
    }
    // Self-migrating — territory-partner/shop-invoice-action.php also
    // creates this column on its own first use, but this function can run
    // first (e.g. a dashboard load before any new invoice has ever been
    // created since this feature shipped).
    $chk3 = $db_conn->query("SHOW COLUMNS FROM user_invoice LIKE 'invoice_district_id'");
    if ($chk3 && $chk3->num_rows === 0) {
        $db_conn->query("ALTER TABLE user_invoice ADD COLUMN invoice_district_id INT NULL DEFAULT NULL AFTER source_ms_order_id");
    }
    $chk4 = $db_conn->query("SHOW INDEX FROM user_invoice WHERE Key_name='idx_user_invoice_district_id'");
    if ($chk4 && $chk4->num_rows === 0) {
        $db_conn->query("ALTER TABLE user_invoice ADD INDEX idx_user_invoice_district_id (invoice_district_id)");
    }
}

// For a TP currently assigned to exactly ONE district, that district IS the
// ground truth, full stop — shop-invoice-action.php only ever lets such a TP
// pick that same one district on the invoice form (invoice_district_id is
// re-validated server-side against getTpAssignedDistricts()), so there is no
// scenario where a single-district TP's real invoice_district_id disagrees
// with their home district. This function therefore checks the TP's OWN
// single-district map FIRST, before even looking at the per-invoice
// COALESCE(invoice_district_id, shop.district_id) resolution (rd.id below) —
// 2026-12-01, broadened after a second, more common failure mode than the
// original "completely unresolvable" one: shop.district_id can be a REAL,
// VALID district, just the WRONG one (a TP reassigned Firkas keeps her old
// shops' old, now-stale district_id — e.g. several Tiruppur-only TPs had
// shops stuck at Perambalur). A plain NULL-check (the original 2026-11-30
// fix) never catches "valid but wrong", only "unresolvable" (literal '0', or
// an id from an old, now-nonexistent location scheme like '17') — checking
// the TP's own single-district map first catches BOTH, since for such a TP
// there's no invoice-level signal worth trusting over "the one district they
// actually work". A TP assigned to 2+ districts is unaffected — rd.id (the
// validated per-invoice resolution) still decides for them, same as before,
// so the double-count bug invoice_district_id was built to fix (2026-11-20)
// can't resurface. Reported by Nirmal Claudius R (2026-11-30, Trichy) and
// ARAVINDSAMY (2026-12-01, Tiruppur) — both had single-district TPs losing
// real sales this way, for two different underlying data-corruption shapes.
function getSingleDistrictTpMap($db_conn): array {
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $cache = [];
    $res = $db_conn->query("
        SELECT tpl.territory_partner_id AS tp_id, MIN(d.id) AS district_id, COUNT(DISTINCT d.id) AS dcnt
        FROM territory_partner_locations tpl
        INNER JOIN territory_partners tp ON tp.id = tpl.territory_partner_id AND tp.deleted_at IS NULL
        INNER JOIN partner_location_nodes f  ON f.id  = tpl.location_id
        INNER JOIN partner_location_nodes t  ON t.id  = f.parent_id
        INNER JOIN partner_location_nodes dv ON dv.id = t.parent_id
        INNER JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        GROUP BY tpl.territory_partner_id
        HAVING COUNT(DISTINCT d.id) = 1
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) { $cache[(int)$r['tp_id']] = (int)$r['district_id']; }
    }
    return $cache;
}

// Builds a `CASE ui.from_user_id WHEN t1 THEN d1 ... ELSE NULL END` SQL
// fragment, restricted to the TPs actually in $tpIds (not the whole map) so
// the CASE stays small even on a company-wide call.
function buildSingleDistrictFallbackCaseSql($db_conn, array $tpIds): string {
    $map = getSingleDistrictTpMap($db_conn);
    $whens = '';
    foreach ($tpIds as $tpId) {
        $tpId = (int)$tpId;
        if (isset($map[$tpId])) {
            $whens .= " WHEN $tpId THEN " . (int)$map[$tpId];
        }
    }
    if ($whens === '') { return 'NULL'; }
    return "(CASE ui.from_user_id$whens ELSE NULL END)";
}

function getTpInvoiceAmountForDistricts($db_conn, array $tpIds, array $districtIds, string $from, string $to): float {
    if (empty($tpIds) || empty($districtIds)) { return 0.0; }
    ensureTpInvoiceAmountIndexes($db_conn);
    $tpList   = implode(',', array_map('intval', $tpIds));
    $distList = implode(',', array_map('intval', $districtIds));
    $fallback = buildSingleDistrictFallbackCaseSql($db_conn, $tpIds);
    $stmt = $db_conn->prepare("
        SELECT COALESCE(SUM(uii.total),0) AS amt
        FROM user_invoice ui
        STRAIGHT_JOIN shop s ON s.temp_id = ui.to_user_id
        STRAIGHT_JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id
        STRAIGHT_JOIN products p ON p.id = uii.pr_id
        LEFT JOIN partner_location_nodes rd ON rd.id = COALESCE(ui.invoice_district_id, s.district_id) AND rd.depth = 3
        WHERE ui.from_user_type='territory_partner' AND ui.to_user_type='shop'
          AND ui.from_user_id IN ($tpList)
          AND ui.`date` BETWEEN ? AND ?
          AND COALESCE($fallback, rd.id) IN ($distList)
          AND p.category = 'napkin'
    ");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $amt = (float)($stmt->get_result()->fetch_assoc()['amt'] ?? 0);
    $stmt->close();
    return $amt;
}

// Same scoping as above, grouped by invoice date — for the Weekly breakdown
// modal, which buckets a day-by-day total into calendar weeks.
function getTpInvoiceAmountByDateForDistricts($db_conn, array $tpIds, array $districtIds, string $from, string $to): array {
    if (empty($tpIds) || empty($districtIds)) { return []; }
    ensureTpInvoiceAmountIndexes($db_conn);
    $tpList   = implode(',', array_map('intval', $tpIds));
    $distList = implode(',', array_map('intval', $districtIds));
    $fallback = buildSingleDistrictFallbackCaseSql($db_conn, $tpIds);
    $res = $db_conn->query("
        SELECT ui.`date`, COALESCE(SUM(uii.total),0) AS amt
        FROM user_invoice ui
        STRAIGHT_JOIN shop s ON s.temp_id = ui.to_user_id
        STRAIGHT_JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id
        STRAIGHT_JOIN products p ON p.id = uii.pr_id
        LEFT JOIN partner_location_nodes rd ON rd.id = COALESCE(ui.invoice_district_id, s.district_id) AND rd.depth = 3
        WHERE ui.from_user_type='territory_partner' AND ui.to_user_type='shop'
          AND ui.from_user_id IN ($tpList)
          AND ui.`date` BETWEEN '" . $db_conn->real_escape_string($from) . "' AND '" . $db_conn->real_escape_string($to) . "'
          AND COALESCE($fallback, rd.id) IN ($distList)
          AND p.category = 'napkin'
        GROUP BY ui.`date`
    ");
    $out = [];
    while ($r = $res->fetch_assoc()) { $out[$r['date']] = (float)$r['amt']; }
    return $out;
}

// Diaper-only twin of getTpInvoiceAmountForDistricts() — same district-
// scoped TP Invoice Amount, just category='diaper' instead of 'napkin'.
// Needed so KPI #9 "Sales Return Control" can compare each brand's returns
// against that SAME brand's turnover definition KPI #1 already uses for
// Napkin (TP Invoice Amount, not the much narrower Get-Order-sourced-only
// figure it used before). 2026-11-24, user-reported ("ipo 514423 la 5% athu
// tha avnaga return la vaara vendiya amount" — the 5% threshold should be
// against the Achieved figure, same ₹5,14,423 as KPI #1).
function getTpDiaperInvoiceAmountForDistricts($db_conn, array $tpIds, array $districtIds, string $from, string $to): float {
    if (empty($tpIds) || empty($districtIds)) { return 0.0; }
    ensureTpInvoiceAmountIndexes($db_conn);
    $tpList   = implode(',', array_map('intval', $tpIds));
    $distList = implode(',', array_map('intval', $districtIds));
    $fallback = buildSingleDistrictFallbackCaseSql($db_conn, $tpIds);
    $stmt = $db_conn->prepare("
        SELECT COALESCE(SUM(uii.total),0) AS amt
        FROM user_invoice ui
        STRAIGHT_JOIN shop s ON s.temp_id = ui.to_user_id
        STRAIGHT_JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id
        STRAIGHT_JOIN products p ON p.id = uii.pr_id
        LEFT JOIN partner_location_nodes rd ON rd.id = COALESCE(ui.invoice_district_id, s.district_id) AND rd.depth = 3
        WHERE ui.from_user_type='territory_partner' AND ui.to_user_type='shop'
          AND ui.from_user_id IN ($tpList)
          AND ui.`date` BETWEEN ? AND ?
          AND COALESCE($fallback, rd.id) IN ($distList)
          AND p.category = 'diaper'
    ");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $amt = (float)($stmt->get_result()->fetch_assoc()['amt'] ?? 0);
    $stmt->close();
    return $amt;
}

// Returns (user_return_stock_items) against the SAME scope (TP set,
// district attribution, date range) as the two turnover functions above,
// split by $category — used by KPI #9 so "5% of turnover" and "returns"
// are drawn from the exact same invoice population, not two different
// definitions of "this person's sales". inv_number/invnumber share the
// same collation (confirmed via information_schema, 2026-11-24) so no
// COLLATE cast is needed on the join, unlike some other invnumber joins
// elsewhere in this codebase.
function getTpReturnAmountForDistrictsByCategory($db_conn, array $tpIds, array $districtIds, string $from, string $to, string $category): float {
    if (empty($tpIds) || empty($districtIds)) { return 0.0; }
    ensureTpInvoiceAmountIndexes($db_conn);
    $tpList   = implode(',', array_map('intval', $tpIds));
    $distList = implode(',', array_map('intval', $districtIds));
    $fallback = buildSingleDistrictFallbackCaseSql($db_conn, $tpIds);
    $stmt = $db_conn->prepare("
        SELECT COALESCE(SUM(r.total),0) AS amt
        FROM user_return_stock_items r
        STRAIGHT_JOIN user_invoice ui ON ui.inv_number = r.invnumber
        STRAIGHT_JOIN shop s ON s.temp_id = ui.to_user_id
        STRAIGHT_JOIN products p ON p.id = r.prid
        LEFT JOIN partner_location_nodes rd ON rd.id = COALESCE(ui.invoice_district_id, s.district_id) AND rd.depth = 3
        WHERE r.to_usertype='territory_partner' AND r.from_usertype='shop' AND r.deleted_at IS NULL
          AND r.to_userid IN ($tpList)
          AND ui.from_user_type='territory_partner' AND ui.to_user_type='shop'
          AND ui.from_user_id IN ($tpList)
          AND ui.`date` BETWEEN ? AND ?
          AND COALESCE($fallback, rd.id) IN ($distList)
          AND p.category = ?
    ");
    $stmt->bind_param('sss', $from, $to, $category);
    $stmt->execute();
    $amt = (float)($stmt->get_result()->fetch_assoc()['amt'] ?? 0);
    $stmt->close();
    return $amt;
}
