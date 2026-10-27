<?php require_once __DIR__ . '/../../shared/db-helpers.php';
/**
 * GSTR-1 offline-tool style JSON (gstin, fp, b2b, b2cs, nil, hsn, doc_issue)
 * for one company godown and period. Used by export_GSTR1_json.php.
 *
 * Mapping to the report tables on GSTR1.php:
 *  - b2b   : rated (rate>0) invoices of registered buyers with a valid GSTIN,
 *            one entry per invoice, one item per GST rate.
 *  - b2cs  : everything rated that is not a valid-GSTIN B2B line, grouped by
 *            supply type (INTRA/INTER), rate and place of supply, net of
 *            unregistered-buyer returns.
 *  - nil   : nil-rated (0%) value by INTRAB2B/INTRAB2C/INTRB2B/INTRB2C, net of
 *            returns, reported in expt_amt (same as the filed sample).
 *  - hsn   : include/HsnB2bB2cHelper.php (hsn_b2b / hsn_b2c).
 *  - doc_issue : invoice series ranges per channel.
 * Not produced: exp (export invoices), cdnr (registered-buyer credit notes).
 */
require_once __DIR__ . '/TpGstHelper.php';
require_once __DIR__ . '/B2bBuyerHelper.php';
require_once __DIR__ . '/HsnB2bB2cHelper.php';

if (!function_exists('gstr1_state_code_from_name')) {
function gstr1_state_code_from_name($name) {
    static $map = null;
    if ($map === null) {
        $raw = ['jammuandkashmir'=>'01','himachalpradesh'=>'02','punjab'=>'03','chandigarh'=>'04','uttarakhand'=>'05','haryana'=>'06','delhi'=>'07','rajasthan'=>'08','uttarpradesh'=>'09','bihar'=>'10','sikkim'=>'11','arunachalpradesh'=>'12','nagaland'=>'13','manipur'=>'14','mizoram'=>'15','tripura'=>'16','meghalaya'=>'17','assam'=>'18','westbengal'=>'19','jharkhand'=>'20','odisha'=>'21','orissa'=>'21','chhattisgarh'=>'22','madhyapradesh'=>'23','gujarat'=>'24','damananddiu'=>'26','dadraandnagarhavelianddamananddiu'=>'26','maharashtra'=>'27','andhrapradesh'=>'37','karnataka'=>'29','goa'=>'30','lakshadweep'=>'31','kerala'=>'32','tamilnadu'=>'33','puducherry'=>'34','pondicherry'=>'34','andamanandnicobarislands'=>'35','telangana'=>'36','ladakh'=>'38'];
        $map = $raw;
    }
    $k = preg_replace('/[^a-z]/', '', strtolower((string)$name));
    return $map[$k] ?? null;
}
}

