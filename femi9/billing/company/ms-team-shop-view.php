<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("include/TeamLevelColors.php");
error_reporting(0);

$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");
$_chkTL = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'team_level_id'");
if ($_chkTL && $_chkTL->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
}
$_chkMgr = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'manager_id'");
if ($_chkMgr && $_chkMgr->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
}

$levelColorMap = getTeamLevelColorMap($db_conn);

$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$fromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : '';
$toDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) ? $toDate : '';
$hasDateFilter = ($fromDate !== '' && $toDate !== '');
$dateParam = $hasDateFilter ? ('&from_date=' . urlencode($fromDate) . '&to_date=' . urlencode($toDate)) : '';
// ms_prorders.php (company-side order view) uses frdate/todate, not from_date/to_date.
$ordersDateParam = $hasDateFilter ? ('&frdate=' . urlencode($fromDate) . '&todate=' . urlencode($toDate)) : '';

function msInitials(string $name): string {
    $words = array_filter(preg_split('/\s+/', trim($name)));
    if (empty($words)) return '?';
    if (count($words) === 1) return mb_strtoupper(mb_substr(reset($words), 0, 1));
    return mb_strtoupper(mb_substr(reset($words), 0, 1) . mb_substr(end($words), 0, 1));
}

// ── Whole company's marketing staff (every level, every SM's downline) ─────
$staffRows = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id, ms.team_level_id, tl.level_name, tl.level_rank
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    ORDER BY tl.level_rank ASC, ms.ms_name ASC
")->fetch_all(MYSQLI_ASSOC);

$byManager = [];
$allIds = [];
foreach ($staffRows as $row) {
    if (!$row['level_rank']) continue;
    $allIds[] = (int)$row['id'];
    $mgrKey = $row['manager_id'] ? (int)$row['manager_id'] : 0;
    $byManager[$mgrKey][] = $row;
}
$smRoots = $byManager[0] ?? [];

// ── Raw per-person shop/order counts, in one pass ───────────────────────────
$rawStats = [];
foreach ($allIds as $id) { $rawStats[$id] = ['shops' => 0, 'oldshops' => 0, 'got' => 0, 'no' => 0]; }
if (!empty($allIds)) {
    $idList = implode(',', $allIds);
    $shopDateWhere = $hasDateFilter ? " AND DATE(created_at) BETWEEN '$fromDate' AND '$toDate'" : '';
    $orderDateWhere = $hasDateFilter ? " AND order_date BETWEEN '$fromDate' AND '$toDate'" : '';
    $_rs = $db_conn->query("SELECT ms_id, COUNT(*) AS cnt FROM ms_shop WHERE ms_id IN ($idList)$shopDateWhere GROUP BY ms_id");
    if ($_rs) { while ($r = $_rs->fetch_assoc()) { $rawStats[(int)$r['ms_id']]['shops'] = (int)$r['cnt']; } }
    // Old Shop — shops that were already added BEFORE the selected range (not
    // new) but actually placed an order (got order) WITHIN the range — i.e.
    // existing shops still active in this period, not just a historical
    // headcount. Not meaningful without a date range, so left at 0 without one.
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
}

function subtreeSumAndIds(int $id, array $byManager, array $rawStats): array {
    $sum = $rawStats[$id] ?? ['shops' => 0, 'oldshops' => 0, 'got' => 0, 'no' => 0];
    $ids = [$id];
    foreach (($byManager[$id] ?? []) as $child) {
        [$childSum, $childIds] = subtreeSumAndIds((int)$child['id'], $byManager, $rawStats);
        $sum['shops']    += $childSum['shops'];
        $sum['oldshops'] += $childSum['oldshops'];
        $sum['got']      += $childSum['got'];
        $sum['no']       += $childSum['no'];
        $ids = array_merge($ids, $childIds);
    }
    return [$sum, $ids];
}

// ── Company-wide KPI totals ─────────────────────────────────────────────────
$kpiShops = 0; $kpiOldShops = 0; $kpiGot = 0; $kpiNo = 0;
foreach ($smRoots as $sm) {
    [$sum] = subtreeSumAndIds((int)$sm['id'], $byManager, $rawStats);
    $kpiShops    += $sum['shops'];
    $kpiOldShops += $sum['oldshops'];
    $kpiGot      += $sum['got'];
    $kpiNo       += $sum['no'];
}
$teamCount = count($allIds);

