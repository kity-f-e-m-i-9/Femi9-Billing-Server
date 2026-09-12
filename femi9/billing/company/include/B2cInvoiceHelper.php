<?php
/**
 * Table 7 (GSTR-1) B2C (Others) invoice-wise computation, shared between
 * GSTR1.php (the aggregate summary row) and gst_b2c_invoice_report.php (the
 * per-invoice detail page) so both always agree on the same numbers.
 *
 * One row per individual invoice from an unregistered (B2C) buyer — Network
 * sales (SS/ST/DT/Shop), Customer sales, OT sales, TP invoices — rated
 * (gst_percentage/gst > 0) supplies only, nil-rated lines belong in Table 8.
 * Credit notes/returns are listed as their own negative-value rows (channel
 * "Credit Note") rather than netted invisibly against a specific original
 * invoice — most return tables here don't reliably carry the original
 * invoice's identity — so the on-screen total still ties out exactly to
 * GSTR1.php's Table 7 summary row (which nets credit notes in aggregate via
 * compute_b2c_totals() below) while staying transparent about what moved.
 *
 * Requires TpGstHelper.php's tp_sales_gst_lines()/tp_credit_gst_lines() to
 * already have been run for this godown/period — pass their results as
 * $tp_sls_lines / $tp_credit_lines (GSTR1.php computes these once via
 * gst_details.php/gst_details_credit.php and reuses them here).
 *
 * CGST/SGST vs IGST per row uses the same GSTIN-state-code approach as
 * include/B2bBuyerHelper.php's b2b_resolve_is_intra() — most B2C buyers here
 * (Network/Customer/OT sales) have no GSTIN at all since they're
 * unregistered by definition, so those fall back to the invoice's own
 * stored gst_type flag; only TP invoices carry a real buyer GSTIN to check.
 */

require_once __DIR__ . '/B2bBuyerHelper.php';

