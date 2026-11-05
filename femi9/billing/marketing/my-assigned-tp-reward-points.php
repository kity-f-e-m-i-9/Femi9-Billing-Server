<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';
require_once __DIR__ . '/../company/include/TpRewardPointsData.php';
error_reporting(0);

function validateDate_matrp(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}
$daysInMonth = (int) date('t');
$defaultFrom = date('Y-m-01');
$defaultTo   = date('Y-m-' . $daysInMonth);
$from_date = validateDate_matrp($_GET['frdate'] ?? null, $defaultFrom);
$to_date   = validateDate_matrp($_GET['todate'] ?? null, $defaultTo);
if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }

$msId = (int)$Login_user_IDvl;

// Same scope resolution as dashboard.php's own district card — District OR
// Division depth nodes directly assigned to this person.
$scopeNodes    = getMsAssignedTargetScopeNodes($db_conn, $msId);
$districtNames = array_column($scopeNodes, 'name');

// Resolved by NODE ID (getMsNodeTpIds), not by name (getMsDistrictTpIds) —
// a Division-scoped assignment (e.g. "Chennai Central B") isn't itself a
// District-depth row, so a name lookup against the District table would
// silently return zero TPs for any DM assigned at Division level. See
// my-assigned-tps.php's own copy of this fix for the full explanation.
$rosterByDistrict = [];
$allTpIds = [];
foreach ($scopeNodes as $node) {
    // includeInactive=true — this roster shows an Active/Inactive status
    // badge per TP, so it needs to include inactive TPs, not silently drop
    // them (same includeInactive=true dashboard.php's own solo-DM roster
    // uses for $allTpIds).
    $tpIds = getMsNodeTpIds($db_conn, [$node['id']], true);
    $rosterByDistrict[$node['name']] = $tpIds;
    $allTpIds = array_merge($allTpIds, $tpIds);
}
$allTpIds = array_values(array_unique($allTpIds));

$tpDetails = [];
if (!empty($allTpIds)) {
    $idList = implode(',', array_map('intval', $allTpIds));
    $res = $db_conn->query("SELECT id, tp_id, name, mobile, is_active FROM territory_partners WHERE id IN ($idList)");
    while ($row = $res->fetch_assoc()) {
        $tpDetails[(int)$row['id']] = $row;
    }
}

