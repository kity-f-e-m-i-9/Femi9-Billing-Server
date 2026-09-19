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
                        <div class="col-md-10">
                            <div class="card">
                                <div class="card-body">
                                    <form action="neksomo-piece-pack-convert-action.php" method="post" id="convertForm">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

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

                                        <hr>

                                        <label class="form-label">Products to Convert <span class="required">*</span></label>
                                        <div id="productRows"></div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="addProductRowBtn">
                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">add</i> Add Another Product
                                        </button>

                                        <div>
                                            <button type="submit" class="btn btn-primary">Convert All</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- One row's markup, cloned by JS for each product added.
                         Kept as an inert <template> so its inputs are never
                         part of the actual form until cloned in. -->
                    <template id="productRowTemplate">
                        <div class="row g-2 align-items-end product-row mb-2 pb-2" style="border-bottom:1px solid #eee;">
                            <div class="col-md-4">
                                <label class="form-label small">Product</label>
                                <select required name="product_id[]" class="form-control product-select">
                                    <option value="" hidden>Select</option>
                                    <?php foreach ($products as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" data-pieces-per-pack="<?= (int)$p['pieces_per_pack'] ?>">
                                        <?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$p['pieces_per_pack'] ?>/pack)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Direction</label>
                                <select required name="direction[]" class="form-control direction-select">
                                    <option value="pieces_to_pack">Pieces &rarr; Pack (assemble)</option>
                                    <option value="pack_to_pieces">Pack &rarr; Pieces (break open)</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Number of Packs</label>
                                <input type="number" min="1" required name="pack_count[]" class="form-control pack-count-input">
                            </div>
                            <div class="col-md-2">
                                <div class="current-stock-panel small text-muted"></div>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn" title="Remove this product">
                                    <i class="material-icons" style="font-size:16px;">delete_outline</i>
                                </button>
                            </div>
                        </div>
                    </template>
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
var rowTemplate = document.getElementById('productRowTemplate');
var rowsContainer = document.getElementById('productRows');

function refreshRowStock(rowEl) {
    var productId = rowEl.querySelector('.product-select').value;
    var godownId = document.getElementById('godownSelect').value;
    var warehouseId = document.getElementById('warehouseSelect').value;
    var panel = rowEl.querySelector('.current-stock-panel');
    if (!productId || !godownId || !warehouseId) {
        panel.textContent = '';
        return;
    }
    fetch('get-piece-pack-stock.php?product_id=' + encodeURIComponent(productId) + '&godown_id=' + encodeURIComponent(godownId) + '&warehouse_id=' + encodeURIComponent(warehouseId))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) { panel.textContent = ''; return; }
            panel.textContent = data.closing_qty + ' pack(s), ' + data.extra_pieces + ' pc(s)';
        })
        .catch(function () { panel.textContent = ''; });
}

function refreshAllRowsStock() {
    rowsContainer.querySelectorAll('.product-row').forEach(refreshRowStock);
}

function addProductRow() {
    var fragment = rowTemplate.content.cloneNode(true);
    var rowEl = fragment.querySelector('.product-row');
    rowEl.querySelector('.product-select').addEventListener('change', function () { refreshRowStock(rowEl); });
    rowEl.querySelector('.remove-row-btn').addEventListener('click', function () {
        // Always keep at least one row — removing the last one would let
        // the form submit with no products[] entries at all.
        if (rowsContainer.querySelectorAll('.product-row').length > 1) {
            rowEl.remove();
        }
    });
    rowsContainer.appendChild(fragment);
}

document.getElementById('addProductRowBtn').addEventListener('click', addProductRow);
['godownSelect', 'warehouseSelect'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', refreshAllRowsStock);
});

// Start with one row so the form is usable immediately.
addProductRow();
</script>
</body>
</html>
