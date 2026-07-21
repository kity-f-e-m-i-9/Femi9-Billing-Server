<?php
/**
 * Converts LLP/Healthcare sales of company pack-products into pieces sold of
 * the corresponding Neksomo piece-product, via neksomo_product_mapping.
 *
 * LLP and Healthcare are both company_godown rows. This mirrors the exact
 * "Sales Qty" formula used by overstock_datewise.php's computeStockMovement()
 * (total_sales = ot_sales + user_invoice_items + invoice_items + tp_invoice_items),
 * scoped to the LLP/Healthcare godown ids, so the two reports tie out:
 *
 *   ot_sales       -> off-track sales.           godownid = 1 (LLP) / 2 (Healthcare)
 *   invoice_items  -> customer invoices.          user_type='company',      user_id      = 1 (LLP) / 2 (Healthcare)
 *   user_invoice_items -> SS/SD/S/D/Shop invoices. from_user_type='company', from_user_id = 1 (LLP) / 2 (Healthcare)
 *   tp_invoices    -> territory partner invoices, godown-sourced only.     source_godown_id = 1 (LLP) / 2 (Healthcare)
 *                     (channel-partner-sourced TP invoices are already excluded here — their
 *                     source_godown_id is 0, mutually exclusive with source_cp_id at creation
 *                     time in tp-invoice-action.php — same as overstock_datewise.php relies on.)
 *
 * Filters are applied on each *_items table's own date column (not the invoice
 * header's date) to match overstock_datewise.php exactly — header and item
 * dates aren't guaranteed identical across every invoice-creation code path.
 */

// Sold qty (packs) per company product_id, from LLP + Healthcare godowns,
// optionally restricted to a date range. Sums ot_sales, invoice_items,
// user_invoice_items and tp_invoice_items — every table a company-issued sale
// can land in, same set overstock_datewise.php's Sales Qty column counts.
function get_llp_healthcare_sold_packs($db_conn, $from_date, $to_date) {
    $llpId = (int) ($db_conn->query("SELECT id FROM company_godown WHERE gname = 'FEMI NAYAN LLP' LIMIT 1")->fetch_row()[0] ?? 0);
    $hcId  = (int) ($db_conn->query("SELECT id FROM company_godown WHERE gname = 'FEMI HEALTH CARE' LIMIT 1")->fetch_row()[0] ?? 0);
    $godownIds = array_filter([$llpId, $hcId]);
    if (empty($godownIds)) return [];
    $godownList = implode(',', $godownIds);

    // Each bound is independently optional — from-only means "everything up
    // to now starting at from_date", to-only means "everything up to and
    // including to_date" (used for as-of-date running-balance snapshots),
    // both means the usual closed range, neither means all-time.
    $dateClause = function($column) use ($db_conn, $from_date, $to_date) {
        $conds = [];
        if ($from_date !== '') $conds[] = "$column >= '" . mysqli_real_escape_string($db_conn, $from_date) . "'";
        if ($to_date   !== '') $conds[] = "$column <= '" . mysqli_real_escape_string($db_conn, $to_date)   . "'";
        return $conds ? ('AND ' . implode(' AND ', $conds)) : '';
    };

    $soldByProduct = [];

    // ot_sales: off-track sales.
    $sqlOt = "SELECT prid AS pr_id, SUM(qty) AS q
              FROM ot_sales
              WHERE godownid IN ($godownList) " . $dateClause('date') . "
              GROUP BY prid";
    $resOt = $db_conn->query($sqlOt);
    while ($row = $resOt->fetch_assoc()) {
        $pid = (int)$row['pr_id'];
        $soldByProduct[$pid] = ($soldByProduct[$pid] ?? 0) + (int)$row['q'];
    }

    // user_invoice_items: SS/SD/S/D/Shop invoices. Filtered on the item row's
    // own date, not the user_invoice header's.
    $sql1 = "SELECT pr_id, SUM(qty) AS q
             FROM user_invoice_items
             WHERE from_user_type = 'company' AND from_user_id IN ($godownList) " . $dateClause('date') . "
             GROUP BY pr_id";
    $res1 = $db_conn->query($sql1);
    while ($row = $res1->fetch_assoc()) {
        $pid = (int)$row['pr_id'];
        $soldByProduct[$pid] = ($soldByProduct[$pid] ?? 0) + (int)$row['q'];
    }

    // invoice_items: direct customer invoices. Item-level date, same reason.
    $sql2 = "SELECT pr_id, SUM(qty) AS q
             FROM invoice_items
             WHERE user_type = 'company' AND user_id IN ($godownList) " . $dateClause('date') . "
             GROUP BY pr_id";
    $res2 = $db_conn->query($sql2);
    while ($row = $res2->fetch_assoc()) {
        $pid = (int)$row['pr_id'];
        $soldByProduct[$pid] = ($soldByProduct[$pid] ?? 0) + (int)$row['q'];
    }

    // tp_invoices: territory partner invoices, godown-sourced only. Channel-
    // partner-sourced rows are already excluded via source_godown_id (see
    // class doc comment above), so no separate source_cp_id check is needed.
    $sql3 = "SELECT tpii.product_id AS pr_id, SUM(tpii.quantity) AS q
             FROM tp_invoices tpi
             JOIN tp_invoice_items tpii ON tpii.tp_invoice_id = tpi.id
             WHERE tpi.source_godown_id IN ($godownList) " . $dateClause('tpi.invoice_date') . "
             GROUP BY tpii.product_id";
    $res3 = $db_conn->query($sql3);
    while ($row = $res3->fetch_assoc()) {
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
