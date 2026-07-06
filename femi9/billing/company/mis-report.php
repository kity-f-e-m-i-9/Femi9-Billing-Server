<?php
include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// ── MIS Type (only Sales is implemented) ────────────────────────────────────
$mis_type = $_GET['mis_type'] ?? 'sales';
if (!in_array($mis_type, ['sales', 'purchase', 'inventory'], true)) $mis_type = 'sales';

// ── Date range & TP filter ─────────────────────────────────────────────────
$preset   = $_GET['preset'] ?? 'month';
$today    = date('Y-m-d');

// ── Scope: company (direct, all-channel) vs a single channel's transactions ─
$scope = $_GET['scope'] ?? 'tp';
if (!in_array($scope, ['company', 'tp', 'super_stockiest', 'stockiest'], true)) $scope = 'tp';
$filter_tp = ($scope === 'tp') ? (int)($_GET['tp_id'] ?? 0) : 0;   // 0 = all TPs, only meaningful in tp scope

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

// Scope = "invoices actually ISSUED BY this entity", not "transactions this
// entity appears in on either side":
//  - the `invoice` table is always company-issued (company is always the
//    seller there), so it only counts for the 'company' scope. There's no
//    per-row column to match against for that, so each invoice-table query
//    below binds $inv_flag ('1' or '0') against a bare `(?='1')` condition
//    instead of a real column — always-true for company scope, always-false
//    otherwise, with no operator-precedence risk since it's self-contained.
//  - the `user_invoice` table's `from_user_type` IS the actual issuer, so it
//    counts uniformly for every scope via a plain `from_user_type = ?`
//    match — 'company' maps to from_user_type='company' (company invoicing
//    through the reseller-chain mechanism), 'tp'/'super_stockiest'/
//    'stockiest' map to that channel issuing its own invoices.
$scope_types = [
    'company'         => 'company',
    'tp'              => 'territory_partner',
    'super_stockiest' => 'super_stockiest',
    'stockiest'       => 'stockiest',
];
$utype = $scope_types[$scope] ?? 'territory_partner';
$inv_flag = ($scope === 'company') ? '1' : '0';
$inv_active = ($scope === 'company') ? '1=1' : '1=0';

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
$tc_ii  = $filter_tp > 0 ? " AND ii.user_id={$filter_tp}"        : "";
$tc_uii = $filter_tp > 0 ? " AND uii.from_user_id={$filter_tp}"  : "";

// ── Load all TPs for filter dropdown ──────────────────────────────────────
$all_tps = call_rows($db_conn,
    "SELECT id, name, tp_id FROM territory_partners WHERE is_active=1 ORDER BY name ASC");

// ── Purchase / Inventory not built yet — show a placeholder and stop ───────
if ($mis_type !== 'sales') {
    $mis_type_label = ucfirst($mis_type);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>MIS Report : <?php echo $business_name; ?></title>
        <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
        <link href="../../assets/css/main.min.css" rel="stylesheet">
        <link href="../../assets/css/custom.css" rel="stylesheet">
        <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
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
                        <div class="mis-filter" style="background:#f5f6fa;border-radius:8px;padding:14px 18px;margin-bottom:22px;">
                            <form method="get" style="display:flex;gap:10px;align-items:flex-end;">
                                <div>
                                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">MIS Type</label>
                                    <select name="mis_type" class="form-control form-control-sm" style="width:200px;" onchange="this.form.submit()">
                                        <option value="sales">Sales</option>
                                        <option value="purchase" <?php echo $mis_type==='purchase' ? 'selected' : ''; ?>>Purchase</option>
                                        <option value="inventory" <?php echo $mis_type==='inventory' ? 'selected' : ''; ?>>Inventory</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="material-icons-outlined" style="font-size:48px;color:#bbb;">construction</i>
                                <h4 class="mt-3"><?php echo $mis_type_label; ?> MIS — Coming Soon</h4>
                                <p class="text-muted">This report type is not built yet.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// 1. KPI — current & previous period
// ═══════════════════════════════════════════════════════════════════════════
$cust_row = crow($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) rev FROM invoice
     WHERE (?='1') AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}",
    'sss', [$inv_flag, $from, $to]);
$shop_row = crow($db_conn,
    "SELECT COUNT(*) cnt, COALESCE(SUM(total),0) rev FROM user_invoice
     WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}",
    'sss', [$utype, $from, $to]);

$total_invoices = (int)$cust_row['cnt'] + (int)$shop_row['cnt'];
$total_revenue  = (float)$cust_row['rev'] + (float)$shop_row['rev'];

$cust_units = (int)cval($db_conn,
    "SELECT COALESCE(SUM(ii.qty),0) FROM invoice_items ii
     JOIN invoice i ON i.inv_id = ii.inv_id
     WHERE (?='1') AND i.date BETWEEN ? AND ?".tp_cond_inv($filter_tp),
    'sss', [$inv_flag, $from, $to]);
$shop_units = (int)cval($db_conn,
    "SELECT COALESCE(SUM(uii.qty),0) FROM user_invoice_items uii
     JOIN user_invoice ui ON ui.inv_id = uii.inv_id
     WHERE ui.from_user_type=? AND ui.date BETWEEN ? AND ?".tp_cond_ui($filter_tp),
    'sss', [$utype, $from, $to]);
$total_units = $cust_units + $shop_units;