// Purchase/Login/Total/Sales points — same math as reward_points_tp.php via
// TpRewardPointsData.php, for the selected date range.
$pointsByTpId = [];
try {
    foreach (getTpRewardPointsData($from_date, $to_date) as $u) {
        $pointsByTpId[(int)$u['user_id']] = $u;
    }
} catch (\Throwable $e) {
    error_log('my-assigned-tp-reward-points: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your TPs' Reward Points : <?php echo $business_name; ?></title>
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
        .district-tp-card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.06); border:none; margin-bottom:20px; }
        .district-tp-card .main-card-header-left { display:flex; align-items:center; gap:10px; }
        .header-icon-box { width:34px; height:34px; border-radius:8px; background:#eef1fd; color:#667eea; display:flex; align-items:center; justify-content:center; }
        .tp-roster-plain { width:100%; border-collapse:collapse; font-size:13px; }
        .tp-roster-plain th { background:#f7f7f6; font-weight:600; color:#52514e; padding:9px 12px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .tp-roster-plain td { padding:9px 12px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .status-badge { font-size:11px; padding:2px 10px; border-radius:12px; font-weight:600; }
        .status-badge.active { background:#dcfce7; color:#059669; }
        .status-badge.inactive { background:#fee2e2; color:#dc2626; }
        .pt-purchase { color:#059669; font-weight:600; }
        .pt-login { color:#2563eb; font-weight:600; }
        .pt-total { color:#4f46e5; font-weight:700; }
        .pt-sales { color:#0ea5e9; font-weight:600; }
        .filter-bar { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 1px 6px rgba(0,0,0,.07); margin-bottom:16px; }
        .mis-pagination { display:flex; align-items:center; justify-content:flex-end; gap:6px; padding:4px 0 20px; flex-wrap:wrap; }
        .mis-pagination button { padding:4px 11px; border-radius:6px; border:1px solid #e5e7eb; background:#fff; color:#374151; font-size:12.5px; font-weight:600; cursor:pointer; }
        .mis-pagination button.active { background:#667eea; border-color:#667eea; color:#fff; }
        .mis-pagination button:disabled { opacity:.4; cursor:default; }
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
                                    <table class="headertble" style="width:100%">
                                        <tr><td>Your TPs' Reward Points</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="filter-bar">
                        <form method="GET">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-1">From Date</label>
                                    <input type="date" name="frdate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from_date, ENT_QUOTES); ?>">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">To Date</label>
                                    <input type="date" name="todate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to_date, ENT_QUOTES); ?>">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="material-icons" style="vertical-align:middle;font-size:16px">search</i> Search</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div id="districtGroupsContainer">
                    <?php if (empty($districtNames)): ?>
                    <div class="main-card district-tp-card">
                        <div style="padding:30px;text-align:center;color:#6b7280;">
                            No district is assigned to you yet.
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($districtNames as $dn): ?>
                        <div class="main-card district-tp-card district-row">
                            <div class="main-card-header">
                                <div class="main-card-header-left">
                                    <div class="header-icon-box"><i class="material-icons-outlined">map</i></div>
                                    <div>
                                        <p class="header-title" style="margin:0;font-weight:600;"><?php echo htmlspecialchars($dn); ?></p>
                                        <p class="header-sub" style="margin:0;color:#6b7280;font-size:12px;"><?php echo count($rosterByDistrict[$dn]); ?> Territory Partner(s)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="padding:0 18px 16px;">
                                <table class="tp-roster-plain">
                                    <thead>
                                        <tr>
                                            <th style="width:48px;text-align:center;">#</th>
                                            <th>Territory Partner</th>
                                            <th>Mobile</th>
                                            <th style="text-align:right;">Purchase Points</th>
                                            <th style="text-align:right;">Login Points</th>
                                            <th style="text-align:right;">Total Points</th>
                                            <th style="text-align:right;">Sales Points</th>
                                            <th style="text-align:center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($rosterByDistrict[$dn])): ?>
                                        <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:20px;">No TP assigned in this district.</td></tr>
                                    <?php else: $i = 0; foreach ($rosterByDistrict[$dn] as $tpId):
                                        $tp = $tpDetails[$tpId] ?? null;
                                        if (!$tp) continue;
                                        $i++;
                                        $pts = $pointsByTpId[$tpId] ?? ['purchase_points' => 0, 'daily_points' => 0, 'total_points' => 0, 'sales_points' => 0];
                                    ?>
                                        <tr>
                                            <td style="text-align:center;color:#9ca3af;"><?php echo $i; ?></td>
                                            <td><b><?php echo htmlspecialchars($tp['name']); ?></b> <span style="color:#9ca3af;font-size:11px;">(<?php echo htmlspecialchars($tp['tp_id']); ?>)</span></td>
                                            <td><?php echo htmlspecialchars($tp['mobile'] ?? ''); ?></td>
                                            <td style="text-align:right;" class="pt-purchase"><?php echo number_format((float)$pts['purchase_points'], 2); ?></td>
                                            <td style="text-align:right;" class="pt-login"><?php echo number_format((float)$pts['daily_points'], 2); ?></td>
                                            <td style="text-align:right;" class="pt-total"><?php echo number_format((float)$pts['total_points'], 2); ?></td>
                                            <td style="text-align:right;" class="pt-sales"><?php echo number_format((float)$pts['sales_points'], 2); ?></td>
                                            <td style="text-align:center;">
                                                <?php if ((int)$tp['is_active'] === 1): ?>
                                                <span class="status-badge active">Active</span>
                                                <?php else: ?>
                                                <span class="status-badge inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>

                    <div id="districtGroupsPagination" class="mis-pagination"></div>

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
// Pagination — 4 district groups per page (same windowed pattern as
// company/district-tp-reward-points-report.php).
(function () {
    var PAGE_SIZE = 4;
    var PAGE_WINDOW = 5;
    var pager = document.getElementById('districtGroupsPagination');
    if (!pager) { return; }
    var currentPage = 1;
    var allRows = Array.prototype.slice.call(document.querySelectorAll('.district-row'));

    function paginateGroups() {
        var totalPages = Math.max(1, Math.ceil(allRows.length / PAGE_SIZE));
        if (currentPage > totalPages) { currentPage = totalPages; }

        allRows.forEach(function (row, idx) {
            var page = Math.floor(idx / PAGE_SIZE) + 1;
            row.style.display = (page === currentPage) ? '' : 'none';
        });

        if (totalPages <= 1) { pager.innerHTML = ''; return; }

        var windowStart = Math.max(1, currentPage - Math.floor(PAGE_WINDOW / 2));
        var windowEnd = Math.min(totalPages, windowStart + PAGE_WINDOW - 1);
        windowStart = Math.max(1, windowEnd - PAGE_WINDOW + 1);

        var html = '';
        html += '<button type="button" id="dgPrev"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>';
        if (windowStart > 1) { html += '<button type="button" class="dg-page-btn" data-page="1">1</button>'; if (windowStart > 2) { html += '<span style="padding:0 4px;color:#9ca3af;">…</span>'; } }
        for (var p = windowStart; p <= windowEnd; p++) {
            html += '<button type="button" class="dg-page-btn ' + (p === currentPage ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        if (windowEnd < totalPages) { if (windowEnd < totalPages - 1) { html += '<span style="padding:0 4px;color:#9ca3af;">…</span>'; } html += '<button type="button" class="dg-page-btn" data-page="' + totalPages + '">' + totalPages + '</button>'; }
        html += '<button type="button" id="dgNext"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
        pager.innerHTML = html;

        var prevBtn = document.getElementById('dgPrev');
        if (prevBtn) prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; paginateGroups(); } });
        var nextBtn = document.getElementById('dgNext');
        if (nextBtn) nextBtn.addEventListener('click', function () { if (currentPage < totalPages) { currentPage++; paginateGroups(); } });
        pager.querySelectorAll('.dg-page-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { currentPage = parseInt(this.getAttribute('data-page'), 10); paginateGroups(); });
        });
    }

    paginateGroups();
})();
</script>
</body>
</html>
