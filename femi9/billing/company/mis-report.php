<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// ── Date range & TP filter ─────────────────────────────────────────────────
$preset   = $_GET['preset'] ?? 'month';
$today    = date('Y-m-d');
$filter_tp = (int)($_GET['tp_id'] ?? 0);   // 0 = all TPs

switch ($preset) {
    case 'today':  $df = $today; $dt = $today; break;
    case 'week':   $df = date('Y-m-d', strtotime('monday this week')); $dt = date('Y-m-d', strtotime('sunday this week')); break;
    case 'year':   $df = date('Y-01-01'); $dt = date('Y-12-31'); break;
    default:       $df = date('Y-m-01'); $dt = date('Y-m-t');
}
$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : $df;
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : $dt;

$days_diff = (strtotime($to) - strtotime($from)) / 86400;
$prev_from = date('Y-m-d', strtotime($from) - ($days_diff + 1) * 86400);
$prev_to   = date('Y-m-d', strtotime($from) - 86400);

$utype = 'territory_partner';

// ── DB helpers ─────────────────────────────────────────────────────────────
function cq($db, $sql, $types = '', $params = []) {
    if (!$types) {
        $r = $db->query($sql);
        return $r ?: null;
    }
    $s = $db->prepare($sql);
    if (!$s) return null;
    $s->bind_param($types, ...$params);
    $s->execute();
    $r = $s->get_result();
    $s->close();
    return $r;
}
function cval($db, $sql, $types = '', $params = []) {
    $r = cq($db, $sql, $types, $params);
    return $r ? ($r->fetch_row()[0] ?? 0) : 0;
}
function crow($db, $sql, $types = '', $params = []) {
    $r = cq($db, $sql, $types, $params);
    return $r ? ($r->fetch_assoc() ?? []) : [];
}
function call_rows($db, $sql, $types = '', $params = []) {
    $r = cq($db, $sql, $types, $params);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

// Build TP WHERE clause additions
function tp_cond_inv($tp_id) {
    return $tp_id > 0 ? " AND i.user_id={$tp_id}" : "";
}
function tp_cond_ui($tp_id) {
    return $tp_id > 0 ? " AND ui.from_user_id={$tp_id}" : "";
}
function tp_cond_ii($tp_id) {
    return $tp_id > 0 ? " AND ii.user_id={$tp_id}" : "";
}
function tp_cond_uii($tp_id) {
    return $tp_id > 0 ? " AND uii.from_user_id={$tp_id}" : "";
}

$tc_inv = $filter_tp > 0 ? " AND user_id={$filter_tp}"           : "";
$tc_ui  = $filter_tp > 0 ? " AND from_user_id={$filter_tp}"      : "";

// ── Load all TPs for filter dropdown ──────────────────────────────────────
$all_tps = call_rows($db_conn,
    "SELECT id, name, tp_id FROM territory_partners WHERE is_active=1 ORDER BY name ASC");

// ═══════════════════════════════════════════════════════════════════════════
// 1. KPI — current & previous period
// ═══════════════════════════════════════════════════════════════════════════
$cust_row = crow($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) rev FROM invoice
     WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}",
    'sss', [$utype, $from, $to]);
$shop_row = crow($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) rev FROM user_invoice
     WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}",
    'sss', [$utype, $from, $to]);

$total_invoices = (int)$cust_row['cnt'] + (int)$shop_row['cnt'];
$total_revenue  = (float)$cust_row['rev'] + (float)$shop_row['rev'];

$cust_units = (int)cval($db_conn,
    "SELECT COALESCE(SUM(ii.qty),0) FROM invoice_items ii
     JOIN invoice i ON i.inv_id = ii.inv_id
     WHERE i.user_type=? AND i.date BETWEEN ? AND ?".tp_cond_inv($filter_tp),
    'sss', [$utype, $from, $to]);
$shop_units = (int)cval($db_conn,
    "SELECT COALESCE(SUM(uii.qty),0) FROM user_invoice_items uii
     JOIN user_invoice ui ON ui.inv_id = uii.inv_id
     WHERE ui.from_user_type=? AND ui.date BETWEEN ? AND ?".tp_cond_ui($filter_tp),
    'sss', [$utype, $from, $to]);
$total_units = $cust_units + $shop_units;

$total_tps = (int)cval($db_conn,
    "SELECT COUNT(DISTINCT user_id) FROM invoice WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'sss', [$utype, $from, $to]);
$total_tps2 = (int)cval($db_conn,
    "SELECT COUNT(DISTINCT from_user_id) FROM user_invoice WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'sss', [$utype, $from, $to]);
$active_tps = max($total_tps, $total_tps2);

$returns_row = crow($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) amount FROM user_return_stock
     WHERE to_usertype=?".($filter_tp > 0 ? " AND to_userid={$filter_tp}" : "")." AND `date` BETWEEN ? AND ?",
    'sss', [$utype, $from, $to]);
$total_returns    = (int)$returns_row['cnt'];
$total_return_amt = (float)$returns_row['amount'];

// Previous period
$prev_rev = (float)cval($db_conn,
    "SELECT COALESCE(SUM(total),0) FROM invoice
     WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}",
    'sss', [$utype, $prev_from, $prev_to])
  + (float)cval($db_conn,
    "SELECT COALESCE(SUM(total),0) FROM user_invoice
     WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}",
    'sss', [$utype, $prev_from, $prev_to]);
