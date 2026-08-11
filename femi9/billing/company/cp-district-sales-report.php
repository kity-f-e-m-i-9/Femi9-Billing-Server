<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

function crr($db, $sql, $types = '', $params = []) {
    if (!$types) { $r = $db->query($sql); return $r ? $r->fetch_all(MYSQLI_ASSOC) : []; }
    $s = $db->prepare($sql);
    if (!$s) return [];
    $s->bind_param($types, ...$params);
    $s->execute();
    $r = $s->get_result();
    $s->close();
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

// ── Filters ──────────────────────────────────────────────────────────────────
$today = date('Y-m-d');
$from  = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-01');
$to    = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-t');
$filter_district_id = (int)($_GET['district_id'] ?? 0);
$gst_filter = $_GET['gst_filter'] ?? 'all'; // all | gst | non_gst

$gst_cond = '';
if ($gst_filter === 'gst')     $gst_cond = " AND p.gst > 0";
elseif ($gst_filter === 'non_gst') $gst_cond = " AND p.gst = 0";

// Company -> TP sales only (matches TP itself never issues a tp_invoice; the
// issuer is whoever created it — company here, not a super-stockist billing
// their own TP), same condition mis-report.php's MIS scoping settled on.
$company_tp_cond = "ti.created_by_user_type != 'super_stockiest'";

// District filter dropdown (depth=3 nodes)
$all_districts = crr($db_conn, "SELECT id, name FROM partner_location_nodes WHERE depth = 3 ORDER BY name ASC");

$district_where = $filter_district_id > 0 ? " AND danc.anc_id = {$filter_district_id}" : "";

// District -> Division breakdown of company-to-TP sales, from tp_invoice_items
// joined to products (for GST filter) and up through the TP's own assigned
// location (territory_partner_locations -> partner_location_nodes), walked to
// its depth-3 (district) and depth-4 (division) ancestors — same recursive-CTE
// pattern as company/mis-report.php's State/District section, since tp_invoices
// carry no location of their own. source_cp_id is carried through per-row so
// each division's sales can be attributed back to the Channel Partner that
// sourced them, for the commission calculation below.
$rows = crr($db_conn,
    "WITH RECURSIVE danc AS (
        SELECT id AS node_id, id AS anc_id, name AS anc_name FROM partner_location_nodes WHERE depth = 3
        UNION ALL
        SELECT c.id, a.anc_id, a.anc_name FROM partner_location_nodes c JOIN danc a ON c.parent_id = a.node_id
    ),
    divanc AS (
        SELECT id AS node_id, id AS anc_id, name AS anc_name FROM partner_location_nodes WHERE depth = 4
        UNION ALL
        SELECT c.id, a.anc_id, a.anc_name FROM partner_location_nodes c JOIN divanc a ON c.parent_id = a.node_id
    )
    SELECT danc.anc_name AS district_name, divanc.anc_name AS division_name, x.source_cp_id,
           x.territory_partner_id, tp.name AS tp_name, tp.tp_id AS tp_code,
           COUNT(DISTINCT x.tp_invoice_id) AS inv_cnt,
           COALESCE(SUM(x.qty), 0) AS total_qty,
           COALESCE(SUM(x.amount), 0) AS total_amount
    FROM (
        SELECT tii.tp_invoice_id, tii.quantity AS qty, tii.amount, ti.source_cp_id, ti.territory_partner_id,
               (SELECT tpl.location_id FROM territory_partner_locations tpl
                WHERE tpl.territory_partner_id = ti.territory_partner_id
                ORDER BY tpl.assigned_at ASC, tpl.id ASC LIMIT 1) AS location_id
        FROM tp_invoices ti
        JOIN tp_invoice_items tii ON tii.tp_invoice_id = ti.id
        JOIN products p ON p.id = tii.product_id
        WHERE {$company_tp_cond} AND ti.invoice_date BETWEEN ? AND ?{$gst_cond}
    ) x
    JOIN danc ON danc.node_id = x.location_id
    LEFT JOIN divanc ON divanc.node_id = x.location_id
    JOIN territory_partners tp ON tp.id = x.territory_partner_id
    WHERE 1=1{$district_where}
    GROUP BY danc.anc_id, danc.anc_name, divanc.anc_id, divanc.anc_name, x.source_cp_id, x.territory_partner_id, tp.name, tp.tp_id
    ORDER BY danc.anc_name ASC, total_amount DESC",
    'ss', [$from, $to]);

