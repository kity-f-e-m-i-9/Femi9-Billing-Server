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

                        <h3>My CRM Dashboard</h3>

                        <?php if ($notLinked): ?>
                            <div class="alert alert-info">Ask your admin to link your CRM account to see your metrics here.</div>
                        <?php elseif ($crmUnavailable): ?>
                            <div class="alert alert-warning">CRM data unavailable — could not connect to EspoCRM. Please try again shortly.</div>
                        <?php else: ?>
                            <form method="get" class="form-inline mb-3">
                                <label class="mr-2">From <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control mx-2"></label>
                                <label class="mr-2">To <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control mx-2"></label>
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </form>

                            <div class="row">
                                <div class="col-md-3"><div class="card p-3"><h6>Leads Converted</h6><h3><?php echo htmlspecialchars($funnel['converted'] ?? 0); ?></h3></div></div>
                                <div class="col-md-3"><div class="card p-3"><h6>Opportunities Won</h6><h3><?php echo htmlspecialchars($wonLost['won'] ?? 0); ?></h3></div></div>
                                <div class="col-md-3"><div class="card p-3"><h6>Avg. Sales Cycle (days)</h6><h3><?php echo htmlspecialchars($avgCycle); ?></h3></div></div>
                                <div class="col-md-3"><div class="card p-3"><h6>Calls Held</h6><h3><?php echo htmlspecialchars($calls['held'] ?? 0); ?></h3></div></div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-3"><div class="card p-3"><h6>Calls per Conversion</h6><h3><?php echo htmlspecialchars($callsPerConv); ?></h3></div></div>
                            </div>

                            <h5 class="mt-4">Conversion Trend (Monthly)</h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr><th>Period</th><th>Leads Created</th><th>Leads Converted</th><th>Lead Conv. Rate (%)</th><th>Opps Created</th><th>Opps Won</th><th>Opp Conv. Rate (%)</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($trend)): ?>
                                        <tr><td colspan="7" class="text-muted">No trend data for this period.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($trend as $period): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($period['period']); ?></td>
                                                <td><?php echo htmlspecialchars($period['leads_created']); ?></td>
                                                <td><?php echo htmlspecialchars($period['leads_converted']); ?></td>
                                                <td><?php echo htmlspecialchars($period['lead_conversion_rate']); ?></td>
                                                <td><?php echo htmlspecialchars($period['opps_created']); ?></td>
                                                <td><?php echo htmlspecialchars($period['opps_won']); ?></td>
                                                <td><?php echo htmlspecialchars($period['opp_conversion_rate']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