$revenue_growth = $prev_rev > 0
    ? round((($total_revenue - $prev_rev) / $prev_rev) * 100, 1) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// 2. DAILY TREND CHART DATA
// ═══════════════════════════════════════════════════════════════════════════
$dc = call_rows($db_conn,
    "SELECT `date` d, COALESCE(SUM(total),0) rev FROM invoice
     WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}
     GROUP BY `date` ORDER BY `date`",
    'sss', [$utype, $from, $to]);
$ds = call_rows($db_conn,
    "SELECT `date` d, COALESCE(SUM(total),0) rev FROM user_invoice
     WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}
     GROUP BY `date` ORDER BY `date`",
    'sss', [$utype, $from, $to]);
$dm = [];
foreach ($dc as $r) $dm[$r['d']]['c'] = (float)$r['rev'];
foreach ($ds as $r) $dm[$r['d']]['s'] = (float)$r['rev'];
$chart_labels = $chart_cust = $chart_shop = [];
$ptr = strtotime($from); $end = strtotime($to);
while ($ptr <= $end) {
    $d = date('Y-m-d', $ptr);
    $chart_labels[] = date('d M', $ptr);
    $chart_cust[]   = $dm[$d]['c'] ?? 0;
    $chart_shop[]   = $dm[$d]['s'] ?? 0;
    $ptr = strtotime('+1 day', $ptr);
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. PERIOD BREAKDOWN
// ═══════════════════════════════════════════════════════════════════════════
function company_period($db, $utype, $from, $to, $tc_inv, $tc_ui, $gfmt, $lfmt) {
    $cust = call_rows($db,
        "SELECT DATE_FORMAT(`date`,'$gfmt') g, DATE_FORMAT(MIN(`date`),'$lfmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total),0) rev
         FROM invoice WHERE user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}
         GROUP BY g ORDER BY g",
        'sss', [$utype, $from, $to]);
    $shop = call_rows($db,
        "SELECT DATE_FORMAT(`date`,'$gfmt') g, DATE_FORMAT(MIN(`date`),'$lfmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total),0) rev
         FROM user_invoice WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}
         GROUP BY g ORDER BY g",
        'sss', [$utype, $from, $to]);
    $map = [];
    foreach ($cust as $r) { $map[$r['g']]['lbl']=$r['lbl']; $map[$r['g']]['c']=(float)$r['rev']; $map[$r['g']]['cc']=(int)$r['cnt']; }
    foreach ($shop as $r) { $map[$r['g']]['lbl']=$map[$r['g']]['lbl']??$r['lbl']; $map[$r['g']]['s']=(float)$r['rev']; $map[$r['g']]['sc']=(int)$r['cnt']; }
    ksort($map); return $map;
}
$daily_p   = company_period($db_conn,$utype,$from,$to,$tc_inv,$tc_ui,'%Y-%m-%d','%d %b');
$weekly_p  = company_period($db_conn,$utype,$from,$to,$tc_inv,$tc_ui,'%Y-%u','W%u %Y');
$monthly_p = company_period($db_conn,$utype,$from,$to,$tc_inv,$tc_ui,'%Y-%m','%b %Y');
$yearly_p  = company_period($db_conn,$utype,$from,$to,$tc_inv,$tc_ui,'%Y','%Y');

// ═══════════════════════════════════════════════════════════════════════════
// 4. PRODUCT-WISE SALES
// ═══════════════════════════════════════════════════════════════════════════
$tc_ii  = $filter_tp > 0 ? " AND ii.user_id={$filter_tp}"        : "";
$tc_uii = $filter_tp > 0 ? " AND uii.from_user_id={$filter_tp}"  : "";
$product_sales = call_rows($db_conn,
    "SELECT p.productName,
            COALESCE(SUM(d.qty),0) total_qty,
            COALESCE(SUM(d.total),0) total_rev
     FROM (
         SELECT ii.pr_id, ii.qty, ii.total
         FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
         WHERE i.user_type=? AND i.date BETWEEN ? AND ?{$tc_ii}
         UNION ALL
         SELECT uii.pr_id, uii.qty, uii.total
         FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
         WHERE ui.from_user_type=? AND ui.date BETWEEN ? AND ?{$tc_uii}
     ) d JOIN products p ON p.id=d.pr_id
     GROUP BY p.id, p.productName ORDER BY total_qty DESC LIMIT 25",
    'ssssss', [$utype, $from, $to, $utype, $from, $to]);
$grand_qty = array_sum(array_column($product_sales, 'total_qty')) ?: 1;

// ═══════════════════════════════════════════════════════════════════════════
// 5. STATE / DISTRICT-WISE (shop invoices → shop → partner_location_nodes)
// ═══════════════════════════════════════════════════════════════════════════
$tc_ui_plain = $filter_tp > 0 ? " AND ui.from_user_id={$filter_tp}" : "";
$state_sales = call_rows($db_conn,
    "SELECT pln.name state_name, COUNT(*) cnt, COALESCE(SUM(ui.total),0) revenue
     FROM user_invoice ui
     JOIN shop s ON s.temp_id=ui.to_user_id
     JOIN partner_location_nodes pln ON pln.id=s.state_id
     WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui_plain}
     GROUP BY pln.id, pln.name ORDER BY revenue DESC",
    'sss', [$utype, $from, $to]);

