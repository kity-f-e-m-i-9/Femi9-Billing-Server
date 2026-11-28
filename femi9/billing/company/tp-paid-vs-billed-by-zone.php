<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("include/PartnerZones.php");
error_reporting(0);
include("config.php");

ensurePartnerZonesTables($db_conn);

$filter_from = $_GET['from_date'] ?? '2026-06-01';
$filter_to   = $_GET['to_date']   ?? '2026-11-30';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_from)) $filter_from = '2026-06-01';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_to))   $filter_to   = '2026-11-30';

function tpZonePaidBilledRow(mysqli $db_conn, int $tpId, string $from, string $to): array {
    $tp = $db_conn->query("SELECT name, mobile, COALESCE(NULLIF(assigned_district,''), branch_district) AS district FROM territory_partners WHERE id = $tpId")->fetch_assoc();

    $paid = (float)($db_conn->query("
        SELECT COALESCE(SUM(amount), 0) AS amt FROM tp_advance_payments
        WHERE territory_partner_id = $tpId AND deleted_at IS NULL
          AND payment_date BETWEEN '$from' AND '$to'
    ")->fetch_assoc()['amt']);

    $billed = (float)($db_conn->query("
        SELECT COALESCE(SUM(total_amount), 0) AS amt FROM tp_invoices
        WHERE territory_partner_id = $tpId AND invoice_date BETWEEN '$from' AND '$to'
    ")->fetch_assoc()['amt']);

    // Balance = this TP's own remaining/unused advance wallet balance for the
    // same range (matches manage-tp-advance-payments.php's "Total Balance"
    // card), not "Billed - Paid".
    $balance = (float)($db_conn->query("
        SELECT COALESCE(SUM(balance_amount), 0) AS amt FROM tp_advance_payments
        WHERE territory_partner_id = $tpId AND deleted_at IS NULL
          AND payment_date BETWEEN '$from' AND '$to'
    ")->fetch_assoc()['amt']);

    return [
        'name'     => $tp['name'] ?? ('TP #' . $tpId),
        'mobile'   => $tp['mobile'] ?? '',
        'district' => $tp['district'] ?? '',
        'paid'     => $paid,
        'billed'   => $billed,
        'balance'  => $balance,
    ];
}

$zones = getAllZonesWithCounts($db_conn);
$zoneSections = [];
$seenTpIds = [];
foreach ($zones as $z) {
    $tpIds = getZoneTpIds($db_conn, (int)$z['id'], false);
    if (empty($tpIds)) continue;
    $rows = [];
    foreach ($tpIds as $tpId) {
        $seenTpIds[(int)$tpId] = true;
        $rows[] = tpZonePaidBilledRow($db_conn, (int)$tpId, $filter_from, $filter_to);
    }
    usort($rows, fn($a, $b) => strcmp($a['name'], $b['name']));
    $zoneSections[] = ['name' => $z['name'], 'rows' => $rows];
}

// Active TPs not covered by any zone — own section, so nobody is silently dropped.
$allActive = $db_conn->query("SELECT id FROM territory_partners WHERE is_active = 1 AND deleted_at IS NULL")->fetch_all(MYSQLI_ASSOC);
$unmappedRows = [];
foreach ($allActive as $r) {
    $id = (int)$r['id'];
    if (!isset($seenTpIds[$id])) { $unmappedRows[] = tpZonePaidBilledRow($db_conn, $id, $filter_from, $filter_to); }
}
if (!empty($unmappedRows)) {
    usort($unmappedRows, fn($a, $b) => strcmp($a['name'], $b['name']));
    $zoneSections[] = ['name' => 'No Zone Assigned', 'rows' => $unmappedRows];
}

$paidHeaderLabel = date('M', strtotime($filter_from)) . ' to ' . date('M', strtotime($filter_to)) . ' Paid Amount';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Paid vs Billed by Zone : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        .filter-card { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:5px; }
        .filter-card .form-control { background:rgba(255,255,255,0.95); border:none; border-radius:6px; }
        .zone-card { background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.06); margin-bottom:22px; overflow:hidden; }
        .zone-card-header { background:#eef0ff; color:#374151; font-weight:700; font-size:15px; padding:12px 18px; }
        .zone-table { width:100%; border-collapse:collapse; }
        .zone-table th, .zone-table td { padding:8px 12px; border:1px solid #e5e7eb; font-size:13px; }
        .zone-table th { background:#f9fafb; font-weight:600; text-align:center; }
        .zone-table td.num { text-align:right; }
        .zone-table tr.total-row td { background:#d9ead3; font-weight:700; }
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

                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1><table class="headertble"><tr><td>TP Paid vs Billed by Zone</td></tr></table></h1>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="filter-card">
                                    <form method="GET" action="">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-3">
                                                <label class="form-label">From Date</label>
                                                <input type="date" name="from_date" class="form-control" value="<?=htmlspecialchars($filter_from)?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">To Date</label>
                                                <input type="date" name="to_date" class="form-control" value="<?=htmlspecialchars($filter_to)?>">
                                            </div>
                                            <div class="col-auto">
                                                <button type="submit" class="btn btn-light font-weight-bold">
                                                    <i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> Filter
                                                </button>
                                            </div>
                                            <div class="col-auto">
                                                <a href="export-tp-paid-vs-billed-by-zone.php?from=<?=urlencode($filter_from)?>&to=<?=urlencode($filter_to)?>" class="btn btn-success font-weight-bold">
                                                    <i class="material-icons" style="vertical-align:middle;font-size:17px;">download</i> Export Excel
                                                </a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($zoneSections)): ?>
                        <div class="alert alert-info">No zones or territory partners found.</div>
                        <?php endif; ?>

                        <?php foreach ($zoneSections as $section): ?>
                        <?php
                            $totalPaid = array_sum(array_column($section['rows'], 'paid'));
                            $totalBilled = array_sum(array_column($section['rows'], 'billed'));
                            $totalBalance = array_sum(array_column($section['rows'], 'balance'));
                        ?>
                        <div class="zone-card">
                            <div class="zone-card-header"><?=htmlspecialchars($section['name'])?></div>
                            <div style="overflow-x:auto;">
                                <table class="zone-table">
                                    <thead>
                                        <tr>
                                            <th>TP Name</th>
                                            <th>Number</th>
                                            <th>District</th>
                                            <th><?=htmlspecialchars($paidHeaderLabel)?></th>
                                            <th>Billed Amount</th>
                                            <th>Balance Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($section['rows'] as $row): ?>
                                        <tr>
                                            <td><?=htmlspecialchars($row['name'])?></td>
                                            <td><?=htmlspecialchars($row['mobile'])?></td>
                                            <td><?=htmlspecialchars($row['district'])?></td>
                                            <td class="num"><?=number_format($row['paid'], 2)?></td>
                                            <td class="num"><?=number_format($row['billed'], 2)?></td>
                                            <td class="num"><?=number_format($row['balance'], 2)?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="total-row">
                                            <td colspan="3">Total</td>
                                            <td class="num"><?=number_format($totalPaid, 2)?></td>
                                            <td class="num"><?=number_format($totalBilled, 2)?></td>
                                            <td class="num"><?=number_format($totalBalance, 2)?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
