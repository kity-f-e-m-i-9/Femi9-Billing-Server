<?php
/**
 * Table 4 (GSTR-1) B2B buyer-wise computation, shared between GSTR1.php (the
 * summary row) and gst_b2b_buyer_report.php (the full buyer-wise detail page)
 * so both always agree on the same numbers.
 *
 * One row per individual registered (B2B) buyer — SS/ST/DT/Shop/Customer each
 * keyed by temp_id/id, TP keyed by territory_partner_id, a company-godown
 * transfer recipient keyed by godown id — across all channels, net of that
 * buyer's own credit notes. Rated (gst_percentage/gst > 0) supplies only —
 * nil-rated lines belong in Table 8, not here. B2C (unregistered) buyers are
 * covered in aggregate by the B2B/B2C tables elsewhere on GSTR1.php, not
 * listed individually since they're typically walk-in/anonymous.
 *
 * Requires TpGstHelper.php's tp_sales_gst_lines() to already have been run for
 * this godown/period — pass its result as $tp_sls_lines (GSTR1.php computes
 * this once via gst_details.php and reuses it here to avoid a second query).
 */

if (!function_exists('b2b_buyer_add')) {
function b2b_buyer_add(&$buyers, $key, $name, $type, $gstin, $inv, $taxable, $is_intra, $gst_amount) {
    if (!isset($buyers[$key])) {
        $buyers[$key] = ['name' => $name, 'type' => $type, 'gstin' => $gstin, 'invoices' => [], 'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
    }
    $buyers[$key]['invoices'][$inv] = true;
    $buyers[$key]['taxable'] += $taxable;
    if ($is_intra) { $buyers[$key]['cgst'] += $gst_amount / 2; $buyers[$key]['sgst'] += $gst_amount / 2; }
    else { $buyers[$key]['igst'] += $gst_amount; }
}
}

if (!function_exists('compute_b2b_buyers')) {
function compute_b2b_buyers($db_conn, $Login_user_TYPEvl, $get_godown_id, $from_date, $to_date, array $tp_sls_lines) {
    $b2b_buyers = [];

    // Network sales (SS/ST/DT/Shop). gst_percentage>0 only.
    $q = "
        SELECT uii.to_user_type, uii.to_user_id, ui.inv_number, uii.gst_type,
               SUM(uii.total-uii.gstamount_total) AS taxable, SUM(uii.gstamount_total) AS gst_amt,
               COALESCE(ss.name,st.name,dt.name,sh.name) AS bname,
               COALESCE(ss.gstin,st.gstin,dt.gstin,sh.gstin) AS bgstin
        FROM user_invoice_items uii
        LEFT JOIN user_invoice ui ON ui.inv_id = uii.inv_id
        LEFT JOIN super_stockiest ss ON uii.to_user_type='super_stockiest' AND ss.temp_id=uii.to_user_id
        LEFT JOIN stockiest       st ON uii.to_user_type='stockiest'       AND st.temp_id=uii.to_user_id
        LEFT JOIN distributor     dt ON uii.to_user_type='distributor'     AND dt.temp_id=uii.to_user_id
        LEFT JOIN shop            sh ON uii.to_user_type='shop'            AND sh.temp_id=uii.to_user_id
        WHERE uii.from_user_type='$Login_user_TYPEvl' AND uii.from_user_id='$get_godown_id'
          AND uii.buyer_gsttype='register' AND uii.gst_percentage>0 AND uii.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY uii.to_user_type, uii.to_user_id, ui.inv_number, uii.gst_type, ss.name, st.name, dt.name, sh.name, ss.gstin, st.gstin, dt.gstin, sh.gstin
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        b2b_buyer_add($b2b_buyers, $r['to_user_type'].'_'.$r['to_user_id'], $r['bname'], ucfirst(str_replace('_',' ',$r['to_user_type'])), $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $r['gst_type']=='inner', (float)$r['gst_amt']);
    }

    // Customer sales. gst_percentage>0 only.
    $q = "
        SELECT ii.customer_id, i.inv_number, ii.gst_type,
               SUM(ii.total-ii.gstamount_total) AS taxable, SUM(ii.gstamount_total) AS gst_amt,
               c.name AS bname, c.gstin AS bgstin
        FROM invoice_items ii
        LEFT JOIN invoice i ON i.inv_id = ii.inv_id
        LEFT JOIN customers c ON c.id = ii.customer_id
        WHERE ii.user_type='$Login_user_TYPEvl' AND ii.user_id='$get_godown_id'
          AND ii.buyer_gsttype='register' AND ii.gst_percentage>0 AND ii.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY ii.customer_id, i.inv_number, ii.gst_type, c.name, c.gstin
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        b2b_buyer_add($b2b_buyers, 'customer_'.$r['customer_id'], $r['bname'], 'Customer', $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $r['gst_type']=='inner', (float)$r['gst_amt']);
    }

    // OT sales. gst>0 only.
    $q = "
        SELECT s.tempid, i.inv_number, s.gst_type, s.customer_name AS bname, s.gst_number AS bgstin,
               SUM(s.total-s.gst_amount) AS taxable, SUM(s.gst_amount) AS gst_amt
        FROM ot_sales s
        LEFT JOIN ot_sales_invoice i ON i.tempid = s.tempid
        WHERE s.godownid='$get_godown_id' AND s.buyer_gsttype='register' AND s.gst>0 AND s.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY s.tempid, i.inv_number, s.gst_type, s.customer_name, s.gst_number
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        b2b_buyer_add($b2b_buyers, 'ot_'.$r['bgstin'].'_'.$r['bname'], $r['bname'], 'OT Sale', $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $r['gst_type']=='inner', (float)$r['gst_amt']);
    }

    // TP invoices — reuses the caller's already-computed per-line list (see
    // gst_details.php) rather than re-querying, and applies the same
    // registered + rated-only filter.
    foreach ($tp_sls_lines as $l) {
        if (!$l['is_registered'] || (float)$l['gst_percentage'] <= 0) continue;
        b2b_buyer_add($b2b_buyers, 'tp_'.$l['tp_invoice_id'], $l['tp_name'], 'Territory Partner', $l['tp_gstin'], $l['invoice_number'], $l['taxable_value'], $l['is_intra'], $l['gst_amount']);
    }

    // Internal transfers (company godown -> company godown, e.g. Neksomo ->
    // Health Care -> LLP). Each company_godown has its own distinct GSTIN, so
    // a transfer between them is a real B2B outward supply — the receiving
    // godown is the "buyer" here. No internal-transfer credit-note/return
    // concept exists in this system, so no return-netting step is needed.
    $q = "
        SELECT it.send_to, cg.gname AS bname, cg.gstin AS bgstin, cg.state AS to_state, it_from.state AS from_state,
               COALESCE(iti.inv_number, it.tempid) AS inv_number, it.gst_type,
               SUM(it.total - it.gst_amount) AS taxable, SUM(it.gst_amount) AS gst_amt
        FROM internal_transfer it
        LEFT JOIN internal_transfer_invoice iti ON iti.tempid = it.tempid
        JOIN company_godown cg ON cg.id = it.send_to
        JOIN company_godown it_from ON it_from.id = it.send_from
        WHERE it.send_from='$get_godown_id' AND it.gst>0 AND it.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY it.send_to, cg.gname, cg.gstin, cg.state, it_from.state, COALESCE(iti.inv_number, it.tempid), it.gst_type
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $is_intra = strtolower(trim($r['to_state'])) == strtolower(trim($r['from_state']));
        b2b_buyer_add($b2b_buyers, 'godown_'.$r['send_to'], $r['bname'], 'Internal Transfer', $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $is_intra, (float)$r['gst_amt']);
    }

    // Net out each buyer's own registered-person credit notes (returns),
    // matched by buyer identity where the return table carries it directly.
    // gst_percentage>0 only, matching the rated-only taxable base being netted.
    $q = "
        SELECT rsi.from_usertype, rsi.from_userid, rsi.gst_type,
               SUM(rsi.total-rsi.gstamount_total) AS taxable, SUM(rsi.gstamount_total) AS gst_amt
        FROM user_return_stock_items rsi
        WHERE rsi.to_usertype='$Login_user_TYPEvl' AND rsi.to_userid='$get_godown_id'
          AND rsi.buyer_gsttype='register' AND rsi.from_usertype != 'customer'
          AND rsi.gst_percentage>0 AND rsi.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY rsi.from_usertype, rsi.from_userid, rsi.gst_type
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $key = $r['from_usertype'].'_'.$r['from_userid'];
        if (!isset($b2b_buyers[$key])) continue;
        $b2b_buyers[$key]['taxable'] -= (float)$r['taxable'];
        if ($r['gst_type']=='inner') { $b2b_buyers[$key]['cgst'] -= (float)$r['gst_amt']/2; $b2b_buyers[$key]['sgst'] -= (float)$r['gst_amt']/2; }
        else { $b2b_buyers[$key]['igst'] -= (float)$r['gst_amt']; }
    }

    usort($b2b_buyers, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
    return $b2b_buyers;
}
}