$district_sales = call_rows($db_conn,
    "SELECT pln.name district_name, COUNT(*) cnt, COALESCE(SUM(ui.total),0) revenue
     FROM user_invoice ui
     JOIN shop s ON s.temp_id=ui.to_user_id
     JOIN partner_location_nodes pln ON pln.id=s.district_id
     WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui_plain}
     GROUP BY pln.id, pln.name ORDER BY revenue DESC LIMIT 20",
    'sss', [$utype, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// 6. TERRITORY PARTNER PERFORMANCE (= Salesperson Performance)
// ═══════════════════════════════════════════════════════════════════════════
$tp_perf = call_rows($db_conn,
    "SELECT tp.id tp_id, tp.name tp_name, tp.tp_id tp_code,
            COALESCE(ci.cnt,0)+COALESCE(si.cnt,0) inv_cnt,
            COALESCE(ci.rev,0)+COALESCE(si.rev,0) revenue,
            COALESCE(ci.units,0)+COALESCE(si.units,0) units,
            COALESCE(tgt.target,0) target
     FROM territory_partners tp
     LEFT JOIN (
         SELECT user_id, COUNT(*) cnt, SUM(total) rev,
                (SELECT COALESCE(SUM(qty),0) FROM invoice_items ii WHERE ii.user_id=i.user_id AND ii.user_type='territory_partner' AND ii.date BETWEEN '{$from}' AND '{$to}') units
         FROM invoice i WHERE user_type='territory_partner' AND sub_total>0 AND `date` BETWEEN '{$from}' AND '{$to}'
         GROUP BY user_id
     ) ci ON ci.user_id = tp.id
     LEFT JOIN (
         SELECT from_user_id, COUNT(*) cnt, SUM(total) rev,
                (SELECT COALESCE(SUM(qty),0) FROM user_invoice_items uii WHERE uii.from_user_id=ui.from_user_id AND uii.from_user_type='territory_partner' AND uii.date BETWEEN '{$from}' AND '{$to}') units
         FROM user_invoice ui WHERE from_user_type='territory_partner' AND sub_total>0 AND `date` BETWEEN '{$from}' AND '{$to}'
         GROUP BY from_user_id
     ) si ON si.from_user_id = tp.id
     LEFT JOIN (
         SELECT tpl.territory_partner_id, COALESCE(SUM(pln.target_amount),0) target
         FROM territory_partner_locations tpl
         JOIN partner_location_nodes pln ON pln.id=tpl.location_id
         GROUP BY tpl.territory_partner_id
     ) tgt ON tgt.territory_partner_id = tp.id
     WHERE tp.is_active=1
     ORDER BY revenue DESC");

$max_tp_rev  = (float)($tp_perf[0]['revenue'] ?? 1) ?: 1;
$total_target_all = array_sum(array_column($tp_perf, 'target'));
$total_achieved_all = array_sum(array_column($tp_perf, 'revenue'));
$overall_pct_all = $total_target_all > 0
    ? min(round($total_achieved_all / $total_target_all * 100, 1), 999) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// 7. TOP SHOPS & TOP CUSTOMERS
// ═══════════════════════════════════════════════════════════════════════════
$top_shops = call_rows($db_conn,
    "SELECT s.name shop_name, COUNT(*) inv_cnt, COALESCE(SUM(ui.total),0) revenue
     FROM user_invoice ui JOIN shop s ON s.temp_id=ui.to_user_id
     WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui_plain}
     GROUP BY s.temp_id, s.name ORDER BY revenue DESC LIMIT 10",
    'sss', [$utype, $from, $to]);

$top_custs = call_rows($db_conn,
    "SELECT COALESCE(c.name,'Walking Customer') cust_name, COUNT(*) inv_cnt, COALESCE(SUM(i.total),0) revenue
     FROM invoice i LEFT JOIN customers c ON c.id=i.customer_id
     WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_inv}
     GROUP BY i.customer_id ORDER BY revenue DESC LIMIT 10",
    'sss', [$utype, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// 8. ORDER STATUS
// ═══════════════════════════════════════════════════════════════════════════
$ord_c = call_rows($db_conn,
    "SELECT i.total, COALESCE(r.paid,0) paid
     FROM invoice i LEFT JOIN (SELECT inv_id, SUM(received) paid FROM receipt GROUP BY inv_id) r ON r.inv_id=i.inv_id
     WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_inv}",
    'sss', [$utype, $from, $to]);
$ord_s = call_rows($db_conn,
    "SELECT ui.total, COALESCE(r.paid,0) paid
     FROM user_invoice ui LEFT JOIN (SELECT inv_id, SUM(received) paid FROM receipt GROUP BY inv_id) r ON r.inv_id=ui.inv_id
     WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_ui}",
    'sss', [$utype, $from, $to]);
$os_paid=$os_part=$os_unpd=0; $os_paid_a=$os_part_a=$os_unpd_a=0;
foreach (array_merge($ord_c,$ord_s) as $o) {
    $t=(float)$o['total']; $p=(float)$o['paid'];
    if ($p>=$t&&$t>0) { $os_paid++; $os_paid_a+=$t; }
    elseif ($p>0&&$p<$t) { $os_part++; $os_part_a+=$t; }
    else { $os_unpd++; $os_unpd_a+=$t; }
}

