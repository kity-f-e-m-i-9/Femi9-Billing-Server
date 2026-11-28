<?php
// TP Target Achievers Report — user-requested: per calendar month, list
// every Territory Partner whose Shop Invoice amount for that month is >=
// their own monthly target amount (target = SUM(target_amount) across every
// Firka assigned to them, same definition tp-bonus-points-calculator.php and
// company/include/TpRewardPointsData.php already use — NOT date-scoped,
// since a TP's assigned-area target doesn't change month to month).
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
error_reporting(0);

function validateMonth_taa(?string $m, string $default): string {
    if (empty($m)) return $default;
    return preg_match('/^\d{4}-\d{2}$/', $m) ? $m : $default;
}

$from_month = validateMonth_taa($_GET['from_month'] ?? null, date('Y-m'));
$to_month   = validateMonth_taa($_GET['to_month'] ?? null, date('Y-m'));
if (strtotime($from_month . '-01') > strtotime($to_month . '-01')) {
    [$from_month, $to_month] = [$to_month, $from_month];
}

// Every calendar month in [$from_month, $to_month], inclusive.
$months = [];
$cursor = strtotime($from_month . '-01');
$endTs  = strtotime($to_month . '-01');
while ($cursor <= $endTs) {
    $months[] = date('Y-m', $cursor);
    $cursor = strtotime('+1 month', $cursor);
}

// TP target amount — fixed, same for every month (see file header comment).
$tpTargets = []; // tp_id => ['name','tp_code','mobile','district_names','target_amount']
$res = $db_conn->query("
    SELECT tp.id, tp.tp_id AS tp_code, tp.name, tp.mobile,
           GROUP_CONCAT(DISTINCT district.name ORDER BY district.name SEPARATOR ', ') AS district_names,
           COALESCE(SUM(leaf.target_amount), 0) AS target_amount
    FROM territory_partners tp
    LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
    LEFT JOIN partner_location_nodes leaf     ON leaf.id = tpl.location_id
    LEFT JOIN partner_location_nodes taluk    ON taluk.id = leaf.parent_id
    LEFT JOIN partner_location_nodes division ON division.id = taluk.parent_id
    LEFT JOIN partner_location_nodes district ON district.id = division.parent_id
    WHERE tp.is_active = 1 AND tp.deleted_at IS NULL
    GROUP BY tp.id
    HAVING target_amount > 0
");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $tpTargets[(int)$r['id']] = [
            'name'            => $r['name'],
            'tp_code'         => $r['tp_code'],
            'mobile'          => $r['mobile'],
            'district_names'  => $r['district_names'] ?: '',
            'target_amount'   => (float)$r['target_amount'],
        ];
    }
}

