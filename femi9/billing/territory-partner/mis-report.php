<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// ── Date range filter ──────────────────────────────────────────────────────
$preset = $_GET['preset'] ?? 'month';
$today  = date('Y-m-d');

switch ($preset) {
    case 'today':
        $default_from = $today; $default_to = $today; break;
    case 'week':
        $default_from = date('Y-m-d', strtotime('monday this week'));
        $default_to   = date('Y-m-d', strtotime('sunday this week')); break;
    case 'year':
        $default_from = date('Y-01-01'); $default_to = date('Y-12-31'); break;
    default:
        $default_from = date('Y-m-01'); $default_to = date('Y-m-t');
}

$from = isset($_GET['from']) && $_GET['from'] ? $_GET['from'] : $default_from;
$to   = isset($_GET['to'])   && $_GET['to']   ? $_GET['to']   : $default_to;
$from = date('Y-m-d', strtotime($from));
$to   = date('Y-m-d', strtotime($to));

$uid   = (int)$Login_user_IDvl;
$utype = 'territory_partner';

// Previous period (same duration shifted back)
$days_diff    = (strtotime($to) - strtotime($from)) / 86400;
$prev_from    = date('Y-m-d', strtotime($from) - ($days_diff + 1) * 86400);
$prev_to      = date('Y-m-d', strtotime($from) - 86400);

// Helper: run a prepared statement with 'si' or 'ssi' etc.
function mis_q($db, $sql, $types, $params) {
    $s = $db->prepare($sql);
    if (!$s) return null;
    $s->bind_param($types, ...$params);
    $s->execute();
    $r = $s->get_result();
    $s->close();
    return $r;
}
function mis_val($db, $sql, $types, $params) {
    $r = mis_q($db, $sql, $types, $params);
    return $r ? ($r->fetch_row()[0] ?? 0) : 0;
}
function mis_row($db, $sql, $types, $params) {
    $r = mis_q($db, $sql, $types, $params);
    return $r ? ($r->fetch_assoc() ?? []) : [];
}
function mis_all($db, $sql, $types, $params) {
    $r = mis_q($db, $sql, $types, $params);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

// ═══════════════════════════════════════════════════════════════════════════
// 1. KPI SUMMARY — current period
// ═══════════════════════════════════════════════════════════════════════════

// Customer invoices
$cust_row = mis_row($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) revenue
     FROM invoice WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);

// Shop invoices
$shop_row = mis_row($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) revenue
     FROM user_invoice WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);

$total_invoices = (int)$cust_row['cnt'] + (int)$shop_row['cnt'];
$total_revenue  = (float)$cust_row['revenue'] + (float)$shop_row['revenue'];

// Units sold
$cust_units = (int)mis_val($db_conn,
    "SELECT COALESCE(SUM(ii.qty),0) FROM invoice_items ii
     JOIN invoice i ON i.inv_id = ii.inv_id
     WHERE i.user_id=? AND i.user_type=? AND i.date BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);
$shop_units = (int)mis_val($db_conn,
    "SELECT COALESCE(SUM(qty),0) FROM user_invoice_items
     WHERE from_user_id=? AND from_user_type=? AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);
$total_units = $cust_units + $shop_units;

// Returns
$returns_row = mis_row($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) amount
     FROM user_return_stock WHERE to_usertype=? AND to_userid=? AND `date` BETWEEN ? AND ?",
    'siss', [$utype, $uid, $from, $to]);
$total_returns     = (int)$returns_row['cnt'];
$total_return_amt  = (float)$returns_row['amount'];

// Advance balance
$adv_balance = (float)mis_val($db_conn,
    "SELECT COALESCE(SUM(balance_amount),0) FROM tp_advance_payments
     WHERE territory_partner_id=? AND status='active'",
    'i', [$uid]);

// Previous period KPI (for growth %)
$prev_cust = mis_row($db_conn,
    "SELECT COALESCE(SUM(total),0) revenue FROM invoice
     WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $prev_from, $prev_to]);
$prev_shop = mis_row($db_conn,
    "SELECT COALESCE(SUM(total),0) revenue FROM user_invoice
     WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'isss', [$uid, $utype, $prev_from, $prev_to]);
$prev_revenue = (float)$prev_cust['revenue'] + (float)$prev_shop['revenue'];
$revenue_growth = $prev_revenue > 0
    ? round((($total_revenue - $prev_revenue) / $prev_revenue) * 100, 1) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// 2. DAILY TREND (for chart)
// ═══════════════════════════════════════════════════════════════════════════
$daily_cust = mis_all($db_conn,
    "SELECT `date` d, COALESCE(SUM(total),0) rev FROM invoice
     WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?
     GROUP BY `date` ORDER BY `date` ASC",
    'isss', [$uid, $utype, $from, $to]);
$daily_shop = mis_all($db_conn,
    "SELECT `date` d, COALESCE(SUM(total),0) rev FROM user_invoice
     WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?
     GROUP BY `date` ORDER BY `date` ASC",
    'isss', [$uid, $utype, $from, $to]);

$daily_map = [];
foreach ($daily_cust as $r) $daily_map[$r['d']]['cust'] = (float)$r['rev'];
foreach ($daily_shop as $r) $daily_map[$r['d']]['shop'] = (float)$r['rev'];

