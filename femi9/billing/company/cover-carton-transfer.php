<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/CartonBoxes.php");
require_once("include/ProductCovers.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

// Covers + Carton Box transfer for the Godown (stockviewer) login — same
// audience as the other Bundle-menu pages (Godown/stockviewer login,
// neksomo, admin). Moves only covers and carton boxes (no product stock,
// no invoice) between company profiles / physical godowns, reusing the
// same move_covers()/move_cartons() logic as godown-stock-move.php.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin', 'stockviewer'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$godowns = $db_conn->query(
    "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC"
)->fetch_all(MYSQLI_ASSOC);

$coverProducts = $db_conn->query(
    "SELECT DISTINCT p.id, p.productName FROM neksomo_product_mapping m
     INNER JOIN products p ON p.id = m.company_product_id ORDER BY p.productName ASC"
)->fetch_all(MYSQLI_ASSOC);
$cartonTypes = get_active_carton_types($db_conn);

$allWarehouses = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Covers &amp; Carton Box Transfer : <?php echo $business_name; ?></title>

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
                        <h1>Covers &amp; Carton Box Transfer</h1>
                        <p class="text-muted mb-0">Move covers and carton boxes from one company profile / godown to another. No invoice is created. The source balance must cover the quantity moved.</p>
                    </div>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <form action="cover-carton-transfer-action.php" method="post" id="moveForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="card mb-3" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.06);border-radius:14px;overflow:hidden;">
                            <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);padding:16px 22px;">
                                <span style="color:#fff;font-weight:600;font-size:15px;">
                                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">swap_horiz</i>
                                    Where is this transfer happening?
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
                                        <input type="text" name="note" maxlength="255" class="form-control" placeholder="Reason for this transfer">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php foreach ([
                            ['covers', 'Covers to Transfer', 'layers', '#f59e0b', '#d97706', 'cover_product_id[]', 'cover_qty[]', $coverProducts, 'productName', 'Cover for product'],
                            ['cartons', 'Carton Boxes to Transfer', 'archive', '#10b981', '#059669', 'carton_type_id[]', 'carton_qty[]', $cartonTypes, 'name', 'Carton type'],
                        ] as [$key, $title, $icon, $c1, $c2, $idName, $qtyName, $opts, $label, $lbl]): ?>
                        <div class="card mt-3" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.06);border-radius:14px;overflow:hidden;">
                            <div style="background:linear-gradient(135deg, <?= $c1 ?> 0%, <?= $c2 ?> 100%);padding:16px 22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                                <span style="color:#fff;font-weight:600;font-size:15px;">
                                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;"><?= $icon ?></i>
                                    <?= $title ?>
                                </span>
                                <button type="button" class="btn btn-sm add-extra-row" data-target="<?= $key ?>" style="background:#fff;color:<?= $c2 ?>;font-weight:600;border:none;">
                                    <i class="material-icons" style="font-size:15px;vertical-align:middle;">add</i> Add
                                </button>
                            </div>
                            <div class="card-body" style="padding:20px 22px;">
                                <div id="rows-<?= $key ?>"></div>
                                <template id="tpl-<?= $key ?>">
                                    <div class="gsm-product-row">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-lg-8 col-md-6">
                                                <label class="form-label small text-muted mb-1"><?= $lbl ?></label>
                                                <select name="<?= $idName ?>" class="form-control extra-select">
                                                    <option value="">Select</option>
                                                    <?php foreach ($opts as $o): ?>
                                                    <option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o[$label], ENT_QUOTES, 'UTF-8') ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-lg-3 col-md-4">
                                                <label class="form-label small text-muted mb-1">Quantity</label>
                                                <input type="number" min="1" name="<?= $qtyName ?>" class="form-control" placeholder="e.g. 100">
                                            </div>
                                            <div class="col-lg-1 col-md-2 d-flex justify-content-md-end">
                                                <button type="button" class="gsm-remove-btn" title="Remove"><i class="material-icons-outlined" style="font-size:18px;">delete</i></button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="d-flex justify-content-end mt-3 mb-4">
                            <a href="manage-covers.php" class="btn btn-secondary me-2">Covers</a>
                            <a href="manage-cartons.php" class="btn btn-secondary me-2">Cartons</a>
                            <button type="submit" class="btn btn-lg" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border:none;color:#fff;font-weight:600;padding:10px 32px;border-radius:10px;box-shadow:0 4px 12px rgba(102,126,234,.35);">
                                <i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">sync_alt</i> Transfer
                            </button>
                        </div>
                    </form>
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
document.querySelectorAll('.add-extra-row').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var key = btn.dataset.target;
        var frag = document.getElementById('tpl-' + key).content.cloneNode(true);
        var rowEl = frag.querySelector('.gsm-product-row');
        rowEl.querySelector('.gsm-remove-btn').addEventListener('click', function () { rowEl.remove(); });
        document.getElementById('rows-' + key).appendChild(frag);
    });
});

document.getElementById('moveForm').addEventListener('submit', function (e) {
    var fromGodown = document.getElementById('fromGodownSelect').value;
    var toGodown = document.getElementById('toGodownSelect').value;
    var fromWarehouse = document.querySelector('[name="from_warehouse_id"]').value;
    var toWarehouse = document.querySelector('[name="to_warehouse_id"]').value;
    if (fromGodown && toGodown && fromGodown === toGodown && fromWarehouse === toWarehouse) {
        e.preventDefault();
        alert('From and To must be different (same company profile and same godown).');
        return;
    }
    var anyItem = false, halfFilled = false;
    document.querySelectorAll('.extra-select').forEach(function (sel) {
        var q = sel.closest('.gsm-product-row').querySelector('input[type=number]').value;
        if (sel.value && q) anyItem = true; else if (sel.value || q) halfFilled = true;
    });
    if (halfFilled || !anyItem) {
        e.preventDefault();
        alert(halfFilled ? 'Please fill in both the item and quantity for every row, or remove the row.' : 'Add at least one cover or carton box to transfer.');
    }
});

// Start with one cover row and one carton row so the form is usable immediately.
document.querySelectorAll('.add-extra-row').forEach(function (b) { b.click(); });
</script>
</body>
</html>