function renderStatRow(array $row, array $byManager, array $rawStats, array $levelColorMap, int $rank = 0, int $indentLevel = 0, string $dateParam = '', string $ordersDateParam = ''): string {
    $id = (int)$row['id'];
    $color = $levelColorMap[(int)$row['team_level_id']] ?? '#999999';
    [$sum, $ids] = subtreeSumAndIds($id, $byManager, $rawStats);
    $idsParam = implode(',', $ids);
    $indentPx = $indentLevel * 24;

    $html = '<tr' . ($indentLevel === 0 ? ' style="background:#f8fafc;"' : '') . '>';
    $html .= '<td>' . ($rank > 0 ? $rank : '') . '</td>';
    $html .= '<td style="padding-left:' . (12 + $indentPx) . 'px;">' . ($indentLevel > 0 ? '&#8618;&nbsp;' : '') . ($indentLevel === 0 ? '<b>' : '') . htmlspecialchars($row['ms_name']) . ($indentLevel === 0 ? '</b>' : '') . '</td>';
    $html .= '<td><span class="tp-tag" style="color:' . $color . ';background:' . $color . '1a;">' . htmlspecialchars($row['level_name'] ?: '-') . '</span></td>';
    $html .= '<td><span class="stat-pill">' . $sum['shops'] . '</span></td>';
    $html .= '<td><span class="stat-pill">' . $sum['oldshops'] . '</span></td>';
    $html .= '<td><span class="stat-pill">' . $sum['got'] . '</span></td>';
    $html .= '<td><span class="stat-pill">' . $sum['no'] . '</span></td>';
    $html .= '<td><span class="stat-pill">' . ($sum['got'] + $sum['no']) . '</span></td>';
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
        ? 'ms_prorders.php?se_msids=' . htmlspecialchars($idsParam) . htmlspecialchars($ordersDateParam)
        : 'ms_prorders.php?se_msid=' . $id . htmlspecialchars($ordersDateParam);
    $html .= '<a class="btn-view-shop-list" target="_blank" rel="noopener" href="' . $ordersHref . '">Orders</a>';
    // "Total Get Order" — every get-order this person/team placed, TP-assigned
    // or not, with the assigned TP's name and their invoice status per row.
    // The "Orders" link above only ever shows the subset still needing
    // company follow-up (no TP has picked it up yet).
    $totalGetOrderHref = $ordersHref . (strpos($ordersHref, '?') !== false ? '&' : '?') . 'view=all';
    $html .= '<a class="btn-view-shop-list" target="_blank" rel="noopener" style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);" href="' . $totalGetOrderHref . '">Total Orders</a>';
    $html .= '</div>';
    $html .= '</td>';
    $html .= '</tr>';
    return $html;
}

// Recursively render a person's row, then their direct reports' rows.
function renderRowsRecursive(array $row, array $byManager, array $rawStats, array $levelColorMap, int $indentLevel, int $rank = 0, string $dateParam = '', string $ordersDateParam = ''): string {
    $html = renderStatRow($row, $byManager, $rawStats, $levelColorMap, $rank, $indentLevel, $dateParam, $ordersDateParam);
    $kids = $byManager[(int)$row['id']] ?? [];
    foreach ($kids as $kid) {
        $html .= renderRowsRecursive($kid, $byManager, $rawStats, $levelColorMap, $indentLevel + 1, 0, $dateParam, $ordersDateParam);
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marketing Team - Shop View : <?php echo $business_name; ?></title>
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

        .tp-tag { font-size:11px; padding:2px 8px; border-radius:6px; font-weight:600; white-space:nowrap; }
        .stat-pill { font-size:13px; font-weight:600; color:#374151; }
        .btn-view-shop-list {
            font-size:12px; font-weight:600; color:#fff; white-space:nowrap;
            background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            padding:5px 12px; border-radius:6px; text-decoration:none; display:inline-block;
        }
        .btn-view-shop-list:hover { color:#fff; opacity:.9; }
        .actions-cell { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }

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
                                        <tr><td>Marketing Team - Shop View</td></tr>
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
                                        <a href="ms-team-shop-view.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="kpi-card">
                                <i class="material-icons-outlined kpi-ico">groups</i>
                                <div class="kpi-t">Total Team</div>
                                <div class="kpi-v"><?php echo (int)$teamCount; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="kpi-card">
                                <i class="material-icons-outlined kpi-ico">storefront</i>
                                <div class="kpi-t">New Shop</div>
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
                                <i class="material-icons-outlined kpi-ico">assignment_turned_in</i>
                                <div class="kpi-t">Total Order Shop</div>
                                <div class="kpi-v"><?php echo (int)($kpiGot + $kpiNo); ?></div>
                                <div class="kpi-sub" style="font-size:11px;color:#6b7280;margin-top:4px;"><?php echo (int)$kpiGot; ?> Get Order + <?php echo (int)$kpiNo; ?> No Order</div>
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

                    <?php if (empty($smRoots)): ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-info">No marketing staff with a team level yet.</div>
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
                                                    <th>New Shop</th>
                                                    <th>Old Shop</th>
                                                    <th>Get Order</th>
                                                    <th>No Order</th>
                                                    <th>Total Order Shop</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php $rank = 0; foreach ($smRoots as $sm):
                                                $rank++;
                                                echo renderRowsRecursive($sm, $byManager, $rawStats, $levelColorMap, 0, $rank, $dateParam, $ordersDateParam);
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
