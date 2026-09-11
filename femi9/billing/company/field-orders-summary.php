<?php
// Per-TP summary of Field Orders (Get Order visits) — Pending / Incomplete /
// Completed invoice-status breakdown, plus estimated vs converted value.
// Companion to manage-field-orders.php (the per-visit detail list); this
// page is the roll-up view. Scoped to a Sales BDM's own assigned districts'
// TPs the same way manage-field-orders.php is.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today    = date("Y-m-d");
$from_date = $_REQUEST['frdate'] ?? date("Y-m-d", strtotime("-6 days"));
$to_date   = $_REQUEST['todate'] ?? $today;

$_isBdm = ($Login_user_TYPEvl ?? '') === 'salesbdm';
$_scopedTpIds = null;
if ($_isBdm) {
    require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
    $_scopedTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID, true);
}

$tpWhereParts = [];
$params = [];
$types = '';
if ($_scopedTpIds !== null) {
    if (empty($_scopedTpIds)) { $_scopedTpIds = [0]; }
    $tpWhereParts[] = 'o.tp_id IN (' . implode(',', array_map('intval', $_scopedTpIds)) . ')';
}
$tpWhereParts[] = 'o.order_date BETWEEN ? AND ?';
$params[] = $from_date; $params[] = $to_date;
$types .= 'ss';
$tpWhereSql = implode(' AND ', $tpWhereParts);

$stmt = mysqli_prepare($db_conn,
    "SELECT o.order_id, o.new_order, o.tp_id, o.pr_id, o.qty, o.discount_percentage, o.discount_amount,
            o.invoiced_inv_id, o.voided_at,
            p.outlet_price, ui.total AS inv_total,
            tp.name tp_name, tp.tp_id tp_code
     FROM tp_orders o
     LEFT JOIN products p ON p.id=o.pr_id
     LEFT JOIN user_invoice ui ON ui.inv_id=o.invoiced_inv_id COLLATE utf8mb4_general_ci
     LEFT JOIN territory_partners tp ON tp.id=o.tp_id
     WHERE $tpWhereSql"
);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$visits = [];
foreach ($rows as $r) {
    $oid = $r['order_id'];
    if (!isset($visits[$oid])) {
        $visits[$oid] = [
            'tp_id' => (int)$r['tp_id'], 'tp_name' => $r['tp_name'], 'tp_code' => $r['tp_code'],
            'new_order' => $r['new_order'], 'voided_at' => $r['voided_at'],
            'invoiced_inv_id' => $r['invoiced_inv_id'], 'inv_total' => $r['inv_total'],
            'est_amount' => 0.0,
        ];
    }
    if ($r['new_order'] === 'yes') {
        $gross = (float)$r['qty'] * (float)($r['outlet_price'] ?? 0);
        $gross -= $gross * ((float)($r['discount_percentage'] ?? 0) / 100);
        $gross -= (float)($r['discount_amount'] ?? 0);
        $visits[$oid]['est_amount'] += max(0, $gross);
    }
}

// Same "completed = has a receipt" rule as manage-field-orders.php /
// territory-partner/manage-orders.php.
$invIds = array_values(array_unique(array_filter(array_column($visits, 'invoiced_inv_id'))));
$completedInvIds = [];
if (!empty($invIds)) {
    $placeholders = implode(',', array_fill(0, count($invIds), '?'));
    $typesR = str_repeat('s', count($invIds));
    $stmtR = mysqli_prepare($db_conn, "SELECT DISTINCT inv_id FROM receipt WHERE inv_id IN ($placeholders)");
    mysqli_stmt_bind_param($stmtR, $typesR, ...$invIds);
    mysqli_stmt_execute($stmtR);
    $resR = mysqli_stmt_get_result($stmtR);
    while ($rr = mysqli_fetch_assoc($resR)) { $completedInvIds[$rr['inv_id']] = true; }
    mysqli_stmt_close($stmtR);
}

