<?php
/**
 * Converts LLP/Healthcare sales of company pack-products into pieces sold of
 * the corresponding Neksomo piece-product, via neksomo_product_mapping.
 *
 * LLP and Healthcare are both company_godown rows (from_user_type/user_type =
 * 'company', from_user_id/user_id = that godown's id) — their sales are
 * ordinary company sales, recorded in the same shared user_invoice(_items)
 * (SS/ST/DT/Shop-bound) and invoice(_items) (direct customer) tables every
 * other company report reads, not a Neksomo-specific table.
 */

// Sold qty (packs) per company product_id, from LLP + Healthcare godowns,
// optionally restricted to a date range. Sums both user_invoice_items and
// invoice_items, matching the pattern used everywhere else in this app that
// totals "company" outward sales.
function get_llp_healthcare_sold_packs($db_conn, $from_date, $to_date) {
    $llpId = (int) ($db_conn->query("SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1")->fetch_row()[0] ?? 0);
    $hcId  = (int) ($db_conn->query("SELECT id FROM company_godown WHERE gname = 'FEMI HEALTH CARE' LIMIT 1")->fetch_row()[0] ?? 0);
    $godownIds = array_filter([$llpId, $hcId]);
    if (empty($godownIds)) return [];
    $godownList = implode(',', $godownIds);

    $hasDateFilter = ($from_date !== '' && $to_date !== '');

    $soldByProduct = [];

    $dateFilter1 = $hasDateFilter ? "AND ui.date BETWEEN '$from_date' AND '$to_date'" : "";
    $sql1 = "SELECT ii.pr_id, SUM(ii.qty) AS q
             FROM user_invoice ui
             JOIN user_invoice_items ii ON ii.inv_id = ui.inv_id
             WHERE ui.from_user_type = 'company' AND ui.from_user_id IN ($godownList) $dateFilter1
             GROUP BY ii.pr_id";
    $res1 = $db_conn->query($sql1);
    while ($row = $res1->fetch_assoc()) {
        $pid = (int)$row['pr_id'];
        $soldByProduct[$pid] = ($soldByProduct[$pid] ?? 0) + (int)$row['q'];
    }

    $dateFilter2 = $hasDateFilter ? "AND i.date BETWEEN '$from_date' AND '$to_date'" : "";
    $sql2 = "SELECT ii.pr_id, SUM(ii.qty) AS q
             FROM invoice i
             JOIN invoice_items ii ON ii.inv_id = i.inv_id
             WHERE i.user_type = 'company' AND i.user_id IN ($godownList) $dateFilter2
             GROUP BY ii.pr_id";
    $res2 = $db_conn->query($sql2);
    while ($row = $res2->fetch_assoc()) {
        $pid = (int)$row['pr_id'];
        $soldByProduct[$pid] = ($soldByProduct[$pid] ?? 0) + (int)$row['q'];
    }

    return $soldByProduct;
}

// Pieces sold per neksomo_product_id, converted from LLP/Healthcare pack sales
// via the manually-curated neksomo_product_mapping (pack qty * pieces_per_pack,
// summed across every company product mapped to that neksomo product).
function get_neksomo_pieces_sold_via_llp_healthcare($db_conn, $from_date, $to_date) {
    $soldByProduct = get_llp_healthcare_sold_packs($db_conn, $from_date, $to_date);
    if (empty($soldByProduct)) return [];

    $res = $db_conn->query(
        "SELECT m.neksomo_product_id, m.company_product_id, p.pieces_per_pack
         FROM neksomo_product_mapping m
         JOIN products p ON p.id = m.company_product_id"
    );
    $piecesByNeksomoProduct = [];
    while ($row = $res->fetch_assoc()) {
        $companyPid = (int)$row['company_product_id'];
        $soldPacks  = $soldByProduct[$companyPid] ?? 0;
        if ($soldPacks === 0) continue;
        $ppp = max((int)$row['pieces_per_pack'], 1);
        $nid = (int)$row['neksomo_product_id'];
        $piecesByNeksomoProduct[$nid] = ($piecesByNeksomoProduct[$nid] ?? 0) + ($soldPacks * $ppp);
    }
    return $piecesByNeksomoProduct;
}
