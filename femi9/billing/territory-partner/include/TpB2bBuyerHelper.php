<?php
/**
 * Table 4 (GSTR-1) B2B buyer-wise computation for the territory-partner
 * login — a scaled-down port of company/include/B2bBuyerHelper.php, since a
 * TP only sells to Shop and Customer (no OT sales, internal transfer, or
 * TP-invoices-received — a TP doesn't buy TP invoices from itself).
 *
 * CGST/SGST vs IGST is decided from the buyer's own GSTIN (its first 2
 * digits are the GST state code) compared against this TP's own GSTIN state
 * code, not from the invoice's stored gst_type flag — same GSTIN-first
 * approach as the company version. Falls back to the stored gst_type flag
 * when the buyer's GSTIN doesn't validate.
 */

if (!function_exists('tp_b2b_gstin_state_code')) {
function tp_b2b_gstin_state_code($gstin) {
    $g = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$gstin));
    if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $g)) return null;
    return substr($g, 0, 2);
}
}

if (!function_exists('tp_b2b_resolve_is_intra')) {
function tp_b2b_resolve_is_intra($buyer_gstin, $tp_state_code, $fallback_is_intra) {
    $buyer_code = tp_b2b_gstin_state_code($buyer_gstin);
    if ($buyer_code === null || empty($tp_state_code)) return $fallback_is_intra;
    return $buyer_code === (string)$tp_state_code;
}
}

if (!function_exists('tp_b2b_buyer_add')) {
function tp_b2b_buyer_add(&$buyers, $key, $name, $type, $gstin, $inv, $taxable, $is_intra, $gst_amount, $inv_date = null) {
    if (!isset($buyers[$key])) {
        $buyers[$key] = ['name' => $name, 'type' => $type, 'gstin' => $gstin, 'invoices' => [], 'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
    }
    if (!isset($buyers[$key]['invoices'][$inv])) {
        $buyers[$key]['invoices'][$inv] = $inv_date;
    }
    $buyers[$key]['taxable'] += $taxable;
    if ($is_intra) { $buyers[$key]['cgst'] += $gst_amount / 2; $buyers[$key]['sgst'] += $gst_amount / 2; }
    else { $buyers[$key]['igst'] += $gst_amount; }
}
}

if (!function_exists('tp_compute_b2b_buyers')) {
function tp_compute_b2b_buyers($db_conn, $Login_user_TYPEvl, $tp_id, $from_date, $to_date) {
    $b2b_buyers = [];

    // This TP's own GSTIN state-code, used to check each buyer's GSTIN
    // state-code against it.
    $tp_state_code = '';
    $gres = mysqli_query($db_conn, "select gstin from territory_partners where id='$tp_id'");
    if ($grow = mysqli_fetch_assoc($gres)) { $tp_state_code = tp_b2b_gstin_state_code($grow['gstin']); }

    // Shop sales. gst_percentage>0 only — Table 4 is B2B *rated* supplies.
    $q = "
        SELECT ui.inv_number, uii.date, uii.gst_type,
               SUM(uii.total-uii.gstamount_total) AS taxable, SUM(uii.gstamount_total) AS gst_amt,
               sh.name AS bname, sh.gstin AS bgstin
        FROM user_invoice_items uii
        LEFT JOIN user_invoice ui ON ui.inv_id = uii.inv_id
        LEFT JOIN shop sh ON sh.temp_id = uii.to_user_id
        WHERE uii.from_user_type='$Login_user_TYPEvl' AND uii.from_user_id='$tp_id'
          AND uii.buyer_gsttype='register' AND uii.gst_percentage>0 AND uii.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY uii.to_user_id, ui.inv_number, uii.date, uii.gst_type, sh.name, sh.gstin
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $is_intra = tp_b2b_resolve_is_intra($r['bgstin'], $tp_state_code, $r['gst_type']=='inner');
        tp_b2b_buyer_add($b2b_buyers, 'shop_'.$r['bname'].'_'.$r['bgstin'], $r['bname'], 'Shop', $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $is_intra, (float)$r['gst_amt'], $r['date']);
    }

    // Customer sales. gst_percentage>0 only.
    $q = "
        SELECT i.inv_number, ii.date, ii.gst_type,
               SUM(ii.total-ii.gstamount_total) AS taxable, SUM(ii.gstamount_total) AS gst_amt,
               c.name AS bname, c.gstin AS bgstin
        FROM invoice_items ii
        LEFT JOIN invoice i ON i.inv_id = ii.inv_id
        LEFT JOIN customers c ON c.id = ii.customer_id
        WHERE ii.user_type='$Login_user_TYPEvl' AND ii.user_id='$tp_id'
          AND ii.buyer_gsttype='register' AND ii.gst_percentage>0 AND ii.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY ii.customer_id, i.inv_number, ii.date, ii.gst_type, c.name, c.gstin
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $is_intra = tp_b2b_resolve_is_intra($r['bgstin'], $tp_state_code, $r['gst_type']=='inner');
        tp_b2b_buyer_add($b2b_buyers, 'customer_'.$r['bname'].'_'.$r['bgstin'], $r['bname'], 'Customer', $r['bgstin'], $r['inv_number'], (float)$r['taxable'], $is_intra, (float)$r['gst_amt'], $r['date']);
    }

    // Net out each buyer's own registered-person credit notes (returns).
    // gst_percentage>0 only, matching the rated-only taxable base being netted.
    $q = "
        SELECT rsi.from_usertype, rsi.from_userid, rsi.gst_type,
               SUM(rsi.total-rsi.gstamount_total) AS taxable, SUM(rsi.gstamount_total) AS gst_amt
        FROM user_return_stock_items rsi
        WHERE rsi.to_usertype='$Login_user_TYPEvl' AND rsi.to_userid='$tp_id'
          AND rsi.buyer_gsttype='register' AND rsi.gst_percentage>0 AND rsi.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY rsi.from_usertype, rsi.from_userid, rsi.gst_type
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        // Match by buyer identity: shop/customer keyed by name+gstin above, so
        // look up which key this return's from_userid belongs to via a join
        // back to the shop/customers table for the matching name+gstin.
        $lookup_table = $r['from_usertype'] === 'customer' ? 'customers' : 'shop';
        $lookup_col = $r['from_usertype'] === 'customer' ? 'id' : 'temp_id';
        $lres = mysqli_query($db_conn, "select name, gstin from $lookup_table where $lookup_col='".mysqli_real_escape_string($db_conn, $r['from_userid'])."'");
        $lrow = mysqli_fetch_assoc($lres);
        if (!$lrow) continue;
        $prefix = $r['from_usertype'] === 'customer' ? 'customer_' : 'shop_';
        $key = $prefix.$lrow['name'].'_'.$lrow['gstin'];
        if (!isset($b2b_buyers[$key])) continue;
        $is_intra = tp_b2b_resolve_is_intra($b2b_buyers[$key]['gstin'], $tp_state_code, $r['gst_type']=='inner');
        $b2b_buyers[$key]['taxable'] -= (float)$r['taxable'];
        if ($is_intra) { $b2b_buyers[$key]['cgst'] -= (float)$r['gst_amt']/2; $b2b_buyers[$key]['sgst'] -= (float)$r['gst_amt']/2; }
        else { $b2b_buyers[$key]['igst'] -= (float)$r['gst_amt']; }
    }

    usort($b2b_buyers, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
    return $b2b_buyers;
}
}
