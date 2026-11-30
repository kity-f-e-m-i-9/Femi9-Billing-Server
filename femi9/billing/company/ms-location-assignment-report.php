<?php
// Flat, searchable "who owns what" lookup across the whole marketing_staff
// tree — built 2026-11-21 after repeatedly being asked "who does Tambaram
// Division / Chengalpattu Division belong to" with no quick way to check
// other than querying the DB directly. One row per marketing_staff_locations
// assignment (a staff member with multiple assignments gets multiple rows),
// each annotated with its own depth label (District/Division/Taluk/etc, from
// partner_location_layers) AND, when the assignment itself is below District
// depth, the District it falls under — so "Chengalpattu Division" is still
// findable by searching "Chengalpattu" even though the row's own name is
// the Division, not the District.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("include/TeamLevelColors.php");
error_reporting(0);
include("config.php");

$levelColorMap = getTeamLevelColorMap($db_conn);

$layerNameByDepth = [];
$_lr = $db_conn->query("SELECT depth, layer_name FROM partner_location_layers");
while ($row = $_lr->fetch_assoc()) { $layerNameByDepth[(int)$row['depth']] = $row['layer_name']; }

$allLocNodes = [];
$_nr = $db_conn->query("SELECT id, parent_id, depth, name FROM partner_location_nodes WHERE is_active=1");
while ($row = $_nr->fetch_assoc()) {
    $allLocNodes[(int)$row['id']] = [
        'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
        'depth'     => (int)$row['depth'],
        'name'      => $row['name'],
    ];
}
// Same walk-up-to-District logic as ms-team-view.php's own
// msTeamViewDistrictForNode() — depth 3 is always DISTRICT in this tree.
function msLocReportDistrictForNode(int $locId, array $allLocNodes): ?string {
    if (!isset($allLocNodes[$locId])) return null;
    $cur = $allLocNodes[$locId];
    if ($cur['depth'] <= 2) return null; // STATE/COUNTRY-level — no single district to show
    while ($cur !== null && $cur['depth'] !== 3) {
        $cur = $cur['parent_id'] !== null ? ($allLocNodes[$cur['parent_id']] ?? null) : null;
    }
    return $cur['name'] ?? null;
}

$staffRows = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id, ms.team_level_id, tl.level_name, tl.level_rank, mgr.ms_name AS manager_name
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    LEFT JOIN marketing_staff mgr ON mgr.id = ms.manager_id
    WHERE ms.deleted_at IS NULL
    ORDER BY (tl.level_rank IS NULL) ASC, tl.level_rank ASC, ms.ms_name ASC
")->fetch_all(MYSQLI_ASSOC);

$locsByMs = [];
$_ar = $db_conn->query("SELECT ms_id, location_id FROM marketing_staff_locations");
if ($_ar) {
    while ($row = $_ar->fetch_assoc()) {
        $locsByMs[(int)$row['ms_id']][] = (int)$row['location_id'];
    }
}

$rows = [];
foreach ($staffRows as $staff) {
    $msId = (int)$staff['id'];
    $locIds = $locsByMs[$msId] ?? [];
    if (empty($locIds)) {
        $rows[] = [
            'ms_name' => $staff['ms_name'], 'level_name' => $staff['level_name'] ?: '-',
            'level_color' => $levelColorMap[(int)$staff['team_level_id']] ?? '#999999',
            'manager_name' => $staff['manager_name'] ?: '-',
            'loc_name' => '', 'loc_depth_label' => '', 'district' => '',
        ];
        continue;
    }
    foreach ($locIds as $locId) {
        $node = $allLocNodes[$locId] ?? null;
        if (!$node) continue;
        $district = msLocReportDistrictForNode($locId, $allLocNodes);
        $rows[] = [
            'ms_name' => $staff['ms_name'], 'level_name' => $staff['level_name'] ?: '-',
            'level_color' => $levelColorMap[(int)$staff['team_level_id']] ?? '#999999',
            'manager_name' => $staff['manager_name'] ?: '-',
            'loc_name' => $node['name'],
            'loc_depth_label' => $layerNameByDepth[$node['depth']] ?? ('Depth ' . $node['depth']),
            'district' => ($node['depth'] === 3) ? $node['name'] : ($district ?? ''),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Location Assignment Report : <?php echo $business_name; ?></title>
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
        .tp-tag { font-size:11px; padding:2px 8px; border-radius:6px; font-weight:600; white-space:nowrap; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:9px 12px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
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
                                <h1><table class="headertble"><tr><td>Location Assignment Report</td></tr></table></h1>
                            </div>
                            <p class="text-muted" style="font-size:12.5px;">Every marketing staff member's assigned location(s) — search by staff name, District, or Division/Taluk/Firka name to find who owns a given area.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <input type="text" id="locReportSearchBox" class="form-control form-control-sm" style="max-width:360px;" placeholder="Search by name, district, division...">
                                    </div>
                                    <div style="overflow-x:auto;">
                                    <table class="mt" id="locReportTable">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Level</th>
                                                <th>Reports To</th>
                                                <th>District</th>
                                                <th>Assigned Location</th>
                                                <th>Depth</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($r['ms_name']); ?></b></td>
                                                <td><span class="tp-tag" style="color:<?php echo $r['level_color']; ?>;background:<?php echo $r['level_color']; ?>1a;"><?php echo htmlspecialchars($r['level_name']); ?></span></td>
                                                <td><?php echo htmlspecialchars($r['manager_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['district']); ?></td>
                                                <td><?php echo $r['loc_name'] !== '' ? htmlspecialchars($r['loc_name']) : '<span class="text-muted">No location assigned</span>'; ?></td>
                                                <td><?php echo htmlspecialchars($r['loc_depth_label']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
(function () {
    var searchBox = document.getElementById('locReportSearchBox');
    var rows = Array.prototype.slice.call(document.querySelectorAll('#locReportTable tbody tr'));
    searchBox.addEventListener('input', function () {
        var q = searchBox.value.trim().toLowerCase();
        rows.forEach(function (tr) {
            var text = tr.textContent.toLowerCase();
            tr.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
        });
    });
})();
</script>
</body>
</html>
