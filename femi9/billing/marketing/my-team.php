<?php
include("checksession.php");
include("config.php");
require_once("include/TeamLevelColors.php");
require_once("include/TeamSubtree.php");
require_once("include/TeamTargetRollup.php");
error_reporting(0);

$rootId = (int)$markeingSTFID;

$subtreeIds = getMsSubtreeIds($db_conn, $rootId);
$idList = implode(',', $subtreeIds);

$levelColorMap = getTeamLevelColorMap($db_conn);

// ── Assigned district(s) per person, batched ────────────────────────────────
// marketing_staff_locations can point at a STATE/DISTRICT/TALUK/FIRKA node;
// whatever depth it's at, the district it belongs to is what's shown here —
// same resolution rule as AssignedLocations.php's getMsAssignedDistricts(),
// just computed once for the whole subtree instead of per row.
$allLocNodes = [];
$_lr = $db_conn->query("SELECT id, parent_id, depth, name FROM partner_location_nodes WHERE is_active=1");
while ($row = $_lr->fetch_assoc()) {
    $allLocNodes[(int)$row['id']] = [
        'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
        'depth'     => (int)$row['depth'],
        'name'      => $row['name'],
    ];
}
function districtForNode(int $locId, array $allLocNodes): ?string {
    if (!isset($allLocNodes[$locId])) return null;
    $cur = $allLocNodes[$locId];
    if ($cur['depth'] === 2) return null; // STATE-level assignment — no single district to show
    while ($cur !== null && $cur['depth'] !== 3) {
        $cur = $cur['parent_id'] !== null ? ($allLocNodes[$cur['parent_id']] ?? null) : null;
    }
    return $cur['name'] ?? null;
}
$districtsByMs = [];
$_ar = $db_conn->query("SELECT ms_id, location_id FROM marketing_staff_locations WHERE ms_id IN ($idList)");
if ($_ar) {
    while ($row = $_ar->fetch_assoc()) {
        $distName = districtForNode((int)$row['location_id'], $allLocNodes);
        if ($distName !== null) { $districtsByMs[(int)$row['ms_id']][$distName] = true; }
    }
}
foreach ($districtsByMs as $msId => $names) { $districtsByMs[$msId] = implode(', ', array_keys($names)); }

$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$fromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : '';
$toDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) ? $toDate : '';
$hasDateFilter = ($fromDate !== '' && $toDate !== '');
$dateParam = $hasDateFilter ? ('&from_date=' . urlencode($fromDate) . '&to_date=' . urlencode($toDate)) : '';
// manage_order_product.php (this filter's own detail page) uses frdate/todate,
// not from_date/to_date — without this, its "Orders" link silently defaults
// to today only, showing 0s for anyone with no orders on the current day.
$ordersDateParam = $hasDateFilter ? ('&frdate=' . urlencode($fromDate) . '&todate=' . urlencode($toDate)) : '';

function msInitials(string $name): string {
    $words = array_filter(preg_split('/\s+/', trim($name)));
    if (empty($words)) return '?';
    if (count($words) === 1) return mb_strtoupper(mb_substr(reset($words), 0, 1));
    return mb_strtoupper(mb_substr(reset($words), 0, 1) . mb_substr(end($words), 0, 1));
}

$staffRows = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id, ms.team_level_id, tl.level_name, tl.level_rank
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    WHERE ms.id IN ($idList)
    ORDER BY tl.level_rank ASC, ms.ms_name ASC
")->fetch_all(MYSQLI_ASSOC);

$byManager = [];
$selfRow = null;
foreach ($staffRows as $row) {
    if ((int)$row['id'] === $rootId) { $selfRow = $row; }
    $mgrKey = $row['manager_id'] ? (int)$row['manager_id'] : 0;
    $byManager[$mgrKey][] = $row;
}