if (!function_exists('compute_b2c_invoices')) {
function compute_b2c_invoices($db_conn, $Login_user_TYPEvl, $get_godown_id, $from_date, $to_date, array $tp_sls_lines, array $tp_credit_lines) {
    $invoices = []; // list of ['type','name','mobile','gstin','invoice_number','date','taxable','gst_amount','cgst','sgst','igst','is_intra']

    $godown_state_code = '';
    $gres = mysqli_query($db_conn, "select state_code from company_godown where id='$get_godown_id'");
    if ($grow = mysqli_fetch_assoc($gres)) { $godown_state_code = $grow['state_code']; }

    // Splits gst_amount into cgst/sgst/igst for one invoice row, given its
    // GSTIN (if any) and the invoice's own stored gst_type-derived flag as
    // fallback, and folds those fields into the row before appending it.
    $push = function(&$invoices, $row, $fallback_is_intra) use ($godown_state_code) {
        $is_intra = b2b_resolve_is_intra($row['gstin'], $godown_state_code, $fallback_is_intra);
        $gst_amount = $row['gst_amount'];
        $row['is_intra'] = $is_intra;
        $row['cgst'] = $is_intra ? $gst_amount / 2 : 0;
        $row['sgst'] = $is_intra ? $gst_amount / 2 : 0;
        $row['igst'] = $is_intra ? 0 : $gst_amount;
        $invoices[] = $row;
    };

    // Network sales (SS/ST/DT/Shop). gst_percentage>0 only.
    $q = "
        SELECT uii.to_user_type, ui.inv_number, uii.date, uii.gst_type,
               SUM(uii.total-uii.gstamount_total) AS taxable, SUM(uii.gstamount_total) AS gst_amt,
               COALESCE(ss.name,st.name,dt.name,sh.name) AS bname,
               COALESCE(ss.mobile_number,st.mobile_number,dt.mobile_number,sh.mobile_number) AS bmobile
        FROM user_invoice_items uii
        LEFT JOIN user_invoice ui ON ui.inv_id = uii.inv_id
        LEFT JOIN super_stockiest ss ON uii.to_user_type='super_stockiest' AND ss.temp_id=uii.to_user_id
        LEFT JOIN stockiest       st ON uii.to_user_type='stockiest'       AND st.temp_id=uii.to_user_id
        LEFT JOIN distributor     dt ON uii.to_user_type='distributor'     AND dt.temp_id=uii.to_user_id
        LEFT JOIN shop            sh ON uii.to_user_type='shop'            AND sh.temp_id=uii.to_user_id
        WHERE uii.from_user_type='$Login_user_TYPEvl' AND uii.from_user_id='$get_godown_id'
          AND uii.buyer_gsttype='unregister' AND uii.gst_percentage>0 AND uii.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY uii.to_user_type, ui.inv_number, uii.date, uii.gst_type, ss.name, st.name, dt.name, sh.name, ss.mobile_number, st.mobile_number, dt.mobile_number, sh.mobile_number
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $push($invoices, [
            'type' => ucfirst(str_replace('_',' ',$r['to_user_type'])), 'name' => $r['bname'], 'mobile' => $r['bmobile'], 'gstin' => '',
            'invoice_number' => $r['inv_number'], 'date' => $r['date'],
            'taxable' => (float)$r['taxable'], 'gst_amount' => (float)$r['gst_amt'],
        ], $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']));
    }

    // Customer sales. gst_percentage>0 only.
    $q = "
        SELECT i.inv_number, ii.date, ii.gst_type,
               SUM(ii.total-ii.gstamount_total) AS taxable, SUM(ii.gstamount_total) AS gst_amt,
               c.name AS bname, c.mobile AS bmobile
        FROM invoice_items ii
        LEFT JOIN invoice i ON i.inv_id = ii.inv_id
        LEFT JOIN customers c ON c.id = ii.customer_id
        WHERE ii.user_type='$Login_user_TYPEvl' AND ii.user_id='$get_godown_id'
          AND ii.buyer_gsttype='unregister' AND ii.gst_percentage>0 AND ii.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY i.inv_number, ii.date, ii.gst_type, c.name, c.mobile
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $push($invoices, [
            'type' => 'Customer', 'name' => $r['bname'], 'mobile' => $r['bmobile'], 'gstin' => '',
            'invoice_number' => $r['inv_number'], 'date' => $r['date'],
            'taxable' => (float)$r['taxable'], 'gst_amount' => (float)$r['gst_amt'],
        ], $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']));
    }

    // OT sales. gst>0 only.
    $q = "
        SELECT i.inv_number, s.date, s.gst_type, s.customer_name AS bname, s.customer_mobile AS bmobile,
               SUM(s.total-s.gst_amount) AS taxable, SUM(s.gst_amount) AS gst_amt
        FROM ot_sales s
        LEFT JOIN ot_sales_invoice i ON i.tempid = s.tempid
        WHERE s.godownid='$get_godown_id' AND s.buyer_gsttype='unregister' AND s.gst>0 AND s.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY i.inv_number, s.date, s.gst_type, s.customer_name, s.customer_mobile
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $push($invoices, [
            'type' => 'OT Sale', 'name' => $r['bname'], 'mobile' => $r['bmobile'], 'gstin' => '',
            'invoice_number' => $r['inv_number'], 'date' => $r['date'],
            'taxable' => (float)$r['taxable'], 'gst_amount' => (float)$r['gst_amt'],
        ], $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']));
    }

    // TP invoices — reuses the caller's already-computed per-line list (see
    // gst_details.php), grouped into one row per invoice.
    $tp_unreg_rated = array_filter($tp_sls_lines, fn($l) => !$l['is_registered'] && (float)$l['gst_percentage'] > 0);
    foreach (tp_group_lines($tp_unreg_rated, 'tp_invoice_id') as $l) {
        $push($invoices, [
            'type' => 'Territory Partner', 'name' => $l['tp_name'], 'mobile' => $l['tp_mobile'] ?? '', 'gstin' => $l['tp_gstin'] ?? '',
            'invoice_number' => $l['invoice_number'], 'date' => $l['invoice_date'],
            'taxable' => $l['taxable_value'], 'gst_amount' => $l['gst_amount'],
        ], $l['is_intra']);
    }

    // Credit notes/returns — negative-value rows so the on-screen total still
    // ties out to compute_b2c_totals()'s aggregate net figure. gst_percentage>0
    // only, matching the rated-only base being netted.
    $q = "select rsi.gst_type, rsi.from_userid, rsi.date, sum(rsi.total-rsi.gstamount_total) as taxable, sum(rsi.gstamount_total) as gst_amt from user_return_stock_items rsi where rsi.to_usertype='$Login_user_TYPEvl' and rsi.to_userid='$get_godown_id' and (rsi.buyer_gsttype='unregister' or rsi.buyer_gsttype not in ('register','unregister')) and rsi.gst_percentage>0 and rsi.date between '$from_date' and '$to_date' group by rsi.gst_type, rsi.from_userid, rsi.date";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $push($invoices, [
            'type' => 'Credit Note', 'name' => '', 'mobile' => '', 'gstin' => '',
            'invoice_number' => 'Return-'.$r['from_userid'], 'date' => $r['date'],
            'taxable' => -(float)$r['taxable'], 'gst_amount' => -(float)$r['gst_amt'],
        ], $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']));
    }

    $q = "select osr.gst_type, osr.tempid, osr.return_date, sum(osr.total) as taxable from ot_sales_return osr join products p on p.id=osr.prid where osr.godownid='$get_godown_id' and osr.buyer_gsttype='unregister' and p.gst>0 and osr.return_date between '$from_date' and '$to_date' group by osr.gst_type, osr.tempid, osr.return_date";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $push($invoices, [
            'type' => 'Credit Note', 'name' => '', 'mobile' => '', 'gstin' => '',
            'invoice_number' => 'Return-'.$r['tempid'], 'date' => $r['return_date'],
            'taxable' => -(float)$r['taxable'], 'gst_amount' => 0,
        ], $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']));
    }

    foreach ($tp_credit_lines as $l) {
        if ($l['is_registered'] || (float)$l['gst_percentage'] <= 0) continue;
        $push($invoices, [
            'type' => 'Credit Note', 'name' => $l['tp_name'] ?? '', 'mobile' => $l['tp_mobile'] ?? '', 'gstin' => $l['tp_gstin'] ?? '',
            'invoice_number' => 'Return-'.($l['invoice_number'] ?? $l['returnid'] ?? ''), 'date' => $l['return_date'] ?? $l['invoice_date'],
            'taxable' => -$l['taxable_value'], 'gst_amount' => -$l['gst_amount'],
        ], $l['is_intra']);
    }

    usort($invoices, fn($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
    return $invoices;
}
}

// Aggregate B2C totals (taxable/CGST/SGST/IGST), net of credit notes — the
// same computation GSTR1.php's Table 7 summary row already did before this
// helper existed, extracted here so the detail page's totals can cross-check
// it, and so both files share one implementation.
if (!function_exists('compute_b2c_totals')) {
function compute_b2c_totals($db_conn, $Login_user_TYPEvl, $get_godown_id, $from_date, $to_date, array $tp_sls_lines, array $tp_credit_lines) {
    $godown_state_code = '';
    $gres = mysqli_query($db_conn, "select state_code from company_godown where id='$get_godown_id'");
    if ($grow = mysqli_fetch_assoc($gres)) { $godown_state_code = $grow['state_code']; }

    $t = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
    // $gstin is only ever non-empty for TP lines here — every other B2C
    // source has no buyer GSTIN at all (unregistered by definition) — so
    // this falls through to $fallback_is_intra for them, same as before.
    $add = function($gstin, $fallback_is_intra, $taxable, $gst_amt) use (&$t, $godown_state_code) {
        $is_intra = b2b_resolve_is_intra($gstin, $godown_state_code, $fallback_is_intra);
        $t['taxable'] += $taxable;
        if ($is_intra) { $t['cgst'] += $gst_amt / 2; $t['sgst'] += $gst_amt / 2; }
        else { $t['igst'] += $gst_amt; }
    };

    $q = "select gst_type, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id' and buyer_gsttype='unregister' and gst_percentage>0 and date between '$from_date' and '$to_date' group by gst_type";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) { $add('', $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), (float)$r['taxable'], (float)$r['gst_amt']); }

    $q = "select gst_type, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$get_godown_id' and buyer_gsttype='unregister' and gst_percentage>0 and date between '$from_date' and '$to_date' group by gst_type";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) { $add('', $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), (float)$r['taxable'], (float)$r['gst_amt']); }

    $q = "select gst_type, sum(total-gst_amount) as taxable, sum(gst_amount) as gst_amt from ot_sales where godownid='$get_godown_id' and buyer_gsttype='unregister' and gst>0 and date between '$from_date' and '$to_date' group by gst_type";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) { $add('', $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), (float)$r['taxable'], (float)$r['gst_amt']); }

    foreach ($tp_sls_lines as $l) {
        if ($l['is_registered'] || (float)$l['gst_percentage'] <= 0) continue;
        $add($l['tp_gstin'] ?? '', $l['is_intra'], $l['taxable_value'], $l['gst_amount']);
    }

    // Net out B2C credit notes (returns) — blank/NULL buyer_gsttype defaults
    // to unregister here too, matching gst_details_credit.php's own convention.
    $q = "select gst_type, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id' and (buyer_gsttype='unregister' or buyer_gsttype not in ('register','unregister')) and gst_percentage>0 and date between '$from_date' and '$to_date' group by gst_type";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) { $add('', $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), -(float)$r['taxable'], -(float)$r['gst_amt']); }

    $q = "select osr.gst_type, sum(osr.total) as taxable from ot_sales_return osr join products p on p.id=osr.prid where osr.godownid='$get_godown_id' and osr.buyer_gsttype='unregister' and p.gst>0 and osr.return_date between '$from_date' and '$to_date' group by osr.gst_type";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) { $add('', $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), -(float)$r['taxable'], 0); }

    foreach ($tp_credit_lines as $l) {
        if ($l['is_registered'] || (float)$l['gst_percentage'] <= 0) continue;
        $add($l['tp_gstin'] ?? '', $l['is_intra'], -$l['taxable_value'], -$l['gst_amount']);
    }

    return $t;
}
}