// ═══════════════════════════════════════════════════════════════════════════
// 9. 6-MONTH GROWTH TREND
// ═══════════════════════════════════════════════════════════════════════════
$six_months = call_rows($db_conn,
    "SELECT DATE_FORMAT(d,'%Y-%m') mon, DATE_FORMAT(MIN(d),'%b %Y') lbl,
            SUM(rev) total_rev, SUM(cnt) total_cnt
     FROM (
         SELECT `date` d, SUM(total) rev, COUNT(*) cnt FROM invoice
         WHERE user_type=? AND sub_total>0 AND `date`>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH){$tc_inv}
         GROUP BY `date`
         UNION ALL
         SELECT `date` d, SUM(total) rev, COUNT(*) cnt FROM user_invoice
         WHERE from_user_type=? AND sub_total>0 AND `date`>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH){$tc_ui}
         GROUP BY `date`
     ) z GROUP BY DATE_FORMAT(d,'%Y-%m') ORDER BY mon",
    'ss', [$utype, $utype]);
$prev_m = null;
foreach ($six_months as &$m) {
    $m['growth'] = ($prev_m!==null&&$prev_m>0) ? round((($m['total_rev']-$prev_m)/$prev_m)*100,1) : null;
    $prev_m = (float)$m['total_rev'];
}
unset($m);

