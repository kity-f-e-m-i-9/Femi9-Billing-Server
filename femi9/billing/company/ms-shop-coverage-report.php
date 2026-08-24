<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once("include/MsShopCoverage.php");
error_reporting(0);

$fromDate = $_GET['from_date'] ?? '';
$toDate   = $_GET['to_date'] ?? '';
$fromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : date('Y-m-01');
$toDate   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)   ? $toDate   : date('Y-m-t');

$data = getMsShopCoverageReport($db_conn, $fromDate, $toDate);
$byId      = $data['byId'];
$byManager = $data['byManager'];
$rawStats  = $data['rawStats'];
$districtsByMs = getMsDistrictMap($db_conn);
$memo = [];

// Distinct hierarchy tiers present (1 = topmost, e.g. Sales Manager), in
// rank order — not hardcoded to "DM"/"ASM"/"SM" text since level names are
// admin-configurable (see manage-marketing-team-levels.php).
$byRank = [];
foreach ($byId as $id => $row) {
    if ($row['level_rank'] !== null) { $byRank[(int)$row['level_rank']][] = $row; }
}
ksort($byRank);

$overallShops   = array_sum(array_column($rawStats, 'shops'));
$overallOrdered = array_sum(array_column($rawStats, 'ordered'));
$overallPercent = $overallShops > 0 ? round(($overallOrdered / $overallShops) * 100, 1) : 0.0;
$overallInvoicedValue = array_sum(array_column($rawStats, 'invoiced_value'));
$overallNewShopOrders = array_sum(array_column($rawStats, 'new_shop_orders'));
$overallNewShopOrderValue = array_sum(array_column($rawStats, 'new_shop_order_value'));

// Staff added directly to shops but with no team level assigned (won't roll
// into any SM/ASM chain) — surfaced separately so their numbers aren't
// silently dropped from the on-screen totals.
$unassigned = [];
foreach ($byId as $id => $row) {
    if ($row['level_rank'] === null && (($rawStats[$id]['shops'] ?? 0) > 0)) {
        $unassigned[] = $row;
    }
}

