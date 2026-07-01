<?php
include("checksession.php");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Load node from encoded ID ────────────────────────────────────────────────
$prid = isset($_GET['prid']) ? trim($_GET['prid']) : '';
$node_id = $prid ? (int) base64_decode($prid) : 0;

if (!$node_id) {
    header("Location: manage-partner-location");
    exit;
}

$stmt_n = $db_conn->prepare("SELECT * FROM partner_location_nodes WHERE id = ?");
$stmt_n->bind_param("i", $node_id);
$stmt_n->execute();
$node = $stmt_n->get_result()->fetch_assoc();
$stmt_n->close();

if (!$node) {
    header("Location: manage-partner-location");
    exit;
}

$return_parent = $node['parent_id'] !== null ? $node['parent_id'] : '';
$manage_url    = "manage-partner-location" . ($return_parent !== '' ? "?parent_id=$return_parent" : "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Partner Location : <?php echo $business_name; ?></title>
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
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
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
                                            <td>Edit Partner Location</td>
                                            <td>
                                                <a href="<?php echo $manage_url; ?>" title="Manage Partner Locations">&#9776;</a>
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
                                        <div class="alert alert-danger">A partner location with this name already exists at this level.</div>
                                    <?php endif; ?>
                                    <?php if (isset($_REQUEST['error'])): ?>
                                        <div class="alert alert-danger">An error occurred while saving. Please try again.</div>
                                    <?php endif; ?>

                                    <p class="text-muted mb-3" style="font-size:13px;">
                                        <i class="material-icons-two-tone" style="vertical-align:middle;font-size:16px;">place</i>
                                        Depth: <?php echo (int)$node['depth']; ?>
                                        &nbsp;|&nbsp; ID: <?php echo (int)$node['id']; ?>
                                    </p>

                                    <?php include("validate-scripts.php"); ?>

                                    <form action="partner-location-action" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="update_id" value="<?php echo (int)$node['id']; ?>">
                                        <input type="hidden" name="prid" value="<?php echo htmlspecialchars($prid); ?>">
                                        <input type="hidden" name="return_parent" value="<?php echo $return_parent; ?>">

                                        <div class="example-container">
                                            <div class="example-content">

                                                <div class="mb-3">
                                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                                    <input type="text" required name="pl_name"
                                                           class="form-control"
                                                           value="<?php echo htmlspecialchars($node['name']); ?>"
                                                           maxlength="150"
                                                           onkeypress="restrictSpecialChars(event)">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Code <span class="text-muted" style="font-size:12px;">(optional)</span></label>
                                                    <input type="text" name="pl_code"
                                                           class="form-control"
                                                           value="<?php echo htmlspecialchars($node['code'] ?? ''); ?>"
                                                           maxlength="50">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Amount <span class="text-muted" style="font-size:12px;">(optional)</span></label>
                                                    <input type="number" name="pl_deposit"
                                                           class="form-control"
                                                           value="<?php echo $node['deposit_amount'] !== null ? htmlspecialchars($node['deposit_amount']) : ''; ?>"
                                                           placeholder="0.00"
                                                           min="0" step="0.01">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="is_active" class="form-control">
                                                        <option value="1" <?php echo $node['is_active'] ? 'selected' : ''; ?>>Active</option>
                                                        <option value="0" <?php echo !$node['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </div>

                                                <br>
                                                <button type="submit" name="update-partner-location" class="btn btn-primary">
                                                    <i class="material-icons">save</i> Save Changes
                                                </button>
                                                <a href="<?php echo $manage_url; ?>" class="btn btn-secondary ms-2">Cancel</a>

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