// ═══════════════════════════════════════════════════════════════════════════
// 10. RETURNS LIST
// ═══════════════════════════════════════════════════════════════════════════
$returns_list = call_rows($db_conn,
    "SELECT urs.*, inv_num.inv_number, tp.name tp_name
     FROM user_return_stock urs
     LEFT JOIN (SELECT inv_id, inv_number FROM invoice UNION ALL SELECT inv_id, inv_number FROM user_invoice) inv_num ON inv_num.inv_id=urs.invnumber
     LEFT JOIN territory_partners tp ON tp.id=urs.to_userid AND urs.to_usertype='territory_partner'
     WHERE urs.to_usertype=?".($filter_tp>0?" AND urs.to_userid={$filter_tp}":"")." AND urs.date BETWEEN ? AND ?
     ORDER BY urs.date DESC LIMIT 25",
    'sss', [$utype, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// JSON for charts
// ═══════════════════════════════════════════════════════════════════════════
$j_labels  = json_encode($chart_labels);
$j_cust    = json_encode($chart_cust);
$j_shop    = json_encode($chart_shop);
$j_glabels = json_encode(array_column($six_months,'lbl'));
$j_gvals   = json_encode(array_map('floatval', array_column($six_months,'total_rev')));
$j_plabels = json_encode(array_column($product_sales,'productName'));
$j_pqty    = json_encode(array_map('intval', array_column($product_sales,'total_qty')));
$j_tplabels= json_encode(array_column($tp_perf,'tp_name'));
$j_tprevs  = json_encode(array_map(fn($r)=>round($r['revenue'],0), $tp_perf));
$j_tptgts  = json_encode(array_map(fn($r)=>round($r['target'],0), $tp_perf));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MIS Report : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        .mis-section { margin-bottom: 30px; }
        .kpi-card { border-radius: 10px; padding: 16px 18px; color: #fff; position: relative; overflow: hidden; height: 100%; }
        .kpi-card .kpi-ico { font-size: 38px; opacity: .22; position: absolute; right: 12px; top: 10px; }
        .kpi-card .kpi-t  { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; opacity: .85; }
        .kpi-card .kpi-v  { font-size: 24px; font-weight: 700; margin-top: 4px; line-height: 1.2; }
        .kpi-card .kpi-s  { font-size: 12px; margin-top: 5px; opacity: .85; }
        .bg-i { background: linear-gradient(135deg,#3f51b5,#5c6bc0); }
        .bg-t { background: linear-gradient(135deg,#00897b,#26a69a); }
        .bg-o { background: linear-gradient(135deg,#ef6c00,#ffa726); }
        .bg-r { background: linear-gradient(135deg,#c62828,#e53935); }
        .bg-p { background: linear-gradient(135deg,#7b1fa2,#ab47bc); }
        .bg-g { background: linear-gradient(135deg,#2e7d32,#43a047); }
        .mis-filter { background:#f5f6fa; border-radius:8px; padding:14px 18px; margin-bottom:22px; }
        .preset-btn { padding:4px 13px; border-radius:20px; border:1.5px solid #3f51b5; color:#3f51b5; background:#fff; font-size:12px; cursor:pointer; text-decoration:none; display:inline-block; }
        .preset-btn.active, .preset-btn:hover { background:#3f51b5; color:#fff; }
        .tab-nav { display:flex; gap:0; border-bottom:2px solid #e0e0e0; margin-bottom:14px; }
        .tab-item { padding:7px 18px; cursor:pointer; font-size:13px; font-weight:600; color:#666; border-bottom:3px solid transparent; margin-bottom:-2px; }
        .tab-item.active { color:#3f51b5; border-bottom-color:#3f51b5; }
        .tab-content { display:none; } .tab-content.active { display:block; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f5f6fa; font-weight:700; padding:8px 11px; text-align:left; border-bottom:2px solid #e0e0e0; white-space:nowrap; }
        .mt td { padding:7px 11px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        .mt tr:hover td { background:#fafbff; }
        .pbar { height:7px; border-radius:4px; background:#e8eaf6; overflow:hidden; }
        .pbar .pf { height:100%; border-radius:4px; }
        .br { background:#e8f5e9; color:#2e7d32; padding:2px 7px; border-radius:10px; font-size:12px; font-weight:600; }
        .bq { background:#e3f2fd; color:#1565c0; padding:2px 7px; border-radius:10px; font-size:12px; font-weight:600; }
        .bp { background:#e8f5e9; color:#2e7d32; }
        .bpa { background:#fff8e1; color:#e65100; }
        .bu { background:#ffebee; color:#c62828; }
        .sbadge { padding:2px 9px; border-radius:10px; font-size:12px; font-weight:600; }
        .gp { color:#2e7d32; font-weight:700; } .gn { color:#c62828; font-weight:700; }
        .chart-box { position:relative; height:250px; }
        .rank-1 { color:#f57f17; font-weight:700; } .rank-2 { color:#757575; } .rank-3 { color:#bf360c; }
        .tp-tag { font-size:11px; background:#e8eaf6; color:#3f51b5; padding:1px 6px; border-radius:4px; }
        .snote { font-size:12px; color:#999; margin-bottom:6px; }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <!-- Header -->
                    <div class="row mb-2">
                        <div class="col">
                            <div class="page-description" style="margin-left:-10px;">
                                <h1>
                                    <i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">assessment</i>
                                    MIS Report — Sales Overview
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- ── FILTER ────────────────────────────────────────── -->
                    <div class="mis-filter">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Territory Partner</label>
                                <select name="tp_id" class="form-control form-control-sm" style="width:200px;" onchange="this.form.submit()">
                                    <option value="0" <?php echo $filter_tp==0?'selected':''; ?>>All Territory Partners</option>
                                    <?php foreach ($all_tps as $tp): ?>
                                    <option value="<?php echo $tp['id']; ?>" <?php echo $filter_tp==$tp['id']?'selected':''; ?>>
                                        <?php echo htmlspecialchars($tp['name']); ?> (<?php echo $tp['tp_id']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $from; ?>" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $to; ?>" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </div>
                            <div style="margin-left:auto;display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap;">
                                <?php $tp_qs = $filter_tp > 0 ? "&tp_id={$filter_tp}" : ""; ?>
                                <a href="?preset=today<?php echo $tp_qs; ?>"  class="preset-btn <?php echo $preset=='today' ?'active':''; ?>">Today</a>
                                <a href="?preset=week<?php echo $tp_qs; ?>"   class="preset-btn <?php echo $preset=='week'  ?'active':''; ?>">This Week</a>
                                <a href="?preset=month<?php echo $tp_qs; ?>"  class="preset-btn <?php echo $preset=='month' ?'active':''; ?>">This Month</a>
                                <a href="?preset=year<?php echo $tp_qs; ?>"   class="preset-btn <?php echo $preset=='year'  ?'active':''; ?>">This Year</a>
                            </div>
                        </form>
                        <div style="font-size:12px;color:#888;margin-top:7px;">
                            <?php if ($filter_tp > 0): ?>
                            Filtered by TP: <b><?php foreach($all_tps as $t) if ($t['id']==$filter_tp) echo htmlspecialchars($t['name']); ?></b> &nbsp;|&nbsp;
                            <?php endif; ?>
                            Period: <b><?php echo date('d M Y', strtotime($from)); ?></b> to <b><?php echo date('d M Y', strtotime($to)); ?></b>
                        </div>
                    </div>

                    <!-- ══ KPI CARDS ════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <?php
                        $kpis = [
                            ['bg-i','payments','Total Revenue','₹'.number_format($total_revenue,0),
                             ($revenue_growth>=0?'▲':'▼').' '.abs($revenue_growth).'% vs prev', $revenue_growth>=0?'#b2ff59':'#ff8a80'],
                            ['bg-t','receipt_long','Total Invoices',number_format($total_invoices),
                             'Cust: '.($cust_row['cnt']??0).' | Shop: '.($shop_row['cnt']??0), '#fff'],
                            ['bg-o','inventory_2','Units Sold',number_format($total_units),
                             'Cust: '.number_format($cust_units).' | Shop: '.number_format($shop_units), '#fff'],
                            ['bg-p','people','Active TPs',number_format($active_tps),
                             'TPs with invoices in period', '#fff'],
                            ['bg-r','keyboard_return','Returns',number_format($total_returns),
                             '₹'.number_format($total_return_amt,0).' returned', '#fff'],
                            ['bg-g','flag','Overall Target %',$overall_pct_all.'%',
                             '₹'.number_format($total_achieved_all,0).' / ₹'.number_format($total_target_all,0), '#fff'],
                        ];
                        foreach ($kpis as $k): ?>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card <?php echo $k[0]; ?>">
                                <i class="material-icons-outlined kpi-ico"><?php echo $k[1]; ?></i>
                                <div class="kpi-t"><?php echo $k[2]; ?></div>
                                <div class="kpi-v"><?php echo $k[3]; ?></div>
                                <div class="kpi-s" style="color:<?php echo $k[5]; ?>"><?php echo $k[4]; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ══ TREND CHART + ORDER STATUS ══════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Daily Sales Trend</h5></div>
                                <div class="card-body"><div class="chart-box"><canvas id="trendChart"></canvas></div></div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Order Status</h5></div>
                                <div class="card-body">
                                    <div class="chart-box"><canvas id="statusChart"></canvas></div>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="sbadge bp">Fully Paid</span>
                                            <span><?php echo $os_paid; ?> — ₹<?php echo number_format($os_paid_a,0); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="sbadge bpa">Partially Paid</span>
                                            <span><?php echo $os_part; ?> — ₹<?php echo number_format($os_part_a,0); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="sbadge bu">Unpaid</span>
                                            <span><?php echo $os_unpd; ?> — ₹<?php echo number_format($os_unpd_a,0); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ PERIOD BREAKDOWN TABS ════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Sales Breakdown by Period</h5></div>
                                <div class="card-body">
                                    <div class="tab-nav" id="ptabs">
                                        <div class="tab-item active" data-tab="daily">Daily</div>
                                        <div class="tab-item" data-tab="weekly">Weekly</div>
                                        <div class="tab-item" data-tab="monthly">Monthly</div>
                                        <div class="tab-item" data-tab="yearly">Yearly</div>
                                    </div>
                                    <?php
                                    function cmp_period_table($data, $id) {
                                        $active = $id === 'daily' ? 'active' : '';
                                        echo "<div class='tab-content {$active}' id='tab-{$id}'>";
                                        if (empty($data)) { echo "<p class='text-muted text-center py-3'>No data.</p></div>"; return; }
                                        $gr = array_sum(array_map(fn($r)=>($r['c']??0)+($r['s']??0), $data)) ?: 1;
                                        echo "<div style='overflow-x:auto'><table class='mt'>";
                                        echo "<thead><tr><th>Period</th><th>Customer</th><th>Shop</th><th>Total</th><th>Invoices</th><th>Share</th></tr></thead><tbody>";
                                        foreach ($data as $g => $r) {
                                            $rev = ($r['c']??0)+($r['s']??0);
                                            $cnt = ($r['cc']??0)+($r['sc']??0);
                                            $pct = round($rev/$gr*100,1);
                                            echo "<tr>
                                                <td><b>".htmlspecialchars($r['lbl']??$g)."</b></td>
                                                <td>₹".number_format($r['c']??0,2)." <small>({$r['cc']})</small></td>
                                                <td>₹".number_format($r['s']??0,2)." <small>({$r['sc']})</small></td>
                                                <td><b>₹".number_format($rev,2)."</b></td>
                                                <td>{$cnt}</td>
                                                <td><div style='display:flex;align-items:center;gap:6px'>
                                                    <div class='pbar' style='width:80px'><div class='pf' style='width:{$pct}%;background:#3f51b5'></div></div>
                                                    <span style='font-size:12px'>{$pct}%</span></div></td>
                                            </tr>";
                                        }
                                        echo "</tbody></table></div></div>";
                                    }
                                    cmp_period_table($daily_p,'daily');
                                    cmp_period_table($weekly_p,'weekly');
                                    cmp_period_table($monthly_p,'monthly');
                                    cmp_period_table($yearly_p,'yearly');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ TERRITORY PARTNER PERFORMANCE ═══════════════════ -->
                    <div class="row mis-section">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Territory Partner Performance (Salesperson View)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-xl-8">
                                            <div class="chart-box" style="height:220px;"><canvas id="tpChart"></canvas></div>
                                        </div>
                                        <div class="col-xl-4">
                                            <div style="background:#f5f6fa;border-radius:8px;padding:14px;text-align:center;margin-bottom:10px;">
                                                <div style="font-size:11px;color:#888;text-transform:uppercase;font-weight:600">Total Revenue (All TPs)</div>
                                                <div style="font-size:22px;font-weight:700;color:#1a237e">₹<?php echo number_format($total_achieved_all,0); ?></div>
                                            </div>
                                            <div style="background:#f5f6fa;border-radius:8px;padding:14px;text-align:center;">
                                                <div style="font-size:11px;color:#888;text-transform:uppercase;font-weight:600">Overall Achievement</div>
                                                <div style="font-size:22px;font-weight:700;color:<?php echo $overall_pct_all>=100?'#2e7d32':($overall_pct_all>=50?'#e65100':'#c62828'); ?>">
                                                    <?php echo $overall_pct_all; ?>%
                                                </div>
                                                <div style="font-size:12px;color:#888">Target: ₹<?php echo number_format($total_target_all,0); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="overflow-x:auto">
                                    <table class="mt">
                                        <thead><tr><th>Rank</th><th>TP Name</th><th>TP Code</th><th>Invoices</th><th>Units</th><th>Revenue</th><th>Target</th><th>Achievement</th><th>Gap</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($tp_perf as $i => $tp): ?>
                                            <?php
                                            $pct = $tp['target']>0 ? min(round($tp['revenue']/$tp['target']*100,1),999) : 0;
                                            $bc  = $pct>=100?'#2e7d32':($pct>=50?'#f57c00':'#c62828');
                                            $gap = (float)$tp['target'] - (float)$tp['revenue'];
                                            $rk_class = $i==0?'rank-1':($i==1?'rank-2':($i==2?'rank-3':''));
                                            ?>
                                            <tr>
                                                <td class="<?php echo $rk_class; ?>"><?php echo $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':($i+1))); ?></td>
                                                <td><b><?php echo htmlspecialchars($tp['tp_name']); ?></b></td>
                                                <td><span class="tp-tag"><?php echo htmlspecialchars($tp['tp_code']); ?></span></td>
                                                <td><?php echo number_format((int)$tp['inv_cnt']); ?></td>
                                                <td><span class="bq"><?php echo number_format((int)$tp['units']); ?></span></td>
                                                <td>
                                                    <b>₹<?php echo number_format($tp['revenue'],2); ?></b>
                                                    <div class="pbar mt-1"><div class="pf" style="width:<?php echo round($tp['revenue']/$max_tp_rev*100,1); ?>%;background:#3f51b5"></div></div>
                                                </td>
                                                <td>₹<?php echo number_format($tp['target'],0); ?></td>
                                                <td>
                                                    <div style="display:flex;align-items:center;gap:5px">
                                                        <div class="pbar" style="width:80px"><div class="pf" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $bc; ?>"></div></div>
                                                        <span style="font-size:13px;font-weight:700;color:<?php echo $bc; ?>"><?php echo $pct; ?>%</span>
                                                    </div>
                                                </td>
                                                <td style="color:<?php echo $gap>0?'#c62828':'#2e7d32'; ?>">
                                                    <?php echo $gap>0?'−':'+'?>₹<?php echo number_format(abs($gap),0); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ PRODUCT-WISE SALES ════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-7">
                            <div class="card h-100">
                                <div class="card-header"><h5 class="card-title">Product-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($product_sales)): ?>
                                        <p class="text-muted text-center py-3">No data.</p>
                                    <?php else: ?>
                                    <table class="mt">
                                        <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($product_sales as $i => $p): ?>
                                            <?php
                                            $pct = $grand_qty>0 ? round($p['total_qty']/$grand_qty*100,1) : 0;
                                            $bc  = ['#f44336','#ff9800','#4caf50','#3f51b5','#9c27b0','#00897b'][$i % 6];
                                            ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($p['productName']); ?></b></td>
                                                <td><span class="bq"><?php echo number_format((int)$p['total_qty']); ?></span></td>
                                                <td><span class="br">₹<?php echo number_format($p['total_rev'],2); ?></span></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%;background:<?php echo $bc; ?>"></div></div>
                                                    <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                </div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-5">
                            <div class="card h-100">
                                <div class="card-header"><h5 class="card-title">Product Mix (Top 8)</h5></div>
                                <div class="card-body"><div class="chart-box"><canvas id="productChart"></canvas></div></div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ STATE / DISTRICT ══════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">State-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <p class="snote">Shop invoices only (customer invoices have no geographic data).</p>
                                    <?php if (empty($state_sales)): ?>
                                        <p class="text-muted text-center py-3">No geographic data.</p>
                                    <?php else:
                                        $ts_rev = array_sum(array_column($state_sales,'revenue')) ?: 1;
                                    ?>
                                    <table class="mt">
                                        <thead><tr><th>State</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($state_sales as $s): ?>
                                            <?php $pct = round($s['revenue']/$ts_rev*100,1); ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($s['state_name']); ?></b></td>
                                                <td><?php echo $s['cnt']; ?></td>
                                                <td><span class="br">₹<?php echo number_format($s['revenue'],2); ?></span></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%;background:#00897b"></div></div>
                                                    <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                </div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">District-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($district_sales)): ?>
                                        <p class="text-muted text-center py-3">No district data.</p>
                                    <?php else:
                                        $td_rev = array_sum(array_column($district_sales,'revenue')) ?: 1;
                                    ?>
                                    <table class="mt">
                                        <thead><tr><th>District</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($district_sales as $d): ?>
                                            <?php $pct = round($d['revenue']/$td_rev*100,1); ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($d['district_name']); ?></b></td>
                                                <td><?php echo $d['cnt']; ?></td>
                                                <td><span class="br">₹<?php echo number_format($d['revenue'],2); ?></span></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%;background:#7b1fa2"></div></div>
                                                    <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                </div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ TOP SHOPS & CUSTOMERS ════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Top 10 Shops by Revenue</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($top_shops)): ?>
                                        <p class="text-muted text-center py-3">No shop data.</p>
                                    <?php else:
                                        $msr = (float)$top_shops[0]['revenue'] ?: 1;
                                    ?>
                                    <table class="mt">
                                        <thead><tr><th>#</th><th>Shop</th><th>Invoices</th><th>Revenue</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($top_shops as $i => $s): ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($s['shop_name']); ?></b></td>
                                                <td><?php echo $s['inv_cnt']; ?></td>
                                                <td>
                                                    <span class="br">₹<?php echo number_format($s['revenue'],2); ?></span>
                                                    <div class="pbar mt-1"><div class="pf" style="width:<?php echo round($s['revenue']/$msr*100,1); ?>%;background:#ef6c00"></div></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Top 10 Customers by Revenue</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($top_custs)): ?>
                                        <p class="text-muted text-center py-3">No customer data.</p>
                                    <?php else:
                                        $mcr = (float)$top_custs[0]['revenue'] ?: 1;
                                    ?>
                                    <table class="mt">
                                        <thead><tr><th>#</th><th>Customer</th><th>Invoices</th><th>Revenue</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($top_custs as $i => $c): ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($c['cust_name']); ?></b></td>
                                                <td><?php echo $c['inv_cnt']; ?></td>
                                                <td>
                                                    <span class="br">₹<?php echo number_format($c['revenue'],2); ?></span>
                                                    <div class="pbar mt-1"><div class="pf" style="width:<?php echo round($c['revenue']/$mcr*100,1); ?>%;background:#3f51b5"></div></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ 6-MONTH GROWTH TREND ══════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-7">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">6-Month Growth Trend</h5></div>
                                <div class="card-body"><div class="chart-box"><canvas id="growthChart"></canvas></div></div>
                            </div>
                        </div>
                        <div class="col-xl-5">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Month-over-Month</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($six_months)): ?>
                                        <p class="text-muted text-center py-3">No data.</p>
                                    <?php else: ?>
                                    <table class="mt">
                                        <thead><tr><th>Month</th><th>Revenue</th><th>Invoices</th><th>Growth</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($six_months as $m): ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($m['lbl']); ?></b></td>
                                                <td>₹<?php echo number_format($m['total_rev'],0); ?></td>
                                                <td><?php echo (int)$m['total_cnt']; ?></td>
                                                <td>
                                                    <?php if ($m['growth']===null): ?>
                                                        <span style="color:#888">—</span>
                                                    <?php elseif ($m['growth']>=0): ?>
                                                        <span class="gp">▲ <?php echo $m['growth']; ?>%</span>
                                                    <?php else: ?>
                                                        <span class="gn">▼ <?php echo abs($m['growth']); ?>%</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ RETURNS & CANCELLATIONS ══════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Returns &amp; Credit Notes
                                        <span class="sbadge bu" style="margin-left:8px;">
                                            <?php echo $total_returns; ?> returns — ₹<?php echo number_format($total_return_amt,2); ?>
                                        </span>
                                    </h5>
                                </div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($returns_list)): ?>
                                        <p class="text-muted text-center py-3">No returns in this period.</p>
                                    <?php else: ?>
                                    <table class="mt">
                                        <thead><tr><th>Return ID</th><th>Invoice No.</th><th>TP</th><th>From</th><th>Date</th><th>Amount</th><th>Status</th><th>Detail</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($returns_list as $r): ?>
                                            <tr>
                                                <td><small><?php echo htmlspecialchars($r['returnid']); ?></small></td>
                                                <td><?php echo htmlspecialchars($r['inv_number'] ?? $r['invnumber']); ?></td>
                                                <td><?php echo htmlspecialchars($r['tp_name'] ?? '—'); ?></td>
                                                <td><?php echo ucfirst(str_replace('_',' ',$r['from_usertype'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                                                <td><span class="br">₹<?php echo number_format($r['total'],2); ?></span></td>
                                                <td>
                                                    <?php if ($r['status']==='pending'): ?>
                                                    <span class="sbadge bpa">Pending</span>
                                                    <?php else: ?>
                                                    <span class="sbadge bp"><?php echo ucfirst($r['status']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><a href="../territory-partner/cnote_details.php?returnid=<?php echo base64_encode($r['returnid']); ?>">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- container-fluid -->
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = 'Poppins, sans-serif';
Chart.defaults.font.size   = 12;

// Tab switching
document.querySelectorAll('.tab-item').forEach(function(t) {
    t.addEventListener('click', function() {
        document.querySelectorAll('#ptabs .tab-item').forEach(x => x.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(x => x.classList.remove('active'));
        t.classList.add('active');
        var el = document.getElementById('tab-' + t.dataset.tab);
        if (el) el.classList.add('active');
    });
});

// 1. Daily Trend
(function() {
    var ctx = document.getElementById('trendChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $j_labels; ?>,
            datasets: [
                { label: 'Customer', data: <?php echo $j_cust; ?>,  borderColor:'#3f51b5', backgroundColor:'rgba(63,81,181,.08)', tension:.3, fill:true, pointRadius:3 },
                { label: 'Shop',     data: <?php echo $j_shop; ?>,  borderColor:'#ef6c00', backgroundColor:'rgba(239,108,0,.08)',  tension:.3, fill:true, pointRadius:3 }
            ]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'k'}}} }
    });
})();

// 2. Order Status Doughnut
(function() {
    var ctx = document.getElementById('statusChart');
    if (!ctx) return;
    new Chart(ctx, {
        type:'doughnut',
        data:{ labels:['Fully Paid','Partial','Unpaid'],
               datasets:[{data:[<?php echo $os_paid; ?>,<?php echo $os_part; ?>,<?php echo $os_unpd; ?>], backgroundColor:['#4caf50','#ff9800','#f44336'], borderWidth:0}] },
        options:{responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{legend:{position:'bottom'}}}
    });
})();

// 3. Product Mix
(function() {
    var ctx = document.getElementById('productChart');
    if (!ctx) return;
    var labels = <?php echo $j_plabels; ?>.slice(0,8);
    var data   = <?php echo $j_pqty; ?>.slice(0,8);
    var colors = ['#3f51b5','#e53935','#ef6c00','#2e7d32','#7b1fa2','#00838f','#c62828','#f57f17'];
    new Chart(ctx, {
        type:'doughnut',
        data:{ labels:labels, datasets:[{data:data, backgroundColor:colors, borderWidth:0}] },
        options:{responsive:true, maintainAspectRatio:false, cutout:'60%', plugins:{legend:{position:'right',labels:{font:{size:11}}}}}
    });
})();

// 4. TP Revenue vs Target (Grouped Bar)
(function() {
    var ctx = document.getElementById('tpChart');
    if (!ctx) return;
    var labels = <?php echo $j_tplabels; ?>;
    var revs   = <?php echo $j_tprevs; ?>;
    var tgts   = <?php echo $j_tptgts; ?>;
    new Chart(ctx, {
        type:'bar',
        data:{
            labels:labels,
            datasets:[
                { label:'Revenue',  data:revs, backgroundColor:'rgba(63,81,181,.8)', borderRadius:4 },
                { label:'Target',   data:tgts, backgroundColor:'rgba(239,108,0,.5)', borderRadius:4 }
            ]
        },
        options:{responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'k'}}} }
    });
})();

// 5. 6-Month Growth
(function() {
    var ctx = document.getElementById('growthChart');
    if (!ctx) return;
    new Chart(ctx, {
        type:'bar',
        data:{ labels:<?php echo $j_glabels; ?>, datasets:[{label:'Revenue', data:<?php echo $j_gvals; ?>, backgroundColor:'rgba(63,81,181,.75)', borderRadius:6}] },
        options:{responsive:true, maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{y:{ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'k'}}} }
    });
})();
</script>
</body>
</html>
