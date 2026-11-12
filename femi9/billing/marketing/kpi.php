<?php
// Personal/team KPI page — Target Amount, Achieved (TP Napkin-only invoice
// value, same definition TeamTargetRollup.php already uses everywhere else),
// Balance, the date each person's target got completed, and this calendar
// week's (Sunday-Saturday) prorated target. Works for whoever is logged in:
// an SM sees their ASMs and under each ASM their DMs; a lone DM with no team
// just sees their own row. Reuses the exact Target/Achieved numbers and
// rollup rule (zero a manager's own target once they have a team, roll up
// the children's sum instead) that my-team.php already established.
include("checksession.php");
include("config.php");
require_once("include/TeamLevelColors.php");
require_once("include/TeamSubtree.php");
require_once __DIR__ . '/../company/include/TeamTargetRollup.php';
error_reporting(0);

// Same ?embed=1 pattern as dashboard.php (company/view-ms-kpi.php's own
// read-only "view as" bridge) — hides sidebar/header when embedded in an
// iframe; $_companyBridgeView (set by checksession.php's bridge) drives the
// read-only banner below when opened standalone instead.
$embed = !empty($_GET['embed']);

$rootId = (int)$markeingSTFID;
$subtreeIds = getMsSubtreeIds($db_conn, $rootId);
$idList = implode(',', $subtreeIds);

$levelColorMap = getTeamLevelColorMap($db_conn);

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

// ── Which week to show — any date the user picks (defaults to today); the
// calendar MONTH used for Target/Achieved/proration always follows whichever
// month that date falls in, so picking a date in an earlier/later month
// shows that month's own target, not the current one.
$weekDateParam = $_GET['week_date'] ?? '';
$weekDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekDateParam) ? $weekDateParam : date('Y-m-d');

$monthStart = date('Y-m-01', strtotime($weekDate));
$monthEnd = date('Y-m-t', strtotime($weekDate));
$daysInMonth = (int)date('t', strtotime($weekDate));

$targetStats = getRawTargetAchievedStats($db_conn, $subtreeIds, $monthStart, $monthEnd);
$dailyByMsId = getRawAchievedDailyByMsId($db_conn, $subtreeIds, $monthStart, $monthEnd);

// ── The picked date's week (Sunday-Saturday), clipped to its calendar month
// e.g. if the month started on a Wednesday, the week covering day 1-6 of the
// month is only Wed-Sat — the Sun/Mon/Tue before the month started don't
// belong to any target.
$refDt = new DateTime($weekDate);
$dow = (int)$refDt->format('w'); // 0=Sun..6=Sat
$weekStartDt = (clone $refDt)->modify('-' . $dow . ' days');
$weekEndDt = (clone $weekStartDt)->modify('+6 days');
$monthStartDt = new DateTime($monthStart);
$monthEndDt = new DateTime($monthEnd);
$weekStartClipped = $weekStartDt < $monthStartDt ? $monthStartDt : $weekStartDt;
$weekEndClipped = $weekEndDt > $monthEndDt ? $monthEndDt : $weekEndDt;
$daysInWeekThisMonth = $weekEndClipped >= $weekStartClipped
    ? ((int)floor(($weekEndClipped->getTimestamp() - $weekStartClipped->getTimestamp()) / 86400) + 1)
    : 0;
$weekLabel = $weekStartClipped->format('d M') . ' - ' . $weekEndClipped->format('d M');
$weekStartStr = $weekStartClipped->format('Y-m-d');
$weekEndStr = $weekEndClipped->format('Y-m-d');

$rawStats = [];
foreach ($subtreeIds as $id) {
    $monthly = (float)($targetStats[$id]['target'] ?? 0.0);
    $perDay = $daysInMonth > 0 ? $monthly / $daysInMonth : 0.0;
    $rawStats[$id] = [
        'target'        => $monthly,
        'achieved'      => (float)($targetStats[$id]['achieved'] ?? 0.0),
        'weekly_target' => $perDay * $daysInWeekThisMonth,
    ];
}