// ── Raw per-person shop/order counts across the whole team, in one pass ────
$rawStats = [];
foreach ($subtreeIds as $id) { $rawStats[$id] = ['shops' => 0, 'oldshops' => 0, 'got' => 0, 'no' => 0, 'target' => 0.0, 'achieved' => 0.0]; }
$shopDateWhere = $hasDateFilter ? " AND DATE(created_at) BETWEEN '$fromDate' AND '$toDate'" : '';
$orderDateWhere = $hasDateFilter ? " AND order_date BETWEEN '$fromDate' AND '$toDate'" : '';
$_rs = $db_conn->query("SELECT ms_id, COUNT(*) AS cnt FROM ms_shop WHERE ms_id IN ($idList)$shopDateWhere GROUP BY ms_id");
if ($_rs) { while ($r = $_rs->fetch_assoc()) { $rawStats[(int)$r['ms_id']]['shops'] = (int)$r['cnt']; } }
// Old Shop — shops added BEFORE the range that actually got an order WITHIN
// it (existing shops still active this period), not just a headcount.
if ($hasDateFilter) {
    $_rs = $db_conn->query(
        "SELECT o.ms_id, COUNT(DISTINCT o.shop_id) AS cnt
         FROM ms_orders o JOIN ms_shop s ON s.id = o.shop_id
         WHERE o.ms_id IN ($idList) AND o.new_order='yes'
               AND o.order_date BETWEEN '$fromDate' AND '$toDate'
               AND DATE(s.created_at) < '$fromDate'
         GROUP BY o.ms_id"
    );
    if ($_rs) { while ($r = $_rs->fetch_assoc()) { $rawStats[(int)$r['ms_id']]['oldshops'] = (int)$r['cnt']; } }
}
$_rs = $db_conn->query("SELECT ms_id, COUNT(DISTINCT order_id) AS cnt FROM ms_orders WHERE ms_id IN ($idList) AND new_order='yes'$orderDateWhere GROUP BY ms_id");
if ($_rs) { while ($r = $_rs->fetch_assoc()) { $rawStats[(int)$r['ms_id']]['got'] = (int)$r['cnt']; } }
$_rs = $db_conn->query("SELECT ms_id, COUNT(*) AS cnt FROM ms_orders WHERE ms_id IN ($idList) AND new_order='no'$orderDateWhere GROUP BY ms_id");
if ($_rs) { while ($r = $_rs->fetch_assoc()) { $rawStats[(int)$r['ms_id']]['no'] = (int)$r['cnt']; } }

// Target/achieved specifically default to the CURRENT CALENDAR MONTH when no
// filter is picked (monthly_target_amount is inherently a monthly figure) —
// the shop/order counts above keep their existing lifetime-if-unfiltered
// behavior untouched. When a filter IS picked, both use the same range.
$targetFromDate = $hasDateFilter ? $fromDate : date('Y-m-01');
$targetToDate   = $hasDateFilter ? $toDate   : date('Y-m-t');
$targetStats = getRawTargetAchievedStats($db_conn, $subtreeIds, $targetFromDate, $targetToDate);
foreach ($subtreeIds as $id) {
    $rawStats[$id]['target']   = $targetStats[$id]['target']   ?? 0.0;
    $rawStats[$id]['achieved'] = $targetStats[$id]['achieved'] ?? 0.0;
}

// Sum a person's own stats + everyone below them, and collect the id list
// that "View Shop List" should link to.
function subtreeSumAndIds(int $id, array $byManager, array $rawStats): array {
    $sum = $rawStats[$id] ?? ['shops' => 0, 'oldshops' => 0, 'got' => 0, 'no' => 0, 'target' => 0.0, 'achieved' => 0.0];
    $ids = [$id];
    foreach (($byManager[$id] ?? []) as $child) {
        [$childSum, $childIds] = subtreeSumAndIds((int)$child['id'], $byManager, $rawStats);
        $sum['shops']    += $childSum['shops'];
        $sum['oldshops'] += $childSum['oldshops'];
        $sum['got']      += $childSum['got'];
        $sum['no']       += $childSum['no'];
        $sum['target']   += $childSum['target'];
        $sum['achieved'] += $childSum['achieved'];
        $ids = array_merge($ids, $childIds);
    }
    return [$sum, $ids];
}

[$kpiSum, $kpiIds] = subtreeSumAndIds($rootId, $byManager, $rawStats);
$teamCount = count($kpiIds) - 1; // exclude yourself
$kpiTarget   = $kpiSum['target'];
$kpiAchieved = $kpiSum['achieved'];
$kpiPercent  = $kpiTarget > 0 ? min(100, ($kpiAchieved / $kpiTarget) * 100) : 0;

// Team completion — how many of root's DIRECT reports have themselves hit
// 100% of THEIR OWN team's target (works the same whether root is an SM
// counting ASM-team completions, or an ASM counting DM completions).
$directReports = $byManager[$rootId] ?? [];
$completedCount = 0;
foreach ($directReports as $dr) {
    [$drSum] = subtreeSumAndIds((int)$dr['id'], $byManager, $rawStats);
    if ($drSum['target'] > 0 && ($drSum['achieved'] / $drSum['target']) * 100 >= 100) {
        $completedCount++;
    }
}
$directReportCount = count($directReports);
$kpiShops    = $kpiSum['shops'];
$kpiOldShops = $kpiSum['oldshops'];
$kpiGot      = $kpiSum['got'];
$kpiNo       = $kpiSum['no'];

