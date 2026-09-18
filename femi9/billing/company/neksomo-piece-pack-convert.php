<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mapped normal company products only — never the raw NKS- placeholder
// products used by the purchase flow (see Global Constraints).
$products = $db_conn->query(
    "SELECT id, productName, pieces_per_pack FROM products
     WHERE pieces_per_pack > 1 AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL)
     ORDER BY productName ASC"
)->fetch_all(MYSQLI_ASSOC);

$godowns = $db_conn->query(
    "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC"
)->fetch_all(MYSQLI_ASSOC);

$warehouses = $db_conn->query(
    "SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Convert Pieces &harr; Packs : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                    <div class="page-description">
                        <h1>Convert Pieces &harr; Packs</h1>
                    </div>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <form action="neksomo-piece-pack-convert-action.php" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                                        <div class="mb-3">
                                            <label class="form-label">Product <span class="required">*</span></label>
                                            <select required name="product_id" id="productSelect" class="form-control">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($products as $p): ?>
                                                <option value="<?= (int)$p['id'] ?>" data-pieces-per-pack="<?= (int)$p['pieces_per_pack'] ?>">
                                                    <?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$p['pieces_per_pack'] ?>/pack)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Company Profile <span class="required">*</span></label>
                                            <select required name="godownid" id="godownSelect" class="form-control">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($godowns as $g): ?>
                                                <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Godown (physical) <span class="required">*</span></label>
                                            <select required name="warehouse_id" id="warehouseSelect" class="form-control">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($warehouses as $wh): ?>
                                                <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div id="currentStockPanel" class="alert alert-info" style="display:none;"></div>

                                        <div class="mb-3">
                                            <label class="form-label">Direction <span class="required">*</span></label><br>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="direction" id="dirP2P" value="pieces_to_pack" checked>
                                                <label class="form-check-label" for="dirP2P">Pieces &rarr; Pack (assemble)</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="direction" id="dirPack2P" value="pack_to_pieces">
                                                <label class="form-check-label" for="dirPack2P">Pack &rarr; Pieces (break open)</label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Number of Packs <span class="required">*</span></label>
                                            <input type="number" min="1" required name="pack_count" class="form-control">
                                            <div class="form-text">How many whole packs to assemble or break open.</div>
                                        </div>

                                        <button type="submit" class="btn btn-primary">Convert</button>
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
<script>
function refreshCurrentStock() {
    var productId = document.getElementById('productSelect').value;
    var godownId = document.getElementById('godownSelect').value;
    var warehouseId = document.getElementById('warehouseSelect').value;
    var panel = document.getElementById('currentStockPanel');
    if (!productId || !godownId || !warehouseId) {
        panel.style.display = 'none';
        return;
    }
    fetch('get-piece-pack-stock.php?product_id=' + encodeURIComponent(productId) + '&godown_id=' + encodeURIComponent(godownId) + '&warehouse_id=' + encodeURIComponent(warehouseId))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) { panel.style.display = 'none'; return; }
            panel.style.display = '';
            panel.textContent = 'Current stock: ' + data.closing_qty + ' pack(s), ' + data.extra_pieces + ' loose piece(s) — ' + data.pieces_per_pack + ' pieces per pack.';
        })
        .catch(function () { panel.style.display = 'none'; });
}
['productSelect', 'godownSelect', 'warehouseSelect'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', refreshCurrentStock);
});
</script>
</body>
</html>