// Fill every date in range
$ptr = strtotime($from);
$end = strtotime($to);
$chart_labels = $chart_cust = $chart_shop = [];
while ($ptr <= $end) {
    $d = date('Y-m-d', $ptr);
    $chart_labels[] = date('d M', $ptr);
    $chart_cust[]   = $daily_map[$d]['cust'] ?? 0;
    $chart_shop[]   = $daily_map[$d]['shop'] ?? 0;
    $ptr = strtotime('+1 day', $ptr);
}

// ═══════════════════════════════════════════════════════════════════════════
// 3. PERIOD SUMMARY (Daily / Weekly / Monthly / Yearly)
// ═══════════════════════════════════════════════════════════════════════════
function period_sales($db, $uid, $utype, $from, $to, $group_fmt, $label_fmt) {
    $cust = mis_all($db,
        "SELECT DATE_FORMAT(`date`, '$group_fmt') g, DATE_FORMAT(MIN(`date`), '$label_fmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total),0) rev
         FROM invoice WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?
         GROUP BY g ORDER BY g ASC",
        'isss', [$uid, $utype, $from, $to]);
    $shop = mis_all($db,
        "SELECT DATE_FORMAT(`date`, '$group_fmt') g, DATE_FORMAT(MIN(`date`), '$label_fmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total),0) rev
         FROM user_invoice WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?
         GROUP BY g ORDER BY g ASC",
        'isss', [$uid, $utype, $from, $to]);
    $map = [];
    foreach ($cust as $r) { $map[$r['g']]['lbl'] = $r['lbl']; $map[$r['g']]['cust'] = (float)$r['rev']; $map[$r['g']]['cust_cnt'] = (int)$r['cnt']; }
    foreach ($shop as $r) { $map[$r['g']]['lbl'] = $map[$r['g']]['lbl'] ?? $r['lbl']; $map[$r['g']]['shop'] = (float)$r['rev']; $map[$r['g']]['shop_cnt'] = (int)$r['cnt']; }
    ksort($map);
    return $map;
}
$daily_periods   = period_sales($db_conn, $uid, $utype, $from, $to, '%Y-%m-%d', '%d %b');
$weekly_periods  = period_sales($db_conn, $uid, $utype, $from, $to, '%Y-%u', 'W%u %Y');
$monthly_periods = period_sales($db_conn, $uid, $utype, $from, $to, '%Y-%m', '%b %Y');
$yearly_periods  = period_sales($db_conn, $uid, $utype, $from, $to, '%Y', '%Y');

// ═══════════════════════════════════════════════════════════════════════════
// 4. PRODUCT-WISE SALES
// ═══════════════════════════════════════════════════════════════════════════
$product_sales = mis_all($db_conn,
    "SELECT p.productName,
            COALESCE(SUM(d.qty),0) total_qty,
            COALESCE(SUM(d.subtotal),0) subtotal_rev,
            COALESCE(SUM(d.total),0) total_rev
     FROM (
         SELECT pr_id, qty, subtotal, total FROM invoice_items
         WHERE user_id=? AND user_type=? AND `date` BETWEEN ? AND ?
         UNION ALL
         SELECT pr_id, qty, subtotal, total FROM user_invoice_items
         WHERE from_user_id=? AND from_user_type=? AND `date` BETWEEN ? AND ?
     ) d
     JOIN products p ON p.id = d.pr_id
     GROUP BY p.id, p.productName ORDER BY total_qty DESC LIMIT 25",
    'isssisss', [$uid, $utype, $from, $to, $uid, $utype, $from, $to]);
$grand_qty = array_sum(array_column($product_sales, 'total_qty')) ?: 1;
$grand_rev = array_sum(array_column($product_sales, 'total_rev')) ?: 1;

// ═══════════════════════════════════════════════════════════════════════════
// 5. STATE / DISTRICT-WISE SALES (shop invoices only, via partner_location_nodes)
// ═══════════════════════════════════════════════════════════════════════════
$state_sales = mis_all($db_conn,
    "SELECT pln.name state_name, COUNT(*) cnt, COALESCE(SUM(ui.total),0) revenue
     FROM user_invoice ui
     JOIN shop s ON s.temp_id = ui.to_user_id
     JOIN partner_location_nodes pln ON pln.id = s.state_id
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
     GROUP BY pln.id, pln.name ORDER BY revenue DESC",
    'isss', [$uid, $utype, $from, $to]);

