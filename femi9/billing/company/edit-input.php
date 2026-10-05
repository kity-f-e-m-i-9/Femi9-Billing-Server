<?php
/**
 * Edit Input Stock
 *
 * Single-product edit of an existing input_stock row. On save, the old
 * credit is reversed and the new values are credited fresh (same approach
 * delete-input.php already uses for reversal), so stock/closing_qty and
 * the ledger stay consistent.
 */

ob_start();
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('manage_input_stock_edit');
include("config.php");
require_once __DIR__ . "/include/GodownAccess.php";

date_default_timezone_set("Asia/Kolkata");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$rowid = (int) base64_decode($_GET['Roowid'] ?? '');
if ($rowid <= 0) {
    header('Location: manage-input');
    exit;
}

$stmt = $db_conn->prepare("SELECT * FROM input_stock WHERE id = ?");
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    header('Location: manage-input');
    exit;
}

if (!is_godown_allowed($db_conn, (int)$record['godownid'])) {
    header('Location: manage-input');
    exit;
}

$godowns = [];
$resGd = $db_conn->query("SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC");
while ($row = $resGd->fetch_assoc()) {
    $godowns[] = $row;
}

$products = [];
$resProd = $db_conn->query("SELECT id, productName FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY productName ASC");
while ($row = $resProd->fetch_assoc()) {
    $products[] = $row;
}

$warehouses = [];
$resWh = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($row = $resWh->fetch_assoc()) {
    $warehouses[] = $row;
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Input Stock : <?= htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
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
                                            <td>Edit Input Stock</td>
                                            <td><a href="manage-input" title="Manage Input Stock">&#9776;</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_GET['saveerror'])): ?>
                        <div class="alert alert-danger">Failed to save. Please try again.</div>
                    <?php endif; ?>

                    <?php if (isset($_GET['invalid'])): ?>
                        <div class="alert alert-danger">Invalid input. Please check all fields and try again.</div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

                                    <form action="edit-input-action" method="post"
                                          onsubmit="return confirm('Save changes to this input stock entry?');">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">

                                        <div class="mb-3">
                                            <label class="form-label">Company Profile *</label>
                                            <select required name="godownid" class="form-control">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($godowns as $gd): ?>
                                                    <option value="<?= (int) $gd['id'] ?>" <?= ((int)$gd['id'] === (int)$record['godownid']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($gd['gname'], ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Date *</label>
                                            <input type="date" required name="input_date"
                                                   value="<?= htmlspecialchars($record['input_date'], ENT_QUOTES, 'UTF-8') ?>"
                                                   class="form-control">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Warehouse (physical) *</label>
                                            <select required name="warehouse_id" class="form-control">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($warehouses as $wh): ?>
                                                    <option value="<?= (int) $wh['id'] ?>" <?= ((int)$wh['id'] === (int)$record['warehouse_id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Physical storage location this stock is being received into.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Product *</label>
                                            <select required name="product_id" class="form-control">
                                                <option value="" hidden>Select Product</option>
                                                <?php foreach ($products as $p): ?>
                                                    <option value="<?= (int) $p['id'] ?>" <?= ((int)$p['id'] === (int)$record['product_id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Qty *</label>
                                            <input type="number" required min="1" name="input_qty"
                                                   value="<?= (int) $record['input_qty'] ?>"
                                                   class="form-control">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Remarks *</label>
                                            <textarea required name="input_remarks" rows="2"
                                                      class="form-control"><?= htmlspecialchars($record['input_remarks'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </div>

                                        <button type="submit" class="btn btn-primary">
                                            <i class="material-icons">save</i> Save Changes
                                        </button>
                                        <a href="manage-input" class="btn btn-secondary">Cancel</a>

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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