// OT Channel sales (Amazon/Flipkart/Website/etc.) — folded only into the
// broad "Income to Company" view; it isn't a TP/Super-Stockist/Stockist
// attributed channel, so other scopes leave it out.
$ot_row = ['cnt' => 0, 'rev' => 0];
$ot_units = 0;
$ot_prev_rev = 0.0;
if ($scope === 'company') {
    $ot_row = crow($db_conn,
        "SELECT COUNT(DISTINCT tempid) cnt, COALESCE(SUM(total),0) rev FROM ot_sales
         WHERE date BETWEEN ? AND ?",
        'ss', [$from, $to]);
    $ot_units = (int)cval($db_conn,
        "SELECT COALESCE(SUM(qty),0) FROM ot_sales WHERE date BETWEEN ? AND ?",
        'ss', [$from, $to]);
    $ot_prev_rev = (float)cval($db_conn,
        "SELECT COALESCE(SUM(total),0) FROM ot_sales WHERE date BETWEEN ? AND ?",
        'ss', [$prev_from, $prev_to]);
}
$total_invoices += (int)($ot_row['cnt'] ?? 0);
$total_revenue  += (float)($ot_row['rev'] ?? 0);
$total_units    += $ot_units;

$total_tps = (int)cval($db_conn,
    "SELECT COUNT(DISTINCT user_id) FROM invoice WHERE (?='1') AND sub_total>0 AND `date` BETWEEN ? AND ?",
    'sss', [$inv_flag, $from, $to]);
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
     WHERE (?='1') AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}",
    'sss', [$inv_flag, $prev_from, $prev_to])
  + (float)cval($db_conn,
    "SELECT COALESCE(SUM(total),0) FROM user_invoice
     WHERE from_user_type=? AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_ui}",
    'sss', [$utype, $prev_from, $prev_to])
  + $ot_prev_rev;
$revenue_growth = $prev_rev > 0
    ? round((($total_revenue - $prev_rev) / $prev_rev) * 100, 1) : 0;

// ═══════════════════════════════════════════════════════════════════════════
// 1b. GROSS PROFIT & NET PROFIT
//
// Gross Profit = Σ (MRP − reference tier price for the buyer's counterparty
// type) × qty, across every sold line item in the current scope. TP-channel
// sales use stockist_price as the reference (TP is priced like a Stockiest);
// shop sales use outlet_price. Unmapped counterparty types (company/customer)
// contribute NULL and are ignored by SUM().
//
// Net Profit = Gross Profit − Expense Tracker's net expense total for the
// same period, and is only meaningful for the "Income to Company" scope
// (expenses are a company-wide cost, not attributable to a single TP).
// ═══════════════════════════════════════════════════════════════════════════
$gp_case = "CASE d.ctype
        WHEN 'super_stockiest'   THEN p.supersstock_price
        WHEN 'stockiest'         THEN p.stockist_price
        WHEN 'super_distributor' THEN p.super_distributor_price
        WHEN 'distributor'       THEN p.distributor_price
        WHEN 'territory_partner' THEN p.stockist_price
        WHEN 'shop'              THEN p.outlet_price
        ELSE NULL
    END";
// OT channel sales are retail/direct-to-consumer, same pricing tier as shop
// sales, so they reuse the 'shop' -> outlet_price mapping. Company scope only.
$gp_ot_union = '';
$gp_params = [$inv_flag, $from, $to, $utype, $from, $to];
if ($scope === 'company') {
    $gp_ot_union = "UNION ALL SELECT os.prid, os.qty, 'shop' AS ctype
         FROM ot_sales os WHERE os.date BETWEEN ? AND ?";
    $gp_params[] = $from;
    $gp_params[] = $to;
}
$gross_profit = (float)cval($db_conn,
    "SELECT COALESCE(SUM((p.mrp - {$gp_case}) * d.qty), 0)
     FROM (
         SELECT ii.pr_id, ii.qty, i.user_type AS ctype
         FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
         WHERE (?='1') AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_ii}
         UNION ALL
         SELECT uii.pr_id, uii.qty, ui.to_user_type AS ctype
         FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
         WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_uii}
         {$gp_ot_union}
     ) d JOIN products p ON p.id = d.pr_id",
    str_repeat('s', count($gp_params)), $gp_params);

$total_expenses = 0.0;
$net_profit = null;
if ($scope === 'company') {
    $total_expenses = (float)cval($db_conn,
        "SELECT COALESCE(SUM(net_amount),0) FROM expense_imports
         WHERE company_id IN (SELECT id FROM company_godown WHERE gname LIKE '%Femi%' AND " . godown_finance_filter_sql($db_conn) . ")
         AND expense_month BETWEEN DATE_FORMAT(?, '%Y-%m-01') AND DATE_FORMAT(?, '%Y-%m-01')",
        'ss', [$from, $to]);
    $net_profit = $gross_profit - $total_expenses;
}

