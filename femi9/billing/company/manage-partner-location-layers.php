<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('partner_location');
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load all layers with node counts in a single query
$stmt_l = $db_conn->query("
    SELECT l.id, l.depth, l.layer_name,
           COALESCE(n.cnt, 0) AS node_count
    FROM partner_location_layers l
    LEFT JOIN (
        SELECT depth, COUNT(*) AS cnt FROM partner_location_nodes GROUP BY depth
    ) n ON n.depth = l.depth
    ORDER BY l.depth
");
$layers = $stmt_l ? $stmt_l->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner Location Layers : <?php echo $business_name; ?></title>
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
                                <div class="alert alert-success">Layer added successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['updatedSuccess'])): ?>
                                <div class="alert alert-info">Layer updated successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['deletedDone'])): ?>
                                <div class="alert alert-warning">Layer deleted successfully.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['hasNodes'])): ?>
                                <div class="alert alert-danger">Cannot delete: partner locations exist at this depth. Delete those locations first.</div>
                            <?php endif; ?>
                            <?php if (isset($_REQUEST['alreadyexists'])): ?>
                                <div class="alert alert-danger">A layer for this depth already exists.</div>
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
                                            <td>Partner Location Layers</td>
                                            <td>
                                                <a href="add-partner-location-layer" title="Add Layer">&#10011;</a>
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
                                Define named layers for each depth level. Example: Depth 1 = <strong>Country</strong>, Depth 2 = <strong>State</strong>, Depth 3 = <strong>City</strong>.
                                Layers determine the labels shown when adding or viewing partner locations.
                                <a href="manage-partner-location" class="ms-2">Go to Partner Locations &rarr;</a>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <div style="overflow-x:scroll;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Depth</th>
                                                    <th>Layer Name</th>
                                                    <th>Nodes at this depth</th>
                                                    
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($layers as $layer):
                                                $enc_id   = base64_encode((string)$layer['id']);
                                                $node_cnt = (int)$layer['node_count'];
                                            ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge badge-secondary" style="font-size:13px;"><?php echo (int)$layer['depth']; ?></span>
                                                    </td>
                                                    <td><strong><?php echo htmlspecialchars($layer['layer_name']); ?></strong></td>
                                                    <td>
                                                        <?php if ($node_cnt > 0): ?>
                                                            <a href="manage-partner-location">
                                                                <?php echo $node_cnt; ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="actions-group">
                                                            <a href="edit-partner-location-layer?prid=<?php echo $enc_id; ?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
                                                            <?php if ($node_cnt > 0): ?>
                                                                <button type="button" class="action-link" title="Cannot delete: <?php echo $node_cnt; ?> location(s) exist" disabled style="opacity:0.35;cursor:not-allowed;"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></button>
                                                            <?php else: ?>
                                                                <a href="delete-partner-location-layer?prid=<?php echo $enc_id; ?>" class="action-link delete" title="Delete" onclick="return confirm('Delete layer &quot;<?php echo addslashes(htmlspecialchars($layer['layer_name'])); ?>&quot;? This cannot be undone.');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($layers)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted" style="padding:20px;">
                                                        No layers configured yet.
                                                        <a href="add-partner-location-layer">Add your first layer</a>.
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
