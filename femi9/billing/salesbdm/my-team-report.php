<?php
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");
require_once("include/TeamTargetRollup.php");
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
$reportIds = array_values(array_diff($subtreeIds, [(int)$salesBdmID]));

require_once __DIR__ . '/../company/include/TeamLevelColors.php';
$levelColorMap = getSalesBdmTeamLevelColorMap($db_conn);

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
    SELECT bs.id, bs.bdm_name, bs.manager_id, bs.team_level_id, bs.zone, tl.level_name, tl.level_rank
    FROM sales_bdm_staff bs LEFT JOIN salesbdm_team_levels tl ON tl.id = bs.team_level_id
    WHERE bs.id IN (" . implode(',', array_map('intval', $subtreeIds)) . ")
")->fetch_all(MYSQLI_ASSOC);
$staffById = [];
foreach ($staffRows as $row) { $staffById[(int)$row['id']] = $row; }

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) $fromDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate))   $toDate   = date('Y-m-d');

$reportRows = [];
foreach ($reportIds as $rid) {
    $row = $staffById[$rid] ?? null;
    if (!$row) continue;
    $roll = getBdmRawTargetAchieved($db_conn, $rid, $fromDate, $toDate);
    $pct = $roll['target'] > 0 ? min(round($roll['achieved'] / $roll['target'] * 100, 1), 999) : 0;
    $reportRows[] = [
        'id' => $rid, 'name' => $row['bdm_name'],
        'level_name' => computeDisplayLevel($rid, (int)$row['team_level_id'], $row['level_name'], $depthToLevelName, $levelDepthById, $dualBdmIds),
        'color' => $levelColorMap[(int)$row['team_level_id']] ?? '#999999',
        'zone' => $row['zone'] ?? '',
        'target' => $roll['target'], 'achieved' => $roll['achieved'], 'tp_count' => $roll['tp_count'], 'pct' => $pct,
    ];
}
usort($reportRows, fn($a, $b) => $b['pct'] <=> $a['pct']);
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
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        :root { --blue:#2a78d6; --blue-tint:#eaf2fc; --good:#0ca30c; --good-tint:#e5f7e5; --critical:#d03b3b; --critical-tint:#fbe6e6; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:8px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .team-name-link { color:var(--blue); font-weight:600; text-decoration:underline dotted; }
        .pbar { height:7px; border-radius:4px; background:var(--blue-tint); overflow:hidden; }
        .pbar .pf { height:100%; border-radius:4px; background:var(--blue); }
        .mis-filter { background:#fff; border:1px solid rgba(11,11,11,0.10); border-radius:10px; padding:14px 18px; margin-bottom:20px; }
        .lvl-badge { font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; color:#fff; white-space:nowrap; }

        @media (max-width: 768px) {
            .mis-filter form { flex-direction: column; align-items: stretch !important; }
            .mis-filter form > div { width: 100% !important; }
            .mis-filter select, .mis-filter input { width: 100% !important; }
            .mt { font-size: 12px; }
        }
        @media (max-width: 480px) {
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
                                <h1><i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">groups</i>Our Team &mdash; Report</h1>
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
                        <div class="card-header"><h5 class="card-title" style="margin:0;font-size:14px;">Team Report — Target vs Achieved (Napkin only)</h5></div>
                        <div class="card-body" style="overflow-x:auto;">
                            <p class="snote">Click a name or the % to open their own dashboard (read-only).</p>
                            <table class="mt">
                                <thead>
                                    <tr>
                                        <th>BDM</th>
                                        <th>Level</th>
                                        <th>Zone</th>
                                        <th>TPs</th>
                                        <th>Target (&#8377;)</th>
                                        <th>Achieved (&#8377;)</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($reportRows)): ?>
                                    <tr><td colspan="7" class="text-muted">No one reports to you yet.</td></tr>
                                <?php else: foreach ($reportRows as $r):
                                    $bc = $r['pct'] >= 100 ? 'var(--good)' : ($r['pct'] >= 50 ? '#eab308' : 'var(--critical)');
                                    $viewUrl = 'dashboard.php?view_bdm_id=' . $r['id'];
                                ?>
                                    <tr>
                                        <td><a class="team-name-link" href="<?php echo $viewUrl; ?>"><?php echo htmlspecialchars($r['name']); ?></a></td>
                                        <td><span class="lvl-badge" style="background:<?php echo $r['color']; ?>;"><?php echo htmlspecialchars($r['level_name']); ?></span></td>
                                        <td><?php echo $r['zone'] ? htmlspecialchars($r['zone']) : '—'; ?></td>
                                        <td><?php echo (int)$r['tp_count']; ?></td>
                                        <td>&#8377;<?php echo inr_format($r['target'], 0); ?></td>
                                        <td>&#8377;<?php echo inr_format($r['achieved'], 0); ?></td>
                                        <td>
                                            <a href="<?php echo $viewUrl; ?>" style="text-decoration:none;">
                                                <div style="display:flex;align-items:center;gap:5px;">
                                                    <div class="pbar" style="width:70px;"><div class="pf" style="width:<?php echo min($r['pct'],100); ?>%;background:<?php echo $bc; ?>;"></div></div>
                                                    <span style="font-size:12.5px;font-weight:700;color:<?php echo $bc; ?>;"><?php echo $r['pct']; ?>%</span>
                                                </div>
                                            </a>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
