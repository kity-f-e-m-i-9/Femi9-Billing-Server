<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';
error_reporting(0);

$msId = (int)$Login_user_IDvl;

// Same scope resolution as my-assigned-tps.php — District OR Division depth
// nodes directly assigned to this person.
$scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);

// Every Firka (depth 6) under each assigned scope node, with its currently
// assigned TP (territory_partner_locations is the live mapping) and the
// most recent entry from tp_daily_assignments — read-only, so a DM can see
// what an ASM (or anyone else with access) has assigned in their own area
// without being able to change it themselves.
$firkasByScope = [];
foreach ($scopeNodes as $node) {
    $stmt = $db_conn->prepare("
        WITH RECURSIVE node_tree AS (
            SELECT id FROM partner_location_nodes WHERE id = ?
            UNION ALL
            SELECT n.id FROM partner_location_nodes n JOIN node_tree nt ON n.parent_id = nt.id
        )
        SELECT
            f.id AS firka_id, f.name AS firka_name,
            tp.id AS tp_id, tp.name AS tp_name,
            tda.assigned_by, tda.assign_date
        FROM partner_location_nodes f
        LEFT JOIN territory_partner_locations tpl ON tpl.location_id = f.id
        LEFT JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
        LEFT JOIN tp_daily_assignments tda ON tda.id = (
            SELECT id FROM tp_daily_assignments
            WHERE location_id = f.id
            ORDER BY assign_date DESC, updated_at DESC
            LIMIT 1
        )
        WHERE f.depth = 6 AND f.is_active = 1 AND f.id IN (SELECT id FROM node_tree)
        ORDER BY f.name ASC
    ");
    $stmt->bind_param('i', $node['id']);
    $stmt->execute();
    $firkasByScope[$node['name']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Firka Assignments : <?php echo $business_name; ?></title>
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
        .status-badge.filled { background:#dcfce7; color:#059669; }
        .status-badge.vacant { background:#fef3c7; color:#b45309; }
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
                                        <tr><td>Firka Assignments</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>
                    <p style="color:#6b7280;font-size:13px;margin-top:-10px;">Read-only — shows which Territory Partner currently covers each Firka in your assigned area(s), and who last assigned it.</p>

                    <?php if (empty($scopeNodes)): ?>
                    <div class="main-card district-tp-card">
                        <div style="padding:30px;text-align:center;color:#6b7280;">
                            No district is assigned to you yet.
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($firkasByScope as $scopeName => $firkas): ?>
                        <div class="main-card district-tp-card">
                            <div class="main-card-header">
                                <div class="main-card-header-left">
                                    <div class="header-icon-box"><i class="material-icons-outlined">map</i></div>
                                    <div>
                                        <p class="header-title" style="margin:0;font-weight:600;"><?php echo htmlspecialchars($scopeName); ?></p>
                                        <p class="header-sub" style="margin:0;color:#6b7280;font-size:12px;"><?php echo count($firkas); ?> Firka(s)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="padding:0 18px 16px;">
                                <table class="tp-roster-plain">
                                    <thead>
                                        <tr>
                                            <th style="width:48px;text-align:center;">#</th>
                                            <th>Firka</th>
                                            <th>Current TP</th>
                                            <th style="text-align:center;">Status</th>
                                            <th>Last Assigned By</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($firkas)): ?>
                                        <tr><td colspan="6" style="text-align:center;color:#6b7280;padding:20px;">No Firka found in this area.</td></tr>
                                    <?php else: $i = 0; foreach ($firkas as $f): $i++; ?>
                                        <tr>
                                            <td style="text-align:center;color:#9ca3af;"><?php echo $i; ?></td>
                                            <td><b><?php echo htmlspecialchars($f['firka_name']); ?></b></td>
                                            <td><?php echo $f['tp_name'] ? htmlspecialchars($f['tp_name']) : '—'; ?></td>
                                            <td style="text-align:center;">
                                                <?php if ($f['tp_id']): ?>
                                                <span class="status-badge filled">Filled</span>
                                                <?php else: ?>
                                                <span class="status-badge vacant">Vacant</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $f['assigned_by'] ? htmlspecialchars($f['assigned_by']) : '—'; ?></td>
                                            <td><?php echo $f['assign_date'] ? htmlspecialchars($f['assign_date']) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
