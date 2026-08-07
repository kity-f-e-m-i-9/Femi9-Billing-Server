<?php
include("checksession.php");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$prid     = isset($_GET['prid']) ? trim($_GET['prid']) : '';
$level_id = $prid ? (int) base64_decode($prid) : 0;

if (!$level_id) {
    header("Location: manage-salesbdm-team-levels");
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    location_layer_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");

$stmt_l = $db_conn->prepare("SELECT id, level_rank, level_name, location_layer_id FROM salesbdm_team_levels WHERE id = ?");
$stmt_l->bind_param("i", $level_id);
$stmt_l->execute();
$level = $stmt_l->get_result()->fetch_assoc();
$stmt_l->close();

if (!$level) {
    header("Location: manage-salesbdm-team-levels");
    exit;
}

$_chkSbdm = $db_conn->query("SHOW COLUMNS FROM partner_location_layers LIKE 'is_salesbdm_filter_enabled'");
if ($_chkSbdm && $_chkSbdm->num_rows === 0) {
    $db_conn->query("ALTER TABLE partner_location_layers ADD COLUMN is_salesbdm_filter_enabled TINYINT(1) NOT NULL DEFAULT 0");
}
$locationLayers = $db_conn->query("SELECT id, depth, layer_name FROM partner_location_layers WHERE is_salesbdm_filter_enabled = 1 ORDER BY depth ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Team Level : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
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
                                            <td>Edit Team Level</td>
                                            <td>
                                                <a href="manage-salesbdm-team-levels" title="Manage Levels">&#9776;</a>
                                            </td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">

                                    <?php if (isset($_REQUEST['alreadyexists'])): ?>
                                        <div class="alert alert-danger">A level for rank <?php echo (int)($_GET['rank'] ?? 0); ?> already exists.</div>
                                    <?php endif; ?>

                                    <?php include("validate-scripts.php"); ?>

                                    <form action="salesbdm-team-level-action" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="update_id" value="<?php echo (int)$level['id']; ?>">
                                        <input type="hidden" name="prid" value="<?php echo htmlspecialchars($prid); ?>">

                                        <div class="example-container">
                                            <div class="example-content">

                                                <div class="mb-3">
                                                    <label class="form-label">Rank <span class="text-danger">*</span></label>
                                                    <input type="number" required name="level_rank"
                                                           class="form-control"
                                                           value="<?php echo (int)$level['level_rank']; ?>"
                                                           min="1" max="100"
                                                           style="max-width:100px;">
                                                    <small class="text-muted">Each rank must be unique. Rank 1 is the top level.</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Level Name <span class="text-danger">*</span></label>
                                                    <input type="text" required name="level_name"
                                                           class="form-control"
                                                           value="<?php echo htmlspecialchars($level['level_name']); ?>"
                                                           maxlength="50"
                                                           onkeypress="restrictSpecialChars(event)">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Location Layer</label>
                                                    <select name="location_layer_id" class="form-control">
                                                        <option value="">-- None --</option>
                                                        <?php while ($resultLL = $locationLayers->fetch_assoc()): ?>
                                                            <option value="<?php echo (int)$resultLL['id']; ?>" <?php echo ((int)$level['location_layer_id'] === (int)$resultLL['id']) ? 'selected' : ''; ?>>
                                                                Depth <?php echo (int)$resultLL['depth']; ?> - <?php echo htmlspecialchars($resultLL['layer_name']); ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                    <small class="text-muted">Staff at this level will only be assignable locations from this layer.</small>
                                                </div>

                                                <br>
                                                <button type="submit" name="update-salesbdm-team-level" class="btn btn-primary">
                                                    <i class="material-icons">save</i> Save Changes
                                                </button>
                                                <a href="manage-salesbdm-team-levels" class="btn btn-secondary ms-2">Cancel</a>

                                            </div>
                                        </div>
                                    </form>

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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
