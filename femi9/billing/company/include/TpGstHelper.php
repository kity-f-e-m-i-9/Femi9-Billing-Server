<?php
/**
 * GST totals for TP (territory partner) transfers/returns, for inclusion in the
 * company's GST reports (GST-report.php, GSTR1.php, GSTR3B_D31.php).
 *
 * Only tp_invoices with source_godown_id > 0 are company outward supply — TP
 * invoices sourced from a channel partner (source_cp_id) or neither are not the
 * company's own GST liability and are excluded.
 *
 * Neither tp_invoices/tp_invoice_items nor the TP branch of user_return_stock
 * store gst_type/buyer_gsttype/GST amount at write time (tp-cnote-action.php
 * inserts them blank/zero), so both are (re)computed here from products.gst /
 * products.gst_type, territory_partners.gstin (register if 15-char GSTIN) and a
 * fuzzy compare of territory_partners.branch_state against the source godown's
 * state (intra vs inter). The fuzzy compare tolerates minor typos (e.g. "Tamill
 * nadu") via Levenshtein distance, but can't recover a state from a misentered
 * district name (e.g. "Pudukkottai") — those fall through as inter-state.
 */

function tp_state_is_intra($tp_state, $godown_state) {
    $norm = function ($s) { return preg_replace('/[^a-z]/', '', strtolower((string)$s)); };
    $a = $norm($tp_state);
    $b = $norm($godown_state);
    if ($a === '') return true; // no state on file — assume local, matches pre-existing invoice behaviour
    if ($a === $b) return true;
    return levenshtein($a, $b) <= 2;
}

function tp_line_taxable_and_gst($line_total, $gst_pct, $product_gst_type) {
    $gst_pct = (int)$gst_pct;
    if (($product_gst_type ?: 'exclusive') === 'inclusive' && $gst_pct > 0) {
        $taxable = $line_total * 100 / (100 + $gst_pct);
        $gst     = $line_total - $taxable;
    } else {
        $taxable = $line_total;
        $gst     = $line_total * $gst_pct / 100;
    }
    return [$taxable, $gst];
}

// $godown_where_sql: raw SQL boolean fragment on tpi.source_godown_id, e.g. "tpi.source_godown_id = '5'"
// or "tpi.source_godown_id IN (SELECT id FROM company_godown WHERE ...)".
function tp_sales_gst_lines($db_conn, $from_date, $to_date, $godown_where_sql) {
    $sql = "
        SELECT tpi.id AS tp_invoice_id, tpi.invoice_number, tpi.invoice_date,
               tp.name AS tp_name, tp.mobile AS tp_mobile,
               tp.branch_state AS tp_state, tp.gstin AS tp_gstin, cg.state AS godown_state,
               tpii.amount, p.gst AS gst_percentage, p.gst_type AS product_gst_type
        FROM tp_invoices tpi
        JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
        JOIN tp_invoice_items tpii ON tpii.tp_invoice_id = tpi.id
        JOIN products p ON p.id = tpii.product_id
        JOIN company_godown cg ON cg.id = tpi.source_godown_id
        WHERE tpi.invoice_date BETWEEN '$from_date' AND '$to_date'
          AND tpi.source_godown_id > 0
          AND ($godown_where_sql)
    ";
    $res = mysqli_query($db_conn, $sql);
    $lines = [];
    while ($row = mysqli_fetch_assoc($res)) {
        [$taxable, $gst] = tp_line_taxable_and_gst((float)$row['amount'], $row['gst_percentage'], $row['product_gst_type']);
        $row['taxable_value'] = $taxable;
        $row['gst_amount']    = $gst;
        $row['is_intra']      = tp_state_is_intra($row['tp_state'], $row['godown_state']);
        $row['is_registered'] = strlen(trim((string)$row['tp_gstin'])) == 15;
        $lines[] = $row;
    }
    return $lines;
}

// Same shape as tp_sales_gst_lines but for finalised TP sales returns (credit notes).
// tp-cnote-action.php stores these in the shared user_return_stock(_items) tables
// tagged from_usertype='territory_partner', joined back to tp_invoices via invnumber
// to recover the source godown / TP (to_userid is stored as the literal string
// 'company', not a godown id, so it can't be used for godown scoping).
function tp_credit_gst_lines($db_conn, $from_date, $to_date, $godown_where_sql) {
    $sql = "
        SELECT urs.returnid, urs.invnumber AS invoice_number, tpi.invoice_date AS invoice_date, ursi.date AS return_date,
               tp.name AS tp_name, tp.mobile AS tp_mobile,
               tp.branch_state AS tp_state, tp.gstin AS tp_gstin, cg.state AS godown_state,
               ursi.total AS amount, p.gst AS gst_percentage, p.gst_type AS product_gst_type
        FROM user_return_stock_items ursi
        JOIN user_return_stock urs ON urs.returnid = ursi.returnid
        JOIN tp_invoices tpi ON tpi.invoice_number = urs.invnumber COLLATE utf8mb4_unicode_ci
        JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
        JOIN company_godown cg ON cg.id = tpi.source_godown_id
        JOIN products p ON p.id = ursi.prid
        WHERE urs.from_usertype = 'territory_partner'
          AND urs.status = 'accept'
          AND ursi.date BETWEEN '$from_date' AND '$to_date'
          AND tpi.source_godown_id > 0
          AND ($godown_where_sql)
    ";
    $res = mysqli_query($db_conn, $sql);
    $lines = [];
    while ($row = mysqli_fetch_assoc($res)) {
        [$taxable, $gst] = tp_line_taxable_and_gst((float)$row['amount'], $row['gst_percentage'], $row['product_gst_type']);
        $row['taxable_value'] = $taxable;
        $row['gst_amount']    = $gst;
        $row['is_intra']      = tp_state_is_intra($row['tp_state'], $row['godown_state']);
        $row['is_registered'] = strlen(trim((string)$row['tp_gstin'])) == 15;
        $lines[] = $row;
    }
    return $lines;
}

// Collapses per-line rows (multiple products per invoice/return) into one row per
// invoice/return, summing taxable_value/gst_amount and keeping the first line's
// other fields (invoice_number, tp_name, etc. are the same across all lines of one doc).
function tp_group_lines($lines, $key_field) {
    $out = [];
    foreach ($lines as $l) {
        $key = $l[$key_field];
        if (!isset($out[$key])) {
            $out[$key] = $l;
            $out[$key]['taxable_value'] = 0;
            $out[$key]['gst_amount']    = 0;
        }
        $out[$key]['taxable_value'] += $l['taxable_value'];
        $out[$key]['gst_amount']    += $l['gst_amount'];
    }
    return array_values($out);
}

// Buckets taxable value into reg_intra / unreg_intra / reg_inter / unreg_inter.
function tp_gst_bucket_totals(array $lines) {
    $t = ['reg_intra' => 0.0, 'unreg_intra' => 0.0, 'reg_inter' => 0.0, 'unreg_inter' => 0.0];
    foreach ($lines as $l) {
        if ($l['is_intra'])  { $t[$l['is_registered'] ? 'reg_intra' : 'unreg_intra'] += $l['taxable_value']; }
        else                 { $t[$l['is_registered'] ? 'reg_inter' : 'unreg_inter'] += $l['taxable_value']; }
    }
    return $t;
}
