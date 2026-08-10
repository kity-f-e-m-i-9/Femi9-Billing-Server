<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("include/TeamLevelColors.php");
require_once __DIR__ . '/../salesbdm/include/TeamTargetRollup.php';
error_reporting(0);

$_chkZoneTR = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'zone'");
if ($_chkZoneTR && $_chkZoneTR->num_rows === 0) {
    $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN zone VARCHAR(100) NULL DEFAULT NULL AFTER monthly_target_amount");
}

$levelColorMap = getSalesBdmTeamLevelColorMap($db_conn);

// depth -> level_name lookups, same dual-role detection as the tree view
// (Chief BDM who also personally holds specific Districts shows as
// "Chief BDM + BDM" etc.)
$depthLevelRows = $db_conn->query("
    SELECT l.id, l.level_name, ll.depth FROM salesbdm_team_levels l
    LEFT JOIN partner_location_layers ll ON ll.id = l.location_layer_id
")->fetch_all(MYSQLI_ASSOC);
$depthToLevelName = [];
$levelDepthById = [];
foreach ($depthLevelRows as $dl) {
    $levelDepthById[(int)$dl['id']] = $dl['depth'] !== null ? (int)$dl['depth'] : null;
    if ($dl['depth'] !== null) { $depthToLevelName[(int)$dl['depth']] = $dl['level_name']; }
}
$dualBdmIds = [];
$dualRes = $db_conn->query("SELECT DISTINCT bdm_id FROM salesbdm_locations WHERE is_dual_role = 1");
if ($dualRes) { while ($r = $dualRes->fetch_assoc()) { $dualBdmIds[(int)$r['bdm_id']] = true; } }

function computeDisplayLevel(int $bdmId, int $lvlId, ?string $levelName, array $depthToLevelName, array $levelDepthById, array $dualBdmIds): string {
    $displayLevel = $levelName ?: '-';
    if (isset($dualBdmIds[$bdmId]) && isset($levelDepthById[$lvlId]) && $levelDepthById[$lvlId] !== null) {
        $nextDepth = $levelDepthById[$lvlId] + 1;
        if (isset($depthToLevelName[$nextDepth])) { $displayLevel .= ' + ' . $depthToLevelName[$nextDepth]; }
    }
    return $displayLevel;
}

$staffRows = $db_conn->query("
    SELECT bs.id, bs.bdm_name, bs.manager_id, bs.team_level_id, bs.zone, bs.account_status, tl.level_name, tl.level_rank,
           mgr.bdm_name AS manager_name
    FROM sales_bdm_staff bs
    LEFT JOIN salesbdm_team_levels tl ON tl.id = bs.team_level_id
    LEFT JOIN sales_bdm_staff mgr ON mgr.id = bs.manager_id
    ORDER BY tl.level_rank ASC, bs.bdm_name ASC
")->fetch_all(MYSQLI_ASSOC);

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) $fromDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate))   $toDate   = date('Y-m-d');

