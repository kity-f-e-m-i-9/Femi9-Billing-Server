<?php
include("checksession.php");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$prid    = isset($_GET['prid']) ? trim($_GET['prid']) : '';
$layer_id = $prid ? (int) base64_decode($prid) : 0;

if (!$layer_id) {
    header("Location: manage-partner-location-layers");
    exit;
}

$_chk = $db_conn->query("SHOW COLUMNS FROM partner_location_layers LIKE 'is_stock_location'");
if ($_chk && $_chk->num_rows === 0) {
    $db_conn->query("ALTER TABLE partner_location_layers ADD COLUMN is_stock_location TINYINT(1) NOT NULL DEFAULT 1 AFTER layer_name");
}

$stmt_l = $db_conn->prepare("SELECT id, depth, layer_name, is_stock_location, is_cp_filter_enabled, is_tp_filter_enabled FROM partner_location_layers WHERE id = ?");
$stmt_l->bind_param("i", $layer_id);
$stmt_l->execute();
$layer = $stmt_l->get_result()->fetch_assoc();
$stmt_l->close();

if (!$layer) {
    header("Location: manage-partner-location-layers");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Location Layer : <?php echo $business_name; ?></title>
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
                                            <td>Edit Location Layer</td>
                                            <td>
                                                <a href="manage-partner-location-layers" title="Manage Layers">&#9776;</a>
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

                                    <p class="text-muted mb-3" style="font-size:13px;">
                                        <i class="material-icons-two-tone" style="vertical-align:middle;font-size:16px;">layers</i>
                                        Depth: <strong><?php echo (int)$layer['depth']; ?></strong>
                                        &nbsp;(depth cannot be changed)
                                    </p>

                                    <?php include("validate-scripts.php"); ?>

                                    <form action="partner-location-layer-action" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="update_id" value="<?php echo (int)$layer['id']; ?>">
                                        <input type="hidden" name="prid" value="<?php echo htmlspecialchars($prid); ?>">

                                        <div class="example-container">
                                            <div class="example-content">

                                                <div class="mb-3">
                                                    <label class="form-label">Layer Name <span class="text-danger">*</span></label>
                                                    <input type="text" required name="pll_name"
                                                           class="form-control"
                                                           value="<?php echo htmlspecialchars($layer['layer_name']); ?>"
                                                           maxlength="50"
                                                           onkeypress="restrictSpecialChars(event)">
                                                </div>

                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="is_stock_location" id="is_stock_location" value="1"
                                                               <?php echo $layer['is_stock_location'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="is_stock_location">Stock Location</label>
                                                    </div>
                                                    <small class="text-muted">Mark if this layer represents a stock-holding location.</small>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="is_cp_filter_enabled" id="is_cp_filter_enabled" value="1"
                                                               <?php echo $layer['is_cp_filter_enabled'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="is_cp_filter_enabled">CP Filter Enabled</label>
                                                    </div>
                                                    <small class="text-muted">Mark if this layer should appear as a filter option in CP pages.</small>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="is_tp_filter_enabled" id="is_tp_filter_enabled" value="1"
                                                               <?php echo $layer['is_tp_filter_enabled'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="is_tp_filter_enabled">TP Filter Enabled</label>
                                                    </div>
                                                    <small class="text-muted">Mark if this layer should appear as a filter option in TP pages.</small>
                                                </div>

                                                <br>
                                                <button type="submit" name="update-partner-location-layer" class="btn btn-primary">
                                                    <i class="material-icons">save</i> Save Changes
                                                </button>
                                                <a href="manage-partner-location-layers" class="btn btn-secondary ms-2">Cancel</a>

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