// ═══════════════════════════════════════════════════════════════════════════
// 1c. CHANNEL BREAKDOWN — only for the "Income to Company" scope, so it's
// visible that every channel (SS, S, SD, D, Customer, Shop, OT) is actually
// being counted in the totals above, not just a single opaque figure.
// ═══════════════════════════════════════════════════════════════════════════
$channel_labels = [
    'super_stockiest'   => 'Super Stockist',
    'stockiest'         => 'Stockist',
    'super_distributor' => 'Super Distributor',
    'distributor'       => 'Distributor',
    'company'           => 'Customer (Direct)',
    'shop'              => 'Shop',
    'territory_partner' => 'Territory Partner',
];
$channel_breakdown = [];
if ($scope === 'company') {
    // Income to Company = every invoice the company itself issued: all of
    // `invoice` (always company-issued) plus the `user_invoice` rows where
    // the company is specifically the issuer (from_user_type='company').
    $ch_a = call_rows($db_conn,
        "SELECT user_type ch, COUNT(*) cnt, COALESCE(SUM(total),0) rev FROM invoice
         WHERE sub_total>0 AND `date` BETWEEN ? AND ?
         GROUP BY user_type", 'ss', [$from, $to]);
    $ch_b = call_rows($db_conn,
        "SELECT to_user_type ch, COUNT(*) cnt, COALESCE(SUM(total),0) rev FROM user_invoice
         WHERE from_user_type='company' AND sub_total>0 AND `date` BETWEEN ? AND ?
         GROUP BY to_user_type", 'ss', [$from, $to]);
    foreach (array_merge($ch_a, $ch_b) as $r) {
        $key = $r['ch'];
        if (!isset($channel_labels[$key])) continue; // skip stray/unlabelled types
        if (!isset($channel_breakdown[$key])) $channel_breakdown[$key] = ['cnt' => 0, 'rev' => 0.0];
        $channel_breakdown[$key]['cnt'] += (int)$r['cnt'];
        $channel_breakdown[$key]['rev'] += (float)$r['rev'];
    }
    $channel_breakdown['ot'] = ['cnt' => (int)($ot_row['cnt'] ?? 0), 'rev' => (float)($ot_row['rev'] ?? 0)];
    $channel_labels['ot'] = 'OT Channel';
}
$channel_total_rev = array_sum(array_column($channel_breakdown, 'rev')) ?: 1;

// ═══════════════════════════════════════════════════════════════════════════
// 2. DAILY TREND CHART DATA
// ═══════════════════════════════════════════════════════════════════════════
$dc = call_rows($db_conn,
    "SELECT `date` d, COALESCE(SUM(total),0) rev FROM invoice
     WHERE (?='1') AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}
     GROUP BY `date` ORDER BY `date`",
    'sss', [$inv_flag, $from, $to]);
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
function company_period($db, $utype, $inv_flag, $from, $to, $tc_inv, $tc_ui, $gfmt, $lfmt) {
    $cust = call_rows($db,
        "SELECT DATE_FORMAT(`date`,'$gfmt') g, DATE_FORMAT(MIN(`date`),'$lfmt') lbl,
                COUNT(*) cnt, COALESCE(SUM(total),0) rev
         FROM invoice WHERE (?='1') AND sub_total>0 AND `date` BETWEEN ? AND ?{$tc_inv}
         GROUP BY g ORDER BY g",
        'sss', [$inv_flag, $from, $to]);
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
$daily_p   = company_period($db_conn,$utype,$inv_flag,$from,$to,$tc_inv,$tc_ui,'%Y-%m-%d','%d %b');
$weekly_p  = company_period($db_conn,$utype,$inv_flag,$from,$to,$tc_inv,$tc_ui,'%Y-%u','W%u %Y');
$monthly_p = company_period($db_conn,$utype,$inv_flag,$from,$to,$tc_inv,$tc_ui,'%Y-%m','%b %Y');
$yearly_p  = company_period($db_conn,$utype,$inv_flag,$from,$to,$tc_inv,$tc_ui,'%Y','%Y');

// ═══════════════════════════════════════════════════════════════════════════
// 4. PRODUCT-WISE SALES
// ═══════════════════════════════════════════════════════════════════════════
$product_sales = call_rows($db_conn,
    "SELECT p.productName,
            COALESCE(SUM(d.qty),0) total_qty,
            COALESCE(SUM(d.total),0) total_rev
     FROM (
         SELECT ii.pr_id, ii.qty, ii.total
         FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
         WHERE (?='1') AND i.date BETWEEN ? AND ?{$tc_ii}
         UNION ALL
         SELECT uii.pr_id, uii.qty, uii.total
         FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
         WHERE ui.from_user_type=? AND ui.date BETWEEN ? AND ?{$tc_uii}
     ) d JOIN products p ON p.id=d.pr_id
     GROUP BY p.id, p.productName ORDER BY total_qty DESC LIMIT 25",
    'ssssss', [$inv_flag, $from, $to, $utype, $from, $to]);
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
// Only meaningful in the TP-channel scope — always about actual TPs, so it's
// skipped entirely for the "Income to Company" (direct, non-TP) scope.
// ═══════════════════════════════════════════════════════════════════════════
// Revenue/units/count come only from invoices TP itself issued
// (user_invoice.from_user_type='territory_partner') — not from what company
// invoiced TO the TP (that's company's own invoice, not TP's).
$tp_perf = ($scope === 'tp') ? call_rows($db_conn,
    "SELECT tp.id tp_id, tp.name tp_name, tp.tp_id tp_code,
            COALESCE(si.cnt,0) inv_cnt,
            COALESCE(si.rev,0) revenue,
            COALESCE(si.units,0) units,
            COALESCE(tgt.target,0) target
     FROM territory_partners tp
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
     ORDER BY revenue DESC") : [];

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

$tc_inv_i = $filter_tp > 0 ? " AND i.user_id={$filter_tp}" : "";
$top_custs = call_rows($db_conn,
    "SELECT COALESCE(c.name,'Walking Customer') cust_name, COUNT(*) inv_cnt, COALESCE(SUM(i.total),0) revenue
     FROM invoice i LEFT JOIN customers c ON c.id=i.customer_id
     WHERE (?='1') AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_inv_i}
     GROUP BY i.customer_id ORDER BY revenue DESC LIMIT 10",
    'sss', [$inv_flag, $from, $to]);

// ═══════════════════════════════════════════════════════════════════════════
// 8. ORDER STATUS
// ═══════════════════════════════════════════════════════════════════════════
$ord_c = call_rows($db_conn,
    "SELECT i.total, COALESCE(r.paid,0) paid
     FROM invoice i LEFT JOIN (SELECT inv_id, SUM(received) paid FROM receipt GROUP BY inv_id) r ON r.inv_id=i.inv_id
     WHERE (?='1') AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_inv}",
    'sss', [$inv_flag, $from, $to]);
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
         WHERE (?='1') AND sub_total>0 AND `date`>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH){$tc_inv}
         GROUP BY `date`
         UNION ALL
         SELECT `date` d, SUM(total) rev, COUNT(*) cnt FROM user_invoice
         WHERE from_user_type=? AND sub_total>0 AND `date`>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH){$tc_ui}
         GROUP BY `date`
     ) z GROUP BY DATE_FORMAT(d,'%Y-%m') ORDER BY mon",
    'ss', [$inv_flag, $utype]);
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
        :root {
            --surface-1:      #ffffff;
            --page-plane:     #f7f7f6;
            --text-primary:   #0b0b0b;
            --text-secondary: #52514e;
            --text-muted:     #898781;
            --gridline:       #e1e0d9;
            --border:         rgba(11,11,11,0.10);

            --blue:    #2a78d6; --blue-tint:    #eaf2fc;
            --aqua:    #1baf7a; --aqua-tint:    #e7f8f1;
            --green:   #008300; --green-tint:   #e5f0e5;
            --violet:  #4a3aa7; --violet-tint:  #eeecf8;
            --orange:  #eb6834; --orange-tint:  #fdece4;
            --red:     #e34948; --red-tint:     #fcecec;

            --good:     #0ca30c; --good-tint:     #e5f7e5;
            --warning:  #fab219; --warning-tint:  #fff3dc;
            --serious:  #ec835a;
            --critical: #d03b3b; --critical-tint: #fbe6e6;
        }

        body { background: var(--page-plane); }
        .mis-section { margin-bottom: 28px; scroll-margin-top: 90px; }

        /* ── Filter bar ──────────────────────────────────────────────── */
        .mis-filter { background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; margin-bottom: 14px; box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .preset-btn { padding:4px 13px; border-radius:20px; border:1.5px solid var(--blue); color:var(--blue); background:var(--surface-1); font-size:12px; cursor:pointer; text-decoration:none; display:inline-block; transition:background .12s,color .12s; }
        .preset-btn.active, .preset-btn:hover { background:var(--blue); color:#fff; border-color:var(--blue); }

        /* ── Section navigation (sticky quick-jump) ──────────────────── */
        .section-nav { position: sticky; top: 0; z-index: 20; background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 8px 10px; margin-bottom: 22px; display: flex; gap: 4px; overflow-x: auto; box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .section-nav a { flex: 0 0 auto; padding: 7px 14px; border-radius: 7px; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); text-decoration: none; white-space: nowrap; transition: background .12s, color .12s; }
        .section-nav a:hover { background: var(--page-plane); color: var(--text-primary); }
        .section-nav a.active { background: var(--blue-tint); color: var(--blue); }

        /* ── KPI stat tiles ───────────────────────────────────────────── */
        .kpi-card { background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 16px 18px 16px 20px; position: relative; overflow: hidden; height: 100%; box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .kpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background: var(--kpi-accent, var(--blue)); }
        .kpi-card .kpi-ico { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; background: var(--kpi-tint, var(--blue-tint)); color: var(--kpi-accent, var(--blue)); font-size:16px; position:absolute; right:14px; top:14px; }
        .kpi-card .kpi-t  { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight:600; color: var(--text-secondary); padding-right:38px; }
        .kpi-card .kpi-v  { font-size: 21px; font-weight: 700; margin-top: 6px; line-height: 1.25; color: var(--text-primary); word-break: break-word; }
        .kpi-card .kpi-s  { font-size: 12px; margin-top: 6px; color: var(--text-secondary); }
        .kpi-card .kpi-s.good { color: var(--good); }
        .kpi-card .kpi-s.bad  { color: var(--critical); }

        /* ── Tabs (period breakdown) ──────────────────────────────────── */
        .tab-nav { display:flex; gap:0; border-bottom:1px solid var(--gridline); margin-bottom:14px; }
        .tab-item { padding:7px 18px; cursor:pointer; font-size:13px; font-weight:600; color:var(--text-secondary); border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .12s,border-color .12s; }
        .tab-item.active { color:var(--blue); border-bottom-color:var(--blue); }
        .tab-content { display:none; } .tab-content.active { display:block; }

        /* ── Tables ───────────────────────────────────────────────────── */
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:var(--page-plane); font-weight:600; color:var(--text-secondary); padding:8px 11px; text-align:left; border-bottom:1px solid var(--gridline); white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:7px 11px; border-bottom:1px solid var(--gridline); vertical-align:middle; color:var(--text-primary); }
        .mt td.num, .mt td.text-right { font-variant-numeric: tabular-nums; }
        .mt tr:hover td { background: var(--page-plane); }

        /* ── Progress / meter bars ───────────────────────────────────── */
        .pbar { height:7px; border-radius:4px; background: var(--blue-tint); overflow:hidden; }
        .pbar .pf { height:100%; border-radius:4px; background: var(--blue); }

        /* ── Badges ───────────────────────────────────────────────────── */
        .br { background:var(--green-tint); color:var(--green); padding:2px 7px; border-radius:10px; font-size:12px; font-weight:600; }
        .bq { background:var(--blue-tint); color:var(--blue); padding:2px 7px; border-radius:10px; font-size:12px; font-weight:600; }
        .bp  { background:var(--good-tint); color:var(--good); }
        .bpa { background:var(--warning-tint); color:#9a6b00; }
        .bu  { background:var(--critical-tint); color:var(--critical); }
        .sbadge { padding:2px 9px; border-radius:10px; font-size:12px; font-weight:600; }
        .gp { color:var(--good); font-weight:700; } .gn { color:var(--critical); font-weight:700; }
        .chart-box { position:relative; height:250px; }
        .rank-1 { color:var(--warning); font-weight:700; } .rank-2 { color:var(--text-muted); font-weight:700; } .rank-3 { color:var(--orange); font-weight:700; }
        .tp-tag { font-size:11px; background:var(--blue-tint); color:var(--blue); padding:1px 6px; border-radius:4px; }
        .snote { font-size:12px; color:var(--text-muted); margin-bottom:6px; }

        /* ── Cards (page-wide polish for the existing Bootstrap .card) ─ */
        .card { border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .card-header { background: var(--surface-1); border-bottom: 1px solid var(--gridline); }
        .card-title { font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 0; }

        /* ── Status legend row (Order Status) ─────────────────────────── */
        .status-row { display:flex; align-items:center; justify-content:space-between; padding:8px 0; }
        .status-row .status-label { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-primary); }
        .status-dot { width:10px; height:10px; border-radius:50%; flex:0 0 auto; }
        .status-row .status-val { font-size:13px; font-weight:600; color:var(--text-primary); font-variant-numeric: tabular-nums; }
        .stackbar { display:flex; height:14px; border-radius:7px; overflow:hidden; background:var(--gridline); margin-bottom:4px; }
        .stackbar > div { height:100%; }
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
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">MIS Type</label>
                                <select name="mis_type" class="form-control form-control-sm" style="width:140px;" onchange="this.form.submit()">
                                    <option value="sales" selected>Sales</option>
                                    <option value="purchase">Purchase</option>
                                    <option value="inventory">Inventory</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Scope</label>
                                <select name="scope" id="scopeSelect" class="form-control form-control-sm" style="width:190px;" onchange="this.form.submit()">
                                    <option value="company" <?php echo $scope==='company'?'selected':''; ?>>Income to Company</option>
                                    <option value="tp" <?php echo $scope==='tp'?'selected':''; ?>>Territory Partner</option>
                                    <option value="super_stockiest" <?php echo $scope==='super_stockiest'?'selected':''; ?>>Super Stockist</option>
                                    <option value="stockiest" <?php echo $scope==='stockiest'?'selected':''; ?>>Stockist</option>
                                </select>
                            </div>
                            <div id="tpSubFilter" style="<?php echo $scope!=='tp' ? 'display:none;' : ''; ?>">
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
                                <?php $tp_qs = "&scope={$scope}" . ($filter_tp > 0 ? "&tp_id={$filter_tp}" : ""); ?>
                                <a href="?preset=today<?php echo $tp_qs; ?>"  class="preset-btn <?php echo $preset=='today' ?'active':''; ?>">Today</a>
                                <a href="?preset=week<?php echo $tp_qs; ?>"   class="preset-btn <?php echo $preset=='week'  ?'active':''; ?>">This Week</a>
                                <a href="?preset=month<?php echo $tp_qs; ?>"  class="preset-btn <?php echo $preset=='month' ?'active':''; ?>">This Month</a>
                                <a href="?preset=year<?php echo $tp_qs; ?>"   class="preset-btn <?php echo $preset=='year'  ?'active':''; ?>">This Year</a>
                            </div>
                        </form>
                        <?php
                        $scope_labels = ['company'=>'Income to Company','tp'=>'Territory Partner','super_stockiest'=>'Super Stockist','stockiest'=>'Stockist'];
                        ?>
                        <div style="font-size:12px;color:#888;margin-top:7px;">
                            Scope: <b><?php echo $scope_labels[$scope]; ?></b> &nbsp;|&nbsp;
                            <?php if ($scope==='tp' && $filter_tp > 0): ?>
                            Filtered by TP: <b><?php foreach($all_tps as $t) if ($t['id']==$filter_tp) echo htmlspecialchars($t['name']); ?></b> &nbsp;|&nbsp;
                            <?php endif; ?>
                            Period: <b><?php echo date('d M Y', strtotime($from)); ?></b> to <b><?php echo date('d M Y', strtotime($to)); ?></b>
                        </div>
                    </div>
                    <script>
                    document.getElementById('scopeSelect').addEventListener('change', function() {
                        document.getElementById('tpSubFilter').style.display = this.value === 'tp' ? '' : 'none';
                    });
                    </script>

                    <!-- ── SECTION NAVIGATION (quick jump) ─────────────────── -->
                    <nav class="section-nav" id="sectionNav">
                        <a href="#sec-overview">Overview</a>
                        <?php if ($scope === 'company'): ?><a href="#sec-channels">Channels</a><?php endif; ?>
                        <a href="#sec-trend">Trend</a>
                        <a href="#sec-breakdown">Breakdown</a>
                        <?php if ($scope === 'tp'): ?><a href="#sec-tpperf">TP Performance</a><?php endif; ?>
                        <a href="#sec-products">Products</a>
                        <a href="#sec-geo">Geography</a>
                        <a href="#sec-topcustomers">Shops &amp; Customers</a>
                        <a href="#sec-growth">Growth</a>
                        <a href="#sec-returns">Returns</a>
                    </nav>

                    <!-- ══ KPI CARDS ════════════════════════════════════════ -->
                    <?php
                    // accent => [border/icon color, icon chip tint]
                    $accents = [
                        'blue'     => ['var(--blue)',     'var(--blue-tint)'],
                        'aqua'     => ['var(--aqua)',     'var(--aqua-tint)'],
                        'green'    => ['var(--green)',    'var(--green-tint)'],
                        'violet'   => ['var(--violet)',   'var(--violet-tint)'],
                        'orange'   => ['var(--orange)',   'var(--orange-tint)'],
                        'good'     => ['var(--good)',     'var(--good-tint)'],
                        'warning'  => ['var(--warning)',  'var(--warning-tint)'],
                        'critical' => ['var(--critical)', 'var(--critical-tint)'],
                    ];
                    $active_labels = [
                        'company'          => ['Active Counterparties', 'Direct customers'],
                        'tp'               => ['Active TPs', 'TPs'],
                        'super_stockiest'  => ['Active Super Stockists', 'Super Stockists'],
                        'stockiest'        => ['Active Stockists', 'Stockists'],
                    ];
                    [$active_label, $active_sublabel] = $active_labels[$scope];
                    // kpi row: [accent key, icon, label, value, sub-text, sub-text tone ('', 'good', 'bad')]
                    $kpis = [
                        ['blue','payments','Total Turnover','₹'.inr_format($total_revenue, 0),
                         ($revenue_growth>=0?'▲':'▼').' '.abs($revenue_growth).'% vs prev', $revenue_growth>=0?'good':'bad'],
                        ['aqua','receipt_long','Total Invoices',inr_format($total_invoices, 0),
                         'Cust: '.($cust_row['cnt']??0).' | Shop: '.($shop_row['cnt']??0), ''],
                        ['green','inventory_2','Units Sold',inr_format($total_units, 0),
                         'Cust: '.inr_format($cust_units, 0).' | Shop: '.inr_format($shop_units, 0), ''],
                        ['violet','people',$active_label,inr_format($active_tps, 0),
                         $active_sublabel.' with invoices in period', ''],
                        ['critical','keyboard_return','Returns',inr_format($total_returns, 0),
                         '₹'.inr_format($total_return_amt, 0).' returned', ''],
                    ];
                    if ($scope === 'tp') {
                        $tgt_accent = $overall_pct_all>=100 ? 'good' : ($overall_pct_all>=50 ? 'warning' : 'critical');
                        $kpis[] = [$tgt_accent,'flag','Overall Target %',$overall_pct_all.'%',
                         '₹'.inr_format($total_achieved_all, 0).' / ₹'.inr_format($total_target_all, 0), ''];
                    }
                    $kpis[] = ['orange','trending_up','Gross Profit','₹'.inr_format($gross_profit, 0),
                         'MRP vs tier price given', ''];
                    if ($scope === 'company') {
                        $kpis[] = [$net_profit>=0?'good':'critical','account_balance_wallet','Net Profit','₹'.inr_format($net_profit, 0),
                         'Gross Profit − Expenses (₹'.inr_format($total_expenses, 0).')', $net_profit>=0?'good':'bad'];
                    }
                    ?>
                    <div class="row mis-section" id="sec-overview">
                        <?php foreach ($kpis as $k): [$accent, $tint] = $accents[$k[0]]; ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-6 mb-3">
                            <div class="kpi-card" style="--kpi-accent:<?php echo $accent; ?>;--kpi-tint:<?php echo $tint; ?>;">
                                <i class="material-icons-outlined kpi-ico"><?php echo $k[1]; ?></i>
                                <div class="kpi-t"><?php echo $k[2]; ?></div>
                                <div class="kpi-v"><?php echo $k[3]; ?></div>
                                <div class="kpi-s<?php echo $k[5] ? ' '.($k[5]==='bad'?'bad':'good') : ''; ?>"><?php echo $k[4]; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ══ CHANNEL BREAKDOWN (Income to Company scope only) ═ -->
                    <?php if ($scope === 'company'): ?>
                    <div class="row mis-section" id="sec-channels">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Channel Breakdown</h5></div>
                                <div class="card-body" style="overflow-x:auto">
                                    <p class="snote">Every channel below is included in the Total Turnover / Gross Profit figures above.</p>
                                    <table class="mt">
                                        <thead><tr><th>Channel</th><th>Invoices</th><th>Revenue</th><th>Share</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($channel_labels as $key => $label):
                                            $row = $channel_breakdown[$key] ?? ['cnt' => 0, 'rev' => 0.0];
                                            $pct = round($row['rev'] / $channel_total_rev * 100, 1);
                                        ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($label); ?></b></td>
                                                <td><?php echo inr_format($row['cnt'], 0); ?></td>
                                                <td><span class="br">₹<?php echo inr_format($row['rev'], 2); ?></span></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%"></div></div>
                                                    <span style="font-size:12px"><?php echo $pct; ?>%</span>
                                                </div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ══ TREND CHART + ORDER STATUS ══════════════════════ -->
                    <div class="row mis-section" id="sec-trend">
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title">Daily Sales Trend</h5></div>
                                <div class="card-body"><div class="chart-box"><canvas id="trendChart"></canvas></div></div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card h-100">
                                <div class="card-header"><h5 class="card-title">Order Status</h5></div>
                                <div class="card-body">
                                    <?php
                                    $os_total_a = $os_paid_a + $os_part_a + $os_unpd_a ?: 1;
                                    $os_paid_pct = round($os_paid_a / $os_total_a * 100, 1);
                                    $os_part_pct = round($os_part_a / $os_total_a * 100, 1);
                                    $os_unpd_pct = round($os_unpd_a / $os_total_a * 100, 1);
                                    ?>
                                    <div class="stackbar">
                                        <div style="width:<?php echo $os_paid_pct; ?>%;background:var(--good)"></div>
                                        <div style="width:<?php echo $os_part_pct; ?>%;background:var(--warning)"></div>
                                        <div style="width:<?php echo $os_unpd_pct; ?>%;background:var(--critical)"></div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="status-row">
                                            <span class="status-label"><span class="status-dot" style="background:var(--good)"></span>Fully Paid</span>
                                            <span class="status-val"><?php echo $os_paid; ?> — ₹<?php echo inr_format($os_paid_a, 0); ?> (<?php echo $os_paid_pct; ?>%)</span>
                                        </div>
                                        <div class="status-row">
                                            <span class="status-label"><span class="status-dot" style="background:var(--warning)"></span>Partially Paid</span>
                                            <span class="status-val"><?php echo $os_part; ?> — ₹<?php echo inr_format($os_part_a, 0); ?> (<?php echo $os_part_pct; ?>%)</span>
                                        </div>
                                        <div class="status-row">
                                            <span class="status-label"><span class="status-dot" style="background:var(--critical)"></span>Unpaid</span>
                                            <span class="status-val"><?php echo $os_unpd; ?> — ₹<?php echo inr_format($os_unpd_a, 0); ?> (<?php echo $os_unpd_pct; ?>%)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ PERIOD BREAKDOWN TABS ════════════════════════════ -->
                    <div class="row mis-section" id="sec-breakdown">
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
                                                <td>₹".inr_format($r['c']??0, 2)." <small>({$r['cc']})</small></td>
                                                <td>₹".inr_format($r['s']??0, 2)." <small>({$r['sc']})</small></td>
                                                <td><b>₹".inr_format($rev, 2)."</b></td>
                                                <td>{$cnt}</td>
                                                <td><div style='display:flex;align-items:center;gap:6px'>
                                                    <div class='pbar' style='width:80px'><div class='pf' style='width:{$pct}%;background:#2a78d6'></div></div>
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
                    <?php if ($scope === 'tp'): ?>
                    <div class="row mis-section" id="sec-tpperf">
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
                                                <div style="font-size:22px;font-weight:700;color:#1a237e">₹<?php echo inr_format($total_achieved_all, 0); ?></div>
                                            </div>
                                            <div style="background:#f5f6fa;border-radius:8px;padding:14px;text-align:center;">
                                                <div style="font-size:11px;color:#888;text-transform:uppercase;font-weight:600">Overall Achievement</div>
                                                <div style="font-size:22px;font-weight:700;color:<?php echo $overall_pct_all>=100?'#0ca30c':($overall_pct_all>=50?'#9a6b00':'#d03b3b'); ?>">
                                                    <?php echo $overall_pct_all; ?>%
                                                </div>
                                                <div style="font-size:12px;color:#888">Target: ₹<?php echo inr_format($total_target_all, 0); ?></div>
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
                                            $bc  = $pct>=100?'#0ca30c':($pct>=50?'#fab219':'#d03b3b');
                                            $gap = (float)$tp['target'] - (float)$tp['revenue'];
                                            $rk_class = $i==0?'rank-1':($i==1?'rank-2':($i==2?'rank-3':''));
                                            ?>
                                            <tr>
                                                <td class="<?php echo $rk_class; ?>"><?php echo $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':($i+1))); ?></td>
                                                <td><b><?php echo htmlspecialchars($tp['tp_name']); ?></b></td>
                                                <td><span class="tp-tag"><?php echo htmlspecialchars($tp['tp_code']); ?></span></td>
                                                <td><?php echo inr_format((int)$tp['inv_cnt'], 0); ?></td>
                                                <td><span class="bq"><?php echo inr_format((int)$tp['units'], 0); ?></span></td>
                                                <td>
                                                    <b>₹<?php echo inr_format($tp['revenue'], 2); ?></b>
                                                    <div class="pbar mt-1"><div class="pf" style="width:<?php echo round($tp['revenue']/$max_tp_rev*100,1); ?>%;background:#2a78d6"></div></div>
                                                </td>
                                                <td>₹<?php echo inr_format($tp['target'], 0); ?></td>
                                                <td>
                                                    <div style="display:flex;align-items:center;gap:5px">
                                                        <div class="pbar" style="width:80px"><div class="pf" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $bc; ?>"></div></div>
                                                        <span style="font-size:13px;font-weight:700;color:<?php echo $bc; ?>"><?php echo $pct; ?>%</span>
                                                    </div>
                                                </td>
                                                <td style="color:<?php echo $gap>0?'#d03b3b':'#0ca30c'; ?>">
                                                    <?php echo $gap>0?'−':'+'?>₹<?php echo inr_format(abs($gap), 0); ?>
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
                    <?php endif; ?>

                    <!-- ══ PRODUCT-WISE SALES ════════════════════════════════ -->
                    <div class="row mis-section" id="sec-products">
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
                                            $bc  = '#2a78d6';
                                            ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><b><?php echo htmlspecialchars($p['productName']); ?></b></td>
                                                <td><span class="bq"><?php echo inr_format((int)$p['total_qty'], 0); ?></span></td>
                                                <td><span class="br">₹<?php echo inr_format($p['total_rev'], 2); ?></span></td>
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
                                <div class="card-body">
                                    <?php if (empty($product_sales)): ?>
                                        <p class="text-muted text-center py-3">No data.</p>
                                    <?php else: ?>
                                    <div class="chart-box"><canvas id="productChart"></canvas></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ STATE / DISTRICT ══════════════════════════════════ -->
                    <div class="row mis-section" id="sec-geo">
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
                                                <td><span class="br">₹<?php echo inr_format($s['revenue'], 2); ?></span></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%;background:#1baf7a"></div></div>
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
                                                <td><span class="br">₹<?php echo inr_format($d['revenue'], 2); ?></span></td>
                                                <td><div style="display:flex;align-items:center;gap:5px">
                                                    <div class="pbar" style="width:70px"><div class="pf" style="width:<?php echo $pct; ?>%;background:#4a3aa7"></div></div>
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
                    <div class="row mis-section" id="sec-topcustomers">
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
                                                    <span class="br">₹<?php echo inr_format($s['revenue'], 2); ?></span>
                                                    <div class="pbar mt-1"><div class="pf" style="width:<?php echo round($s['revenue']/$msr*100,1); ?>%;background:#eb6834"></div></div>
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
                                                    <span class="br">₹<?php echo inr_format($c['revenue'], 2); ?></span>
                                                    <div class="pbar mt-1"><div class="pf" style="width:<?php echo round($c['revenue']/$mcr*100,1); ?>%;background:#2a78d6"></div></div>
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
                    <div class="row mis-section" id="sec-growth">
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
                                                <td>₹<?php echo inr_format($m['total_rev'], 0); ?></td>
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
                    <div class="row mis-section" id="sec-returns">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Returns &amp; Credit Notes
                                        <span class="sbadge bu" style="margin-left:8px;">
                                            <?php echo $total_returns; ?> returns — ₹<?php echo inr_format($total_return_amt, 2); ?>
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
                                                <td><span class="br">₹<?php echo inr_format($r['total'], 2); ?></span></td>
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

// Section nav: smooth scroll + scrollspy active-state
(function() {
    var navLinks = Array.from(document.querySelectorAll('#sectionNav a'));
    if (!navLinks.length) return;
    var sections = navLinks.map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);

    navLinks.forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(a.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    var onScroll = function() {
        var pos = window.scrollY + 110;
        var current = sections[0];
        sections.forEach(function(s) { if (s.offsetTop <= pos) current = s; });
        navLinks.forEach(function(a) {
            a.classList.toggle('active', current && a.getAttribute('href') === '#' + current.id);
        });
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();

// 1. Daily Trend
(function() {
    var ctx = document.getElementById('trendChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $j_labels; ?>,
            datasets: [
                { label: 'Customer', data: <?php echo $j_cust; ?>,  borderColor:'#2a78d6', backgroundColor:'rgba(42,120,214,.10)', borderWidth:2, tension:.3, fill:true, pointRadius:3, pointBackgroundColor:'#2a78d6' },
                { label: 'Shop',     data: <?php echo $j_shop; ?>,  borderColor:'#1baf7a', backgroundColor:'rgba(27,175,122,.10)', borderWidth:2, tension:.3, fill:true, pointRadius:3, pointBackgroundColor:'#1baf7a' }
            ]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{grid:{color:'#e1e0d9'},ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'k'}}, x:{grid:{display:false}}} }
    });
})();

// 3. Product Mix — horizontal bar (magnitude, single hue; a donut would need
// re-coloring 8 nominal categories, which the palette reserves for identity).
(function() {
    var ctx = document.getElementById('productChart');
    if (!ctx) return;
    var labels = <?php echo $j_plabels; ?>.slice(0,8).map(l => l.length > 28 ? l.slice(0,26)+'…' : l);
    var data   = <?php echo $j_pqty; ?>.slice(0,8);
    new Chart(ctx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: '#2a78d6', borderRadius: 4, maxBarThickness: 20 }] },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: '#e1e0d9' }, ticks: { callback: v => v.toLocaleString('en-IN') } },
                y: { grid: { display: false } }
            }
        }
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
                { label:'Revenue',  data:revs, backgroundColor:'#2a78d6', borderRadius:4, maxBarThickness:24 },
                { label:'Target',   data:tgts, backgroundColor:'#c3c2b7', borderRadius:4, maxBarThickness:24 }
            ]
        },
        options:{responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{grid:{color:'#e1e0d9'},ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'k'}}, x:{grid:{display:false}}} }
    });
})();

// 5. 6-Month Growth
(function() {
    var ctx = document.getElementById('growthChart');
    if (!ctx) return;
    new Chart(ctx, {
        type:'bar',
        data:{ labels:<?php echo $j_glabels; ?>, datasets:[{label:'Revenue', data:<?php echo $j_gvals; ?>, backgroundColor:'#2a78d6', borderRadius:6, maxBarThickness:36}] },
        options:{responsive:true, maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{y:{grid:{color:'#e1e0d9'},ticks:{callback:v=>'₹'+(v/1000).toFixed(0)+'k'}}, x:{grid:{display:false}}} }
    });
})();
</script>
</body>
</html>
