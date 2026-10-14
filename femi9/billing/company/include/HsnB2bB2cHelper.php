<?php
/**
 * GSTR-1 HSN-wise B2B vs B2C split (taxable value + CGST/SGST/IGST), shared
 * by GSTR1.php (on-screen table) and export_GSTR1_hsn.php (CSV) so both always
 * show identical numbers.
 *
 * One row per (HSN, GST rate) — nil-rated (0%) HSNs are included. Each row
 * carries a 'b2b' and a 'b2c' bucket of qty / taxable / cgst / sgst / igst.
 * Sales add, returns (credit notes) subtract. Intra-state lines split GST
 * 50/50 into CGST/SGST, inter-state lines go to IGST — same gst_type
 * convention as include/B2cInvoiceHelper.php ('inner' or unknown => intra).
 *
 * Territory Partner invoice lines come from the caller's tp_sales_gst_lines()
 * result (already carries hsn, is_registered, is_intra); TP credit lines have
 * no HSN on them so, as before, they are not part of this table.
 */

if (!function_exists('compute_hsn_b2b_b2c')) {
function compute_hsn_b2b_b2c($db_conn, $Login_user_TYPEvl, $get_godown_id, $from_date, $to_date, array $tp_sls_lines) {
    $rows = []; // "hsn|rate" => ['hsn','rate','uqc','b2b'=>[...],'b2c'=>[...]]
    $blank = function () { return ['qty' => 0.0, 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0]; };

    // Everything is invoiced in packs ("Packs" on every invoice), so the UQC is always PAC-PACKS.

    $add = function ($hsn, $rate, $is_b2b, $is_intra, $qty, $taxable, $gst) use (&$rows, $blank) {
        $hsn = trim((string)$hsn); $rate = (float)$rate;
        $k = $hsn . '|' . $rate;
        if (!isset($rows[$k])) {
            $rows[$k] = ['hsn' => $hsn, 'rate' => $rate, 'uqc' => 'PAC-PACKS', 'b2b' => $blank(), 'b2c' => $blank()];
        }
        $b = $is_b2b ? 'b2b' : 'b2c';
        $rows[$k][$b]['qty']     += $qty;
        $rows[$k][$b]['taxable'] += $taxable;
        if ($is_intra) { $rows[$k][$b]['cgst'] += $gst / 2; $rows[$k][$b]['sgst'] += $gst / 2; }
        else           { $rows[$k][$b]['igst'] += $gst; }
    };
    $intra = function ($gst_type) { return $gst_type == 'inner' || !in_array($gst_type, ['inner', 'outer']); };

    // $sign +1 = sale, -1 = return. $strict_b2c: sales only count 'unregister'
    // as B2C; returns treat any non-'register' type as B2C (matches Table 7).
    $run = function ($sql, $sign, $strict_b2c) use ($db_conn, $add, $intra) {
        $res = mysqli_query($db_conn, $sql);
        while ($r = mysqli_fetch_assoc($res)) {
            $is_b2b = ($r['bgt'] === 'register');
            if ($strict_b2c && !$is_b2b && $r['bgt'] !== 'unregister') continue;
            $add($r['hsn'], $r['rate'], $is_b2b, $intra($r['gst_type']), $sign * (float)$r['qty'], $sign * (float)$r['taxable'], $sign * (float)$r['gst_amt']);
        }
    };

    $run("select hsn, gst_percentage as rate, buyer_gsttype as bgt, gst_type, sum(qty) as qty, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$get_godown_id' and date between '$from_date' and '$to_date' group by hsn, gst_percentage, buyer_gsttype, gst_type", 1, true);
    $run("select hsn, gst_percentage as rate, buyer_gsttype as bgt, gst_type, sum(qty) as qty, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$get_godown_id' and date between '$from_date' and '$to_date' group by hsn, gst_percentage, buyer_gsttype, gst_type", 1, true);
    $run("select hsn, gst as rate, buyer_gsttype as bgt, gst_type, sum(qty) as qty, sum(total-gst_amount) as taxable, sum(gst_amount) as gst_amt from ot_sales where godownid='$get_godown_id' and date between '$from_date' and '$to_date' group by hsn, gst, buyer_gsttype, gst_type", 1, true);

    // Returns / credit notes.
    $run("select hsn, gst_percentage as rate, buyer_gsttype as bgt, gst_type, sum(qty) as qty, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst_amt from user_return_stock_items where to_usertype='$Login_user_TYPEvl' and to_userid='$get_godown_id' and date between '$from_date' and '$to_date' group by hsn, gst_percentage, buyer_gsttype, gst_type", -1, false);
    // ot_sales_return stores a single total (no separate GST amount), so the
    // return reduces taxable value only, as in Table 7.
    $run("select osr.hsn as hsn, p.gst as rate, osr.buyer_gsttype as bgt, osr.gst_type as gst_type, sum(osr.qty) as qty, sum(osr.total) as taxable, 0 as gst_amt from ot_sales_return osr join products p on p.id=osr.prid where osr.godownid='$get_godown_id' and osr.return_date between '$from_date' and '$to_date' group by osr.hsn, p.gst, osr.buyer_gsttype, osr.gst_type", -1, false);

    foreach ($tp_sls_lines as $l) {
        $add($l['hsn'] ?? '', $l['gst_percentage'], !empty($l['is_registered']), !empty($l['is_intra']), (float)$l['quantity'], (float)$l['taxable_value'], (float)$l['gst_amount']);
    }

    // Drop all-zero rows, sort by HSN then rate.
    foreach ($rows as $k => $r) {
        $z = true;
        foreach (['b2b', 'b2c'] as $b) { foreach ($r[$b] as $v) { if (abs($v) > 0.004) { $z = false; } } }
        if ($z) unset($rows[$k]);
    }
    uasort($rows, function ($a, $b) { return [$a['hsn'], $a['rate']] <=> [$b['hsn'], $b['rate']]; });
    return array_values($rows);
}
}