$rows = [];
$allStaffMap = [];
foreach ($staffRows as $r) {
    $bid = (int)$r['id'];
    $displayLevel = computeDisplayLevel($bid, (int)$r['team_level_id'], $r['level_name'], $depthToLevelName, $levelDepthById, $dualBdmIds);
    $roll = getBdmRawTargetAchieved($db_conn, $bid, $fromDate, $toDate);
    $pct = $roll['target'] > 0 ? min(round($roll['achieved'] / $roll['target'] * 100, 1), 999) : 0;
    $entry = [
        'id' => $bid, 'name' => $r['bdm_name'], 'level_name' => $displayLevel,
        'zone' => $r['zone'] ?? '', 'manager_name' => $r['manager_name'],
        'status' => $r['account_status'], 'target' => $roll['target'],
        'achieved' => $roll['achieved'], 'tp_count' => $roll['tp_count'], 'pct' => $pct,
        'color' => $levelColorMap[(int)$r['team_level_id']] ?? '#999999',
    ];
    $rows[] = $entry;
    $allStaffMap[$bid] = $entry;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Our Team Report : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        :root { --blue:#2a78d6; --blue-tint:#eaf2fc; --good:#0ca30c; --good-tint:#e5f7e5; --critical:#d03b3b; --critical-tint:#fbe6e6; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:8px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .team-name-cell { cursor:pointer; color:var(--blue); font-weight:600; text-decoration:underline dotted; }
        .pbar { height:7px; border-radius:4px; background:var(--blue-tint); overflow:hidden; }
        .pbar .pf { height:100%; border-radius:4px; background:var(--blue); }
        .mis-filter { background:#fff; border:1px solid rgba(11,11,11,0.10); border-radius:10px; padding:14px 18px; margin-bottom:20px; }
        .lvl-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; color:#fff; white-space:nowrap; }
        .chain-pill { display:inline-block; padding:7px 16px; border-radius:20px; color:#fff; font-size:14px; font-weight:700; }
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
                                <h1><table class="headertble"><tr><td>Our Team Report</td><td><a href="salesbdm-team-view" title="Tree View">&#9776;</a></td></tr></table></h1>
                            </div>
                        </div>
                    </div>

                    <div class="mis-filter">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from" value="<?php echo htmlspecialchars($fromDate); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to" value="<?php echo htmlspecialchars($toDate); ?>" class="form-control form-control-sm">
                            </div>
                            <div><button type="submit" class="btn btn-primary btn-sm">Apply</button></div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header"><h5 class="card-title" style="margin:0;font-size:14px;">All Sales BDM — Target vs Achieved (Napkin only)</h5></div>
                        <div class="card-body" style="overflow-x:auto;">
                            <p class="snote">Click a name for their role/manager, click % for their district/TP breakdown.</p>
                            <table class="mt" id="teamReportTable">
                                <thead>
                                    <tr>
                                        <th>BDM</th>
                                        <th>Level</th>
                                        <th>Reports To</th>
                                        <th>Zone</th>
                                        <th>Status</th>
                                        <th>TPs</th>
                                        <th>Target (&#8377;)</th>
                                        <th>Achieved (&#8377;)</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="9" class="text-muted">No Sales BDM found.</td></tr>
                                <?php else: foreach ($rows as $r):
                                    $bc = $r['pct'] >= 100 ? 'var(--good)' : ($r['pct'] >= 50 ? '#eab308' : 'var(--critical)');
                                ?>
                                    <tr>
                                        <td><span class="team-name-cell" data-bdm-id="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></span></td>
                                        <td><span class="lvl-badge" style="background:<?php echo $r['color']; ?>;"><?php echo htmlspecialchars($r['level_name']); ?></span></td>
                                        <td><?php echo $r['manager_name'] ? htmlspecialchars($r['manager_name']) : '<span class="text-muted">—</span>'; ?></td>
                                        <td><?php echo $r['zone'] ? htmlspecialchars($r['zone']) : '<span class="text-muted">—</span>'; ?></td>
                                        <td><?php echo $r['status'] === 'active' ? '<span style="color:#0ca30c;font-weight:600;">Active</span>' : '<span style="color:#d03b3b;font-weight:600;">Inactive</span>'; ?></td>
                                        <td><?php echo (int)$r['tp_count']; ?></td>
                                        <td>&#8377;<?php echo inr_format($r['target'], 0); ?></td>
                                        <td>&#8377;<?php echo inr_format($r['achieved'], 0); ?></td>
                                        <td>
                                            <span class="team-name-cell" data-bdm-id="<?php echo $r['id']; ?>" style="text-decoration:none;">
                                                <div style="display:flex;align-items:center;gap:5px;">
                                                    <div class="pbar" style="width:70px;"><div class="pf" style="width:<?php echo min($r['pct'],100); ?>%;background:<?php echo $bc; ?>;"></div></div>
                                                    <span style="font-size:12.5px;font-weight:700;color:<?php echo $bc; ?>;"><?php echo $r['pct']; ?>%</span>
                                                </div>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bdmNodeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bdmNodeModalTitle">Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="bdmNodeModalBody"></div>
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
var bdmAllStaff = <?php echo json_encode($allStaffMap); ?>;
var REPORT_FROM = <?php echo json_encode($fromDate); ?>;
var REPORT_TO = <?php echo json_encode($toDate); ?>;

(function ($) {
    $(document).on('click', '.team-name-cell', function () {
        var id = $(this).data('bdm-id');
        var node = bdmAllStaff[id];
        if (!node) return;
        $('#bdmNodeModalTitle').text(node.level_name + ': ' + node.name);
        var $body = $('#bdmNodeModalBody').empty();

        var $head = $('<div style="text-align:center;margin-bottom:14px;"></div>');
        $head.append($('<span class="chain-pill"></span>').css('background', node.color).text(node.level_name));
        if (node.manager_name) {
            $head.append(' <span class="chain-pill" style="background:#eee;color:#333;">Reports to: ' + $('<div>').text(node.manager_name).html() + '</span>');
        }
        if (node.zone) {
            $head.append(' <span class="chain-pill" style="background:#fff8e1;color:#92400e;">Zone: ' + $('<div>').text(node.zone).html() + '</span>');
        }
        $body.append($head);

        var $tpWrap = $('<div><div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;text-align:center;margin-bottom:8px;">Loading district/TP breakdown…</div></div>');
        $body.append($tpWrap);
        $('#bdmNodeModal').modal('show');

        $.getJSON('get-salesbdm-team-member-detail.php', { bdm_id: id, from: REPORT_FROM, to: REPORT_TO }, function (resp) {
            if (resp.error) { $tpWrap.html('<div class="text-muted text-center">Could not load details.</div>'); return; }
            var pct = resp.pct;
            var bc = pct >= 100 ? '#0ca30c' : (pct >= 50 ? '#eab308' : '#d03b3b');
            var html = '<div style="text-align:center;margin-bottom:12px;font-size:13px;">' +
                'Target: <b>₹' + Number(resp.target).toLocaleString('en-IN') + '</b> &nbsp;|&nbsp; ' +
                'Achieved: <b>₹' + Number(resp.achieved).toLocaleString('en-IN') + '</b> &nbsp;|&nbsp; ' +
                '<b style="color:' + bc + ';">' + pct + '%</b></div>';
            if (resp.tps && resp.tps.length) {
                html += '<div style="overflow-x:auto;"><table class="mt"><thead><tr><th>TP</th><th>District</th><th>Target</th><th>Achieved</th><th>%</th></tr></thead><tbody>';
                $.each(resp.tps, function (_, tp) {
                    var tbc = tp.pct >= 100 ? '#0ca30c' : (tp.pct >= 50 ? '#eab308' : '#d03b3b');
                    html += '<tr><td>' + $('<div>').text(tp.name).html() + '</td><td style="font-size:12px;color:#666;">' + $('<div>').text(tp.district || '—').html() + '</td>' +
                        '<td>₹' + Number(tp.target).toLocaleString('en-IN') + '</td><td>₹' + Number(tp.achieved).toLocaleString('en-IN') + '</td>' +
                        '<td style="color:' + tbc + ';font-weight:700;">' + tp.pct + '%</td></tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<div class="text-muted text-center">No TPs assigned.</div>';
            }
            $tpWrap.html(html);
        }).fail(function () { $tpWrap.html('<div class="text-muted text-center">Could not load details.</div>'); });
    });
})(jQuery);
</script>
</body>
</html>