// Roll a person's own stats + everyone below them up, same convention as
// my-team.php's subtreeSumAndIds(): once someone has a team, their own raw
// target/weekly_target is dropped in favour of the children's summed total
// (Achieved is always a real performance number, so it's summed either way).
function kpiSubtreeSumAndIds(int $id, array $byManager, array $rawStats): array {
    $sum = $rawStats[$id] ?? ['target' => 0.0, 'achieved' => 0.0, 'weekly_target' => 0.0];
    $children = $byManager[$id] ?? [];
    if (!empty($children)) { $sum['target'] = 0.0; $sum['weekly_target'] = 0.0; }
    $ids = [$id];
    foreach ($children as $child) {
        [$childSum, $childIds] = kpiSubtreeSumAndIds((int)$child['id'], $byManager, $rawStats);
        $sum['target']        += $childSum['target'];
        $sum['achieved']      += $childSum['achieved'];
        $sum['weekly_target'] += $childSum['weekly_target'];
        $ids = array_merge($ids, $childIds);
    }
    return [$sum, $ids];
}

function kpiCombinedDaily(array $ids, array $dailyByMsId): array {
    $combined = [];
    foreach ($ids as $id) {
        foreach (($dailyByMsId[$id] ?? []) as $date => $amt) {
            $combined[$date] = ($combined[$date] ?? 0.0) + $amt;
        }
    }
    return $combined;
}

function kpiCompletedDate(array $daily, float $target): ?string {
    if ($target <= 0) { return null; }
    ksort($daily);
    $running = 0.0;
    foreach ($daily as $date => $amt) {
        $running += $amt;
        if ($running >= $target) { return $date; }
    }
    return null;
}

// Sum of a (date => amount) daily map restricted to [weekStart, weekEnd] —
// the amount a person/team actually achieved during the selected week.
function kpiWeeklyAchieved(array $daily, string $weekStart, string $weekEnd): float {
    $sum = 0.0;
    foreach ($daily as $date => $amt) {
        if ($date >= $weekStart && $date <= $weekEnd) { $sum += $amt; }
    }
    return $sum;
}