// Shop Invoice amount per TP, per calendar month in range — one query.
function tpShopInvoiceByMonth(mysqli $db_conn, string $monthStart, string $monthEnd): array {
    $stmt = $db_conn->prepare("
        SELECT ui.from_user_id AS tp_id, COALESCE(SUM(ui.total), 0) AS achieved
        FROM user_invoice ui
        WHERE ui.from_user_type = 'territory_partner' AND ui.to_user_type = 'shop'
          AND ui.sub_total > 0 AND ui.`date` BETWEEN ? AND ?
        GROUP BY ui.from_user_id
    ");
    $stmt->bind_param('ss', $monthStart, $monthEnd);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $out = [];
    foreach ($rows as $r) { $out[(int)$r['tp_id']] = (float)$r['achieved']; }
    return $out;
}

// Shop-wise split of one TP's Shop Invoice amount for one month —
// user-requested ("shop wise ahh also pakkanum") so an achiever's total can
// be drilled down to exactly which shops contributed how much.
function tpShopWiseBreakdown(mysqli $db_conn, int $tpId, string $monthStart, string $monthEnd): array {
    $stmt = $db_conn->prepare("
        SELECT COALESCE(s.name, '(unknown shop)') AS shop_name, COALESCE(s.mobile_number, '') AS shop_mobile,
               COUNT(*) AS invoice_count, SUM(ui.total) AS total_amount
        FROM user_invoice ui
        LEFT JOIN shop s ON s.temp_id = ui.to_user_id
        WHERE ui.from_user_type = 'territory_partner' AND ui.from_user_id = ? AND ui.to_user_type = 'shop'
          AND ui.sub_total > 0 AND ui.`date` BETWEEN ? AND ?
        GROUP BY ui.to_user_id
        ORDER BY total_amount DESC
    ");
    $stmt->bind_param('iss', $tpId, $monthStart, $monthEnd);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$monthSections = [];
foreach ($months as $ym) {
    $monthStart = $ym . '-01';
    $monthEnd   = date('Y-m-t', strtotime($monthStart));
    $achievedByTp = tpShopInvoiceByMonth($db_conn, $monthStart, $monthEnd);

    $rows = [];
    foreach ($tpTargets as $tpId => $t) {
        $achieved = $achievedByTp[$tpId] ?? 0.0;
        if ($achieved >= $t['target_amount']) {
            $rows[] = array_merge($t, [
                'tp_id'    => $tpId,
                'achieved' => $achieved,
                'pct'      => round($achieved / $t['target_amount'] * 100, 1),
                'shops'    => tpShopWiseBreakdown($db_conn, $tpId, $monthStart, $monthEnd),
            ]);
        }
    }
    usort($rows, fn($a, $b) => $b['achieved'] <=> $a['achieved']);

    $monthSections[] = [
        'ym'    => $ym,
        'label' => date('F Y', strtotime($monthStart)),
        'rows'  => $rows,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Target Achievers Report : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        .filter-card { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:5px; }
        .filter-card .form-control { background:rgba(255,255,255,0.95); border:none; border-radius:6px; }
        .month-card { background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.06); margin-bottom:22px; overflow:hidden; }
        .month-card-header { background:#eef0ff; color:#374151; font-weight:700; font-size:15px; padding:12px 18px; display:flex; justify-content:space-between; align-items:center; }
        .month-table { width:100%; border-collapse:collapse; }
        .month-table th, .month-table td { padding:8px 12px; border:1px solid #e5e7eb; font-size:13px; }
        .month-table th { background:#f9fafb; font-weight:600; text-align:center; }
        .month-table td.num { text-align:right; }
        .pct-badge { background:#10b981; color:#fff; font-size:11px; padding:2px 8px; border-radius:10px; font-weight:600; }
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
                                    <h1><table class="headertble"><tr><td>TP Target Achievers Report</td></tr></table></h1>
                                </div>
                            </div>
                        </div>
                        <p class="text-muted" style="font-size:12.5px;margin-top:-8px;">
                            Lists every Territory Partner whose Shop Invoice amount for a month is equal to or more than their own monthly target amount. One section per month below; each month is scored independently.
                        </p>

                        <div class="row">
                            <div class="col-12">
                                <div class="filter-card">
                                    <form method="GET" action="">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-3">
                                                <label class="form-label">From Month</label>
                                                <input type="month" name="from_month" class="form-control" value="<?=htmlspecialchars($from_month)?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">To Month</label>
                                                <input type="month" name="to_month" class="form-control" value="<?=htmlspecialchars($to_month)?>">
                                            </div>
                                            <div class="col-auto">
                                                <button type="submit" class="btn btn-light font-weight-bold">
                                                    <i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> Filter
                                                </button>
                                            </div>
                                            <div class="col-auto">
                                                <a href="export-tp-target-achievers-xlsx.php?from_month=<?=urlencode($from_month)?>&to_month=<?=urlencode($to_month)?>" class="btn btn-success font-weight-bold">
                                                    <i class="material-icons" style="vertical-align:middle;font-size:17px;">download</i> Export Excel (one sheet per month)
                                                </a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <?php foreach ($monthSections as $section): ?>
                        <div class="month-card">
                            <div class="month-card-header">
                                <span><?=htmlspecialchars($section['label'])?></span>
                                <span style="font-size:12px;font-weight:600;color:#6b7280;"><?=count($section['rows'])?> TP(s) met/exceeded target</span>
                            </div>
                            <?php if (empty($section['rows'])): ?>
                            <div style="padding:16px;color:#9ca3af;font-size:13px;">No TP met their target this month.</div>
                            <?php else: ?>
                            <div style="overflow-x:auto;">
                                <table class="month-table month-datatable" id="monthTable_<?=htmlspecialchars($section['ym'])?>" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>TP Name</th>
                                            <th>TP Code</th>
                                            <th>Mobile</th>
                                            <th>District(s)</th>
                                            <th>Target Amount</th>
                                            <th>Invoiced (Shop)</th>
                                            <th>% Achieved</th>
                                            <th>Shop-wise</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($section['rows'] as $row): $modalId = 'shopModal_' . $section['ym'] . '_' . $row['tp_id']; ?>
                                        <tr>
                                            <td><?=htmlspecialchars($row['name'])?></td>
                                            <td><?=htmlspecialchars($row['tp_code'])?></td>
                                            <td><?=htmlspecialchars($row['mobile'])?></td>
                                            <td><?=htmlspecialchars($row['district_names'])?></td>
                                            <td class="num"><?=number_format($row['target_amount'], 2)?></td>
                                            <td class="num"><?=number_format($row['achieved'], 2)?></td>
                                            <td class="num"><span class="pct-badge"><?=number_format($row['pct'], 1)?>%</span></td>
                                            <td style="text-align:center;">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?=$modalId?>">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">store</i> View Shops
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php foreach ($section['rows'] as $row): $modalId = 'shopModal_' . $section['ym'] . '_' . $row['tp_id']; ?>
                        <div class="modal fade" id="<?=$modalId?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title" style="font-weight:700;"><?=htmlspecialchars($row['name'])?> (<?=htmlspecialchars($row['tp_code'])?>) &mdash; <?=htmlspecialchars($section['label'])?> &mdash; Shop-wise</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if (empty($row['shops'])): ?>
                                        <p class="text-muted mb-0">No shop invoices found.</p>
                                        <?php else: ?>
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr><th>Shop</th><th>Mobile</th><th>Invoices</th><th class="text-end">Amount</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($row['shops'] as $sh): ?>
                                                <tr>
                                                    <td><?=htmlspecialchars($sh['shop_name'])?></td>
                                                    <td><?=htmlspecialchars($sh['shop_mobile'])?></td>
                                                    <td><?=(int)$sh['invoice_count']?></td>
                                                    <td class="text-end">&#8377;<?=number_format((float)$sh['total_amount'], 2)?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="font-weight:700;">
                                                    <td colspan="3">Total</td>
                                                    <td class="text-end">&#8377;<?=number_format($row['achieved'], 2)?></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(function () {
        // Column header click -> asc/desc sort, per month's own table — the
        // "Shop-wise" button column is excluded from sorting (nothing to
        // compare there, it's just a modal trigger). Default order matches
        // what the server already sorted by (Invoiced, highest first).
        $('.month-datatable').each(function () {
            $(this).DataTable({
                order: [[5, 'desc']],
                columnDefs: [{ targets: 7, orderable: false }],
                paging: false,
                info: false,
                searching: false
            });
        });
    });
    </script>
</body>
</html>