function renderStatRow(array $row, array $byManager, array $rawStats, array $levelColorMap, int $rank = 0, bool $indent = false, string $dateParam = '', array $districtsByMs = [], string $ordersDateParam = ''): string {
    $id = (int)$row['id'];
    $color = $levelColorMap[(int)$row['team_level_id']] ?? '#999999';
    [$sum, $ids] = subtreeSumAndIds($id, $byManager, $rawStats);
    $idsParam = implode(',', $ids);
    $district = $districtsByMs[$id] ?? '';
    // SM/ASM rows don't carry their own location assignment (only individual
    // DMs do) — show the union of districts their whole team covers instead
    // of a blank dash.
    if ($district === '' && count($ids) > 1) {
        $teamDistricts = [];
        foreach ($ids as $memberId) {
            if (empty($districtsByMs[$memberId])) { continue; }
            foreach (explode(', ', $districtsByMs[$memberId]) as $d) { $teamDistricts[$d] = true; }
        }
        $district = implode(', ', array_keys($teamDistricts));
    }

    $html = '<tr' . ($indent ? '' : ' style="background:#f8fafc;"') . '>';
    $html .= '<td>' . ($rank > 0 ? $rank : '') . '</td>';
    $html .= '<td>' . ($indent ? '&#8618;&nbsp;' : '') . ($indent ? '' : '<b>') . htmlspecialchars($row['ms_name']) . ($indent ? '' : '</b>') . '</td>';
    $html .= '<td><span class="tp-tag" style="color:' . $color . ';background:' . $color . '1a;">' . htmlspecialchars($row['level_name'] ?: '-') . '</span></td>';
    $html .= '<td style="max-width:220px;">' . ($district !== '' ? '<span style="display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom;" title="' . htmlspecialchars($district) . '">' . htmlspecialchars($district) . '</span>' : '<span class="text-muted" style="font-size:11px;">&mdash;</span>') . '</td>';
    $html .= '<td><span class="stat-pill">' . $sum['shops'] . '</span></td>';
    $html .= '<td><span class="stat-pill">' . $sum['oldshops'] . '</span></td>';
    $html .= '<td><span class="stat-pill">' . $sum['got'] . '</span></td>';
    $html .= '<td><span class="stat-pill">' . $sum['no'] . '</span></td>';
    $html .= '<td>';
    $html .= '<div class="actions-cell">';
    if ($sum['shops'] > 0) {
        $html .= '<a class="btn-view-shop-list" target="_blank" rel="noopener" href="ms-team-shops.php?ms_ids=' . htmlspecialchars($idsParam) . htmlspecialchars($dateParam) . '">Shop List</a>';
    } else {
        $html .= '<span class="text-muted" style="font-size:11px;">&mdash;</span>';
    }
    // A manager's "Orders" link must aggregate their whole subtree — same ids
    // used for the Get Order count in this row and for Shop List — otherwise
    // the KPI number (team total) and the linked detail page (one person's
    // own orders only) silently disagree for anyone above the leaf level.
    $ordersHref = (count($ids) > 1)
        ? 'manage_order_product.php?view_ms_ids=' . htmlspecialchars($idsParam) . htmlspecialchars($ordersDateParam)
        : 'manage_order_product.php?view_ms_id=' . $id . htmlspecialchars($ordersDateParam);
    $html .= '<a class="btn-view-shop-list" target="_blank" rel="noopener" href="' . $ordersHref . '">Orders</a>';
    // "Total Orders" — every get-order this person/team placed, TP-assigned
    // or not, with the assigned TP's name and their invoice status per row.
    // The "Orders" link above shows the same rows already (this page never
    // restricted by tp_id), just without those two extra columns.
    $totalOrdersHref = $ordersHref . (strpos($ordersHref, '?') !== false ? '&' : '?') . 'view=all';
    $html .= '<a class="btn-view-shop-list" target="_blank" rel="noopener" style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);" href="' . $totalOrdersHref . '">Total Orders</a>';
    $html .= '</div>';
    $html .= '</td>';
    $html .= '</tr>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Team : <?php echo $business_name; ?></title>
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
        .kpi-card .kpi-ico {
            width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center;
            background: var(--kpi-tint, #eef1fd); color: var(--kpi-accent, #667eea); font-size:16px;
            position:absolute; right:14px; top:14px;
        }
        .kpi-card .kpi-t { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight:600; color: #6b7280; padding-right:38px; }
        .kpi-card .kpi-v { font-size: 24px; font-weight: 700; margin-top: 6px; color: #111827; }

        .stat-pill { font-size:13px; font-weight:600; color:#374151; }
        .btn-view-shop-list {
            font-size:12px; font-weight:600; color:#fff; white-space:nowrap;
            background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            padding:5px 12px; border-radius:6px; text-decoration:none; display:inline-block;
        }
        .btn-view-shop-list:hover { color:#fff; opacity:.9; }
        .actions-cell { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }

        .tp-tag { font-size:11px; padding:2px 8px; border-radius:6px; font-weight:600; white-space:nowrap; }

        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th {
            background:#f7f7f6; font-weight:600; color:#52514e; padding:9px 12px; text-align:left;
            border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px;
        }
        .mt td { padding:9px 12px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
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
                                        <tr><td>My Team</td></tr>
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
                                    <?php if ($hasDateFilter): ?>
                                        <a href="my-team.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($selfRow && $selfRow['team_level_id']): ?>
                        <div class="row mb-3">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="kpi-card">
                                    <i class="material-icons-outlined kpi-ico">groups</i>
                                    <div class="kpi-t">Team Size</div>
                                    <div class="kpi-v"><?php echo (int)$teamCount; ?></div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="kpi-card">
                                    <i class="material-icons-outlined kpi-ico">storefront</i>
                                    <div class="kpi-t"><?php echo $hasDateFilter ? 'New Shop' : 'Total Shops'; ?></div>
                                    <div class="kpi-v"><?php echo (int)$kpiShops; ?></div>
                                    <?php if ($hasDateFilter): ?><div class="kpi-sub" style="font-size:11px;color:#6b7280;margin-top:4px;">Added in this date range</div><?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="kpi-card">
                                    <i class="material-icons-outlined kpi-ico">store</i>
                                    <div class="kpi-t">Old Shop</div>
                                    <div class="kpi-v"><?php echo (int)$kpiOldShops; ?></div>
                                    <?php if ($hasDateFilter): ?><div class="kpi-sub" style="font-size:11px;color:#6b7280;margin-top:4px;">Existing shops that got an order in this range</div><?php else: ?><div class="kpi-sub" style="font-size:11px;color:#6b7280;margin-top:4px;">Apply a date filter to see this</div><?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="kpi-card">
                                    <i class="material-icons-outlined kpi-ico">check_circle</i>
                                    <div class="kpi-t">Get Order</div>
                                    <div class="kpi-v"><?php echo (int)$kpiGot; ?></div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="kpi-card">
                                    <i class="material-icons-outlined kpi-ico">cancel</i>
                                    <div class="kpi-t">No Order</div>
                                    <div class="kpi-v"><?php echo (int)$kpiNo; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col">
                                <p class="text-muted" style="font-size:12px;margin-bottom:6px;">
                                    Team Target &amp; Achievement &mdash; <?php echo $hasDateFilter ? (date('d M Y', strtotime($fromDate)) . ' to ' . date('d M Y', strtotime($toDate))) : ('This Month (' . date('d M Y', strtotime($targetFromDate)) . ' to ' . date('d M Y', strtotime($targetToDate)) . ')'); ?>
                                </p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="kpi-card">
                                    <i class="material-icons-outlined kpi-ico">flag</i>
                                    <div class="kpi-t">Team Target</div>
                                    <div class="kpi-v">&#8377;<?php echo inr_format($kpiTarget, 0); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="kpi-card">
                                    <i class="material-icons-outlined kpi-ico">trending_up</i>
                                    <div class="kpi-t">Achieved</div>
                                    <div class="kpi-v">&#8377;<?php echo inr_format($kpiAchieved, 0); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="kpi-card">
                                    <i class="material-icons-outlined kpi-ico">percent</i>
                                    <div class="kpi-t">% Achieved</div>
                                    <div class="kpi-v" style="<?php echo $kpiPercent >= 100 ? 'color:#0ca30c;' : ''; ?>"><?php echo round($kpiPercent, 1); ?>%</div>
                                </div>
                            </div>
                            <?php if ($directReportCount > 0): ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="kpi-card">
                                    <i class="material-icons-outlined kpi-ico">military_tech</i>
                                    <div class="kpi-t">Teams Hit Target</div>
                                    <div class="kpi-v"><?php echo (int)$completedCount; ?> / <?php echo (int)$directReportCount; ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$selfRow || !$selfRow['team_level_id']): ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-info">No team level has been assigned to you yet.</div>
                            </div>
                        </div>
                    <?php elseif (empty($byManager[$rootId])): ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-info">No one reports to you yet.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:auto;">
                                        <table class="mt">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Name</th>
                                                    <th>Level</th>
                                                    <th>District</th>
                                                    <th>Total Shops</th>
                                                    <th>Old Shop</th>
                                                    <th>Get Order</th>
                                                    <th>No Order</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php $rank = 0; foreach ($byManager[$rootId] as $asm):
                                                $asmId = (int)$asm['id'];
                                                $rank++;
                                                echo renderStatRow($asm, $byManager, $rawStats, $levelColorMap, $rank, false, $dateParam, $districtsByMs, $ordersDateParam);
                                                $dmList = $byManager[$asmId] ?? [];
                                                foreach ($dmList as $dm): echo renderStatRow($dm, $byManager, $rawStats, $levelColorMap, 0, true, $dateParam, $districtsByMs, $ordersDateParam); endforeach;
                                            endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
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