$district_sales = mis_all($db_conn,
    "SELECT pln.name district_name, COUNT(*) cnt, COALESCE(SUM(ui.total),0) revenue
     FROM user_invoice ui
     JOIN shop s ON s.temp_id = ui.to_user_id
     JOIN partner_location_nodes pln ON pln.id = s.district_id
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
     GROUP BY pln.id, pln.name ORDER BY revenue DESC",
    'isss', [$uid, $utype, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// 6. TOP SHOPS & CUSTOMERS (Salesperson Performance)
// ═══════════════════════════════════════════════════════════════════════════
$top_shops = mis_all($db_conn,
    "SELECT s.name shop_name, COUNT(*) inv_cnt,
            COALESCE(SUM(ui.total),0) revenue,
            COALESCE(SUM(uii.qty),0) units
     FROM user_invoice ui
     JOIN shop s ON s.temp_id = ui.to_user_id
     LEFT JOIN user_invoice_items uii ON uii.inv_id = ui.inv_id AND uii.from_user_id=? AND uii.from_user_type=?
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
     GROUP BY s.temp_id, s.name ORDER BY revenue DESC LIMIT 10",
    'isisss', [$uid, $utype, $uid, $utype, $from, $to]);

$top_customers = mis_all($db_conn,
    "SELECT COALESCE(c.name,'Walking Customer') cust_name, COUNT(*) inv_cnt,
            COALESCE(SUM(i.total),0) revenue,
            COALESCE(SUM(ii.qty),0) units
     FROM invoice i
     LEFT JOIN customers c ON c.id = i.customer_id
     LEFT JOIN invoice_items ii ON ii.inv_id = i.inv_id AND ii.user_id=? AND ii.user_type=?
     WHERE i.user_id=? AND i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?
     GROUP BY i.customer_id ORDER BY revenue DESC LIMIT 10",
    'isisss', [$uid, $utype, $uid, $utype, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// 7. TARGET VS ACHIEVEMENT
// ═══════════════════════════════════════════════════════════════════════════
$target_rows = mis_all($db_conn,
    "SELECT pln.id loc_id, pln.name loc_name, pln.depth, COALESCE(pln.target_amount,0) target
     FROM territory_partner_locations tpl
     JOIN partner_location_nodes pln ON pln.id = tpl.location_id
     WHERE tpl.territory_partner_id=?
     ORDER BY pln.depth ASC, pln.name ASC",
    'i', [$uid]);

$total_target = 0;
foreach ($target_rows as &$tr) {
    $total_target += (float)$tr['target'];
    $loc_id = (int)$tr['loc_id'];

    if ($loc_id > 0) {
        $achieved = (float)mis_val($db_conn,
            "SELECT COALESCE(SUM(ui.total),0) FROM user_invoice ui
             JOIN shop s ON s.temp_id = ui.to_user_id
             WHERE ui.from_user_id=? AND ui.from_user_type=?
               AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?
               AND (s.state_id=? OR s.district_id=?)",
            'isssii', [$uid, $utype, $from, $to, $loc_id, $loc_id]);
    } else {
        $achieved = 0;
    }
    $tr['achieved'] = $achieved;
    $tr['pct'] = $tr['target'] > 0 ? min(round($achieved / $tr['target'] * 100, 1), 999) : 0;
}
unset($tr);
$total_achieved = array_sum(array_column($target_rows, 'achieved'));
$overall_pct    = $total_target > 0 ? min(round($total_achieved / $total_target * 100, 1), 999) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// 8. ORDER STATUS
// ═══════════════════════════════════════════════════════════════════════════
$order_cust = mis_all($db_conn,
    "SELECT i.inv_id, i.total, COALESCE(r.received,0) AS paid
     FROM invoice i
     LEFT JOIN (SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id) r ON r.inv_id = i.inv_id
     WHERE i.user_id=? AND i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);

$order_shop = mis_all($db_conn,
    "SELECT ui.inv_id, ui.total, COALESCE(r.received,0) AS paid
     FROM user_invoice ui
     LEFT JOIN (SELECT inv_id, SUM(received) received FROM receipt GROUP BY inv_id) r ON r.inv_id = ui.inv_id
     WHERE ui.from_user_id=? AND ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?",
    'isss', [$uid, $utype, $from, $to]);

$all_orders  = array_merge($order_cust, $order_shop);
$os_paid = $os_partial = $os_unpaid = 0;
$os_paid_amt = $os_partial_amt = $os_unpaid_amt = 0;
foreach ($all_orders as $o) {
    $t = (float)$o['total']; $p = (float)$o['paid'];
    if ($p >= $t && $t > 0) { $os_paid++; $os_paid_amt += $t; }
    elseif ($p > 0 && $p < $t) { $os_partial++; $os_partial_amt += $t; }
    else { $os_unpaid++; $os_unpaid_amt += $t; }
}

// ═══════════════════════════════════════════════════════════════════════════
// 9. 6-MONTH GROWTH TREND
// ═══════════════════════════════════════════════════════════════════════════
$six_months = mis_all($db_conn,
    "SELECT DATE_FORMAT(d, '%Y-%m') mon, DATE_FORMAT(MIN(d), '%b %Y') lbl,
            SUM(rev) total_rev, SUM(cnt) total_cnt
     FROM (
         SELECT `date` d, SUM(total) rev, COUNT(*) cnt FROM invoice
         WHERE user_id=? AND user_type=? AND sub_total>0 AND `date` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY `date`
         UNION ALL
         SELECT `date` d, SUM(total) rev, COUNT(*) cnt FROM user_invoice
         WHERE from_user_id=? AND from_user_type=? AND sub_total>0 AND `date` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY `date`
     ) combined
     GROUP BY DATE_FORMAT(d, '%Y-%m')
     ORDER BY mon ASC",
    'isis', [$uid, $utype, $uid, $utype]);

// Growth % month-over-month
$prev_m_rev = null;
foreach ($six_months as &$m) {
    $m['growth'] = ($prev_m_rev !== null && $prev_m_rev > 0)
        ? round((($m['total_rev'] - $prev_m_rev) / $prev_m_rev) * 100, 1)
        : null;
    $prev_m_rev = (float)$m['total_rev'];
}
unset($m);

// Chart for 6-month
$growth_labels = array_column($six_months, 'lbl');
$growth_values = array_column($six_months, 'total_rev');

// ═══════════════════════════════════════════════════════════════════════════
// 10. RETURNS & CANCELLATIONS
// ═══════════════════════════════════════════════════════════════════════════
$returns_list = mis_all($db_conn,
    "SELECT urs.*, inv_num.inv_number
     FROM user_return_stock urs
     LEFT JOIN (
         SELECT inv_id, inv_number FROM invoice
         UNION ALL SELECT inv_id, inv_number FROM user_invoice
     ) inv_num ON inv_num.inv_id = urs.invnumber
     WHERE urs.to_usertype=? AND urs.to_userid=? AND urs.date BETWEEN ? AND ?
     ORDER BY urs.date DESC LIMIT 20",
    'siss', [$utype, $uid, $from, $to]);

$return_by_month = mis_all($db_conn,
    "SELECT DATE_FORMAT(`date`, '%b %Y') lbl, COUNT(*) cnt, COALESCE(SUM(total),0) amount
     FROM user_return_stock WHERE to_usertype=? AND to_userid=? AND `date` BETWEEN ? AND ?
     GROUP BY DATE_FORMAT(`date`, '%Y-%m') ORDER BY DATE_FORMAT(`date`, '%Y-%m') ASC",
    'siss', [$utype, $uid, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// JSON encode chart data
// ═══════════════════════════════════════════════════════════════════════════
$j_labels  = json_encode($chart_labels);
$j_cust    = json_encode($chart_cust);
$j_shop    = json_encode($chart_shop);
$j_glabels = json_encode($growth_labels);
$j_gvals   = json_encode(array_map('floatval', $growth_values));

$j_plabels = json_encode(array_column($product_sales, 'productName'));
$j_pqty    = json_encode(array_map('intval', array_column($product_sales, 'total_qty')));
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
        .mis-section { margin-bottom: 32px; }
        .mis-section-title {
            font-size: 16px; font-weight: 700; color: #1a237e;
            border-left: 4px solid #3f51b5; padding-left: 10px;
            margin-bottom: 14px;
        }
        .kpi-card { border-radius: 10px; padding: 18px 20px; color: #fff; position: relative; overflow: hidden; }
        .kpi-card .kpi-icon { font-size: 40px; opacity: 0.25; position: absolute; right: 14px; top: 10px; }
        .kpi-card .kpi-title { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; opacity: .85; }
        .kpi-card .kpi-value { font-size: 26px; font-weight: 700; margin-top: 4px; line-height: 1.2; }
        .kpi-card .kpi-sub { font-size: 12px; margin-top: 6px; opacity: .85; }
        .bg-indigo   { background: linear-gradient(135deg, #3f51b5, #5c6bc0); }
        .bg-teal     { background: linear-gradient(135deg, #00897b, #26a69a); }
        .bg-orange   { background: linear-gradient(135deg, #ef6c00, #ffa726); }
        .bg-crimson  { background: linear-gradient(135deg, #c62828, #e53935); }
        .bg-purple   { background: linear-gradient(135deg, #7b1fa2, #ab47bc); }
        .mis-filter-bar { background: #f5f6fa; border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; }
        .mis-filter-bar .preset-btns { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .preset-btn { padding: 5px 14px; border-radius: 20px; border: 1.5px solid #3f51b5;
                      color: #3f51b5; background: #fff; font-size: 12px; cursor: pointer; }
        .preset-btn.active, .preset-btn:hover { background: #3f51b5; color: #fff; }
        .tab-nav { display: flex; gap: 0; border-bottom: 2px solid #e0e0e0; margin-bottom: 16px; }
        .tab-nav .tab-item { padding: 8px 20px; cursor: pointer; font-size: 13px; font-weight: 600;
                             color: #666; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-nav .tab-item.active { color: #3f51b5; border-bottom-color: #3f51b5; }
        .tab-content { display: none; } .tab-content.active { display: block; }
        .mis-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .mis-table th { background: #f5f6fa; font-weight: 700; padding: 9px 12px; text-align: left; border-bottom: 2px solid #e0e0e0; }
        .mis-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .mis-table tr:hover td { background: #fafbff; }
        .progress-bar-mis { height: 8px; border-radius: 4px; background: #e8eaf6; overflow: hidden; min-width: 80px; }
        .progress-fill { height: 100%; border-radius: 4px; transition: width .3s; }
        .badge-rev { background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }
        .badge-qty { background: #e3f2fd; color: #1565c0; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }
        .badge-paid     { background: #e8f5e9; color: #2e7d32; }
        .badge-partial  { background: #fff8e1; color: #e65100; }
        .badge-unpaid   { background: #ffebee; color: #c62828; }
        .status-badge   { padding: 3px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
        .growth-pos { color: #2e7d32; font-weight: 700; }
        .growth-neg { color: #c62828; font-weight: 700; }
        .chart-container { position: relative; height: 260px; }
        .section-note { font-size: 12px; color: #888; margin-bottom: 8px; }
        @media(max-width: 768px) { .kpi-card .kpi-value { font-size: 20px; } }
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

                    <!-- Page header -->
                    <div class="row mb-2">
                        <div class="col">
                            <div class="page-description" style="margin-left:-10px;">
                                <h1><i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">assessment</i>MIS Report</h1>
                            </div>
                        </div>
                    </div>

                    <!-- ── FILTER BAR ──────────────────────────────────────── -->
                    <div class="mis-filter-bar">
                        <form method="get" id="filterForm" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $from; ?>" style="width:150px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $to; ?>" style="width:150px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:3px;">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </div>
                            <div class="preset-btns" style="margin-left:auto;align-self:flex-end;">
                                <a href="?preset=today"  class="preset-btn <?php echo $preset==='today'  ? 'active':'' ?>">Today</a>
                                <a href="?preset=week"   class="preset-btn <?php echo $preset==='week'   ? 'active':'' ?>">This Week</a>
                                <a href="?preset=month"  class="preset-btn <?php echo $preset==='month'  ? 'active':'' ?>">This Month</a>
                                <a href="?preset=year"   class="preset-btn <?php echo $preset==='year'   ? 'active':'' ?>">This Year</a>
                            </div>
                        </form>
                        <div style="font-size:12px;color:#888;margin-top:8px;">
                            Showing data for: <b><?php echo date('d M Y', strtotime($from)); ?></b>
                            to <b><?php echo date('d M Y', strtotime($to)); ?></b>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         KPI CARDS
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card bg-indigo">
                                <i class="material-icons-outlined kpi-icon">payments</i>
                                <div class="kpi-title">Total Revenue</div>
                                <div class="kpi-value">&#x20B9;<?php echo number_format($total_revenue, 0); ?></div>
                                <div class="kpi-sub">
                                    <?php if ($revenue_growth != 0): ?>
                                    <span style="<?php echo $revenue_growth >= 0 ? 'color:#b2ff59' : 'color:#ff8a80'; ?>">
                                        <?php echo $revenue_growth >= 0 ? '▲' : '▼'; ?> <?php echo abs($revenue_growth); ?>% vs prev
                                    </span>
                                    <?php else: ?>vs previous period<?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card bg-teal">
                                <i class="material-icons-outlined kpi-icon">receipt_long</i>
                                <div class="kpi-title">Invoices</div>
                                <div class="kpi-value"><?php echo number_format($total_invoices); ?></div>
                                <div class="kpi-sub">Cust: <?php echo $cust_row['cnt'] ?? 0; ?> &nbsp;|&nbsp; Shop: <?php echo $shop_row['cnt'] ?? 0; ?></div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card bg-orange">
                                <i class="material-icons-outlined kpi-icon">inventory_2</i>
                                <div class="kpi-title">Units Sold</div>
                                <div class="kpi-value"><?php echo number_format($total_units); ?></div>
                                <div class="kpi-sub">Cust: <?php echo number_format($cust_units); ?> &nbsp;|&nbsp; Shop: <?php echo number_format($shop_units); ?></div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card bg-crimson">
                                <i class="material-icons-outlined kpi-icon">keyboard_return</i>
                                <div class="kpi-title">Returns</div>
                                <div class="kpi-value"><?php echo number_format($total_returns); ?></div>
                                <div class="kpi-sub">&#x20B9;<?php echo number_format($total_return_amt, 0); ?> returned</div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card bg-purple">
                                <i class="material-icons-outlined kpi-icon">account_balance_wallet</i>
                                <div class="kpi-title">Advance Balance</div>
                                <div class="kpi-value">&#x20B9;<?php echo number_format($adv_balance, 0); ?></div>
                                <div class="kpi-sub">Available advance</div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card" style="background:linear-gradient(135deg,#00695c,#00897b);">
                                <i class="material-icons-outlined kpi-icon">flag</i>
                                <div class="kpi-title">Target vs Achieved</div>
                                <div class="kpi-value"><?php echo $overall_pct; ?>%</div>
                                <div class="kpi-sub">&#x20B9;<?php echo number_format($total_achieved,0); ?> / &#x20B9;<?php echo number_format($total_target,0); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         SALES TREND CHART
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Daily Sales Trend</h5></div>
                                <div class="card-body">
                                    <div class="chart-container"><canvas id="trendChart"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Order Status</h5></div>
                                <div class="card-body">
                                    <div class="chart-container"><canvas id="statusChart"></canvas></div>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="status-badge badge-paid">Fully Paid</span>
                                            <span><?php echo $os_paid; ?> invoices — &#x20B9;<?php echo number_format($os_paid_amt,0); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="status-badge badge-partial">Partially Paid</span>
                                            <span><?php echo $os_partial; ?> invoices — &#x20B9;<?php echo number_format($os_partial_amt,0); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="status-badge badge-unpaid">Unpaid</span>
                                            <span><?php echo $os_unpaid; ?> invoices — &#x20B9;<?php echo number_format($os_unpaid_amt,0); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         PERIOD BREAKDOWN (tabs)
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Sales Breakdown by Period</h5>
                                </div>
                                <div class="card-body">
                                    <div class="tab-nav" id="periodTabs">
                                        <div class="tab-item active" data-tab="daily">Daily</div>
                                        <div class="tab-item" data-tab="weekly">Weekly</div>
                                        <div class="tab-item" data-tab="monthly">Monthly</div>
                                        <div class="tab-item" data-tab="yearly">Yearly</div>
                                    </div>

                                    <?php
                                    function render_period_table($data, $tab_id) {
                                        $active = $tab_id === 'daily' ? 'active' : '';
                                        echo "<div class='tab-content $active' id='tab-$tab_id'>";
                                        if (empty($data)) {
                                            echo "<p class='text-muted text-center py-3'>No data for this period.</p>";
                                            echo "</div>"; return;
                                        }
                                        $grand_rev = array_sum(array_map(fn($r) => ($r['cust'] ?? 0) + ($r['shop'] ?? 0), $data));
                                        echo "<div style='overflow-x:auto'><table class='mis-table'>";
                                        echo "<thead><tr><th>Period</th><th>Customer Sales</th><th>Shop Sales</th><th>Total Revenue</th><th>Total Invoices</th><th>Share</th></tr></thead><tbody>";
                                        foreach ($data as $g => $r) {
                                            $rev  = ($r['cust'] ?? 0) + ($r['shop'] ?? 0);
                                            $cnt  = ($r['cust_cnt'] ?? 0) + ($r['shop_cnt'] ?? 0);
                                            $pct  = $grand_rev > 0 ? round($rev / $grand_rev * 100, 1) : 0;
                                            $lbl  = $r['lbl'] ?? $g;
                                            echo "<tr>
                                                <td><b>$lbl</b></td>
                                                <td>&#x20B9;" . number_format($r['cust'] ?? 0, 2) . " <small>({$r['cust_cnt']})</small></td>
                                                <td>&#x20B9;" . number_format($r['shop'] ?? 0, 2) . " <small>({$r['shop_cnt']})</small></td>
                                                <td><b>&#x20B9;" . number_format($rev, 2) . "</b></td>
                                                <td>$cnt</td>
                                                <td><div class='d-flex align-items-center gap-2'>
                                                    <div class='progress-bar-mis' style='width:80px'><div class='progress-fill' style='width:{$pct}%;background:#3f51b5'></div></div>
                                                    <span style='font-size:12px'>$pct%</span>
                                                </div></td>
                                            </tr>";
                                        }
                                        echo "</tbody></table></div></div>";
                                    }
                                    render_period_table($daily_periods, 'daily');
                                    render_period_table($weekly_periods, 'weekly');
                                    render_period_table($monthly_periods, 'monthly');
                                    render_period_table($yearly_periods, 'yearly');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         PRODUCT-WISE SALES
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-7">
                            <div class="card h-100">
                                <div class="card-header"><h5 class="card-title">Product-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($product_sales)): ?>
                                        <p class="text-muted text-center py-3">No product sales in this period.</p>
                                    <?php else: ?>
                                    <table class="mis-table">
                                        <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue (incl. GST)</th><th>% Qty</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($product_sales as $i => $p): ?>
                                            <?php
                                            $pct_qty = $grand_qty > 0 ? round($p['total_qty'] / $grand_qty * 100, 1) : 0;
                                            $bar_color = $i === 0 ? '#f44336' : ($i === 1 ? '#ff9800' : ($i === 2 ? '#4caf50' : '#3f51b5'));
                                            ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($p['productName']); ?></b></td>
                                                <td><span class="badge-qty"><?php echo number_format((int)$p['total_qty']); ?> u</span></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo number_format($p['total_rev'], 2); ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:70px"><div class="progress-fill" style="width:<?php echo $pct_qty; ?>%;background:<?php echo $bar_color; ?>"></div></div>
                                                        <span style="font-size:12px"><?php echo $pct_qty; ?>%</span>
                                                    </div>
                                                </td>
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
                                <div class="card-body">
                                    <div class="chart-container"><canvas id="productChart"></canvas></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         STATE / DISTRICT-WISE SALES
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">State-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <p class="section-note">Based on shop invoices only (customer invoices have no geographic data).</p>
                                    <?php if (empty($state_sales)): ?>
                                        <p class="text-muted text-center py-3">No geographic data available.</p>
                                    <?php else:
                                        $max_state = max(array_column($state_sales, 'revenue')) ?: 1;
                                    ?>
                                    <table class="mis-table">
                                        <thead><tr><th>State</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php $total_state_rev = array_sum(array_column($state_sales, 'revenue')) ?: 1; ?>
                                        <?php foreach ($state_sales as $s): ?>
                                            <?php $pct = round($s['revenue'] / $total_state_rev * 100, 1); ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($s['state_name']); ?></b></td>
                                                <td><?php echo $s['cnt']; ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo number_format($s['revenue'], 2); ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:70px"><div class="progress-fill" style="width:<?php echo $pct; ?>%;background:#00897b"></div></div>
                                                        <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                    </div>
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
                                <div class="card-header"><h5 class="card-title">District-wise Sales</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($district_sales)): ?>
                                        <p class="text-muted text-center py-3">No district data available.</p>
                                    <?php else:
                                        $total_dist_rev = array_sum(array_column($district_sales, 'revenue')) ?: 1;
                                    ?>
                                    <table class="mis-table">
                                        <thead><tr><th>District</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($district_sales as $d): ?>
                                            <?php $pct = round($d['revenue'] / $total_dist_rev * 100, 1); ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($d['district_name']); ?></b></td>
                                                <td><?php echo $d['cnt']; ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo number_format($d['revenue'], 2); ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:70px"><div class="progress-fill" style="width:<?php echo $pct; ?>%;background:#7b1fa2"></div></div>
                                                        <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                    </div>
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

                    <!-- ══════════════════════════════════════════════════════
                         TOP SHOPS & CUSTOMERS (Salesperson Performance)
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-6">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Top 10 Shops by Revenue</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($top_shops)): ?>
                                        <p class="text-muted text-center py-3">No shop sales in this period.</p>
                                    <?php else:
                                        $max_shop_rev = (float)$top_shops[0]['revenue'] ?: 1;
                                    ?>
                                    <table class="mis-table">
                                        <thead><tr><th>#</th><th>Shop Name</th><th>Invoices</th><th>Units</th><th>Revenue</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($top_shops as $i => $s): ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($s['shop_name']); ?></b></td>
                                                <td><?php echo $s['inv_cnt']; ?></td>
                                                <td><span class="badge-qty"><?php echo number_format((int)$s['units']); ?></span></td>
                                                <td>
                                                    <span class="badge-rev">&#x20B9;<?php echo number_format($s['revenue'], 2); ?></span>
                                                    <div class="progress-bar-mis mt-1"><div class="progress-fill" style="width:<?php echo round($s['revenue']/$max_shop_rev*100,1); ?>%;background:#ef6c00"></div></div>
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
                                    <?php if (empty($top_customers)): ?>
                                        <p class="text-muted text-center py-3">No customer sales in this period.</p>
                                    <?php else:
                                        $max_cust_rev = (float)$top_customers[0]['revenue'] ?: 1;
                                    ?>
                                    <table class="mis-table">
                                        <thead><tr><th>#</th><th>Customer</th><th>Invoices</th><th>Units</th><th>Revenue</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($top_customers as $i => $c): ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($c['cust_name']); ?></b></td>
                                                <td><?php echo $c['inv_cnt']; ?></td>
                                                <td><span class="badge-qty"><?php echo number_format((int)$c['units']); ?></span></td>
                                                <td>
                                                    <span class="badge-rev">&#x20B9;<?php echo number_format($c['revenue'], 2); ?></span>
                                                    <div class="progress-bar-mis mt-1"><div class="progress-fill" style="width:<?php echo round($c['revenue']/$max_cust_rev*100,1); ?>%;background:#3f51b5"></div></div>
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

                    <!-- ══════════════════════════════════════════════════════
                         TARGET VS ACHIEVEMENT
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Target vs Achievement — by Location</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($target_rows)): ?>
                                        <p class="text-muted text-center py-3">No assigned locations with targets found.</p>
                                    <?php else: ?>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div style="background:#f5f6fa;padding:14px;border-radius:8px;text-align:center;">
                                                <div style="font-size:12px;color:#888;text-transform:uppercase;font-weight:600;">Total Target</div>
                                                <div style="font-size:24px;font-weight:700;color:#1a237e;">&#x20B9;<?php echo number_format($total_target, 0); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div style="background:#f5f6fa;padding:14px;border-radius:8px;text-align:center;">
                                                <div style="font-size:12px;color:#888;text-transform:uppercase;font-weight:600;">Total Achieved</div>
                                                <div style="font-size:24px;font-weight:700;color:#2e7d32;">&#x20B9;<?php echo number_format($total_achieved, 0); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div style="background:#f5f6fa;padding:14px;border-radius:8px;text-align:center;">
                                                <div style="font-size:12px;color:#888;text-transform:uppercase;font-weight:600;">Achievement %</div>
                                                <div style="font-size:24px;font-weight:700;color:<?php echo $overall_pct >= 100 ? '#2e7d32' : ($overall_pct >= 50 ? '#e65100' : '#c62828'); ?>;">
                                                    <?php echo $overall_pct; ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="overflow-x:auto">
                                    <table class="mis-table">
                                        <thead><tr><th>Location</th><th>Depth</th><th>Target</th><th>Achieved</th><th>Gap</th><th>Achievement %</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($target_rows as $tr): ?>
                                            <?php
                                            $gap      = (float)$tr['target'] - (float)$tr['achieved'];
                                            $pct      = (float)$tr['pct'];
                                            $bar_c    = $pct >= 100 ? '#2e7d32' : ($pct >= 50 ? '#f57c00' : '#c62828');
                                            ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($tr['loc_name']); ?></b></td>
                                                <td><span style="font-size:11px;background:#e8eaf6;padding:2px 6px;border-radius:4px;">L<?php echo $tr['depth']; ?></span></td>
                                                <td>&#x20B9;<?php echo number_format($tr['target'], 2); ?></td>
                                                <td>&#x20B9;<?php echo number_format($tr['achieved'], 2); ?></td>
                                                <td style="color:<?php echo $gap > 0 ? '#c62828' : '#2e7d32'; ?>">
                                                    <?php echo $gap > 0 ? '−' : '+'; ?>&#x20B9;<?php echo number_format(abs($gap), 2); ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:100px"><div class="progress-fill" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $bar_c; ?>"></div></div>
                                                        <span style="font-size:13px;font-weight:700;color:<?php echo $bar_c; ?>"><?php echo $pct; ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════
                         6-MONTH GROWTH TREND
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-7">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">6-Month Growth Trend</h5></div>
                                <div class="card-body">
                                    <div class="chart-container"><canvas id="growthChart"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-5">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Month-over-Month Summary</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($six_months)): ?>
                                        <p class="text-muted text-center py-3">No data.</p>
                                    <?php else: ?>
                                    <table class="mis-table">
                                        <thead><tr><th>Month</th><th>Revenue</th><th>Invoices</th><th>Growth</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($six_months as $m): ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($m['lbl']); ?></b></td>
                                                <td>&#x20B9;<?php echo number_format($m['total_rev'], 0); ?></td>
                                                <td><?php echo (int)$m['total_cnt']; ?></td>
                                                <td>
                                                    <?php if ($m['growth'] === null): ?>
                                                        <span style="color:#888">—</span>
                                                    <?php elseif ($m['growth'] >= 0): ?>
                                                        <span class="growth-pos">▲ <?php echo $m['growth']; ?>%</span>
                                                    <?php else: ?>
                                                        <span class="growth-neg">▼ <?php echo abs($m['growth']); ?>%</span>
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

                    <!-- ══════════════════════════════════════════════════════
                         RETURNS & CANCELLATIONS
                    ═══════════════════════════════════════════════════════ -->
                    <div class="row mis-section">
                        <div class="col-xl-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Returns &amp; Credit Notes
                                        <span class="badge badge-style-light badge-danger" style="margin-left:8px;">
                                            <?php echo $total_returns; ?> returns — &#x20B9;<?php echo number_format($total_return_amt, 2); ?>
                                        </span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($returns_list)): ?>
                                        <p class="text-muted text-center py-3">No returns in this period.</p>
                                    <?php else: ?>
                                    <div style="overflow-x:auto">
                                    <table class="mis-table">
                                        <thead><tr><th>Return ID</th><th>Invoice No.</th><th>From</th><th>Date</th><th>Amount</th><th>Status</th><th>Detail</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($returns_list as $r): ?>
                                            <tr>
                                                <td><small><?php echo htmlspecialchars($r['returnid']); ?></small></td>
                                                <td><?php echo htmlspecialchars($r['inv_number'] ?? $r['invnumber']); ?></td>
                                                <td><?php echo ucfirst(str_replace('_',' ',$r['from_usertype'])); ?></td>
                                                <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo number_format($r['total'], 2); ?></span></td>
                                                <td>
                                                    <?php if ($r['status'] === 'pending'): ?>
                                                    <span class="status-badge badge-partial">Pending</span>
                                                    <?php else: ?>
                                                    <span class="status-badge badge-paid"><?php echo ucfirst($r['status']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><a href="cnote_details.php?returnid=<?php echo base64_encode($r['returnid']); ?>">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
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
// ── Tab switching
document.querySelectorAll('.tab-item').forEach(function(t) {
    t.addEventListener('click', function() {
        document.querySelectorAll('#periodTabs .tab-item').forEach(function(x) { x.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(x) { x.classList.remove('active'); });
        t.classList.add('active');
        var tab = document.getElementById('tab-' + t.dataset.tab);
        if (tab) tab.classList.add('active');
    });
});

// ── Chart defaults
Chart.defaults.font.family = 'Poppins, sans-serif';
Chart.defaults.font.size   = 12;

// ── 1. Daily Trend Chart
(function() {
    var ctx = document.getElementById('trendChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $j_labels; ?>,
            datasets: [
                {
                    label: 'Customer Sales',
                    data: <?php echo $j_cust; ?>,
                    borderColor: '#3f51b5', backgroundColor: 'rgba(63,81,181,0.08)',
                    tension: 0.3, fill: true, pointRadius: 3
                },
                {
                    label: 'Shop Sales',
                    data: <?php echo $j_shop; ?>,
                    borderColor: '#ef6c00', backgroundColor: 'rgba(239,108,0,0.08)',
                    tension: 0.3, fill: true, pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { ticks: { callback: function(v) { return '₹' + (v/1000).toFixed(0) + 'k'; } } }
            }
        }
    });
})();

// ── 2. Order Status Doughnut
(function() {
    var ctx = document.getElementById('statusChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Fully Paid', 'Partial', 'Unpaid'],
            datasets: [{
                data: [<?php echo $os_paid; ?>, <?php echo $os_partial; ?>, <?php echo $os_unpaid; ?>],
                backgroundColor: ['#4caf50','#ff9800','#f44336'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
})();

// ── 3. Product Mix Doughnut
(function() {
    var ctx = document.getElementById('productChart');
    if (!ctx) return;
    var labels = <?php echo $j_plabels; ?>.slice(0,8);
    var data   = <?php echo $j_pqty; ?>.slice(0,8);
    var colors = ['#3f51b5','#e53935','#ef6c00','#2e7d32','#7b1fa2','#00838f','#c62828','#f57f17'];
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{ data: data, backgroundColor: colors, borderWidth: 0 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '60%',
            plugins: { legend: { position: 'right', labels: { font: { size: 11 } } } }
        }
    });
})();

// ── 4. 6-Month Growth Bar
(function() {
    var ctx = document.getElementById('growthChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo $j_glabels; ?>,
            datasets: [{
                label: 'Monthly Revenue',
                data: <?php echo $j_gvals; ?>,
                backgroundColor: 'rgba(63,81,181,0.75)',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { ticks: { callback: function(v) { return '₹' + (v/1000).toFixed(0) + 'k'; } } }
            }
        }
    });
})();
</script>
</body>
</html>