function renderKpiRow(array $row, array $byManager, array $rawStats, array $dailyByMsId, array $levelColorMap, int $depth, string $weekStartStr, string $weekEndStr, int $parentId = 0): string {
    $id = (int)$row['id'];
    $color = $levelColorMap[(int)$row['team_level_id']] ?? '#999999';
    [$sum, $ids] = kpiSubtreeSumAndIds($id, $byManager, $rawStats);
    $target = $sum['target'];
    $achieved = $sum['achieved'];
    $balance = max(0, $target - $achieved);
    $weeklyTarget = $sum['weekly_target'];

    $combinedDaily = kpiCombinedDaily($ids, $dailyByMsId);
    $completedDate = $target > 0 ? kpiCompletedDate($combinedDaily, $target) : null;
    $weeklyAchieved = kpiWeeklyAchieved($combinedDaily, $weekStartStr, $weekEndStr);
    $today = date('Y-m-d');

    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
    $isRoot = $depth === 0;

    $html = '<tr data-id="' . $id . '" data-parent="' . $parentId . '"' . ($isRoot ? ' style="background:#f8fafc;"' : '') . '>';
    $html .= '<td class="kpi-name">' . $indent . ($depth > 0 ? '&#8618;&nbsp;' : '') . ($isRoot ? '<b>' : '') . htmlspecialchars($row['ms_name']) . ($isRoot ? '</b>' : '') . '</td>';
    $html .= '<td><span class="tp-tag" style="color:' . $color . ';background:' . $color . '1a;">' . htmlspecialchars($row['level_name'] ?: '-') . '</span></td>';
    $html .= '<td>&#8377;' . inr_format($target, 0) . '</td>';
    $html .= '<td>&#8377;' . inr_format($achieved, 0) . '</td>';
    if ($target > 0 && $achieved >= $target) {
        $html .= '<td><span style="color:#0ca30c;font-weight:600;">&#8377;0 &mdash; Target Achieved</span></td>';
    } else {
        $html .= '<td>&#8377;' . inr_format($balance, 0) . '</td>';
    }
    if ($target <= 0) {
        $html .= '<td><span class="text-muted" style="font-size:11px;">&mdash;</span></td>';
    } elseif ($completedDate === null) {
        $html .= '<td><span style="color:#b45309;font-weight:600;">Not yet</span></td>';
    } else {
        $label = date('d M Y', strtotime($completedDate)) . ($completedDate === $today ? ' (Today)' : '');
        $html .= '<td><span style="color:#166534;font-weight:600;">' . htmlspecialchars($label) . '</span></td>';
    }
    $html .= '<td>&#8377;' . inr_format($weeklyTarget, 0) . '</td>';
    if ($weeklyTarget > 0 && $weeklyAchieved >= $weeklyTarget) {
        $html .= '<td><span style="color:#0ca30c;font-weight:600;">&#8377;' . inr_format($weeklyAchieved, 0) . '</span></td>';
    } else {
        $html .= '<td>&#8377;' . inr_format($weeklyAchieved, 0) . '</td>';
    }
    $html .= '</tr>';

    $children = $byManager[$id] ?? [];
    foreach ($children as $child) {
        $html .= renderKpiRow($child, $byManager, $rawStats, $dailyByMsId, $levelColorMap, $depth + 1, $weekStartStr, $weekEndStr, $id);
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
    <title>KPI : <?php echo $business_name; ?></title>
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
        .tp-tag { font-size:11px; padding:2px 8px; border-radius:6px; font-weight:600; white-space:nowrap; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th {
            background:#f7f7f6; font-weight:600; color:#52514e; padding:9px 12px; text-align:left;
            border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px;
        }
        .mt td { padding:9px 12px; border-bottom:1px solid #e1e0d9; vertical-align:middle; white-space:nowrap; }
    </style>
</head>
<body<?php echo $embed ? ' class="ms-embed"' : ''; ?>>
<div class="app align-content-stretch d-flex flex-wrap">

    <?php if (!$embed): ?>
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <?php endif; ?>

    <div class="app-container">
        <?php if (!$embed) include("app-header.php"); ?>

        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr><td>KPI</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($_companyBridgeView) && !$embed): ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-info" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                                    <span><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">visibility</i> Viewing <b><?php echo htmlspecialchars($result_LoGuserDtails['ms_name'] ?? ''); ?>'s</b> KPI (read-only).</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$selfRow || !$selfRow['team_level_id']): ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-info">No team level has been assigned to you yet.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row mb-2">
                            <div class="col">
                                <p class="text-muted" style="font-size:12px;margin-bottom:6px;">
                                    Target &amp; Achieved for <?php echo date('F Y', strtotime($monthStart)); ?> (<?php echo date('d M', strtotime($monthStart)); ?> - <?php echo date('d M', strtotime($monthEnd)); ?>) &mdash;
                                    Weekly Target/Achieved for the week of <?php echo htmlspecialchars($weekLabel); ?>
                                </p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <form method="get" class="d-flex flex-wrap align-items-end gap-2">
                                    <div>
                                        <label class="form-label mb-0" style="font-size:11px;font-weight:600;color:#6b7280;">Any date in the week</label>
                                        <input type="date" name="week_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($weekDate); ?>">
                                    </div>
                                    <div>
                                        <button type="submit" class="btn btn-primary btn-sm">Show Week</button>
                                        <?php if ($weekDate !== date('Y-m-d')): ?>
                                            <a href="kpi" class="btn btn-outline-secondary btn-sm">This Week</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <input type="text" id="kpiSearchBox" class="form-control form-control-sm" style="max-width:320px;" placeholder="Search by Name...">
                                        </div>
                                        <div style="overflow-x:auto;">
                                        <table class="mt">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Level</th>
                                                    <th>Target Amount</th>
                                                    <th>Achieved (TP Invoice)</th>
                                                    <th>Balance</th>
                                                    <th>Target Completed Date</th>
                                                    <th>Weekly Target</th>
                                                    <th>Weekly Achieved</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php echo renderKpiRow($selfRow, $byManager, $rawStats, $dailyByMsId, $levelColorMap, 0, $weekStartStr, $weekEndStr); ?>
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
<script>
(function () {
    var searchBox = document.getElementById('kpiSearchBox');
    if (!searchBox) return;
    var rows = Array.prototype.slice.call(document.querySelectorAll('.mt tbody tr'));
    var rowById = {};
    rows.forEach(function (tr) { rowById[tr.getAttribute('data-id')] = tr; });

    function showWithAncestors(tr) {
        var cur = tr;
        while (cur) {
            cur.style.display = '';
            var parentId = cur.getAttribute('data-parent');
            cur = (parentId && parentId !== '0') ? rowById[parentId] : null;
        }
    }

    searchBox.addEventListener('input', function () {
        var q = searchBox.value.trim().toLowerCase();
        if (q === '') {
            rows.forEach(function (tr) { tr.style.display = ''; });
            return;
        }
        rows.forEach(function (tr) { tr.style.display = 'none'; });
        rows.forEach(function (tr) {
            var nameCell = tr.querySelector('.kpi-name');
            var nameText = nameCell ? nameCell.textContent.toLowerCase() : '';
            if (nameText.indexOf(q) !== -1) {
                showWithAncestors(tr);
            }
        });
    });
})();
</script>
</body>
</html>
