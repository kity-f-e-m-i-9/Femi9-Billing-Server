<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/GodownWarehouseMapping.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

// Finance-only, same as the rest of the Internal Stock Transfer area —
// but this is a deliberately SEPARATE concept: a direct godown-to-godown
// stock move with no invoice, never touching internal_transfer /
// internal_transfer_invoice or Auto Transfer's tables/logic.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Same product scope manual Internal Transfer uses (excludes Neksomo-
// internal temp_id-tagged placeholder products).
$products = $db_conn->query(
    "SELECT id, productName FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY productName ASC"
)->fetch_all(MYSQLI_ASSOC);

$godowns = $db_conn->query(
    "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC"
)->fetch_all(MYSQLI_ASSOC);

$allWarehouses = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Move Stock Between Godowns : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .gsm-remove-btn { width:36px; height:36px; border-radius:50%; border:1px solid #fecdd3; background:#fff; color:#e11d48; display:inline-flex; align-items:center; justify-content:center; padding:0; transition:background .15s, color .15s; }
        .gsm-remove-btn:hover { background:#e11d48; color:#fff; border-color:#e11d48; }
        .gsm-remove-btn:disabled { opacity:.35; cursor:not-allowed; }
        .gsm-remove-btn:disabled:hover { background:#fff; color:#e11d48; }
        .gsm-product-row { background:#f8f9fc; border:1px solid #eaecf5; border-radius:12px; padding:14px 16px; margin-bottom:12px; }
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
                    <div class="page-description">
                        <h1>
                            <table class="headertble">
                            <tr>
                            <td>Move Stock Between Godowns</td>
                            <td><a href="godown-stock-move-manage.php" title="Move History">&#9776;</a></td>
                            </tr>
                            </table>
                        </h1>
                        <p class="text-muted mb-0">Directly move stock from one company profile to another — for one product or several at once. No invoice is created. Separate from Internal Stock Transfer / Auto Transfer.</p>
                    </div>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <form action="godown-stock-move-action.php" method="post" id="moveForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="card mb-3" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.06);border-radius:14px;overflow:hidden;">
                            <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);padding:16px 22px;">
                                <span style="color:#fff;font-weight:600;font-size:15px;">
                                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">swap_horiz</i>
                                    Where is this move happening?
                                </span>
                            </div>
                            <div class="card-body" style="padding:20px 22px;">
                                <div class="row g-3">
                                    <div class="col-md-6 col-lg-3">
                                        <label class="form-label">From Company Profile <span class="required text-danger">*</span></label>
                                        <select required name="from_company_godown_id" id="fromGodownSelect" class="form-control">
                                            <option value="" hidden>Select</option>
                                            <?php foreach ($godowns as $g): ?>
                                            <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <label class="form-label">To Company Profile <span class="required text-danger">*</span></label>
                                        <select required name="to_company_godown_id" id="toGodownSelect" class="form-control">
                                            <option value="" hidden>Select</option>
                                            <?php foreach ($godowns as $g): ?>
                                            <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <label class="form-label">From Godown (physical)</label>
                                        <select name="from_warehouse_id" class="form-control">
                                            <option value="">— Not tracked —</option>
                                            <?php foreach ($allWarehouses as $wh): ?>
                                            <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <label class="form-label">To Godown (physical)</label>
                                        <select name="to_warehouse_id" class="form-control">
                                            <option value="">— Not tracked —</option>
                                            <?php foreach ($allWarehouses as $wh): ?>
                                            <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Note (optional)</label>
                                        <input type="text" name="note" maxlength="255" class="form-control" placeholder="Reason for this move, e.g. rebalancing stock">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.06);border-radius:14px;overflow:hidden;">
                            <div style="background:linear-gradient(135deg, #0891b2 0%, #0e7490 100%);padding:16px 22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                                <span style="color:#fff;font-weight:600;font-size:15px;">
                                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">inventory_2</i>
                                    Products to Move
                                </span>
                                <button type="button" class="btn btn-sm" id="addProductRowBtn" style="background:#fff;color:#0e7490;font-weight:600;border:none;">
                                    <i class="material-icons" style="font-size:15px;vertical-align:middle;">add</i> Add Another Product
                                </button>
                            </div>
                            <div class="card-body" style="padding:20px 22px;">
                                <div id="productRows"></div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3 mb-4">
                            <a href="godown-stock-move-manage.php" class="btn btn-secondary me-2">View History</a>
                            <button type="submit" class="btn btn-lg" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border:none;color:#fff;font-weight:600;padding:10px 32px;border-radius:10px;box-shadow:0 4px 12px rgba(102,126,234,.35);">
                                <i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">sync_alt</i> Move Stock
                            </button>
                        </div>
                    </form>

                    <!-- One row's markup, cloned by JS for each product added.
                         Kept as an inert <template> so its inputs are never
                         part of the actual form until cloned in. -->
                    <template id="productRowTemplate">
                        <div class="gsm-product-row">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-8 col-md-6">
                                    <label class="form-label small text-muted mb-1">Product</label>
                                    <select required name="product_id[]" class="form-control product-select">
                                        <option value="" hidden>Select</option>
                                        <?php foreach ($products as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-4">
                                    <label class="form-label small text-muted mb-1">Quantity</label>
                                    <input type="number" min="1" required name="qty[]" class="form-control" placeholder="e.g. 50">
                                </div>
                                <div class="col-lg-1 col-md-2 d-flex justify-content-md-end">
                                    <button type="button" class="gsm-remove-btn" title="Remove this product">
                                        <i class="material-icons-outlined" style="font-size:18px;">delete</i>
                                    </button>
                                </div>
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
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
var rowTemplate = document.getElementById('productRowTemplate');
var rowsContainer = document.getElementById('productRows');

// Always keep at least one row — disable every row's delete button while
// only one remains, rather than letting a click silently do nothing.
function updateRemoveButtonsState() {
    var rows = rowsContainer.querySelectorAll('.gsm-product-row');
    var onlyOneLeft = rows.length <= 1;
    rows.forEach(function (row) {
        row.querySelector('.gsm-remove-btn').disabled = onlyOneLeft;
    });
}

function addProductRow() {
    var fragment = rowTemplate.content.cloneNode(true);
    var rowEl = fragment.querySelector('.gsm-product-row');
    var productSelect = rowEl.querySelector('.product-select');
    rowEl.querySelector('.gsm-remove-btn').addEventListener('click', function () {
        $(productSelect).select2('destroy');
        rowEl.remove();
        updateRemoveButtonsState();
    });
    rowsContainer.appendChild(fragment);

    // Select2 must init after the element is actually in the DOM, not
    // while it's still inside the detached template fragment.
    $(productSelect).select2({ placeholder: 'Search a product…', width: '100%' });
    updateRemoveButtonsState();
}

document.getElementById('addProductRowBtn').addEventListener('click', addProductRow);

document.getElementById('moveForm').addEventListener('submit', function (e) {
    var fromGodown = document.getElementById('fromGodownSelect').value;
    var toGodown = document.getElementById('toGodownSelect').value;
    if (fromGodown && toGodown && fromGodown === toGodown) {
        e.preventDefault();
        alert('From and To company profile must be different.');
        return;
    }

    var productIds = Array.from(rowsContainer.querySelectorAll('.product-select')).map(function (s) { return s.value; });
    var seen = {};
    for (var i = 0; i < productIds.length; i++) {
        if (!productIds[i]) continue;
        if (seen[productIds[i]]) {
            e.preventDefault();
            alert('Each product can only appear once per move batch — remove the duplicate before submitting.');
            return;
        }
        seen[productIds[i]] = true;
    }
});

// Start with one row so the form is usable immediately.
addProductRow();
</script>
</body>
</html>
