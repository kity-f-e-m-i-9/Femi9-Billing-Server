<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/GodownWarehouseMapping.php");

// Dedicated to the finance login (godown/company-profile assignment is a
// finance-level concern, matching every other finance-only page's gate).
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$title = "Manage Godowns";
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errorMessage = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            if ($code === '') {
                $errorMessage = 'Godown code is required.';
            } else {
                $stmt = mysqli_prepare($db_conn, "INSERT INTO warehouses (code, name) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "ss", $code, $name);
                if (mysqli_stmt_execute($stmt)) {
                    header('Location: manage-warehouses.php?addesuccess');
                    exit;
                } else {
                    $errorMessage = (mysqli_errno($db_conn) === 1062) ? 'That godown code already exists.' : 'Could not add godown.';
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'edit') {
            $id   = (int)($_POST['id'] ?? 0);
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            if ($id > 0 && $code !== '') {
                $stmt = mysqli_prepare($db_conn, "UPDATE warehouses SET code = ?, name = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ssi", $code, $name, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $linkedGodownIds = array_map('intval', $_POST['linked_godowns'] ?? []);
                    set_godowns_for_warehouse($db_conn, $id, $linkedGodownIds);
                    header('Location: manage-warehouses.php?updatedSuccess');
                    exit;
                } else {
                    $errorMessage = (mysqli_errno($db_conn) === 1062) ? 'That godown code already exists.' : 'Could not update godown.';
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action === 'toggle_active') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = mysqli_prepare($db_conn, "UPDATE warehouses SET is_active = NOT is_active WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header('Location: manage-warehouses.php?updatedSuccess');
                exit;
            }
        }
    }
}

$warehouses = [];
$res = mysqli_query($db_conn, "SELECT id, code, name, is_active FROM warehouses ORDER BY code ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $warehouses[] = $row;
}

$eligibleGodowns = [];
$res2 = mysqli_query($db_conn, "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC");
while ($row = mysqli_fetch_assoc($res2)) {
    $eligibleGodowns[] = $row;
}

// Pre-load each warehouse's currently-linked company profile ids, so the
// checkbox list below can mark them checked.
$linkedByWarehouse = [];
foreach ($warehouses as $wh) {
    $linked = get_godowns_for_warehouse($db_conn, (int) $wh['id']);
    $linkedByWarehouse[(int) $wh['id']] = array_column($linked, 'id');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

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
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
        .badge-active { background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-inactive { background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
    </style>
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
            <?php include("app-header.php");?>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <?php if(isset($_REQUEST['addesuccess'])){?><div class="alert alert-success">Godown added successfully.</div><?php }?>
                                    <?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved successfully.</div><?php }?>
                                    <?php if($errorMessage){?><div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage);?></div><?php }?>
                                    <h1><?php echo $title;?></h1>
                                    <p class="text-muted">Godowns are physical storage locations (e.g. H1, G1, G2) used to tag stock. A godown can hold stock from any company entity (LLP / Healthcare / Neksomo).</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xl-4">
                                <div class="card">
                                    <div class="card-header"><h5 class="card-title">Add Godown</h5></div>
                                    <div class="card-body">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                                            <input type="hidden" name="action" value="add">
                                            <div class="mb-3">
                                                <label class="form-label">Code</label>
                                                <input type="text" name="code" class="form-control" required maxlength="32" placeholder="e.g. H1">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Name (optional)</label>
                                                <input type="text" name="name" class="form-control" maxlength="255" placeholder="e.g. Hosur Warehouse 1">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Add Godown</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-8">
                                <div class="card">
                                    <div class="card-header"><h5 class="card-title">Godowns</h5></div>
                                    <div class="card-body">
                                        <?php if (empty($warehouses)): ?>
                                            <p class="text-muted">No godowns created yet.</p>
                                        <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Name</th>
                                                        <th>Status</th>
                                                        <th>Linked Company Profiles</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($warehouses as $wh): $rid = (int)$wh['id']; $editFormId = 'edit-form-' . $rid; $toggleFormId = 'toggle-form-' . $rid; ?>
                                                    <tr>
                                                        <td style="min-width:120px;">
                                                            <input form="<?php echo $editFormId; ?>" type="text" name="code" class="form-control form-control-sm" value="<?php echo htmlspecialchars($wh['code']); ?>" required maxlength="32">
                                                        </td>
                                                        <td style="min-width:200px;">
                                                            <input form="<?php echo $editFormId; ?>" type="text" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($wh['name'] ?? ''); ?>" maxlength="255">
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo $wh['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                                <?php echo $wh['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                        </td>
                                                        <td style="min-width:260px;">
                                                            <?php
                                                            $linkedIds = $linkedByWarehouse[$rid] ?? [];
                                                            $linkedNames = array_map(
                                                                fn($cg) => $cg['gname'],
                                                                array_filter($eligibleGodowns, fn($cg) => in_array((int) $cg['id'], $linkedIds, true))
                                                            );
                                                            ?>
                                                            <div style="margin-bottom:8px;">
                                                                <?php if (empty($linkedNames)): ?>
                                                                    <span class="text-muted small">Not linked to any company profile yet.</span>
                                                                <?php else: ?>
                                                                    <?php foreach ($linkedNames as $ln): ?>
                                                                    <span class="badge-active" style="margin-right:4px;margin-bottom:4px;display:inline-block;"><?php echo htmlspecialchars($ln); ?></span>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (empty($eligibleGodowns)): ?>
                                                                <span class="text-muted small">No company profiles available.</span>
                                                            <?php else: ?>
                                                                <?php foreach ($eligibleGodowns as $cg): $cgId = (int) $cg['id']; ?>
                                                                <div class="form-check form-check-inline" style="margin-bottom:4px;">
                                                                    <input class="form-check-input" type="checkbox"
                                                                           form="<?php echo $editFormId; ?>"
                                                                           name="linked_godowns[]"
                                                                           value="<?php echo $cgId; ?>"
                                                                           id="linked_<?php echo $rid; ?>_<?php echo $cgId; ?>"
                                                                           <?php echo in_array($cgId, $linkedByWarehouse[$rid] ?? [], true) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label small" for="linked_<?php echo $rid; ?>_<?php echo $cgId; ?>">
                                                                        <?php echo htmlspecialchars($cg['gname']); ?>
                                                                    </label>
                                                                </div>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="actions-group">
                                                                <form id="<?php echo $editFormId; ?>" method="post" style="display:inline;">
                                                                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                                                                    <input type="hidden" name="action" value="edit">
                                                                    <input type="hidden" name="id" value="<?php echo $rid; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                                                </form>
                                                                <form id="<?php echo $toggleFormId; ?>" method="post" style="display:inline;" onsubmit="return confirm('<?php echo $wh['is_active'] ? 'Deactivate' : 'Reactivate'; ?> this godown?');">
                                                                    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                                                                    <input type="hidden" name="action" value="toggle_active">
                                                                    <input type="hidden" name="id" value="<?php echo $rid; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $wh['is_active'] ? 'Deactivate' : 'Reactivate'; ?></button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
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

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