// ── CP commission rate lookup ────────────────────────────────────────────────
// Same rule as cp-wallet-commission-calculator.php's evaluateCpCommission():
// a CP's commission is whichever is higher — 6% of the CP's net sourced sales
// (tp_invoices.source_cp_id, net of accepted TP returns) across the WHOLE
// period, or 2% of the CP's total location deposit. That decision is made
// once per CP (deposit isn't tied to any one division), then re-expressed as
// a single effective %, which is applied to each division's own slice of that
// CP's sales — so a deposit-floor CP still contributes a division-level
// commission figure using "the same commission concept", just computed as an
// effective rate instead of a directly-split rupee amount.
//
// Most tp_invoices carry no source_cp_id (sourced directly from a company
// godown, not a CP) — those still get a commission figure here, using the
// same sales-based leg of the formula (6% of sales) with no CP to hold a
// deposit floor against, since there's no real CP for the deposit-floor half
// of the rule to apply to.
const NO_CP_COMMISSION_PCT = 0.06;
$cp_ids = array_values(array_unique(array_filter(array_column($rows, 'source_cp_id'), fn($v) => (int)$v > 0)));
$cp_effective_pct = []; // source_cp_id => effective commission %

if ($cp_ids) {
    $ph = implode(',', array_fill(0, count($cp_ids), '?'));
    $types = str_repeat('i', count($cp_ids));

    $cp_sales_gross = crr($db_conn,
        "SELECT source_cp_id, COALESCE(SUM(total_amount),0) AS gross
         FROM tp_invoices
         WHERE source_cp_id IN ({$ph}) AND invoice_date BETWEEN ? AND ?
         GROUP BY source_cp_id",
        $types . 'ss', array_merge($cp_ids, [$from, $to]));
    $cp_gross = [];
    foreach ($cp_sales_gross as $r) $cp_gross[(int)$r['source_cp_id']] = (float)$r['gross'];

    $cp_sales_returns = crr($db_conn,
        "SELECT tpi.source_cp_id, COALESCE(SUM(urs.total),0) AS returns
         FROM user_return_stock urs
         JOIN tp_invoices tpi ON tpi.invoice_number = urs.invnumber COLLATE utf8mb4_unicode_ci
         WHERE tpi.source_cp_id IN ({$ph})
           AND urs.from_usertype = 'territory_partner'
           AND urs.date BETWEEN ? AND ?
           AND urs.status = 'accept'
         GROUP BY tpi.source_cp_id",
        $types . 'ss', array_merge($cp_ids, [$from, $to]));
    $cp_returns = [];
    foreach ($cp_sales_returns as $r) $cp_returns[(int)$r['source_cp_id']] = (float)$r['returns'];

    $cp_deposits = crr($db_conn,
        "SELECT cpl.channel_partner_id, COALESCE(SUM(n.deposit_amount),0) AS deposit
         FROM channel_partner_locations cpl
         JOIN partner_location_nodes n ON n.id = cpl.location_id
         WHERE cpl.channel_partner_id IN ({$ph})
         GROUP BY cpl.channel_partner_id",
        $types, $cp_ids);
    $cp_deposit = [];
    foreach ($cp_deposits as $r) $cp_deposit[(int)$r['channel_partner_id']] = (float)$r['deposit'];

    foreach ($cp_ids as $cid) {
        $net_sales = max(0, ($cp_gross[$cid] ?? 0) - ($cp_returns[$cid] ?? 0));
        $from_sales   = round($net_sales * 0.06, 2);
        $from_deposit = round(($cp_deposit[$cid] ?? 0) * 0.02, 2);
        $commission   = max($from_sales, $from_deposit);
        // Re-expressed as a % of net sales so it can multiply cleanly against
        // any division's slice of this CP's sales, even when the deposit
        // floor (not sales) is what actually won.
        $cp_effective_pct[$cid] = $net_sales > 0 ? ($commission / $net_sales) : 0.0;
    }
}

$grand_qty = 0; $grand_amount = 0; $grand_inv = 0; $grand_commission = 0;
foreach ($rows as $r) { $grand_qty += (float)$r['total_qty']; $grand_amount += (float)$r['total_amount']; $grand_inv += (int)$r['inv_cnt']; }

