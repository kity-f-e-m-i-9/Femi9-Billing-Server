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
    case 'all':
        $default_from = '2000-01-01'; $default_to = $today; break;
    default:
        $default_from = date('Y-m-01'); $default_to = date('Y-m-t');
}

$from = isset($_GET['from']) && $_GET['from'] ? $_GET['from'] : $default_from;
$to   = isset($_GET['to'])   && $_GET['to']   ? $_GET['to']   : $default_to;
$from = date('Y-m-d', strtotime($from));
$to   = date('Y-m-d', strtotime($to));

$statusFilter = $_GET['status_filter'] ?? 'all';
$allowedStatusFilters = ['all', 'not_paid', 'partially_paid', 'fully_paid'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) $statusFilter = 'all';

$searchTerm = trim($_GET['q'] ?? '');

$uid   = (int)$Login_user_IDvl;
$utype = $Login_user_TYPEvl;

// ── Per-shop invoice + payment summary ──────────────────────────────────────
// One row per shop this TP onboarded, aggregating all `user_invoice` rows
// billed to that shop (to_user_type='shop') within the date range, joined
// against `receipt` for amount actually collected. This mirrors the same
// paid/partial/unpaid classification used on shop-manage-invoice.php and
// add-receipt.php so the numbers here always agree with those pages.
require_once __DIR__ . '/include/shop-report-data.php';
$report           = tp_shop_report_fetch($db_conn, $uid, $utype, $from, $to, $statusFilter, $searchTerm);
$shops             = $report['rows'];
$grand_billed      = $report['grand_billed'];
$grand_received    = $report['grand_received'];
$grand_due         = $report['grand_due'];
$grand_invoices    = $report['grand_invoices'];
$shops_with_sales  = $report['shops_with_sales'];