$tpSummary = [];
$seenInvIdsPerTp = [];
foreach ($visits as $v) {
    $tid = $v['tp_id'];
    if (!isset($tpSummary[$tid])) {
        $tpSummary[$tid] = [
            'name' => $v['tp_name'], 'code' => $v['tp_code'],
            'get_order' => 0, 'pending' => 0, 'incomplete' => 0, 'completed' => 0, 'no_order' => 0,
            'est_amount' => 0.0, 'converted_amount' => 0.0,
        ];
        $seenInvIdsPerTp[$tid] = [];
    }
    if ($v['new_order'] !== 'yes') {
        $tpSummary[$tid]['no_order']++;
        continue;
    }
    if (!empty($v['voided_at'])) { continue; } // cancelled — excluded from every status bucket, same as manage-orders.php

    $tpSummary[$tid]['get_order']++;
    $tpSummary[$tid]['est_amount'] += $v['est_amount'];

    if (empty($v['invoiced_inv_id'])) {
        $tpSummary[$tid]['pending']++;
    } elseif (isset($completedInvIds[$v['invoiced_inv_id']])) {
        $tpSummary[$tid]['completed']++;
        if (!isset($seenInvIdsPerTp[$tid][$v['invoiced_inv_id']])) {
            $seenInvIdsPerTp[$tid][$v['invoiced_inv_id']] = true;
            $tpSummary[$tid]['converted_amount'] += (float)($v['inv_total'] ?? 0);
        }
    } else {
        $tpSummary[$tid]['incomplete']++;
    }
}
uasort($tpSummary, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

$grand = ['get_order'=>0,'pending'=>0,'incomplete'=>0,'completed'=>0,'no_order'=>0,'est_amount'=>0.0,'converted_amount'=>0.0];
foreach ($tpSummary as $s) {
    foreach ($grand as $k => $v) { $grand[$k] += $s[$k]; }
}

// Totals row above is always computed from the FULL set — only the table
// body is paginated, so "Total" never silently reflects just page 1.
$perPage = 15;
$totalTpCount = count($tpSummary);
$totalPages = max(1, (int)ceil($totalTpCount / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$pagedSummary = array_slice($tpSummary, ($page - 1) * $perPage, $perPage, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Orders Summary : <?php echo $business_name;?></title>
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
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:10px 12px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:10px 12px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .mt tfoot td { font-weight:700; background:#f7f7f6; }
        .tp-tag { font-size:11px; padding:3px 9px; border-radius:6px; font-weight:600; white-space:nowrap; display:inline-block; }
        .tp-tag-good     { background:#e5f7e5; color:#0ca30c; }
        .tp-tag-bad      { background:#fbe6e6; color:#d03b3b; }
        .tp-tag-info     { background:#eaf2fc; color:#2a78d6; }
        .tp-tag-neutral  { background:#f0f1f2; color:#52525b; }
        .tp-action-link {
            font-size:12px; font-weight:600; text-decoration:none; padding:6px 13px;
            border-radius:6px; display:inline-block;
        }
        .tp-action-success {
            color:#374151; background:#f3f4f6; border:1px solid #d1d5db;
        }
        .tp-action-success:hover { color:#111827; background:#e9eaec; }
    </style>
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include((($Login_user_TYPEvl ?? '') === 'salesbdm') ? '../salesbdm/logo.php' : 'logo.php'); ?>
            <?php include((($Login_user_TYPEvl ?? '') === 'salesbdm') ? '../salesbdm/femi_menu.php' : 'femi_menu.php'); ?>
        </div>
        <div class="app-container">
            <?php include((($Login_user_TYPEvl ?? '') === 'salesbdm') ? '../salesbdm/app-header.php' : 'app-header.php'); ?>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">

                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>Field Orders Summary <?php if ($_isBdm): ?><small class="text-muted" style="font-size:13px;">(your districts' TPs)</small><?php endif; ?></h1>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <a href="manage-field-orders" class="tp-action-link tp-action-success"><i class="material-icons" style="font-size:14px;vertical-align:middle;">list_alt</i> Back to Field Orders</a>
                        </div>

                        <form method="get" class="row g-2 align-items-end mb-3">
                            <div class="col-auto">
                                <label class="form-label">From Date</label>
                                <input type="date" name="frdate" value="<?=htmlspecialchars($from_date)?>" class="form-control">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">To Date</label>
                                <input type="date" name="todate" value="<?=htmlspecialchars($to_date)?>" class="form-control">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary"><i class="material-icons">search</i> Search</button>
                            </div>
                        </form>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:auto;">
                                        <table class="mt">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>TP</th>
                                                    <th>Get Order</th>
                                                    <th>Pending</th>
                                                    <th>Incomplete</th>
                                                    <th>Completed</th>
                                                    <th>No Order</th>
                                                    <th>Get Order Value (est.)</th>
                                                    <th>Converted Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($tpSummary)): ?>
                                                <tr><td colspan="9" class="text-center text-muted">No field order entries in this date range.</td></tr>
                                                <?php else: $_sno = ($page - 1) * $perPage; foreach ($pagedSummary as $s): $_sno++; ?>
                                                <tr>
                                                    <td><?=$_sno?></td>
                                                    <td>
                                                        <?=htmlspecialchars($s['name'] ?? '-')?>
                                                        <?php if (!empty($s['code'])): ?><br/><span class="text-muted" style="font-size:11px;"><?=htmlspecialchars($s['code'])?></span><?php endif; ?>
                                                    </td>
                                                    <td><span class="tp-tag tp-tag-info"><?=$s['get_order']?></span></td>
                                                    <td><span class="tp-tag tp-tag-neutral"><?=$s['pending']?></span></td>
                                                    <td><span class="tp-tag" style="background:#fef3c7;color:#92400e;"><?=$s['incomplete']?></span></td>
                                                    <td><span class="tp-tag tp-tag-good"><?=$s['completed']?></span></td>
                                                    <td><span class="tp-tag tp-tag-bad"><?=$s['no_order']?></span></td>
                                                    <td>&#8377;<?=number_format($s['est_amount'], 2)?></td>
                                                    <td>&#8377;<?=number_format($s['converted_amount'], 2)?></td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                            <?php if (!empty($tpSummary)): ?>
                                            <tfoot>
                                                <tr>
                                                    <td></td>
                                                    <td>Total</td>
                                                    <td><?=$grand['get_order']?></td>
                                                    <td><?=$grand['pending']?></td>
                                                    <td><?=$grand['incomplete']?></td>
                                                    <td><?=$grand['completed']?></td>
                                                    <td><?=$grand['no_order']?></td>
                                                    <td>&#8377;<?=number_format($grand['est_amount'], 2)?></td>
                                                    <td>&#8377;<?=number_format($grand['converted_amount'], 2)?></td>
                                                </tr>
                                            </tfoot>
                                            <?php endif; ?>
                                        </table>
                                        </div>

                                        <?php if ($totalPages > 1): ?>
                                        <nav class="mt-3">
                                            <ul class="pagination justify-content-center" style="margin-bottom:0;">
                                                <?php
                                                $qs = $_GET;
                                                function _foPageLink($qs, $p) { $qs['page'] = $p; return '?' . http_build_query($qs); }
                                                ?>
                                                <?php if ($page > 1): ?>
                                                <li class="page-item"><a class="page-link" href="<?=htmlspecialchars(_foPageLink($qs, $page - 1))?>">Previous</a></li>
                                                <?php endif; ?>
                                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li class="page-item <?=($i == $page) ? 'active' : ''?>"><a class="page-link" href="<?=htmlspecialchars(_foPageLink($qs, $i))?>"><?=$i?></a></li>
                                                <?php endfor; ?>
                                                <?php if ($page < $totalPages): ?>
                                                <li class="page-item"><a class="page-link" href="<?=htmlspecialchars(_foPageLink($qs, $page + 1))?>">Next</a></li>
                                                <?php endif; ?>
                                            </ul>
                                            <p class="text-center text-muted small mt-2">Showing <?=(($page-1)*$perPage)+1?>–<?=min($page*$perPage, $totalTpCount)?> of <?=$totalTpCount?> TPs</p>
                                        </nav>
                                        <?php endif; ?>

                                        <p class="text-muted" style="font-size:11.5px;margin:10px 0 0;">"Get Order Value" is an estimate (qty &times; outlet price, minus captured discount) — the TP hasn't set a real price until invoicing. "Converted Value" is the actual invoice total, once submitted. Cancelled visits are excluded from every column.</p>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
