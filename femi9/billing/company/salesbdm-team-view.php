<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("include/TeamLevelColors.php");
error_reporting(0);

$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    location_layer_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");
$_chkTL = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'team_level_id'");
if ($_chkTL && $_chkTL->num_rows === 0) {
    $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
}
$_chkMgr = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'manager_id'");
if ($_chkMgr && $_chkMgr->num_rows === 0) {
    $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
}
$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bdm_id INT NOT NULL,
    location_id INT NOT NULL,
    is_dual_role TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bdm_location (bdm_id, location_id)
)");
$_chkDual = $db_conn->query("SHOW COLUMNS FROM salesbdm_locations LIKE 'is_dual_role'");
if ($_chkDual && $_chkDual->num_rows === 0) {
    $db_conn->query("ALTER TABLE salesbdm_locations ADD COLUMN is_dual_role TINYINT(1) NOT NULL DEFAULT 0 AFTER location_id");
}

// depth -> level_name / level_id -> depth lookups, used to detect + label a
// dual-role person (e.g. Chief BDM who also personally holds specific
// Districts) so the tree shows them under BOTH their own level and the
// level one depth below.
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

// ── Cards: staff count per level ────────────────────────────────────────────
$levelCounts = $db_conn->query("
    SELECT l.id, l.level_rank, l.level_name, COALESCE(s.cnt, 0) AS staff_count
    FROM salesbdm_team_levels l
    LEFT JOIN (
        SELECT team_level_id, COUNT(*) AS cnt FROM sales_bdm_staff GROUP BY team_level_id
    ) s ON s.team_level_id = l.id
    ORDER BY l.level_rank ASC
")->fetch_all(MYSQLI_ASSOC);

// One distinct color per level, in rank order (shared across the whole app)
$levelColorMap = getSalesBdmTeamLevelColorMap($db_conn);

// ── Staff, grouped by level (for card click) and by manager (for drill-down) ──
$staffRows = $db_conn->query("
    SELECT bs.id, bs.bdm_name, bs.manager_id, bs.team_level_id, tl.level_name, tl.level_rank
    FROM sales_bdm_staff bs
    LEFT JOIN salesbdm_team_levels tl ON tl.id = bs.team_level_id
    ORDER BY tl.level_rank ASC, bs.bdm_name ASC
")->fetch_all(MYSQLI_ASSOC);

function bdmInitials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    $words = array_filter($words);
    if (empty($words)) return '?';
    if (count($words) === 1) return mb_strtoupper(mb_substr($words[0], 0, 1));
    $first = mb_substr(reset($words), 0, 1);
    $last  = mb_substr(end($words), 0, 1);
    return mb_strtoupper($first . $last);
}

function computeDisplayLevel(int $bdmId, int $lvlId, ?string $levelName, array $depthToLevelName, array $levelDepthById, array $dualBdmIds): string {
    $displayLevel = $levelName ?: '-';
    if (isset($dualBdmIds[$bdmId]) && isset($levelDepthById[$lvlId]) && $levelDepthById[$lvlId] !== null) {
        $nextDepth = $levelDepthById[$lvlId] + 1;
        if (isset($depthToLevelName[$nextDepth])) {
            $displayLevel .= ' + ' . $depthToLevelName[$nextDepth];
        }
    }
    return $displayLevel;
}

function toBdmEntry(array $row, array $levelColorMap, array $depthToLevelName = [], array $levelDepthById = [], array $dualBdmIds = []): array {
    $lvlId = (int)$row['team_level_id'];
    $bdmId = (int)$row['id'];
    $displayLevel = computeDisplayLevel($bdmId, $lvlId, $row['level_name'], $depthToLevelName, $levelDepthById, $dualBdmIds);
    return [
        'id'         => $bdmId,
        'name'       => $row['bdm_name'],
        'level_name' => $displayLevel,
        'color'      => $levelColorMap[$lvlId] ?? '#999999',
        'initials'   => bdmInitials($row['bdm_name']),
    ];
}

$levelStaffMap = [];
$byManager = [];
$noLevelCount = 0;
foreach ($staffRows as $row) {
    if (!$row['level_rank']) { $noLevelCount++; continue; }
    $lvlId = (int)$row['team_level_id'];
    $levelStaffMap[$lvlId][] = toBdmEntry($row, $levelColorMap, $depthToLevelName, $levelDepthById, $dualBdmIds);
    $mgrKey = $row['manager_id'] ? (int)$row['manager_id'] : 0;
    $byManager[$mgrKey][] = $row;
}

// Direct-reports map, keyed by bdm_id — powers each drill-down step in the modal
$childrenMap = [];
foreach ($byManager as $mgrId => $children) {
    if ($mgrId === 0) continue;
    $childrenMap[$mgrId] = array_map(fn($c) => toBdmEntry($c, $levelColorMap, $depthToLevelName, $levelDepthById, $dualBdmIds), $children);
}

// Location names held by each bdm (primary + dual-role), for the modal's
// "Assigned Locations" list when drilling into a specific person.
$locationsByBdm = [];
$locRes = $db_conn->query("
    SELECT sl.bdm_id, pn.name
    FROM salesbdm_locations sl
    JOIN partner_location_nodes pn ON pn.id = sl.location_id
    ORDER BY pn.name ASC
");
if ($locRes) {
    while ($r = $locRes->fetch_assoc()) {
        $locationsByBdm[(int)$r['bdm_id']][] = $r['name'];
    }
}

// Flat map of every staff member — powers the ancestor-chain breadcrumb
$allStaffMap = [];
foreach ($staffRows as $row) {
    if (!$row['level_rank']) continue;
    $entry = toBdmEntry($row, $levelColorMap, $depthToLevelName, $levelDepthById, $dualBdmIds);
    $entry['manager_id'] = $row['manager_id'] ? (int)$row['manager_id'] : 0;
    $entry['locations']  = $locationsByBdm[(int)$row['id']] ?? [];
    $allStaffMap[(int)$row['id']] = $entry;
}

// ── Trunk + branch clusters: one root per cluster, its direct reports as
// circles around it, connected by a simple trunk→bar→stem line. Deeper levels
// are reached by clicking — never rendered inline, so the layout never sprawls.
function renderBdmCluster(array $root, array $byManager, array $levelColorMap, array $depthToLevelName, array $levelDepthById, array $dualBdmIds): string {
    $rootId = (int)$root['id'];
    $rootColor = $levelColorMap[(int)$root['team_level_id']] ?? '#6b4226';
    $branchList = $byManager[$rootId] ?? [];
    $rootDisplayLevel = computeDisplayLevel($rootId, (int)$root['team_level_id'], $root['level_name'], $depthToLevelName, $levelDepthById, $dualBdmIds);

    $html = '<div class="sm-cluster" style="--trunk-color:' . $rootColor . ';">';
    $html .= '<div class="sm-trunk org-node-clickable" style="--own-color:' . $rootColor . ';"'
           . ' data-bdm-id="' . $rootId . '" data-bdm-name="' . htmlspecialchars($root['bdm_name'], ENT_QUOTES) . '" data-bdm-level="' . htmlspecialchars($rootDisplayLevel, ENT_QUOTES) . '">'
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
            $html .= '<div class="branch-circle org-node-clickable" style="--own-color:' . $branchColor . ';"'
                   . ' data-bdm-id="' . $branchId . '" data-bdm-name="' . htmlspecialchars($branch['bdm_name'], ENT_QUOTES) . '" data-bdm-level="' . htmlspecialchars($branchDisplayLevel, ENT_QUOTES) . '">'
                   . '<div class="branch-stem"></div>'
                   . '<div class="org-node-circle">' . htmlspecialchars(bdmInitials($branch['bdm_name'])) . '</div>'
                   . '<div class="branch-level">' . htmlspecialchars($branchDisplayLevel) . '</div>'
                   . '<div class="branch-name">' . htmlspecialchars($branch['bdm_name']) . '</div>';
            if (!empty($subList)) {
                $subLevelName = $subList[0]['level_name'] ?: 'report';
                $subColor = $levelColorMap[(int)$subList[0]['team_level_id']] ?? '#999999';
                $subAbbrev = bdmInitials($subLevelName);
                $html .= '<div class="sub-stem"></div>';
                $html .= '<div class="branch-circle sub-level solo org-node-clickable" style="--own-color:' . $subColor . ';"'
                       . ' data-bdm-id="' . $branchId . '" data-bdm-name="' . htmlspecialchars($branch['bdm_name'], ENT_QUOTES) . '" data-bdm-level="' . htmlspecialchars($branch['level_name'] ?: '-', ENT_QUOTES) . '"'
                       . ' title="' . htmlspecialchars($subLevelName, ENT_QUOTES) . '">'
                       . '<div class="org-node-circle two-line">'
                       . '<span class="circle-label">' . htmlspecialchars($subAbbrev) . '</span>'
                       . '<span class="circle-count">' . count($subList) . '</span>'
                       . '</div>'
                       . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

$rootStaff = $byManager[0] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales BDM Team - Tree View : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .level-card {
            border:1px solid #e5e7eb; border-left:5px solid var(--lvl-color, #667eea);
            border-radius:10px; padding:18px; text-align:center; background:#fff;
            cursor:pointer; transition: box-shadow .15s;
            height:150px; display:flex; flex-direction:column; align-items:center; justify-content:center;
        }
        .level-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,.12); }
        .level-card .count { font-size:32px; font-weight:700; color:var(--lvl-color, #667eea); }
        .level-card .name {
            font-size:11.5px; line-height:1.3; color:#555; margin-top:4px; text-transform:uppercase; letter-spacing:.3px;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
        }
        .level-card .hint { font-size:11px; color:#999; margin-top:6px; }

        .org-tree-wrap { padding:10px 6px; }
        .sm-cluster { text-align:center; padding:20px 10px; border-bottom:1px solid #f0f0f0; }
        .sm-cluster:last-child { border-bottom:none; }
        .sm-trunk {
            display:inline-flex; flex-direction:column; align-items:center;
            background: var(--own-color, #6b4226); color:#fff;
            border-radius: 18px; padding: 14px 30px; min-width:190px;
            box-shadow: 0 3px 10px rgba(0,0,0,.18);
        }
        .sm-trunk.org-node-clickable:hover { box-shadow: 0 5px 14px rgba(0,0,0,.25); transform: translateY(-1px); }
        .sm-trunk .trunk-level { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; opacity:.85; }
        .sm-trunk .trunk-name { font-size:16px; font-weight:700; margin-top:3px; }
        .sm-trunk-stem { width:2px; height:18px; background:#ccc; margin:0 auto; }
        .sm-branch-label { font-size:11px; color:#999; margin:6px 0 0; text-transform:uppercase; letter-spacing:.5px; }
        .sm-branches {
            display:flex; flex-wrap:wrap; gap:24px; justify-content:center;
            margin-top:22px; max-width:100%;
        }

        .org-node-circle {
            width: 50px; height: 50px; flex-shrink: 0; border-radius: 50%;
            background: var(--own-color, #999); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 700;
            box-shadow: 0 2px 6px rgba(0,0,0,.15);
        }
        .org-node-clickable { cursor: pointer; transition: box-shadow .15s, transform .15s; }

        .branch-circle { display:flex; flex-direction:column; align-items:center; width:100px; position:relative; padding-top:20px; }
        .branch-stem { position:absolute; top:0; left:50%; width:2px; height:20px; background:#ccc; transform:translateX(-1px); }
        .branch-circle::before, .branch-circle::after {
            content:''; position:absolute; top:0; height:0;
            border-top:2px solid #ccc;
        }
        .branch-circle::before { right:50%; left:-12px; }
        .branch-circle::after  { left:50%;  right:-12px; }
        .branch-circle:first-child::before { display:none; }
        .branch-circle:last-child::after { display:none; }
        .branch-circle.org-node-clickable:hover .org-node-circle { box-shadow: 0 4px 12px rgba(0,0,0,.28); transform: scale(1.06); }
        .branch-circle .branch-level {
            font-size:9px; line-height:12px; font-weight:700; color:var(--own-color,#999); text-transform:uppercase; letter-spacing:.5px; margin-top:8px;
            height:24px; overflow:hidden; display:flex; align-items:flex-start; justify-content:center;
        }
        .branch-circle .branch-name {
            font-size:12px; line-height:15px; font-weight:600; color:#333; text-align:center;
            height:30px; overflow:hidden; display:flex; align-items:flex-start; justify-content:center;
        }
        .sub-indicator-stem { width:2px; height:8px; background:var(--own-color,#ccc); margin-top:6px; }
        .branch-circle .branch-count {
            font-size:10px; font-weight:700; color:#fff;
            background:var(--sub-color,#999);
            border-radius:10px; padding:3px 9px; margin-top:0; white-space:nowrap;
            box-shadow: 0 1px 4px rgba(0,0,0,.2);
        }

        .sub-stem { width:2px; height:14px; background:#ccc; margin:10px auto 0; }
        .branch-circle.sub-level { width:auto; padding-top:0; }
        .branch-circle.solo::before, .branch-circle.solo::after { display:none; }
        .branch-circle.sub-level .org-node-circle { width:42px; height:42px; }
        .org-node-circle.two-line { flex-direction:column; line-height:1.1; }
        .org-node-circle .circle-label { font-size:9px; font-weight:700; letter-spacing:.3px; }
        .org-node-circle .circle-count { font-size:14px; font-weight:800; margin-top:1px; }

        .branch-highlighted .org-node-circle {
            box-shadow: 0 0 0 4px rgba(0,0,0,.18), 0 4px 12px rgba(0,0,0,.3);
            transform: scale(1.1);
        }
        .branch-highlighted .branch-name { text-decoration: underline; }

        .chain-row { display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:6px; margin-bottom:18px; }
        .chain-pill {
            display:inline-block; padding:7px 16px; border-radius:20px;
            background:var(--own-color,#999); color:#fff; font-size:14px; font-weight:700;
        }
        .chain-arrow { color:#ccc; font-size:16px; }

        .org-node-text { text-align: left; }
        .org-node-text .node-level { font-size: 10px; font-weight:700; color:var(--own-color, #999); text-transform:uppercase; letter-spacing:.5px; }
        .org-node-text .node-name { font-size: 13px; color:#333; font-weight:600; margin-top:2px; }

        .modal-report-grid { display:flex; flex-wrap:wrap; gap:12px; }
        .modal-report-item {
            display: flex; align-items: center; gap: 10px;
            border: 1px solid #eee; border-radius: 30px;
            padding: 5px 14px 5px 5px; min-width: 180px; background: #fff;
        }
        .modal-report-item .org-node-circle { width: 40px; height: 40px; font-size: 13px; }
        .modal-report-item.is-clickable { cursor:pointer; transition: box-shadow .15s; }
        .modal-report-item.is-clickable:hover { box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        .modal-report-item .node-level { font-size:10px; font-weight:700; color:var(--own-color,#999); text-transform:uppercase; letter-spacing:.5px; }
        .modal-report-item .node-name { font-size:13px; color:#333; font-weight:600; margin-top:2px; }
        #bdmModalBack { margin-right:8px; }
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
                                        <tr>
                                            <td>Sales BDM Team - Tree View</td>
                                            <td><a href="salesbdm_manage" title="Manage Sales BDM">&#9776;</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col">
                            <p class="text-muted" style="font-size:13px;">Click a card to see everyone at that level, then click a person to drill into their team.</p>
                        </div>
                    </div>

                    <!-- Level count cards -->
                    <div class="row mb-3">
                        <?php if (empty($levelCounts)): ?>
                            <div class="col">
                                <div class="alert alert-info">No team levels configured yet. <a href="add-salesbdm-team-level">Add one</a>.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($levelCounts as $lc): ?>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="level-card"
                                         style="--lvl-color:<?php echo $levelColorMap[(int)$lc['id']]; ?>;"
                                         data-level-id="<?php echo (int)$lc['id']; ?>"
                                         data-level-name="<?php echo htmlspecialchars($lc['level_name'], ENT_QUOTES); ?>">
                                        <div class="count"><?php echo (int)$lc['staff_count']; ?></div>
                                        <div class="name"><?php echo htmlspecialchars($lc['level_name']); ?></div>
                                        <div class="hint">Click to view &rarr;</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($noLevelCount > 0): ?>
                        <div class="row mb-3">
                            <div class="col">
                                <div class="alert alert-warning" style="font-size:13px;"><?php echo (int)$noLevelCount; ?> Sales BDM have no Team Level assigned and won't appear here.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-2">
                        <div class="col">
                            <p class="text-muted" style="font-size:13px;margin-bottom:0;">Each trunk is a top-level Sales BDM. Click the trunk or any circle to see who reports to them.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div class="org-tree-wrap">
                                        <?php if (empty($rootStaff)): ?>
                                            <div class="text-center text-muted" style="padding:20px;">No Sales BDM with a team level yet.</div>
                                        <?php else: ?>
                                            <?php foreach ($rootStaff as $root): ?>
                                                <?php echo renderBdmCluster($root, $byManager, $levelColorMap, $depthToLevelName, $levelDepthById, $dualBdmIds); ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
var bdmChildrenMap = <?php echo json_encode($childrenMap); ?>;
var bdmLevelStaffMap = <?php echo json_encode($levelStaffMap); ?>;
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
        if (frame.type === 'list') {
            renderListFrame(frame);
        } else {
            renderPersonFrame(frame);
        }
    }

    function renderListFrame(frame) {
        $('#bdmNodeModalTitle').text(frame.title + ' (' + frame.items.length + ')');
        var $body = $('#bdmNodeModalBody').empty();
        if (!frame.items.length) {
            $body.html('<div class="text-muted">No one here yet.</div>');
            return;
        }
        var $grid = $('<div class="modal-report-grid"></div>');
        $.each(frame.items, function (_, c) {
            var $item = $('<div class="modal-report-item is-clickable"></div>').css('--own-color', c.color);
            $item.append($('<div class="org-node-circle"></div>').text(c.initials));
            var $text = $('<div class="org-node-text"></div>');
            $text.append($('<div class="node-level"></div>').text(c.level_name));
            $text.append($('<div class="node-name"></div>').text(c.name));
            $item.append($text);
            $item.on('click', function (e) {
                e.stopPropagation();
                modalStack.push({ type: 'person', id: c.id });
                renderModalFrame();
            });
            $grid.append($item);
        });
        $body.append($grid);
    }

    function renderPersonFrame(frame) {
        var clickedNode = bdmAllStaff[frame.id];
        var node = clickedNode;
        var $body = $('#bdmNodeModalBody').empty();
        if (!node) {
            $('#bdmNodeModalTitle').text('Not found');
            $body.html('<div class="text-muted">This person could not be found.</div>');
            return;
        }

        // Always show the clicked person's own trunk/reports — no flipping to
        // the manager's cluster, so clicking a BDM shows only that BDM, not
        // their siblings too. "Who is their Chief BDM" is covered by the
        // chain breadcrumb below instead.
        var displayId = frame.id;
        var highlightId = frame.id;
        var kids = bdmChildrenMap[displayId] || [];

        $('#bdmNodeModalTitle').text(node.level_name + ': ' + node.name);

        var chain = getChain(frame.id);
        if (chain.length) {
            var $chainRow = $('<div class="chain-row"></div>');
            $.each(chain, function (i, a) {
                if (i > 0) { $chainRow.append('<span class="chain-arrow">→</span>'); }
                var $pill = $('<span class="chain-pill"></span>').css('--own-color', a.color).text(a.level_name + ': ' + a.name);
                $chainRow.append($pill);
            });
            $body.append($chainRow);
        }

        var $locWrap = null;
        if (clickedNode.locations && clickedNode.locations.length) {
            $locWrap = $('<div style="margin:0 0 16px;text-align:center;"></div>');
            $locWrap.append($('<div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"></div>').text(clickedNode.name + '’s Assigned Locations'));
            var $locChips = $('<div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;"></div>');
            $.each(clickedNode.locations, function (_, locName) {
                $locChips.append($('<span class="chain-pill" style="background:#eee;color:#333;"></span>').text(locName));
            });
            $locWrap.append($locChips);
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
                if (k.id === highlightId) { $b.addClass('branch-highlighted'); }
                $b.append('<div class="branch-stem"></div>');
                $b.append($('<div class="org-node-circle"></div>').text(k.initials));
                $b.append($('<div class="branch-level"></div>').text(k.level_name));
                $b.append($('<div class="branch-name"></div>').text(k.name));
                if (subKids.length) {
                    $b.append('<div class="sub-indicator-stem"></div>');
                    var $count = $('<div class="branch-count"></div>').text(subKids.length + ' below');
                    $count.css('--sub-color', subKids[0].color);
                    $b.append($count);
                }
                $b.on('click', function (e) {
                    e.stopPropagation();
                    modalStack.push({ type: 'person', id: k.id });
                    renderModalFrame();
                });
                $branches.append($b);
            });
            $cluster.append($branches);
        } else {
            $cluster.append($('<div class="sm-branch-label" style="margin-top:10px;"></div>').text('No one reports to them yet.'));
        }
        $body.append($cluster);
        if ($locWrap) { $body.append($locWrap); }
    }

    $('#bdmModalBack').on('click', function () {
        if (modalStack.length > 1) { modalStack.pop(); renderModalFrame(); }
    });

    $(document).on('click', '.level-card', function () {
        var levelId = $(this).data('level-id');
        var levelName = $(this).data('level-name');
        var items = bdmLevelStaffMap[levelId] || [];
        modalStack = [{ type: 'list', title: levelName, items: items }];
        renderModalFrame();
        $('#bdmNodeModal').modal('show');
    });

    $(document).on('click', '.org-node-clickable', function () {
        var id = $(this).data('bdm-id');
        modalStack = [{ type: 'person', id: id }];
        renderModalFrame();
        $('#bdmNodeModal').modal('show');
    });
})(jQuery);
</script>
</body>
</html>
