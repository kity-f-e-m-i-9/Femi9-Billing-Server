<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once __DIR__ . '/../includes/EspoDb.php';
require_once __DIR__ . '/../includes/EspoMetrics.php';
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-01');
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-t');

$espoConn = getEspoDbConnection();
$crmUnavailable = ($espoConn === null);

$teamFunnel = $teamTrend = $teamWonLost = [];
$teamAvgCycle = $teamCalls = $teamCallsPerConv = 0;
$repRows = [];

if (!$crmUnavailable) {
    $teamFunnel = espoFunnelSnapshot($espoConn, null, $from, $to);
    $teamTrend = espoConversionTrend($espoConn, null, $from, $to, 'monthly');
    $teamWonLost = espoWonLostSplit($espoConn, null, $from, $to);
    $teamAvgCycle = espoAvgSalesCycleDays($espoConn, null, $from, $to);
    $teamCalls = espoCallActivity($espoConn, null, $from, $to);
    $teamCallsPerConv = espoCallsPerConversion($espoConn, null, $from, $to);

    $_chkEspo = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'espo_user_id'");
    if ($_chkEspo && $_chkEspo->num_rows === 0) {
        $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN espo_user_id VARCHAR(24) NULL DEFAULT NULL AFTER monthly_target_amount");
    }
    $bdms = $db_conn->query("SELECT id, bdm_name, espo_user_id FROM sales_bdm_staff ORDER BY bdm_name");
    // NOTE: N+1 query pattern — each linked BDM issues ~8 remote queries. Fine for small teams; batch via GROUP BY ... IN (...) if linked BDM count grows past ~10.
    while ($bdm = $bdms->fetch_assoc()) {
        if (empty($bdm['espo_user_id'])) {
            $repRows[] = ['bdm_name' => $bdm['bdm_name'], 'linked' => false];
            continue;
        }
        $eid = $bdm['espo_user_id'];
        $repRows[] = [
            'bdm_name' => $bdm['bdm_name'],
            'linked' => true,
            'funnel' => espoFunnelSnapshot($espoConn, $eid, $from, $to),
            'won_lost' => espoWonLostSplit($espoConn, $eid, $from, $to),
            'calls' => espoCallActivity($espoConn, $eid, $from, $to),
            'calls_per_conv' => espoCallsPerConversion($espoConn, $eid, $from, $to),
        ];
    }
    $espoConn->close();
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
    <title>CRM Sales Dashboard : <?php echo $business_name; ?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">

    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->

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
        .neat-table .rep-name { display:flex; align-items:center; gap:9px; font-weight:700; color:#2b2a26; }
        .neat-table .rep-avatar { width:28px; height:28px; border-radius:8px; background:linear-gradient(135deg,#4f6cf7,#7b5cf0); color:#fff; font-size:11.5px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .neat-table .won-cell { color:#1fa971; font-weight:700; }
        .neat-table .lost-cell { color:#d9534f; font-weight:600; }
        .neat-table .rate-cell { font-weight:700; color:#7b5cf0; }
        .neat-table .not-linked { color:#a8a69d; font-style:italic; }

        .badge-soft { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; }
        .badge-soft.linked { background:#e9f9f0; color:#1fa971; }
        .badge-soft.unlinked { background:#f3f2ec; color:#a8a69d; }

        .empty-state { text-align:center; padding:60px 20px; color:#a8a69d; }
        .empty-state i { font-size:44px; opacity:.5; display:block; margin-bottom:10px; }
        .empty-state .empty-title { font-size:15px; font-weight:700; color:#6b6960; margin-bottom:4px; }
        .empty-state .empty-sub { font-size:12.5px; }

        .crm-down-banner { display:flex; align-items:center; gap:16px; background:#fff8ec; border:1px solid #f4dfa8; border-radius:12px; padding:20px 24px; margin-bottom:22px; }
        .crm-down-banner .ic { width:46px; height:46px; min-width:46px; border-radius:10px; background:#fff1cf; color:#e08a1f; display:flex; align-items:center; justify-content:center; }
        .crm-down-banner .ic i { font-size:24px; }
        .crm-down-banner .title { font-weight:800; font-size:14.5px; color:#8a5a10; margin:0 0 2px; }
        .crm-down-banner .sub { font-size:12.5px; color:#a0763a; margin:0; }
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
                                <h1><span class="ic-badge"><i class="material-icons-outlined">insights</i></span>CRM Sales Dashboard</h1>
                                <p class="page-sub">Whole-team performance from EspoCRM, with a person-wise breakdown below.</p>
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
                                <div class="f-group" style="min-width:0;">
                                    <button type="submit" class="btn-apply">Apply Filters</button>
                                </div>
                            </form>
                        </div>

                        <div class="period-chip"><i class="material-icons-outlined">event</i><?php echo date('d M Y', strtotime($from)); ?> &ndash; <?php echo date('d M Y', strtotime($to)); ?></div>

                        <?php if ($crmUnavailable): ?>
                            <div class="crm-down-banner">
                                <div class="ic"><i class="material-icons-outlined">cloud_off</i></div>
                                <div>
                                    <p class="title">CRM data unavailable</p>
                                    <p class="sub">Could not connect to EspoCRM right now. This does not affect any other part of the billing app — please try again shortly.</p>
                                </div>
                            </div>
                        <?php else: ?>

                            <div class="kpi-row">
                                <div class="kpi-card">
                                    <div class="kpi-ic blue"><i class="material-icons-outlined">how_to_reg</i></div>
                                    <div><div class="kpi-t">Leads Converted</div><div class="kpi-v"><?php echo htmlspecialchars($teamFunnel['converted']); ?></div></div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-ic green"><i class="material-icons-outlined">emoji_events</i></div>
                                    <div><div class="kpi-t">Opportunities Won</div><div class="kpi-v"><?php echo htmlspecialchars($teamWonLost['won']); ?></div></div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-ic amber"><i class="material-icons-outlined">schedule</i></div>
                                    <div><div class="kpi-t">Avg. Sales Cycle</div><div class="kpi-v"><?php echo htmlspecialchars($teamAvgCycle); ?> <span style="font-size:12px;font-weight:600;color:#a8a69d;">days</span></div></div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-ic purple"><i class="material-icons-outlined">call</i></div>
                                    <div><div class="kpi-t">Calls Held</div><div class="kpi-v"><?php echo htmlspecialchars($teamCalls['held']); ?></div></div>
                                </div>
                                <div class="kpi-card">
                                    <div class="kpi-ic blue"><i class="material-icons-outlined">query_stats</i></div>
                                    <div><div class="kpi-t">Calls / Conversion</div><div class="kpi-v"><?php echo htmlspecialchars($teamCallsPerConv); ?></div></div>
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
                                            <?php if (empty($teamTrend)): ?>
                                                <tr><td colspan="7">
                                                    <div class="empty-state">
                                                        <i class="material-icons-outlined">show_chart</i>
                                                        <div class="empty-title">No trend data for this period</div>
                                                        <div class="empty-sub">Try widening the date range above.</div>
                                                    </div>
                                                </td></tr>
                                            <?php else: ?>
                                                <?php foreach ($teamTrend as $period): ?>
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

                            <div class="section-title"><i class="material-icons-outlined">groups</i>Person-wise Breakdown</div>
                            <div class="data-card">
                                <div style="overflow-x:auto;">
                                    <table class="neat-table">
                                        <thead>
                                            <tr>
                                                <th>BDM</th>
                                                <th class="num">Leads Converted</th>
                                                <th class="num">Opps Won</th>
                                                <th class="num">Opps Lost</th>
                                                <th class="num">Calls Held</th>
                                                <th class="num">Calls / Conversion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($repRows)): ?>
                                                <tr><td colspan="6">
                                                    <div class="empty-state">
                                                        <i class="material-icons-outlined">person_search</i>
                                                        <div class="empty-title">No Sales BDM records found</div>
                                                    </div>
                                                </td></tr>
                                            <?php else: ?>
                                                <?php foreach ($repRows as $row): ?>
                                                    <?php
                                                        $initials = '';
                                                        foreach (explode(' ', trim($row['bdm_name'])) as $part) {
                                                            if ($part !== '') { $initials .= strtoupper($part[0]); }
                                                            if (strlen($initials) >= 2) { break; }
                                                        }
                                                    ?>
                                                    <?php if (!$row['linked']): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="rep-name">
                                                                    <div class="rep-avatar" style="background:#e3e1d9;color:#8a887f;"><?php echo htmlspecialchars($initials); ?></div>
                                                                    <?php echo htmlspecialchars($row['bdm_name']); ?>
                                                                </div>
                                                            </td>
                                                            <td colspan="5"><span class="badge-soft unlinked"><i class="material-icons-outlined" style="font-size:13px;">link_off</i>Not linked to a CRM user</span></td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td>
                                                                <div class="rep-name">
                                                                    <div class="rep-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                                                    <?php echo htmlspecialchars($row['bdm_name']); ?>
                                                                </div>
                                                            </td>
                                                            <td class="num"><?php echo htmlspecialchars($row['funnel']['converted']); ?></td>
                                                            <td class="num won-cell"><?php echo htmlspecialchars($row['won_lost']['won']); ?></td>
                                                            <td class="num lost-cell"><?php echo htmlspecialchars($row['won_lost']['lost']); ?></td>
                                                            <td class="num"><?php echo htmlspecialchars($row['calls']['held']); ?></td>
                                                            <td class="num rate-cell"><?php echo htmlspecialchars($row['calls_per_conv']); ?></td>
                                                        </tr>
                                                    <?php endif; ?>
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
    <script src="../../assets/plugins/jquery/jquery-3.4.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>

    <!-- Theme Scripts -->
    <script src="../../assets/js/main.min.js"></script>
</body>

</html>