// Group rows by district, then by division, then by TP (a TP's sales can
// still split across several source_cp_id rows within one division, so TPs
// are collapsed to one line the same way divisions collapse per-CP rows).
$by_district = [];
foreach ($rows as $r) {
    $dist = $r['district_name'];
    $div_key = $r['division_name'] ?? '';
    $tp_key = (int)$r['territory_partner_id'];
    $cid = (int)$r['source_cp_id'];
    $pct = $cid > 0 ? ($cp_effective_pct[$cid] ?? 0.0) : NO_CP_COMMISSION_PCT;
    $commission = (float)$r['total_amount'] * $pct;

    if (!isset($by_district[$dist])) $by_district[$dist] = ['divisions' => [], 'qty' => 0, 'amount' => 0, 'inv' => 0, 'commission' => 0];
    if (!isset($by_district[$dist]['divisions'][$div_key])) {
        $by_district[$dist]['divisions'][$div_key] = [
            'division_name' => $r['division_name'], 'inv_cnt' => 0, 'total_qty' => 0, 'total_amount' => 0, 'commission' => 0, 'tps' => [],
        ];
    }
    $by_district[$dist]['divisions'][$div_key]['inv_cnt']      += (int)$r['inv_cnt'];
    $by_district[$dist]['divisions'][$div_key]['total_qty']    += (float)$r['total_qty'];
    $by_district[$dist]['divisions'][$div_key]['total_amount'] += (float)$r['total_amount'];
    $by_district[$dist]['divisions'][$div_key]['commission']   += $commission;

    if (!isset($by_district[$dist]['divisions'][$div_key]['tps'][$tp_key])) {
        $by_district[$dist]['divisions'][$div_key]['tps'][$tp_key] = [
            'tp_name' => $r['tp_name'], 'tp_code' => $r['tp_code'], 'inv_cnt' => 0, 'total_qty' => 0, 'total_amount' => 0, 'commission' => 0,
        ];
    }
    $by_district[$dist]['divisions'][$div_key]['tps'][$tp_key]['inv_cnt']      += (int)$r['inv_cnt'];
    $by_district[$dist]['divisions'][$div_key]['tps'][$tp_key]['total_qty']    += (float)$r['total_qty'];
    $by_district[$dist]['divisions'][$div_key]['tps'][$tp_key]['total_amount'] += (float)$r['total_amount'];
    $by_district[$dist]['divisions'][$div_key]['tps'][$tp_key]['commission']   += $commission;

    $by_district[$dist]['qty']        += (float)$r['total_qty'];
    $by_district[$dist]['amount']     += (float)$r['total_amount'];
    $by_district[$dist]['inv']        += (int)$r['inv_cnt'];
    $by_district[$dist]['commission'] += $commission;
    $grand_commission                 += $commission;
}
// Highest-selling district first, so the ranked share-bars read top to bottom.
uasort($by_district, fn($a, $b) => $b['amount'] <=> $a['amount']);
foreach ($by_district as &$d) {
    uasort($d['divisions'], fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);
    foreach ($d['divisions'] as &$div) { uasort($div['tps'], fn($a, $b) => $b['total_amount'] <=> $a['total_amount']); }
    unset($div);
}
unset($d);
$max_district_amount = $by_district ? max(array_column($by_district, 'amount')) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>District Sales Report : <?php echo $business_name; ?></title>
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { background:#f5f5f2; }

        .page-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:18px; }
        .page-head h1 { font-size:22px; font-weight:800; color:#1c1b18; margin:0; display:flex; align-items:center; gap:10px; }
        .page-head h1 .ic-badge { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#4f6cf7,#7b5cf0); display:inline-flex; align-items:center; justify-content:center; color:#fff; }
        .page-head h1 .ic-badge i { font-size:20px; }
        .page-sub { font-size:12.5px; color:#82807a; margin:3px 0 0 48px; }

        .filter-bar { background:#fff; border:1px solid rgba(11,11,11,.08); box-shadow:0 1px 2px rgba(20,20,10,.04); border-radius:12px; padding:16px 20px; margin-bottom:18px; }
        .filter-bar form { display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end; }
        .filter-bar .f-group { min-width:150px; }
        .filter-bar label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#8a887f; display:block; margin-bottom:5px; }
        .filter-bar .form-control { border-radius:8px; border-color:#e3e1d9; font-size:13px; }
        .filter-bar .form-control:focus { border-color:#7b5cf0; box-shadow:0 0 0 3px rgba(123,92,240,.12); }
        .btn-apply { background:linear-gradient(135deg,#4f6cf7,#7b5cf0); border:none; color:#fff; font-weight:600; font-size:13px; border-radius:8px; padding:7px 20px; transition:opacity .15s; }
        .btn-apply:hover { opacity:.9; color:#fff; }
        .period-chip { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:#6b6960; background:#eeede6; border-radius:20px; padding:5px 13px; margin-bottom:16px; }
        .period-chip i { font-size:15px; }

        .kpi-row { display:flex; flex-wrap:wrap; gap:14px; margin-bottom:22px; }
        .kpi-card { flex:1 1 220px; background:#fff; border:1px solid rgba(11,11,11,.08); box-shadow:0 1px 2px rgba(20,20,10,.04); border-radius:12px; padding:18px 20px; display:flex; align-items:center; gap:14px; transition:transform .15s, box-shadow .15s; }
        .kpi-card:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(20,20,10,.08); }
        .kpi-ic { width:44px; height:44px; min-width:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; }
        .kpi-ic i { font-size:22px; }
        .kpi-ic.blue  { background:#eaf0ff; color:#4f6cf7; }
        .kpi-ic.green { background:#e9f9f0; color:#1fa971; }
        .kpi-ic.amber { background:#fff4e5; color:#e08a1f; }
        .kpi-ic.purple { background:#f3eeff; color:#7b5cf0; }
        .kpi-t { font-size:11px; text-transform:uppercase; letter-spacing:.5px; font-weight:700; color:#8a887f; }
        .kpi-v { font-size:22px; font-weight:800; margin-top:3px; color:#161512; line-height:1.15; }

        .section-title { font-size:15px; font-weight:800; color:#1c1b18; margin:0 0 14px; display:flex; align-items:center; gap:8px; }
        .section-title i { font-size:19px; color:#7b5cf0; }

        .dist-card { background:#fff; border:1px solid rgba(11,11,11,.08); border-radius:12px; margin-bottom:12px; overflow:hidden; box-shadow:0 1px 2px rgba(20,20,10,.03); }
        .dist-head { display:flex; align-items:center; gap:14px; padding:14px 18px; cursor:pointer; user-select:none; }
        .dist-head:hover { background:#fafaf7; }
        .dist-rank { width:26px; height:26px; border-radius:7px; background:#f0efe9; color:#6b6960; font-size:11.5px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .dist-name { font-weight:700; font-size:14.5px; color:#1c1b18; min-width:150px; }
        .dist-meta { font-size:12px; color:#8a887f; white-space:nowrap; }
        .dist-bar-wrap { flex:1; min-width:120px; height:7px; background:#f0efe9; border-radius:20px; overflow:hidden; }
        .dist-bar { height:100%; border-radius:20px; background:linear-gradient(90deg,#4f6cf7,#7b5cf0); }
        .dist-amount { font-weight:800; font-size:14.5px; color:#161512; white-space:nowrap; min-width:120px; text-align:right; }
        .dist-commission { display:block; font-weight:600; font-size:11px; color:#7b5cf0; margin-top:2px; }
        .dist-caret { color:#b3b1a8; transition:transform .18s; flex-shrink:0; }
        .dist-card.open .dist-caret { transform:rotate(180deg); }
        .dist-body { display:none; border-top:1px solid #eeede6; }
        .dist-card.open .dist-body { display:block; }

        .div-table { width:100%; border-collapse:collapse; font-size:13px; }
        .div-table th { background:#fafaf7; font-weight:700; color:#8a887f; padding:8px 18px; text-align:left; font-size:10.5px; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid #eeede6; }
        .div-table td { padding:9px 18px; border-bottom:1px solid #f3f2ec; color:#3a3833; }
        .div-table tr:last-child td { border-bottom:none; }
        .div-table tr:hover td { background:#fafaf7; }
        .div-table .div-name { display:flex; align-items:center; gap:8px; font-weight:600; color:#2b2a26; }
        .div-table .div-name .dot { width:6px; height:6px; border-radius:50%; background:#c9c7bd; flex-shrink:0; }
        .div-table td.num, .div-table th.num { text-align:right; }
        .div-table .commission-cell { color:#7b5cf0; font-weight:700; }
        .div-table tbody tr.div-row { cursor:pointer; }
        .div-table tbody tr.div-row td { padding-top:11px; padding-bottom:11px; }
        .div-caret { color:#c9c7bd; font-size:17px; vertical-align:middle; margin-left:2px; transition:transform .18s; }
        .div-row.open .div-caret { transform:rotate(180deg); color:#7b5cf0; }
        .tp-sub-row { display:none; }
        .div-row.open + .tp-sub-row { display:table-row; }
        .tp-sub-row > td { padding:0 !important; border-bottom:1px solid #f3f2ec; background:#fbfaf7; }
        .tp-table { width:100%; border-collapse:collapse; font-size:12.5px; }
        .tp-table th { background:transparent; font-weight:700; color:#a8a69d; padding:6px 18px 6px 46px; text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid #eeede6; }
        .tp-table td { padding:7px 18px; border-bottom:1px dashed #eeede6; color:#52514e; }
        .tp-table tr:last-child td { border-bottom:none; }
        .tp-table td.num, .tp-table th.num { text-align:right; }
        .tp-table .tp-name-cell { padding-left:46px; display:flex; align-items:center; gap:8px; }
        .tp-table .tp-name-cell i { font-size:15px; color:#b3b1a8; }
        .tp-table .tp-code { color:#a8a69d; font-size:11px; margin-left:4px; }
        .tp-table .commission-cell { color:#9c85f0; font-weight:600; }

        .empty-state { text-align:center; padding:50px 20px; color:#a8a69d; }
        .empty-state i { font-size:40px; opacity:.5; display:block; margin-bottom:8px; }
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

                    <div class="page-head">
                        <div>
                            <h1><span class="ic-badge"><i class="material-icons-outlined">map</i></span>District Sales Report</h1>
                            <p class="page-sub">Company &rarr; Territory Partner sales, broken down by district and division.</p>
                        </div>
                    </div>

                    <div class="filter-bar">
                        <form method="get">
                            <div class="f-group">
                                <label>From</label>
                                <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control form-control-sm">
                            </div>
                            <div class="f-group">
                                <label>To</label>
                                <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control form-control-sm">
                            </div>
                            <div class="f-group">
                                <label>District</label>
                                <select name="district_id" class="form-control form-control-sm">
                                    <option value="0">All Districts</option>
                                    <?php foreach ($all_districts as $d): ?>
                                        <option value="<?php echo (int)$d['id']; ?>" <?php echo $filter_district_id === (int)$d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="f-group">
                                <label>Product Type</label>
                                <select name="gst_filter" class="form-control form-control-sm">
                                    <option value="all" <?php echo $gst_filter === 'all' ? 'selected' : ''; ?>>All Products</option>
                                    <option value="gst" <?php echo $gst_filter === 'gst' ? 'selected' : ''; ?>>GST Products</option>
                                    <option value="non_gst" <?php echo $gst_filter === 'non_gst' ? 'selected' : ''; ?>>Non-GST Products</option>
                                </select>
                            </div>
                            <div class="f-group" style="min-width:0;">
                                <button type="submit" class="btn-apply">Apply Filters</button>
                            </div>
                        </form>
                    </div>

                    <div class="period-chip"><i class="material-icons-outlined">event</i><?php echo date('d M Y', strtotime($from)); ?> &ndash; <?php echo date('d M Y', strtotime($to)); ?></div>

                    <div class="kpi-row">
                        <div class="kpi-card">
                            <div class="kpi-ic blue"><i class="material-icons-outlined">receipt_long</i></div>
                            <div><div class="kpi-t">Invoices</div><div class="kpi-v"><?php echo inr_format($grand_inv, 0); ?></div></div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-ic amber"><i class="material-icons-outlined">inventory_2</i></div>
                            <div><div class="kpi-t">Quantity Sold</div><div class="kpi-v"><?php echo inr_format($grand_qty, 0); ?></div></div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-ic green"><i class="material-icons-outlined">payments</i></div>
                            <div><div class="kpi-t">Total Sales</div><div class="kpi-v">&#8377;<?php echo inr_format($grand_amount, 2); ?></div></div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-ic purple"><i class="material-icons-outlined">savings</i></div>
                            <div><div class="kpi-t">CP Commission</div><div class="kpi-v">&#8377;<?php echo inr_format($grand_commission, 2); ?></div></div>
                        </div>
                    </div>
                    <p class="text-muted" style="font-size:11.5px;margin:-14px 0 20px;">Commission = the sourcing Channel Partner's own winning commission rate (higher of 6% of net sales or 2% of location deposit, per <a href="cp-wallet-commission-calculator">CP Wallet Commission Calculator</a>) applied to each division's share of that CP's sales. Sales with no Channel Partner attached use a flat 6% of sales, the same sales-based leg of that formula. Not a wallet-credited amount — an estimate for reporting only.</p>

                    <h5 class="section-title"><i class="material-icons-outlined">layers</i>District / Division-wise Sales</h5>

                    <?php if (empty($by_district)): ?>
                        <div class="dist-card">
                            <div class="empty-state">
                                <i class="material-icons-outlined">search_off</i>
                                No sales in this period.
                            </div>
                        </div>
                    <?php else: $rank = 0; foreach ($by_district as $district_name => $d):
                        $rank++;
                        $share_pct = $max_district_amount > 0 ? round(($d['amount'] / $max_district_amount) * 100, 1) : 0;
                    ?>
                    <div class="dist-card<?php echo $rank === 1 ? ' open' : ''; ?>">
                        <div class="dist-head" onclick="this.parentElement.classList.toggle('open')">
                            <div class="dist-rank">#<?php echo $rank; ?></div>
                            <div class="dist-name"><?php echo htmlspecialchars($district_name); ?></div>
                            <div class="dist-bar-wrap"><div class="dist-bar" style="width:<?php echo $share_pct; ?>%;"></div></div>
                            <div class="dist-meta"><?php echo inr_format($d['inv'], 0); ?> inv &middot; <?php echo inr_format($d['qty'], 0); ?> qty</div>
                            <div class="dist-amount">&#8377;<?php echo inr_format($d['amount'], 2); ?><span class="dist-commission">&#8377;<?php echo inr_format($d['commission'], 2); ?> commission</span></div>
                            <i class="material-icons-outlined dist-caret">expand_more</i>
                        </div>
                        <div class="dist-body">
                            <div style="overflow-x:auto;">
                            <table class="div-table">
                                <thead><tr><th>Division</th><th class="num">Invoices</th><th class="num">Quantity</th><th class="num">Sales Amount &#8377;</th><th class="num">Commission &#8377;</th></tr></thead>
                                <tbody>
                                <?php foreach ($d['divisions'] as $div): ?>
                                    <tr class="div-row" onclick="this.classList.toggle('open')">
                                        <td><span class="div-name"><span class="dot"></span><?php echo htmlspecialchars($div['division_name'] ?? 'Unassigned Division'); ?><i class="material-icons-outlined div-caret">expand_more</i></span></td>
                                        <td class="num"><?php echo inr_format((int)$div['inv_cnt'], 0); ?></td>
                                        <td class="num"><?php echo inr_format((float)$div['total_qty'], 0); ?></td>
                                        <td class="num">&#8377;<?php echo inr_format((float)$div['total_amount'], 2); ?></td>
                                        <td class="num commission-cell">&#8377;<?php echo inr_format((float)$div['commission'], 2); ?></td>
                                    </tr>
                                    <tr class="tp-sub-row">
                                        <td colspan="5">
                                            <table class="tp-table">
                                                <thead><tr><th>Territory Partner</th><th class="num">Invoices</th><th class="num">Quantity</th><th class="num">Sales Amount &#8377;</th><th class="num">Commission &#8377;</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($div['tps'] as $tp): ?>
                                                    <tr>
                                                        <td><span class="tp-name-cell"><i class="material-icons-outlined">person_outline</i><?php echo htmlspecialchars($tp['tp_name']); ?><span class="tp-code"><?php echo htmlspecialchars($tp['tp_code']); ?></span></span></td>
                                                        <td class="num"><?php echo inr_format((int)$tp['inv_cnt'], 0); ?></td>
                                                        <td class="num"><?php echo inr_format((float)$tp['total_qty'], 0); ?></td>
                                                        <td class="num">&#8377;<?php echo inr_format((float)$tp['total_amount'], 2); ?></td>
                                                        <td class="num commission-cell">&#8377;<?php echo inr_format((float)$tp['commission'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.4.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
</body>
</html>