if (!function_exists('build_gstr1_json')) {
function build_gstr1_json($db_conn, $Login_user_TYPEvl, $gid, $from_date, $to_date) {
    $gid_e = mysqli_real_escape_string($db_conn, (string)$gid);
    $g = mysqli_fetch_assoc(mysqli_query($db_conn, "select gstin, state_code from company_godown where id='$gid_e'"));
    $own_gstin = $g['gstin'] ?? '';
    $own_code  = $g['state_code'] ?? '';
    if ($own_code === '' && b2b_gstin_state_code($own_gstin)) $own_code = b2b_gstin_state_code($own_gstin);
    $own_code = str_pad((string)$own_code, 2, '0', STR_PAD_LEFT);

    $intra_fn = function ($gst_type) { return $gst_type == 'inner' || !in_array($gst_type, ['inner', 'outer']); };

    // Normalised sale lines: inum, date, reg(bool), gstin, is_intra, rate, taxable, gst, total, state(optional name)
    $lines = [];
    $push = function ($inum, $date, $reg, $gstin, $is_intra, $rate, $taxable, $gst, $total, $state = null) use (&$lines) {
        $lines[] = ['inum' => (string)$inum, 'date' => $date, 'reg' => $reg, 'gstin' => strtoupper(trim((string)$gstin)), 'intra' => $is_intra,
                    'rate' => (float)$rate, 'taxable' => (float)$taxable, 'gst' => (float)$gst, 'total' => (float)$total, 'state' => $state];
    };

    // Network sales
    $res = mysqli_query($db_conn, "
        SELECT ui.inv_number AS inum, MAX(uii.date) AS d, uii.gst_percentage AS rate, uii.gst_type, uii.buyer_gsttype AS bgt,
               COALESCE(ss.gstin,st.gstin,dt.gstin,sh.gstin) AS gstin,
               SUM(uii.total-uii.gstamount_total) AS taxable, SUM(uii.gstamount_total) AS gst, SUM(uii.total) AS total
        FROM user_invoice_items uii
        LEFT JOIN user_invoice ui ON ui.inv_id = uii.inv_id
        LEFT JOIN super_stockiest ss ON uii.to_user_type='super_stockiest' AND ss.temp_id=uii.to_user_id
        LEFT JOIN stockiest       st ON uii.to_user_type='stockiest'       AND st.temp_id=uii.to_user_id
        LEFT JOIN distributor     dt ON uii.to_user_type='distributor'     AND dt.temp_id=uii.to_user_id
        LEFT JOIN shop            sh ON uii.to_user_type='shop'            AND sh.temp_id=uii.to_user_id
        WHERE uii.from_user_type='".db_esc($Login_user_TYPEvl)."' AND uii.from_user_id='$gid_e' AND uii.date BETWEEN '".db_esc($from_date)."' AND '".db_esc($to_date)."'
        GROUP BY ui.inv_number, uii.gst_percentage, uii.gst_type, uii.buyer_gsttype, uii.to_user_type, uii.to_user_id, ss.gstin, st.gstin, dt.gstin, sh.gstin");
    while ($r = mysqli_fetch_assoc($res)) {
        $push($r['inum'], $r['d'], $r['bgt'] === 'register', $r['gstin'], $intra_fn($r['gst_type']), $r['rate'], $r['taxable'], $r['gst'], $r['total']);
    }

    // Customer sales
    $res = mysqli_query($db_conn, "
        SELECT i.inv_number AS inum, MAX(ii.date) AS d, ii.gst_percentage AS rate, ii.gst_type, ii.buyer_gsttype AS bgt, c.gstin AS gstin,
               SUM(ii.total-ii.gstamount_total) AS taxable, SUM(ii.gstamount_total) AS gst, SUM(ii.total) AS total
        FROM invoice_items ii
        LEFT JOIN invoice i ON i.inv_id = ii.inv_id
        LEFT JOIN customers c ON c.id = ii.customer_id
        WHERE ii.user_type='".db_esc($Login_user_TYPEvl)."' AND ii.user_id='$gid_e' AND ii.date BETWEEN '".db_esc($from_date)."' AND '".db_esc($to_date)."'
        GROUP BY i.inv_number, ii.gst_percentage, ii.gst_type, ii.buyer_gsttype, ii.customer_id, c.gstin");
    while ($r = mysqli_fetch_assoc($res)) {
        $push($r['inum'], $r['d'], $r['bgt'] === 'register', $r['gstin'], $intra_fn($r['gst_type']), $r['rate'], $r['taxable'], $r['gst'], $r['total']);
    }

    // OT sales (state of supply from the sale's own state)
    $res = mysqli_query($db_conn, "
        SELECT COALESCE(i.inv_number, s.tempid) AS inum, MAX(s.date) AS d, s.gst AS rate, s.gst_type, s.buyer_gsttype AS bgt,
               s.gst_number AS gstin, stt.st_name AS st_name,
               SUM(s.total-s.gst_amount) AS taxable, SUM(s.gst_amount) AS gst, SUM(s.total) AS total
        FROM ot_sales s
        LEFT JOIN ot_sales_invoice i ON i.tempid = s.tempid
        LEFT JOIN state stt ON stt.id = s.state_id
        WHERE s.godownid='$gid_e' AND s.date BETWEEN '".db_esc($from_date)."' AND '".db_esc($to_date)."'
        GROUP BY s.tempid, i.inv_number, s.gst, s.gst_type, s.buyer_gsttype, s.gst_number, stt.st_name");
    while ($r = mysqli_fetch_assoc($res)) {
        $push($r['inum'], $r['d'], $r['bgt'] === 'register', $r['gstin'], $intra_fn($r['gst_type']), $r['rate'], $r['taxable'], $r['gst'], $r['total'], $r['st_name']);
    }

    // Territory partner invoices
    $tp_lines = tp_sales_gst_lines($db_conn, $from_date, $to_date, "tpi.source_godown_id = '$gid_e'");
    foreach ($tp_lines as $l) {
        $push($l['invoice_number'], $l['invoice_date'], !empty($l['is_registered']), $l['tp_gstin'] ?? '', !empty($l['is_intra']),
              $l['gst_percentage'], $l['taxable_value'], $l['gst_amount'], $l['taxable_value'] + $l['gst_amount']);
    }

    // Internal transfers: receiving company godown is a registered buyer
    $res = mysqli_query($db_conn, "
        SELECT COALESCE(iti.inv_number, it.tempid) AS inum, MAX(it.date) AS d, it.gst AS rate, it.gst_type, cg.gstin AS gstin,
               cg.state AS to_state, it_from.state AS from_state,
               SUM(it.total-it.gst_amount) AS taxable, SUM(it.gst_amount) AS gst, SUM(it.total) AS total
        FROM internal_transfer it
        LEFT JOIN internal_transfer_invoice iti ON iti.tempid = it.tempid
        JOIN company_godown cg ON cg.id = it.send_to
        JOIN company_godown it_from ON it_from.id = it.send_from
        WHERE it.send_from='$gid_e' AND it.date BETWEEN '".db_esc($from_date)."' AND '".db_esc($to_date)."'
        GROUP BY COALESCE(iti.inv_number, it.tempid), it.gst, it.gst_type, cg.gstin, cg.state, it_from.state");
    while ($r = mysqli_fetch_assoc($res)) {
        $fallback = strtolower(trim($r['to_state'])) == strtolower(trim($r['from_state']));
        $push($r['inum'], $r['d'], true, $r['gstin'], $fallback, $r['rate'], $r['taxable'], $r['gst'], $r['total']);
    }

    // ---- classify ----
    $b2b = [];   // ctin => inum => ['idt','pos','val','items'=>[rate=>[txval,iamt,camt,samt]]]
    $b2cs = [];  // key => row
    $nil = ['INTRAB2B' => 0.0, 'INTRAB2C' => 0.0, 'INTRB2B' => 0.0, 'INTRB2C' => 0.0];

    // invoice totals (all rates) for b2b "val"
    $inv_total = [];
    foreach ($lines as $l) {
        $k = $l['inum'] . '|' . ($l['gstin']);
        $inv_total[$k] = ($inv_total[$k] ?? 0) + $l['total'];
    }

    $add_b2cs = function ($is_intra, $rate, $pos, $taxable, $gst) use (&$b2cs) {
        $sply = $is_intra ? 'INTRA' : 'INTER';
        $k = $sply . '|' . $rate . '|' . $pos;
        if (!isset($b2cs[$k])) $b2cs[$k] = ['typ' => 'OE', 'sply_ty' => $sply, 'rt' => $rate, 'pos' => $pos, 'txval' => 0.0, 'iamt' => 0.0, 'camt' => 0.0, 'samt' => 0.0, 'csamt' => 0];
        $b2cs[$k]['txval'] += $taxable;
        if ($is_intra) { $b2cs[$k]['camt'] += $gst / 2; $b2cs[$k]['samt'] += $gst / 2; }
        else { $b2cs[$k]['iamt'] += $gst; }
    };

    foreach ($lines as $l) {
        $code = b2b_gstin_state_code($l['gstin']);
        $is_b2b_valid = $l['reg'] && $code !== null;
        $is_intra = $is_b2b_valid ? ($code === $own_code) : $l['intra'];
        if ($l['rate'] <= 0) {
            $key = ($is_intra ? 'INTRA' : 'INTR') . ($l['reg'] ? 'B2B' : 'B2C');
            $nil[$key] += $l['taxable'];
            continue;
        }
        if ($is_b2b_valid) {
            $ct = $l['gstin']; $in = $l['inum'];
            if (!isset($b2b[$ct][$in])) {
                $b2b[$ct][$in] = ['idt' => date('d-m-Y', strtotime($l['date'])), 'pos' => $code, 'val' => $inv_total[$l['inum'] . '|' . $l['gstin']] ?? 0, 'items' => []];
            }
            $it =& $b2b[$ct][$in]['items'];
            $rk = (string)$l['rate'];
            if (!isset($it[$rk])) $it[$rk] = ['rt' => $l['rate'], 'txval' => 0.0, 'iamt' => 0.0, 'camt' => 0.0, 'samt' => 0.0];
            $it[$rk]['txval'] += $l['taxable'];
            if ($is_intra) { $it[$rk]['camt'] += $l['gst'] / 2; $it[$rk]['samt'] += $l['gst'] / 2; }
            else { $it[$rk]['iamt'] += $l['gst']; }
            unset($it);
        } else {
            $pos = $own_code;
            if (!$is_intra) {
                $sc = $l['state'] ? gstr1_state_code_from_name($l['state']) : null;
                if ($sc) $pos = $sc;
            }
            $add_b2cs($is_intra, $l['rate'], $pos, $l['taxable'], $l['gst']);
        }
    }

    // ---- returns (unregistered-buyer credit notes net off B2CS / nil) ----
    $res = mysqli_query($db_conn, "select gst_percentage as rate, gst_type, buyer_gsttype as bgt, sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst from user_return_stock_items where to_usertype='".db_esc($Login_user_TYPEvl)."' and to_userid='$gid_e' and date between '".db_esc($from_date)."' and '".db_esc($to_date)."' group by gst_percentage, gst_type, buyer_gsttype");
    while ($r = mysqli_fetch_assoc($res)) {
        $is_reg = ($r['bgt'] === 'register'); $is_intra = $intra_fn($r['gst_type']);
        if ((float)$r['rate'] <= 0) { $nil[($is_intra ? 'INTRA' : 'INTR') . ($is_reg ? 'B2B' : 'B2C')] -= (float)$r['taxable']; continue; }
        if ($is_reg) continue; // registered returns would be cdnr — not produced
        $add_b2cs($is_intra, (float)$r['rate'], $own_code, -(float)$r['taxable'], -(float)$r['gst']);
    }
    $res = mysqli_query($db_conn, "select p.gst as rate, osr.gst_type, osr.buyer_gsttype as bgt, sum(osr.total) as taxable from ot_sales_return osr join products p on p.id=osr.prid where osr.godownid='$gid_e' and osr.return_date between '".db_esc($from_date)."' and '".db_esc($to_date)."' group by p.gst, osr.gst_type, osr.buyer_gsttype");
    while ($r = mysqli_fetch_assoc($res)) {
        $is_reg = ($r['bgt'] === 'register'); $is_intra = $intra_fn($r['gst_type']);
        $rate = (float)$r['rate']; $tx = (float)$r['taxable'];
        if ($rate <= 0) { $nil[($is_intra ? 'INTRA' : 'INTR') . ($is_reg ? 'B2B' : 'B2C')] -= $tx; continue; }
        if ($is_reg) continue;
        $add_b2cs($is_intra, $rate, $own_code, -$tx, -($tx * $rate / 100));
    }

    $r2 = function ($v) { return round((float)$v, 2); };
    $rt = function ($v) { $v = (float)$v; return $v == floor($v) ? (int)$v : $v; };

    // ---- b2b ----
    $out_b2b = [];
    foreach ($b2b as $ctin => $invs) {
        $inv_list = [];
        foreach ($invs as $inum => $d) {
            $items = []; $n = 0;
            foreach ($d['items'] as $it) {
                $n++;
                $items[] = ['num' => $n, 'itm_det' => ['txval' => $r2($it['txval']), 'rt' => $rt($it['rt']), 'iamt' => $r2($it['iamt']), 'camt' => $r2($it['camt']), 'samt' => $r2($it['samt']), 'csamt' => 0]];
            }
            $inv_list[] = ['inum' => (string)$inum, 'idt' => $d['idt'], 'val' => $r2($d['val']), 'pos' => $d['pos'], 'rchrg' => 'N', 'inv_typ' => 'R', 'itms' => $items];
        }
        $out_b2b[] = ['ctin' => $ctin, 'inv' => $inv_list];
    }

    // ---- b2cs ----
    $out_b2cs = [];
    foreach ($b2cs as $row) {
        if (abs($row['txval']) < 0.005 && abs($row['camt']) < 0.005 && abs($row['iamt']) < 0.005) continue;
        $o = ['typ' => 'OE', 'sply_ty' => $row['sply_ty'], 'rt' => $rt($row['rt']), 'pos' => $row['pos'], 'txval' => $r2($row['txval'])];
        if ($row['sply_ty'] === 'INTER') $o['iamt'] = $r2($row['iamt']);
        else { $o['camt'] = $r2($row['camt']); $o['samt'] = $r2($row['samt']); }
        $o['csamt'] = 0;
        $out_b2cs[] = $o;
    }

    // ---- nil ----
    $out_nil = [];
    foreach ($nil as $sply => $v) {
        if (abs($v) < 0.005) continue;
        $out_nil[] = ['sply_ty' => $sply, 'nil_amt' => 0, 'expt_amt' => $r2($v), 'ngsup_amt' => 0];
    }

    // ---- hsn ----
    $desc = [];
    $pq = mysqli_query($db_conn, "select hsn, min(category) as cat from products group by hsn");
    while ($p = mysqli_fetch_assoc($pq)) { $desc[trim($p['hsn'])] = ($p['cat'] === 'diaper') ? 'Clinical Diapers' : (($p['cat'] === 'napkin') ? 'Sanitary Napkins' : ''); }
    $hsn_rows = compute_hsn_b2b_b2c($db_conn, $Login_user_TYPEvl, $gid, $from_date, $to_date, $tp_lines);
    $hsn_out = ['hsn_b2b' => [], 'hsn_b2c' => []];
    foreach (['b2b' => 'hsn_b2b', 'b2c' => 'hsn_b2c'] as $bk => $ok) {
        $n = 0;
        foreach ($hsn_rows as $h) {
            $b = $h[$bk];
            if (abs($b['taxable']) < 0.005 && abs($b['qty']) < 0.005) continue;
            $n++;
            $hsn_out[$ok][] = ['num' => $n, 'hsn_sc' => $h['hsn'], 'txval' => $r2($b['taxable']), 'iamt' => $r2($b['igst']), 'camt' => $r2($b['cgst']),
                'samt' => $r2($b['sgst']), 'csamt' => 0, 'desc' => '', 'user_desc' => $desc[$h['hsn']] ?? '', 'uqc' => 'PAC', 'qty' => (int)round($b['qty']), 'rt' => $rt($h['rate'])];
        }
    }

    // ---- doc_issue: per channel + leading letter series ----
    $channels = [
        "select ui.inv_number as num from user_invoice ui where ui.from_user_type='$Login_user_TYPEvl' and ui.from_user_id='$gid_e' and ui.date between '$from_date' and '$to_date' order by ui.date, ui.inv_number",
        "select i.inv_number as num from invoice i where i.user_type='$Login_user_TYPEvl' and i.user_id='$gid_e' and i.date between '$from_date' and '$to_date' order by i.date, i.inv_number",
        "select i.inv_number as num from ot_sales_invoice i join ot_sales s on s.tempid=i.tempid where s.godownid='$gid_e' and s.date between '$from_date' and '$to_date' group by i.inv_number order by min(s.date), i.inv_number",
        "select invoice_number as num from tp_invoices where source_godown_id='$gid_e' and invoice_date between '$from_date' and '$to_date' order by invoice_date, invoice_number",
    ];
    $docs = []; $dn = 0;
    foreach ($channels as $sql) {
        $series = [];
        $q = mysqli_query($db_conn, $sql);
        while ($d = mysqli_fetch_assoc($q)) {
            $num = trim($d['num']);
            if ($num === '') continue;
            $code = preg_match('/^([A-Za-z]+)/', $num, $m) ? strtoupper($m[1]) : 'OTHER';
            $series[$code][] = $num;
        }
        ksort($series);
        foreach ($series as $nums) {
            $dn++; $c = count($nums);
            $docs[] = ['cancel' => 0, 'from' => $nums[0], 'net_issue' => $c, 'num' => $dn, 'to' => $nums[$c - 1], 'totnum' => $c];
        }
    }

    $out = ['gstin' => $own_gstin, 'fp' => date('mY', strtotime($to_date)), 'b2b' => $out_b2b, 'b2cs' => $out_b2cs, 'nil' => ['inv' => $out_nil], 'hsn' => $hsn_out];
    if ($docs) $out['doc_issue'] = ['doc_det' => [['doc_num' => 1, 'docs' => $docs]]];
    return $out;
}
}
