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

    $bdms = $db_conn->query("SELECT id, bdm_name, espo_user_id FROM sales_bdm_staff ORDER BY bdm_name");
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

                        <h3>CRM Sales Dashboard &mdash; Whole Team</h3>

                        <form method="get" class="form-inline mb-3">
                            <label class="mr-2">From <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control mx-2"></label>
                            <label class="mr-2">To <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control mx-2"></label>
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </form>

                        <?php if ($crmUnavailable): ?>
                            <div class="alert alert-warning">CRM data unavailable — could not connect to EspoCRM. Please try again shortly.</div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-3"><div class="card p-3"><h6>Leads Converted</h6><h3><?php echo htmlspecialchars($teamFunnel['converted']); ?></h3></div></div>
                                <div class="col-md-3"><div class="card p-3"><h6>Opportunities Won</h6><h3><?php echo htmlspecialchars($teamWonLost['won']); ?></h3></div></div>
                                <div class="col-md-3"><div class="card p-3"><h6>Avg. Sales Cycle (days)</h6><h3><?php echo htmlspecialchars($teamAvgCycle); ?></h3></div></div>
                                <div class="col-md-3"><div class="card p-3"><h6>Calls Held</h6><h3><?php echo htmlspecialchars($teamCalls['held']); ?></h3></div></div>
                            </div>

                            <h5 class="mt-4">Person-wise Breakdown</h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr><th>BDM</th><th>Leads Converted</th><th>Opps Won</th><th>Opps Lost</th><th>Calls Held</th><th>Calls/Conversion</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($repRows as $row): ?>
                                        <?php if (!$row['linked']): ?>
                                            <tr><td><?php echo htmlspecialchars($row['bdm_name']); ?></td><td colspan="5" class="text-muted">Not linked to a CRM user</td></tr>
                                        <?php else: ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['bdm_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['funnel']['converted']); ?></td>
                                                <td><?php echo htmlspecialchars($row['won_lost']['won']); ?></td>
                                                <td><?php echo htmlspecialchars($row['won_lost']['lost']); ?></td>
                                                <td><?php echo htmlspecialchars($row['calls']['held']); ?></td>
                                                <td><?php echo htmlspecialchars($row['calls_per_conv']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
