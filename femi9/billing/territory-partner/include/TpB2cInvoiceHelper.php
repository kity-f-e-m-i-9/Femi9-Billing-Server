<?php
/**
 * Table 7 (GSTR-1) B2C (Others) invoice-wise computation for the
 * territory-partner login — a scaled-down port of
 * company/include/B2cInvoiceHelper.php (Shop/Customer channels only, no OT
 * sales/internal transfer/TP-invoices-received).
 */

require_once __DIR__ . '/TpB2bBuyerHelper.php';

if (!function_exists('tp_compute_b2c_invoices')) {
function tp_compute_b2c_invoices($db_conn, $Login_user_TYPEvl, $tp_id, $from_date, $to_date) {
    $invoices = [];

    $tp_state_code = '';
    $gres = mysqli_query($db_conn, "select gstin from territory_partners where id='$tp_id'");
    if ($grow = mysqli_fetch_assoc($gres)) { $tp_state_code = tp_b2b_gstin_state_code($grow['gstin']); }

    $push = function(&$invoices, $row, $fallback_is_intra) use ($tp_state_code) {
        $is_intra = tp_b2b_resolve_is_intra($row['gstin'], $tp_state_code, $fallback_is_intra);
        $gst_amount = $row['gst_amount'];
        $row['is_intra'] = $is_intra;
        $row['cgst'] = $is_intra ? $gst_amount / 2 : 0;
        $row['sgst'] = $is_intra ? $gst_amount / 2 : 0;
        $row['igst'] = $is_intra ? 0 : $gst_amount;
        $invoices[] = $row;
    };

    // Shop sales. gst_percentage>0 only.
    $q = "
        SELECT ui.inv_number, uii.date, uii.gst_type,
               SUM(uii.total-uii.gstamount_total) AS taxable, SUM(uii.gstamount_total) AS gst_amt,
               sh.name AS bname, sh.mobile_number AS bmobile
        FROM user_invoice_items uii
        LEFT JOIN user_invoice ui ON ui.inv_id = uii.inv_id
        LEFT JOIN shop sh ON sh.temp_id = uii.to_user_id
        WHERE uii.from_user_type='$Login_user_TYPEvl' AND uii.from_user_id='$tp_id'
          AND uii.buyer_gsttype='unregister' AND uii.gst_percentage>0 AND uii.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY uii.to_user_id, ui.inv_number, uii.date, uii.gst_type, sh.name, sh.mobile_number
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $push($invoices, [
            'type' => 'Shop', 'name' => $r['bname'], 'mobile' => $r['bmobile'], 'gstin' => '',
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
        WHERE ii.user_type='$Login_user_TYPEvl' AND ii.user_id='$tp_id'
          AND ii.buyer_gsttype='unregister' AND ii.gst_percentage>0 AND ii.date BETWEEN '$from_date' AND '$to_date'
        GROUP BY ii.customer_id, i.inv_number, ii.date, ii.gst_type, c.name, c.mobile
    ";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $push($invoices, [
            'type' => 'Customer', 'name' => $r['bname'], 'mobile' => $r['bmobile'], 'gstin' => '',
            'invoice_number' => $r['inv_number'], 'date' => $r['date'],
            'taxable' => (float)$r['taxable'], 'gst_amount' => (float)$r['gst_amt'],
        ], $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']));
    }

    // Credit notes/returns — as their own negative-value "Credit Note" rows,
    // same convention as company/include/B2cInvoiceHelper.php.
    $q = "select gst_type, from_userid, date, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and (buyer_gsttype='unregister' or buyer_gsttype not in ('register','unregister')) and gst_percentage>0 and date between '$from_date' and '$to_date' group by gst_type, from_userid, date";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) {
        $push($invoices, [
            'type' => 'Credit Note', 'name' => '', 'mobile' => '', 'gstin' => '',
            'invoice_number' => 'Return-'.$r['from_userid'], 'date' => $r['date'],
            'taxable' => -(float)$r['taxable'], 'gst_amount' => -(float)$r['gst_amt'],
        ], $r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']));
    }

    usort($invoices, fn($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
    return $invoices;
}
}

if (!function_exists('tp_compute_b2c_totals')) {
function tp_compute_b2c_totals($db_conn, $Login_user_TYPEvl, $tp_id, $from_date, $to_date) {
    $tp_state_code = '';
    $gres = mysqli_query($db_conn, "select gstin from territory_partners where id='$tp_id'");
    if ($grow = mysqli_fetch_assoc($gres)) { $tp_state_code = tp_b2b_gstin_state_code($grow['gstin']); }

    $t = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
    $add = function($fallback_is_intra, $taxable, $gst_amt) use (&$t) {
        $t['taxable'] += $taxable;
        if ($fallback_is_intra) { $t['cgst'] += $gst_amt / 2; $t['sgst'] += $gst_amt / 2; }
        else { $t['igst'] += $gst_amt; }
    };

    $q = "select gst_type, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$tp_id' and buyer_gsttype='unregister' and gst_percentage>0 and date between '$from_date' and '$to_date' group by gst_type";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) { $add($r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), (float)$r['taxable'], (float)$r['gst_amt']); }

    $q = "select gst_type, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$tp_id' and buyer_gsttype='unregister' and gst_percentage>0 and date between '$from_date' and '$to_date' group by gst_type";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) { $add($r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), (float)$r['taxable'], (float)$r['gst_amt']); }

    $q = "select gst_type, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$tp_id' and (buyer_gsttype='unregister' or buyer_gsttype not in ('register','unregister')) and gst_percentage>0 and date between '$from_date' and '$to_date' group by gst_type";
    $res = mysqli_query($db_conn, $q);
    while ($r = mysqli_fetch_assoc($res)) { $add($r['gst_type']=='inner' || !in_array($r['gst_type'],['inner','outer']), -(float)$r['taxable'], -(float)$r['gst_amt']); }

    return $t;
}
}
