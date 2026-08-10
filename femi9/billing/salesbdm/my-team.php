<?php
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");
require_once("include/db-connect.php");
error_reporting(0);

// This page only makes sense for a BDM who actually has reports — sidebar
// already hides the link otherwise, but guard the page itself too.
$directReports = getBdmDirectReports($db_conn, (int)$salesBdmID);
if (empty($directReports)) {
    header('Location: dashboard.php');
    exit;
}

$subtreeIds = getBdmSubtreeIds($db_conn, (int)$salesBdmID);

require_once __DIR__ . '/../company/include/TeamLevelColors.php';
$levelColorMap = getSalesBdmTeamLevelColorMap($db_conn);

$depthLevelRows = $db_conn->query("
    SELECT l.id, l.level_name, ll.depth
    FROM salesbdm_team_levels l
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

function bdmInitials(string $name): string {
    $words = array_filter(preg_split('/\s+/', trim($name)));
    if (empty($words)) return '?';
    if (count($words) === 1) return mb_strtoupper(mb_substr(reset($words), 0, 1));
    return mb_strtoupper(mb_substr(reset($words), 0, 1) . mb_substr(end($words), 0, 1));
}
function computeDisplayLevel(int $bdmId, int $lvlId, ?string $levelName, array $depthToLevelName, array $levelDepthById, array $dualBdmIds): string {
    $displayLevel = $levelName ?: '-';
    if (isset($dualBdmIds[$bdmId]) && isset($levelDepthById[$lvlId]) && $levelDepthById[$lvlId] !== null) {
        $nextDepth = $levelDepthById[$lvlId] + 1;
        if (isset($depthToLevelName[$nextDepth])) { $displayLevel .= ' + ' . $depthToLevelName[$nextDepth]; }
    }
    return $displayLevel;
}
function toBdmEntry(array $row, array $levelColorMap, array $depthToLevelName, array $levelDepthById, array $dualBdmIds): array {
    $lvlId = (int)$row['team_level_id'];
    $bdmId = (int)$row['id'];
    return [
        'id'         => $bdmId,
        'name'       => $row['bdm_name'],
        'level_name' => computeDisplayLevel($bdmId, $lvlId, $row['level_name'], $depthToLevelName, $levelDepthById, $dualBdmIds),
        'color'      => $levelColorMap[$lvlId] ?? '#999999',
        'initials'   => bdmInitials($row['bdm_name']),
        'zone'       => $row['zone'] ?? '',
    ];
}

// ── Tree data, scoped to just this BDM's own subtree ────────────────────────
$idList = implode(',', array_map('intval', $subtreeIds));
$staffRows = $db_conn->query("
    SELECT bs.id, bs.bdm_name, bs.manager_id, bs.team_level_id, bs.zone, tl.level_name, tl.level_rank
    FROM sales_bdm_staff bs
    LEFT JOIN salesbdm_team_levels tl ON tl.id = bs.team_level_id
    WHERE bs.id IN ($idList)
    ORDER BY tl.level_rank ASC, bs.bdm_name ASC
")->fetch_all(MYSQLI_ASSOC);

$byManager = [];
$rootRow = null;
foreach ($staffRows as $row) {
    $mgrKey = $row['manager_id'] ? (int)$row['manager_id'] : 0;
    $byManager[$mgrKey][] = $row;
    if ((int)$row['id'] === (int)$salesBdmID) { $rootRow = $row; }
}
$childrenMap = [];
foreach ($byManager as $mgrId => $children) {
    if ($mgrId === 0) continue;
    $childrenMap[$mgrId] = array_map(fn($c) => toBdmEntry($c, $levelColorMap, $depthToLevelName, $levelDepthById, $dualBdmIds), $children);
}
$locationsByBdm = [];
$locRes = $db_conn->query("
    SELECT sl.bdm_id, pn.name FROM salesbdm_locations sl JOIN partner_location_nodes pn ON pn.id = sl.location_id
    WHERE sl.bdm_id IN ($idList) ORDER BY pn.name ASC
");
if ($locRes) { while ($r = $locRes->fetch_assoc()) { $locationsByBdm[(int)$r['bdm_id']][] = $r['name']; } }

$allStaffMap = [];
foreach ($staffRows as $row) {
    $entry = toBdmEntry($row, $levelColorMap, $depthToLevelName, $levelDepthById, $dualBdmIds);
    $entry['manager_id'] = $row['manager_id'] ? (int)$row['manager_id'] : 0;
    $entry['locations']  = $locationsByBdm[(int)$row['id']] ?? [];
    $allStaffMap[(int)$row['id']] = $entry;
}

function renderBdmCluster(array $root, array $byManager, array $levelColorMap, array $depthToLevelName, array $levelDepthById, array $dualBdmIds): string {
    $rootId = (int)$root['id'];
    $rootColor = $levelColorMap[(int)$root['team_level_id']] ?? '#6b4226';
    $branchList = $byManager[$rootId] ?? [];
    $rootDisplayLevel = computeDisplayLevel($rootId, (int)$root['team_level_id'], $root['level_name'], $depthToLevelName, $levelDepthById, $dualBdmIds);

    $html = '<div class="sm-cluster" style="--trunk-color:' . $rootColor . ';">';
    $html .= '<div class="sm-trunk tree-node-clickable org-node-clickable" style="--own-color:' . $rootColor . ';"'
           . ' data-bdm-id="' . $rootId . '">'
           . '<div class="trunk-level">' . htmlspecialchars($rootDisplayLevel) . '</div>'
           . '<div class="trunk-name">' . htmlspecialchars($root['bdm_name']) . '</div>'
           . '</div>';

    if (!empty($branchList)) {
        $html .= '<div class="sm-trunk-stem"></div>';
        $branchLevelName = $branchList[0]['level_name'] ?: 'report';
        $html .= '<div class="sm-branch-label">' . count($branchList) . ' ' . htmlspecialchars($branchLevelName) . '</div>';
        $html .= '<div class="sm-branches">';
        foreach ($branchList as $branch) {
            $branchId = (int)$branch['id'];
            $branchColor = $levelColorMap[(int)$branch['team_level_id']] ?? '#999999';
            $branchDisplayLevel = computeDisplayLevel($branchId, (int)$branch['team_level_id'], $branch['level_name'], $depthToLevelName, $levelDepthById, $dualBdmIds);
            $subList = $byManager[$branchId] ?? [];
            $html .= '<div class="branch-circle tree-node-clickable org-node-clickable" style="--own-color:' . $branchColor . ';" data-bdm-id="' . $branchId . '">'
                   . '<div class="branch-stem"></div>'
                   . '<div class="org-node-circle">' . htmlspecialchars(bdmInitials($branch['bdm_name'])) . '</div>'
                   . '<div class="branch-level">' . htmlspecialchars($branchDisplayLevel) . '</div>'
                   . '<div class="branch-name">' . htmlspecialchars($branch['bdm_name']) . '</div>';
            if (!empty($subList)) {
                $subLevelName = $subList[0]['level_name'] ?: 'report';
                $subColor = $levelColorMap[(int)$subList[0]['team_level_id']] ?? '#999999';
                $html .= '<div class="sub-stem"></div>';
                $html .= '<div class="branch-circle sub-level solo tree-node-clickable org-node-clickable" style="--own-color:' . $subColor . ';" data-bdm-id="' . $branchId . '" title="' . htmlspecialchars($subLevelName, ENT_QUOTES) . '">'
                       . '<div class="org-node-circle two-line">'
                       . '<span class="circle-label">' . htmlspecialchars(bdmInitials($subLevelName)) . '</span>'
                       . '<span class="circle-count">' . count($subList) . '</span>'
                       . '</div></div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Our Team - Tree View : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .org-tree-wrap { padding:10px 6px; }
        .sm-cluster { text-align:center; padding:20px 10px; }
        .sm-trunk { display:inline-flex; flex-direction:column; align-items:center; background:var(--own-color,#6b4226); color:#fff; border-radius:18px; padding:14px 30px; min-width:190px; box-shadow:0 3px 10px rgba(0,0,0,.18); }
        .sm-trunk.org-node-clickable:hover { box-shadow:0 5px 14px rgba(0,0,0,.25); transform:translateY(-1px); }
        .sm-trunk .trunk-level { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; opacity:.85; }
        .sm-trunk .trunk-name { font-size:16px; font-weight:700; margin-top:3px; }
        .sm-trunk-stem { width:2px; height:18px; background:#ccc; margin:0 auto; }
        .sm-branch-label { font-size:11px; color:#999; margin:6px 0 0; text-transform:uppercase; letter-spacing:.5px; }
        .sm-branches { display:flex; flex-wrap:wrap; gap:24px; justify-content:center; margin-top:22px; max-width:100%; }
        .org-node-circle { width:50px; height:50px; flex-shrink:0; border-radius:50%; background:var(--own-color,#999); color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:700; box-shadow:0 2px 6px rgba(0,0,0,.15); }
        .org-node-clickable { cursor:pointer; transition:box-shadow .15s, transform .15s; }
        .branch-circle { display:flex; flex-direction:column; align-items:center; width:100px; position:relative; padding-top:20px; }
        .branch-stem { position:absolute; top:0; left:50%; width:2px; height:20px; background:#ccc; transform:translateX(-1px); }
        .branch-circle::before, .branch-circle::after { content:''; position:absolute; top:0; height:0; border-top:2px solid #ccc; }
        .branch-circle::before { right:50%; left:-12px; }
        .branch-circle::after  { left:50%;  right:-12px; }
        .branch-circle:first-child::before { display:none; }
        .branch-circle:last-child::after { display:none; }
        .branch-circle.org-node-clickable:hover .org-node-circle { box-shadow:0 4px 12px rgba(0,0,0,.28); transform:scale(1.06); }
        .branch-circle .branch-level { font-size:9px; line-height:12px; font-weight:700; color:var(--own-color,#999); text-transform:uppercase; letter-spacing:.5px; margin-top:8px; height:24px; overflow:hidden; display:flex; align-items:flex-start; justify-content:center; }
        .branch-circle .branch-name { font-size:12px; line-height:15px; font-weight:600; color:#333; text-align:center; height:30px; overflow:hidden; display:flex; align-items:flex-start; justify-content:center; }
        .sub-indicator-stem { width:2px; height:8px; background:var(--own-color,#ccc); margin-top:6px; }
        .sub-stem { width:2px; height:14px; background:#ccc; margin:10px auto 0; }
        .branch-circle.sub-level { width:auto; padding-top:0; }
        .branch-circle.solo::before, .branch-circle.solo::after { display:none; }
        .branch-circle.sub-level .org-node-circle { width:42px; height:42px; }
        .org-node-circle.two-line { flex-direction:column; line-height:1.1; }
        .org-node-circle .circle-label { font-size:9px; font-weight:700; letter-spacing:.3px; }
        .org-node-circle .circle-count { font-size:14px; font-weight:800; margin-top:1px; }
        .chain-row { display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:6px; margin-bottom:18px; }
        .chain-pill { display:inline-block; padding:7px 16px; border-radius:20px; background:var(--own-color,#999); color:#fff; font-size:14px; font-weight:700; }
        .chain-arrow { color:#ccc; font-size:16px; }

        /* #bdmNodeModal's content is replaced in place while already open
           (drilling into a branch) — only the very first open should animate
           in; a near-zero transition here stops that from reading as a
           second "flash" when the body's innerHTML swaps underneath it. */
        #bdmNodeModal.show .modal-dialog { transition-duration: .08s; }
        .modal-backdrop.show { transition-duration: .08s; }

        @media (max-width: 768px) {
            .org-tree-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .sm-branches { flex-wrap: nowrap; justify-content: flex-start; }
            .page-description h1 { font-size: 20px; }
        }
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
                    <div class="row mb-2">
                        <div class="col">
                            <div class="page-description" style="margin-left:-10px;">
                                <h1><i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">groups</i>Our Team &mdash; Tree View</h1>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col">
                            <p class="text-muted" style="font-size:13px;">Click a circle to see who reports to them.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div class="org-tree-wrap">
                                        <?php echo renderBdmCluster($rootRow, $byManager, $levelColorMap, $depthToLevelName, $levelDepthById, $dualBdmIds); ?>
                                    </div>
                                </div>
                            </div>
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
                <h5 class="modal-title">
                    <button type="button" id="bdmModalBack" class="btn btn-sm btn-outline-secondary" style="display:none;">&larr; Back</button>
                    <span id="bdmNodeModalTitle">Reports</span>
                </h5>
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
var bdmChildrenMap = <?php echo json_encode($childrenMap); ?>;
var bdmAllStaff = <?php echo json_encode($allStaffMap); ?>;

(function ($) {
    var modalStack = [];

    function getChain(nodeId) {
        var chain = [];
        var current = bdmAllStaff[nodeId];
        var guard = 0;
        while (current && current.manager_id && guard < 20) {
            var mgr = bdmAllStaff[current.manager_id];
            if (!mgr) break;
            chain.unshift(mgr);
            current = mgr;
            guard++;
        }
        return chain;
    }

    function renderModalFrame() {
        var frame = modalStack[modalStack.length - 1];
        $('#bdmModalBack').toggle(modalStack.length > 1);
        renderPersonFrame(frame);
    }

    function renderPersonFrame(frame) {
        var node = bdmAllStaff[frame.id];
        var $body = $('#bdmNodeModalBody').empty();
        if (!node) {
            $('#bdmNodeModalTitle').text('Not found');
            $body.html('<div class="text-muted">This person could not be found.</div>');
            return;
        }
        var kids = bdmChildrenMap[frame.id] || [];
        $('#bdmNodeModalTitle').text(node.level_name + ': ' + node.name);

        var chain = getChain(frame.id);
        if (chain.length) {
            var $chainRow = $('<div class="chain-row"></div>');
            $.each(chain, function (i, a) {
                if (i > 0) { $chainRow.append('<span class="chain-arrow">→</span>'); }
                $chainRow.append($('<span class="chain-pill"></span>').css('--own-color', a.color).text(a.level_name + ': ' + a.name));
            });
            $body.append($chainRow);
        }

        if (node.zone) {
            $body.append($('<div style="text-align:center;margin-bottom:12px;"></div>').append(
                $('<span class="chain-pill" style="background:#fff8e1;color:#92400e;"></span>').text('Zone: ' + node.zone)
            ));
        }

        var $cluster = $('<div class="sm-cluster"></div>').css('--trunk-color', node.color);
        var $trunk = $('<div class="sm-trunk"></div>').css('--own-color', node.color);
        $trunk.append($('<div class="trunk-level"></div>').text(node.level_name));
        $trunk.append($('<div class="trunk-name"></div>').text(node.name));
        $cluster.append($trunk);

        if (kids.length) {
            $cluster.append($('<div class="sm-trunk-stem"></div>'));
            $cluster.append($('<div class="sm-branch-label"></div>').text(kids.length + ' ' + kids[0].level_name));
            var $branches = $('<div class="sm-branches"></div>');
            $.each(kids, function (_, k) {
                var subKids = bdmChildrenMap[k.id] || [];
                var $b = $('<div class="branch-circle org-node-clickable"></div>').css('--own-color', k.color);
                $b.append('<div class="branch-stem"></div>');
                $b.append($('<div class="org-node-circle"></div>').text(k.initials));
                $b.append($('<div class="branch-level"></div>').text(k.level_name));
                $b.append($('<div class="branch-name"></div>').text(k.name));
                if (subKids.length) {
                    $b.append('<div class="sub-indicator-stem"></div>');
                    $b.append($('<div class="branch-count" style="font-size:10px;font-weight:700;color:#fff;border-radius:10px;padding:3px 9px;"></div>').css('background', subKids[0].color).text(subKids.length + ' below'));
                }
                $b.on('click', function (e) { e.stopPropagation(); modalStack.push({ id: k.id }); renderModalFrame(); });
                $branches.append($b);
            });
            $cluster.append($branches);
        } else {
            $cluster.append($('<div class="sm-branch-label" style="margin-top:10px;"></div>').text('No one reports to them yet.'));
        }
        $body.append($cluster);

        var $locWrap = null;
        if (node.locations && node.locations.length) {
            $locWrap = $('<div style="margin-top:16px;text-align:center;"></div>');
            $locWrap.append($('<div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"></div>').text(node.name + '’s Assigned Locations'));
            var $locChips = $('<div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;"></div>');
            $.each(node.locations, function (_, locName) {
                $locChips.append($('<span class="chain-pill" style="background:#eee;color:#333;"></span>').text(locName));
            });
            $locWrap.append($locChips);
            $body.append($locWrap);
        }
    }

    $('#bdmModalBack').on('click', function () { if (modalStack.length > 1) { modalStack.pop(); renderModalFrame(); } });

    $(document).on('click', '.tree-node-clickable', function () {
        var id = $(this).data('bdm-id');
        modalStack = [{ id: id }];
        renderModalFrame();
        $('#bdmNodeModal').modal('show');
    });
})(jQuery);
</script>
</body>
</html>
