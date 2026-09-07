<?php
include("checksession.php"); // sets $result_LoGuserDtails from sales_bdm_staff (existing pattern)
include("config.php");
require_once __DIR__ . '/../includes/EspoDb.php';
require_once __DIR__ . '/../includes/EspoMetrics.php';
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-01');
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-t');

$myEspoId = $result_LoGuserDtails['espo_user_id'] ?? null;
$notLinked = empty($myEspoId);

$funnel = $trend = $wonLost = [];
$avgCycle = 0;
$calls = ['planned' => 0, 'held' => 0, 'not_held' => 0, 'overdue' => 0, 'upcoming' => 0];
$callsPerConv = 0.0;
$crmUnavailable = false;
$leadsAssigned = 0;

if (!$notLinked) {
    $espoConn = getEspoDbConnection();
    $crmUnavailable = ($espoConn === null);
    if (!$crmUnavailable) {
        $funnel = espoFunnelSnapshot($espoConn, $myEspoId, $from, $to);
        $trend = espoConversionTrend($espoConn, $myEspoId, $from, $to, 'monthly');
        $wonLost = espoWonLostSplit($espoConn, $myEspoId, $from, $to);
        $avgCycle = espoAvgSalesCycleDays($espoConn, $myEspoId, $from, $to);
        $calls = espoCallActivity($espoConn, $myEspoId, $from, $to);
        $callsPerConv = espoCallsPerConversion($espoConn, $myEspoId, $from, $to);
        $leadsAssigned = $funnel['leads_assigned'];
        $espoConn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>My CRM Dashboard : <?php echo $business_name; ?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">

    <style>
        /* Same design system as the company-login CRM dashboard
           (femi9/billing/company/dashboard-crm.php) — scoped to the
           content area, not `body`, so it can't affect the sidebar. */
        .app-content .container-fluid { background:#f5f5f2; margin:-12px -20px; padding:24px 20px; min-height:calc(100vh - 60px); }

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
        .kpi-card { flex:1 1 200px; background:#fff; border:1px solid rgba(11,11,11,.08); box-shadow:0 1px 2px rgba(20,20,10,.04); border-radius:12px; padding:18px 20px; display:flex; align-items:center; gap:14px; transition:transform .15s, box-shadow .15s; }
        .kpi-card:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(20,20,10,.08); }
        .kpi-ic { width:44px; height:44px; min-width:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; }
        .kpi-ic i { font-size:22px; }
        .kpi-ic.blue   { background:#eaf0ff; color:#4f6cf7; }
        .kpi-ic.green  { background:#e9f9f0; color:#1fa971; }
        .kpi-ic.amber  { background:#fff4e5; color:#e08a1f; }
        .kpi-ic.purple { background:#f3eeff; color:#7b5cf0; }
        .kpi-t { font-size:11px; text-transform:uppercase; letter-spacing:.5px; font-weight:700; color:#8a887f; }
        .kpi-v { font-size:22px; font-weight:800; margin-top:3px; color:#161512; line-height:1.15; }

        .section-title { font-size:15px; font-weight:800; color:#1c1b18; margin:28px 0 14px; display:flex; align-items:center; gap:8px; }
        .section-title i { font-size:19px; color:#7b5cf0; }

        .data-card { background:#fff; border:1px solid rgba(11,11,11,.08); border-radius:12px; box-shadow:0 1px 2px rgba(20,20,10,.03); overflow:hidden; margin-bottom:22px; }

        .neat-table { width:100%; border-collapse:collapse; font-size:13px; margin:0; }
        .neat-table th { background:#fafaf7; font-weight:700; color:#8a887f; padding:10px 18px; text-align:left; font-size:10.5px; text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid #eeede6; white-space:nowrap; }
        .neat-table td { padding:11px 18px; border-bottom:1px solid #f3f2ec; color:#3a3833; vertical-align:middle; }
        .neat-table tr:last-child td { border-bottom:none; }
        .neat-table tbody tr:hover td { background:#fafaf7; }
        .neat-table td.num, .neat-table th.num { text-align:right; }
        .neat-table .rate-cell { font-weight:700; color:#7b5cf0; }

        .empty-state { text-align:center; padding:60px 20px; color:#a8a69d; }
        .empty-state i { font-size:44px; opacity:.5; display:block; margin-bottom:10px; }
        .empty-state .empty-title { font-size:15px; font-weight:700; color:#6b6960; margin-bottom:4px; }
        .empty-state .empty-sub { font-size:12.5px; }

        .crm-down-banner { display:flex; align-items:center; gap:16px; background:#fff8ec; border:1px solid #f4dfa8; border-radius:12px; padding:20px 24px; margin-bottom:22px; }
        .crm-down-banner .ic { width:46px; height:46px; min-width:46px; border-radius:10px; background:#fff1cf; color:#e08a1f; display:flex; align-items:center; justify-content:center; }
        .crm-down-banner .ic i { font-size:24px; }
        .crm-down-banner .title { font-weight:800; font-size:14.5px; color:#8a5a10; margin:0 0 2px; }
        .crm-down-banner .sub { font-size:12.5px; color:#a0763a; margin:0; }

        .not-linked-banner { display:flex; align-items:center; gap:16px; background:#eef2ff; border:1px solid #c9d4f8; border-radius:12px; padding:20px 24px; margin-bottom:22px; }
        .not-linked-banner .ic { width:46px; height:46px; min-width:46px; border-radius:10px; background:#e0e7ff; color:#4f6cf7; display:flex; align-items:center; justify-content:center; }
        .not-linked-banner .ic i { font-size:24px; }
        .not-linked-banner .title { font-weight:800; font-size:14.5px; color:#37418f; margin:0 0 2px; }
        .not-linked-banner .sub { font-size:12.5px; color:#5a67b8; margin:0; }
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
                                <h1><span class="ic-badge"><i class="material-icons-outlined">insights</i></span>My CRM Dashboard</h1>
                                <p class="page-sub">Your own EspoCRM performance — leads, opportunities, and calls.</p>
                            </div>
                        </div>

                        <?php if ($notLinked): ?>
                            <div class="not-linked-banner">
                                <div class="ic"><i class="material-icons-outlined">link_off</i></div>
                                <div>
                                    <p class="title">Your account isn't linked to a CRM user yet</p>
                                    <p class="sub">Ask your admin to link your account to EspoCRM so your metrics can show up here.</p>
                                </div>
                            </div>
                        <?php elseif ($crmUnavailable): ?>
                            <div class="crm-down-banner">
                                <div class="ic"><i class="material-icons-outlined">cloud_off</i></div>
                                <div>
                                    <p class="title">CRM data unavailable</p>
                                    <p class="sub">Could not connect to EspoCRM right now. This does not affect any other part of the billing app — please try again shortly.</p>
                                </div>
                            </div>
                        <?php else: ?>

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
                                    <div class="f-group" style="min-width:0;">
                                        <button type="submit" class="btn-apply">Apply Filters</button>
                                    </div>
                                </form>
                            </div>

                            <div class="period-chip"><i class="material-icons-outlined">event</i><?php echo date('d M Y', strtotime($from)); ?> &ndash; <?php echo date('d M Y', strtotime($to)); ?></div>

                            <div class="kpi-row">
                                <div class="kpi-card">
                                    <div class="kpi-ic blue"><i class="material-icons-outlined">assignment_ind</i></div>
                                    <div><div class="kpi-t">Leads Assigned</div><div class="kpi-v"><?php echo htmlspecialchars($leadsAssigned); ?></div></div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-ic blue"><i class="material-icons-outlined">how_to_reg</i></div>
                                    <div><div class="kpi-t">Leads Converted</div><div class="kpi-v"><?php echo htmlspecialchars($funnel['converted'] ?? 0); ?></div></div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-ic green"><i class="material-icons-outlined">emoji_events</i></div>
                                    <div><div class="kpi-t">Opportunities Won</div><div class="kpi-v"><?php echo htmlspecialchars($wonLost['won'] ?? 0); ?></div></div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-ic amber"><i class="material-icons-outlined">schedule</i></div>
                                    <div><div class="kpi-t">Avg. Sales Cycle</div><div class="kpi-v"><?php echo htmlspecialchars($avgCycle); ?> <span style="font-size:12px;font-weight:600;color:#a8a69d;">days</span></div></div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-ic purple"><i class="material-icons-outlined">call</i></div>
                                    <div><div class="kpi-t">Calls Held</div><div class="kpi-v"><?php echo htmlspecialchars($calls['held'] ?? 0); ?></div></div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-ic blue"><i class="material-icons-outlined">query_stats</i></div>
                                    <div><div class="kpi-t">Calls / Conversion</div><div class="kpi-v"><?php echo htmlspecialchars($callsPerConv); ?></div></div>
                                </div>
                            </div>

                            <div class="section-title"><i class="material-icons-outlined">trending_up</i>Conversion Trend (Monthly)</div>
                            <div class="data-card">
                                <div style="overflow-x:auto;">
                                    <table class="neat-table">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th class="num">Leads Created</th>
                                                <th class="num">Leads Converted</th>
                                                <th class="num">Lead Conv. Rate</th>
                                                <th class="num">Opps Created</th>
                                                <th class="num">Opps Won</th>
                                                <th class="num">Opp Conv. Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($trend)): ?>
                                                <tr><td colspan="7">
                                                    <div class="empty-state">
                                                        <i class="material-icons-outlined">show_chart</i>
                                                        <div class="empty-title">No trend data for this period</div>
                                                        <div class="empty-sub">Try widening the date range above.</div>
                                                    </div>
                                                </td></tr>
                                            <?php else: ?>
                                                <?php foreach ($trend as $period): ?>
                                                    <tr>
                                                        <td style="font-weight:700;"><?php echo htmlspecialchars($period['period']); ?></td>
                                                        <td class="num"><?php echo htmlspecialchars($period['leads_created']); ?></td>
                                                        <td class="num"><?php echo htmlspecialchars($period['leads_converted']); ?></td>
                                                        <td class="num rate-cell"><?php echo htmlspecialchars($period['lead_conversion_rate']); ?>%</td>
                                                        <td class="num"><?php echo htmlspecialchars($period['opps_created']); ?></td>
                                                        <td class="num"><?php echo htmlspecialchars($period['opps_won']); ?></td>
                                                        <td class="num rate-cell"><?php echo htmlspecialchars($period['opp_conversion_rate']); ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor Scripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>

    <!-- Theme Scripts -->
    <script src="../../assets/js/main.min.js"></script>
</body>

</html>
