<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("include/TeamLevelColors.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    location_layer_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");

$stmt_l = $db_conn->query("
    SELECT l.id, l.level_rank, l.level_name, ll.layer_name,
           COALESCE(s.cnt, 0) AS staff_count
    FROM salesbdm_team_levels l
    LEFT JOIN partner_location_layers ll ON ll.id = l.location_layer_id
    LEFT JOIN (
        SELECT team_level_id, COUNT(*) AS cnt FROM sales_bdm_staff GROUP BY team_level_id
    ) s ON s.team_level_id = l.id
    ORDER BY l.level_rank
");
$levels = $stmt_l ? $stmt_l->fetch_all(MYSQLI_ASSOC) : [];
$levelColorMap = getSalesBdmTeamLevelColorMap($db_conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales BDM Team Levels : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }

        .level-color-card {
            border:1px solid #e5e7eb; border-left:6px solid var(--lvl-color,#667eea);
            border-radius:10px; padding:14px 16px; background:#fff; height:100%;
        }
        .level-color-card .rank { font-size:11px; font-weight:700; color:#999; text-transform:uppercase; letter-spacing:.5px; }
        .level-color-card .name { font-size:16px; font-weight:700; color:var(--lvl-color,#333); margin-top:2px; }
        .level-color-card .count { font-size:12px; color:#666; margin-top:6px; }
        .level-color-swatch { display:inline-block; width:12px; height:12px; border-radius:50%; background:var(--lvl-color,#999); vertical-align:middle; margin-right:6px; }
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

                    <!-- Alerts -->
                    <div class="row">
                        <div class="col">
                            <?php if (isset($_REQUEST['addesuccess'])): ?>
                                <div class="alert alert-success">Team level added successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['updatedSuccess'])): ?>
                                <div class="alert alert-info">Team level updated successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['deletedDone'])): ?>
                                <div class="alert alert-warning">Team level deleted successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['hasStaff'])): ?>
                                <div class="alert alert-danger">Cannot delete: Sales BDM are assigned to this level. Reassign them first.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['alreadyexists'])): ?>
                                <div class="alert alert-danger">A level for this rank already exists.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Page header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>Sales BDM Team Levels</td>
                                            <td>
                                                <a href="add-salesbdm-team-level" title="Add Level">&#10011;</a>
                                            </td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Info note -->
                    <div class="row mb-3">
                        <div class="col">
                            <div class="alert alert-info" style="font-size:13px;margin-bottom:0;">
                                <i class="material-icons-two-tone" style="vertical-align:middle;font-size:16px;">info</i>
                                Define the Sales BDM team hierarchy levels (e.g. Rank 1 = top, Rank 2 = below, and so on).
                                Rank 1 is the top of the hierarchy. These levels appear as the "Team Level" option when adding or editing a Sales BDM.
                            </div>
                        </div>
                    </div>

                    <!-- Color cards -->
                    <?php if (!empty($levels)): ?>
                    <div class="row mb-3">
                        <?php foreach ($levels as $level): ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="level-color-card" style="--lvl-color:<?php echo $levelColorMap[(int)$level['id']] ?? '#667eea'; ?>;">
                                    <div class="rank">Rank <?php echo (int)$level['level_rank']; ?></div>
                                    <div class="name"><?php echo htmlspecialchars($level['level_name']); ?></div>
                                    <div class="count"><?php echo (int)$level['staff_count']; ?> staff</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Table -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <div style="overflow-x:scroll;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Level Name</th>
                                                    <th>Location Layer</th>
                                                    <th>Staff at this level</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($levels as $level):
                                                $enc_id = base64_encode((string)$level['id']);
                                                $staff_cnt = (int)$level['staff_count'];
                                            ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge badge-secondary" style="font-size:13px;"><?php echo (int)$level['level_rank']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="level-color-swatch" style="--lvl-color:<?php echo $levelColorMap[(int)$level['id']] ?? '#667eea'; ?>;"></span>
                                                        <strong><?php echo htmlspecialchars($level['level_name']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($level['layer_name'])): ?>
                                                            <?php echo htmlspecialchars($level['layer_name']); ?>
                                                        <?php else: ?>
                                                            <span class="text-danger">Not linked</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($staff_cnt > 0): ?>
                                                            <a href="salesbdm_manage">
                                                                <?php echo $staff_cnt; ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="actions-group">
                                                            <a href="edit-salesbdm-team-level?prid=<?php echo $enc_id; ?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
                                                            <?php if ($staff_cnt > 0): ?>
                                                                <button type="button" class="action-link" title="Cannot delete: <?php echo $staff_cnt; ?> staff assigned" disabled style="opacity:0.35;cursor:not-allowed;"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></button>
                                                            <?php else: ?>
                                                                <a href="delete-salesbdm-team-level?prid=<?php echo $enc_id; ?>" class="action-link delete" title="Delete" onclick="return confirm('Delete level &quot;<?php echo addslashes(htmlspecialchars($level['level_name'])); ?>&quot;? This cannot be undone.');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($levels)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted" style="padding:20px;">
                                                        No team levels configured yet.
                                                        <a href="add-salesbdm-team-level">Add your first level</a>.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
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
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
</body>
</html>