$exportQuery = http_build_query([
    'from' => $from, 'to' => $to,
    'status_filter' => $statusFilter, 'q' => $searchTerm,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop Report : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        :root {
            --ink: #1a1d29; --ink-soft: #5c6072; --ink-faint: #9598a6;
            --line: #eceef4; --surface: #ffffff; --canvas: #f4f5fa;
            --indigo: #4338ca; --indigo-soft: #eef0fd;
            --teal: #0d9488; --teal-soft: #e6f7f5;
            --amber: #d97706; --amber-soft: #fef3e2;
            --rose: #dc2626; --rose-soft: #fdeaea;
            --green: #16a34a; --green-soft: #e8f8ed;
            --shadow-sm: 0 1px 2px rgba(20,20,43,.04), 0 1px 1px rgba(20,20,43,.03);
            --shadow-hover: 0 10px 28px rgba(24,24,60,.10), 0 2px 6px rgba(24,24,60,.05);
        }
        body { background: var(--canvas); }
        .container-fluid { max-width: 1440px; }
        .mis-page-title { font-size: 22px; font-weight: 800; color: var(--ink); letter-spacing: -.3px;
            display: flex; align-items: center; gap: 10px; margin-bottom: 2px; }
        .mis-page-title .icon-chip { width: 38px; height: 38px; border-radius: 11px; display: inline-flex;
            align-items: center; justify-content: center; background: var(--indigo-soft); color: var(--indigo); flex-shrink: 0; }
        .mis-page-sub { font-size: 13px; color: var(--ink-faint); margin: 0 0 20px 48px; }
        .mis-filter-bar { background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            padding: 16px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm); }
        .mis-filter-bar label { font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .4px; color: var(--ink-faint); display: block; margin-bottom: 4px; }
        .mis-filter-bar .form-control-sm, .mis-filter-bar select.form-control-sm {
            border-radius: 8px; border: 1px solid #dfe1eb; font-size: 13px; }
        .mis-filter-bar .btn-primary { background: var(--indigo); border-color: var(--indigo); border-radius: 8px;
            font-size: 13px; font-weight: 600; padding: 6px 16px; }
        .preset-btn { padding: 6px 15px; border-radius: 20px; border: 1px solid #e2e4ee;
            color: var(--ink-soft); background: var(--surface); font-size: 12.5px; font-weight: 600;
            cursor: pointer; text-decoration: none; }
        .preset-btn:hover { border-color: var(--indigo); color: var(--indigo); text-decoration: none; }
        .preset-btn.active { background: var(--indigo); color: #fff; border-color: var(--indigo); }
        .kpi-card { border-radius: 14px; padding: 16px 18px; background: var(--surface);
            border: 1px solid var(--line); box-shadow: var(--shadow-sm); position: relative; height: 100%; }
        .kpi-card .kpi-icon-chip { width: 34px; height: 34px; border-radius: 9px; display: inline-flex;
            align-items: center; justify-content: center; margin-bottom: 10px; }
        .kpi-card .kpi-icon-chip .material-icons-outlined { font-size: 18px; }
        .kpi-card .kpi-title { font-size: 11.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .4px; color: var(--ink-faint); }
        .kpi-card .kpi-value { font-size: 24px; font-weight: 800; margin-top: 3px; color: var(--ink); }
        .kpi-card .kpi-sub { font-size: 12px; margin-top: 7px; color: var(--ink-faint); font-weight: 500; }
        .chip-indigo { background: var(--indigo-soft); color: var(--indigo); }
        .chip-teal   { background: var(--teal-soft); color: var(--teal); }
        .chip-amber  { background: var(--amber-soft); color: var(--amber); }
        .chip-rose   { background: var(--rose-soft); color: var(--rose); }
        .chip-green  { background: var(--green-soft); color: var(--green); }
        .card { border-radius: 14px !important; border: 1px solid var(--line) !important; box-shadow: var(--shadow-sm) !important; }
        .card-header { background: transparent !important; border-bottom: 1px solid var(--line) !important;
            padding: 16px 20px !important; display: flex; align-items: center; gap: 10px; }
        .card-header .card-title { font-size: 14.5px !important; font-weight: 700 !important; color: var(--ink); margin: 0; }
        .card-header .hdr-icon { width: 28px; height: 28px; border-radius: 8px; display: inline-flex;
            align-items: center; justify-content: center; flex-shrink: 0; }
        .card-header .hdr-icon .material-icons-outlined { font-size: 16px; }
        .card-body { padding: 18px 20px !important; }
        .mis-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .mis-table th { background: var(--canvas); font-weight: 700; font-size: 11.5px; text-transform: uppercase;
            letter-spacing: .3px; color: var(--ink-soft); padding: 10px 14px; text-align: left; }
        .mis-table th:first-child { border-radius: 8px 0 0 8px; }
        .mis-table th:last-child { border-radius: 0 8px 8px 0; }
        .mis-table td { padding: 10px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; color: var(--ink); }
        .mis-table tbody tr:last-child td { border-bottom: none; }
        .mis-table tbody tr:hover td { background: var(--indigo-soft); }
        .badge-rev { background: var(--green-soft); color: var(--green); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .badge-due { background: var(--rose-soft); color: var(--rose); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .badge-qty { background: var(--indigo-soft); color: var(--indigo); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .status-badge { padding: 4px 11px; border-radius: 20px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .badge-paid    { background: var(--green-soft); color: var(--green); }
        .badge-partial { background: var(--amber-soft); color: var(--amber); }
        .badge-unpaid  { background: var(--rose-soft); color: var(--rose); }
        .badge-none    { background: var(--canvas); color: var(--ink-faint); }
        .progress-bar-mis { height: 7px; border-radius: 6px; background: var(--canvas); overflow: hidden; min-width: 70px; }
        .progress-fill { height: 100%; border-radius: 6px; }
        .empty-state { text-align: center; padding: 36px 20px; color: var(--ink-faint); }
        .empty-state .material-icons-outlined { font-size: 32px; opacity: .4; display: block; margin: 0 auto 8px; }
        .view-btn { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; border-radius: 8px;
            background: var(--indigo-soft); color: var(--indigo); font-size: 12px; font-weight: 700; text-decoration: none; }
        .view-btn:hover { background: var(--indigo); color: #fff; text-decoration: none; }
        .view-btn .material-icons-outlined { font-size: 14px; }
        @media(max-width: 768px) { .kpi-card .kpi-value { font-size: 19px; } .mis-page-title { font-size: 18px; } }
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

                    <div class="mis-page-title">
                        <span class="icon-chip"><i class="material-icons-outlined">storefront</i></span>
                        Shop Report — Sales &amp; Payments
                    </div>
                    <div class="mis-page-sub">Invoice and payment detail for every shop, at a glance.</div>

                    <!-- ── FILTER BAR ──────────────────────────────────────── -->
                    <div class="mis-filter-bar">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                            <div>
                                <label>From</label>
                                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $from; ?>" style="width:150px;">
                            </div>
                            <div>
                                <label>To</label>
                                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $to; ?>" style="width:150px;">
                            </div>
                            <div>
                                <label>Payment Status</label>
                                <select name="status_filter" class="form-control form-control-sm" style="width:160px;">
                                    <option value="all"            <?php echo $statusFilter==='all'?'selected':''; ?>>All</option>
                                    <option value="fully_paid"     <?php echo $statusFilter==='fully_paid'?'selected':''; ?>>Fully Paid</option>
                                    <option value="partially_paid" <?php echo $statusFilter==='partially_paid'?'selected':''; ?>>Partially Paid</option>
                                    <option value="not_paid"       <?php echo $statusFilter==='not_paid'?'selected':''; ?>>Not Paid</option>
                                </select>
                            </div>
                            <div>
                                <label>Search Shop</label>
                                <input type="text" name="q" class="form-control form-control-sm" placeholder="Name / mobile / ID" value="<?php echo htmlspecialchars($searchTerm); ?>" style="width:190px;">
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">search</i> Apply
                                </button>
                            </div>
                            <div style="margin-left:auto;align-self:flex-end;display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="?preset=today" class="preset-btn <?php echo $preset==='today'?'active':''; ?>">Today</a>
                                <a href="?preset=week"  class="preset-btn <?php echo $preset==='week'?'active':''; ?>">This Week</a>
                                <a href="?preset=month" class="preset-btn <?php echo $preset==='month'?'active':''; ?>">This Month</a>
                                <a href="?preset=year"  class="preset-btn <?php echo $preset==='year'?'active':''; ?>">This Year</a>
                                <a href="?preset=all"   class="preset-btn <?php echo $preset==='all'?'active':''; ?>">All Time</a>
                            </div>
                        </form>
                    </div>

                    <!-- ── SUMMARY KPIs ─────────────────────────────────────── -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-indigo"><i class="material-icons-outlined">storefront</i></span>
                                <div class="kpi-title">Shops with Sales</div>
                                <div class="kpi-value"><?php echo $shops_with_sales; ?></div>
                                <div class="kpi-sub">of <?php echo count($shops); ?> shown</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-teal"><i class="material-icons-outlined">receipt_long</i></span>
                                <div class="kpi-title">Total Invoices</div>
                                <div class="kpi-value"><?php echo inr_format($grand_invoices, 0); ?></div>
                                <div class="kpi-sub">in selected range</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-green"><i class="material-icons-outlined">payments</i></span>
                                <div class="kpi-title">Total Received</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($grand_received, 0); ?></div>
                                <div class="kpi-sub">of &#x20B9;<?php echo inr_format($grand_billed, 0); ?> billed</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-rose"><i class="material-icons-outlined">hourglass_bottom</i></span>
                                <div class="kpi-title">Total Due</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($grand_due, 0); ?></div>
                                <div class="kpi-sub">outstanding</div>
                            </div>
                        </div>
                    </div>

                    <!-- ── SHOP TABLE ───────────────────────────────────────── -->
                    <div class="row">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <span class="hdr-icon chip-indigo"><i class="material-icons-outlined">storefront</i></span>
                                    <h5 class="card-title">Shop-wise Sales &amp; Payment Summary</h5>
                                    <a href="shop-report-export.php?<?php echo $exportQuery; ?>" class="view-btn" style="margin-left:auto;background:var(--green-soft);color:var(--green);">
                                        <i class="material-icons-outlined">file_download</i> Export Excel
                                    </a>
                                </div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($shops)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">storefront</i>No shops match this filter.</div>
                                    <?php else: ?>
                                    <table id="shopReportTable" class="mis-table" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Shop</th>
                                                <th>Mobile</th>
                                                <th>Category</th>
                                                <th>Invoices</th>
                                                <th>Billed</th>
                                                <th>Received</th>
                                                <th>Due</th>
                                                <th>Status</th>
                                                <th>Detail</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($shops as $s):
                                            $pct = $s['billed'] > 0 ? min(round($s['received'] / $s['billed'] * 100, 1), 100) : 0;
                                            $bar_c = $pct >= 100 ? 'var(--green)' : ($pct > 0 ? 'var(--amber)' : 'var(--rose)');
                                        ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars(ucwords($s['name'])); ?></b>
                                                    <div style="font-size:11px;color:var(--ink-faint);">ID: <?php echo htmlspecialchars($s['useridtext']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($s['country_code']); ?> <?php echo htmlspecialchars($s['mobile_number']); ?></td>
                                                <td><?php echo htmlspecialchars($s['catlable'] ?? '—'); ?></td>
                                                <td><span class="badge-qty"><?php echo (int)$s['inv_count']; ?></span></td>
                                                <td>&#x20B9;<?php echo inr_format($s['billed'], 2); ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($s['received'], 2); ?></span></td>
                                                <td><?php if ($s['due'] > 0): ?><span class="badge-due">&#x20B9;<?php echo inr_format($s['due'], 2); ?></span><?php else: ?>—<?php endif; ?></td>
                                                <td>
                                                    <?php if ($s['status'] === 'fully_paid'): ?>
                                                        <span class="status-badge badge-paid">Fully Paid</span>
                                                    <?php elseif ($s['status'] === 'partially_paid'): ?>
                                                        <span class="status-badge badge-partial">Partial</span>
                                                    <?php elseif ($s['status'] === 'not_paid'): ?>
                                                        <span class="status-badge badge-unpaid">Not Paid</span>
                                                    <?php else: ?>
                                                        <span class="status-badge badge-none">No Invoices</span>
                                                    <?php endif; ?>
                                                    <div class="progress-bar-mis mt-1"><div class="progress-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $bar_c; ?>"></div></div>
                                                </td>
                                                <td>
                                                    <a class="view-btn" href="shop-report-detail.php?shop=<?php echo base64_encode($s['id']); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">
                                                        <i class="material-icons-outlined">visibility</i> View
                                                    </a>
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

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
$(function(){
    $('#shopReportTable').DataTable({
        order: [[4, 'desc']],
        pageLength: 25,
        language: { emptyTable: 'No data found' }
    });
});
</script>
</body>
</html>