$exportParams = 'from_date=' . urlencode($fromDate) . '&to_date=' . urlencode($toDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Shop Coverage : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .kpi-card {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 18px 20px; position: relative; overflow: hidden;
            height: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .kpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background: var(--kpi-accent, #667eea); }
        .kpi-card .kpi-t { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight:600; color: #6b7280; }
        .kpi-card .kpi-v { font-size: 24px; font-weight: 700; margin-top: 6px; color: #111827; }

        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th {
            background:#f7f7f6; font-weight:600; color:#52514e; padding:9px 12px; text-align:left;
            border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px;
        }
        .mt td { padding:9px 12px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .cov-pill { font-size:12px; font-weight:700; padding:3px 10px; border-radius:20px; display:inline-block; }
        .section-title { font-size:15px; font-weight:700; color:#111827; margin:26px 0 10px; }
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
                                <h1>
                                    <table class="headertble">
                                        <tr><td>New Shop Coverage</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <form method="get" class="d-flex flex-wrap align-items-end gap-2">
                                <div>
                                    <label class="form-label mb-0" style="font-size:11px;font-weight:600;color:#6b7280;">From Date</label>
                                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fromDate); ?>">
                                </div>
                                <div>
                                    <label class="form-label mb-0" style="font-size:11px;font-weight:600;color:#6b7280;">To Date</label>
                                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($toDate); ?>">
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                    <a href="ms-shop-coverage-export.php?<?php echo $exportParams; ?>" class="btn btn-success btn-sm">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">download</i> Export Excel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col">
                            <p class="text-muted" style="font-size:12px;">
                                New shops added <b><?php echo date('d M Y', strtotime($fromDate)); ?></b> to <b><?php echo date('d M Y', strtotime($toDate)); ?></b>, versus shops (any age) whose <b>first-ever Get Order</b> fell in this same period &mdash; so a shop added earlier that only just got its first order this month is counted too. Rolled up DM &rarr; ASM &rarr; SM.
                            </p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="kpi-card">
                                <div class="kpi-t">New Shops Added</div>
                                <div class="kpi-v"><?php echo (int)$overallShops; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="kpi-card">
                                <div class="kpi-t">Shops With First Order This Period</div>
                                <div class="kpi-v"><?php echo (int)$overallOrdered; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="kpi-card">
                                <div class="kpi-t">Coverage %</div>
                                <div class="kpi-v" style="<?php echo $overallPercent >= 50 ? 'color:#0ca30c;' : ''; ?>"><?php echo $overallPercent; ?>%</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="kpi-card">
                                <div class="kpi-t">Invoiced Value</div>
                                <div class="kpi-v">&#8377;<?php echo inr_format($overallInvoicedValue, 0); ?></div>
                                <div class="kpi-sub" style="font-size:11px;color:#6b7280;margin-top:4px;">Get Orders placed this period that have actually been converted to a TP invoice</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="kpi-card">
                                <div class="kpi-t">Get Order (New Shops)</div>
                                <div class="kpi-v"><?php echo (int)$overallNewShopOrders; ?></div>
                                <div class="kpi-sub" style="font-size:11px;color:#6b7280;margin-top:4px;">Orders placed on shops added this period, within this same period</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="kpi-card">
                                <div class="kpi-t">Get Order Value (New Shops)</div>
                                <div class="kpi-v">&#8377;<?php echo inr_format($overallNewShopOrderValue, 0); ?></div>
                                <div class="kpi-sub" style="font-size:11px;color:#6b7280;margin-top:4px;">Raw ordered value (not yet necessarily invoiced) of the above</div>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($byRank as $rank => $rows): ?>
                        <?php $tierLabel = $rows[0]['level_name'] ?: ('Level ' . $rank); ?>
                        <div class="section-title"><?php echo htmlspecialchars($tierLabel); ?> Wise</div>
                        <div class="card">
                            <div class="card-body">
                                <div style="overflow-x:auto;">
                                <table class="mt">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Reports To</th>
                                            <th>District</th>
                                            <th>New Shops Added</th>
                                            <th>Shops With First Order</th>
                                            <th>Coverage %</th>
                                            <th>Get Order (New Shops)</th>
                                            <th>Get Order Value (New Shops)</th>
                                            <th>Invoiced Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($rows as $row):
                                        $id = (int)$row['id'];
                                        $sum = msCoverageSubtreeSum($id, $byManager, $rawStats, $memo);
                                        $pct = msCoveragePercent($sum);
                                        $mgrId = $row['manager_id'] ? (int)$row['manager_id'] : 0;
                                        $mgrName = $mgrId && isset($byId[$mgrId]) ? $byId[$mgrId]['ms_name'] : '-';
                                        $pillColor = $pct >= 50 ? '#d1fae5;color:#065f46;' : ($sum['shops'] > 0 ? '#fef3c7;color:#92400e;' : '#f3f4f6;color:#6b7280;');
                                        // A manager row (has a team below them) shows the union of
                                        // their whole team's districts instead of their own blank one.
                                        $district = $districtsByMs[$id] ?? '';
                                        if ($district === '' && !empty($byManager[$id])) {
                                            $teamDistricts = [];
                                            $queue = $byManager[$id];
                                            while ($child = array_shift($queue)) {
                                                $cId = (int)$child['id'];
                                                if (!empty($districtsByMs[$cId])) {
                                                    foreach (explode(', ', $districtsByMs[$cId]) as $d) { $teamDistricts[$d] = true; }
                                                }
                                                foreach (($byManager[$cId] ?? []) as $grandchild) { $queue[] = $grandchild; }
                                            }
                                            $district = implode(', ', array_keys($teamDistricts));
                                        }
                                    ?>
                                        <tr>
                                            <td><b><?php echo htmlspecialchars($row['ms_name']); ?></b></td>
                                            <td><?php echo htmlspecialchars($mgrName); ?></td>
                                            <td><?php echo $district !== '' ? htmlspecialchars($district) : '<span class="text-muted">&mdash;</span>'; ?></td>
                                            <td><?php echo (int)$sum['shops']; ?></td>
                                            <td><?php echo (int)$sum['ordered']; ?></td>
                                            <td><span class="cov-pill" style="background:<?php echo $pillColor; ?>"><?php echo $pct; ?>%</span></td>
                                            <td><?php echo (int)$sum['new_shop_orders']; ?></td>
                                            <td>&#8377;<?php echo inr_format($sum['new_shop_order_value'], 0); ?></td>
                                            <td>&#8377;<?php echo inr_format($sum['invoiced_value'], 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($unassigned)): ?>
                        <div class="section-title">Unassigned Staff (no team level)</div>
                        <div class="card">
                            <div class="card-body">
                                <div style="overflow-x:auto;">
                                <table class="mt">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>New Shops Added</th>
                                            <th>Shops With First Order</th>
                                            <th>Coverage %</th>
                                            <th>Get Order (New Shops)</th>
                                            <th>Get Order Value (New Shops)</th>
                                            <th>Invoiced Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($unassigned as $row):
                                        $id = (int)$row['id'];
                                        $sum = $rawStats[$id] ?? ['shops' => 0, 'ordered' => 0, 'invoiced_value' => 0.0, 'new_shop_orders' => 0, 'new_shop_order_value' => 0.0];
                                        $pct = msCoveragePercent($sum);
                                    ?>
                                        <tr>
                                            <td><b><?php echo htmlspecialchars($row['ms_name']); ?></b></td>
                                            <td><?php echo (int)$sum['shops']; ?></td>
                                            <td><?php echo (int)$sum['ordered']; ?></td>
                                            <td><?php echo $pct; ?>%</td>
                                            <td><?php echo (int)$sum['new_shop_orders']; ?></td>
                                            <td>&#8377;<?php echo inr_format($sum['new_shop_order_value'], 0); ?></td>
                                            <td>&#8377;<?php echo inr_format($sum['invoiced_value'], 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

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
