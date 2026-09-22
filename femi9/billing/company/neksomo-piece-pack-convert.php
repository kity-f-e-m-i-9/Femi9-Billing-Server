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
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .select2-container--default .select2-selection--single { border: 1px solid #dde1ea; border-radius: 8px; height: 42px; padding: 6px 12px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.85; padding: 0; color: #344054; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
        .select2-container--default.select2-container--open .select2-selection--single { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
        .select2-dropdown { border-radius: 8px; border-color: #dde1ea; box-shadow: 0 8px 24px rgba(0,0,0,.12); }
        .select2-search--dropdown .select2-search__field { border-radius: 6px; border: 1px solid #dde1ea; padding: 6px 10px; }

        #convertForm .form-control,
        #convertForm select.form-control { border: 1px solid #dde1ea; border-radius: 8px; height: 42px; font-size: 14px; }
        #convertForm .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
        #convertForm .direction-select { padding-top: 8px; padding-bottom: 8px; }

        .bundle-picker-wrap .select2-container--default .select2-selection--single { border-color: #ddd6fe; background: #fbfaff; }
        .bundle-picker-wrap .select2-container--default.select2-container--open .select2-selection--single { border-color: #764ba2; box-shadow: 0 0 0 3px rgba(118,75,162,.15); }
        .bundle-option-remaining { display: inline-block; font-size: 11.5px; font-weight: 600; color: #6d28d9; background: #ede9fe; border-radius: 6px; padding: 1px 7px; margin-left: 8px; white-space: nowrap; }
        .select2-results__option .bundle-option-remaining { float: right; margin-top: 2px; }

        .rmb-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .rmb-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:500; text-decoration:none; transition:filter .15s, background .15s; }
        .rmb-tab i { font-size:17px; }
        .rmb-tab.active { background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:#fff; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .rmb-tab:not(.active) { background:#f3f4f6; color:#4b5563; }
        .rmb-tab:not(.active):hover { background:#e5e7eb; color:#1f2937; }

        .remove-row-btn {
            width: 42px; height: 42px; border-radius: 50%; border: 1px solid #fecdd3;
            background: #fff; color: #e11d48; display: inline-flex;
            align-items: center; justify-content: center; padding: 0;
            transition: background .15s, color .15s, transform .1s;
        }
        .remove-row-btn:hover { background: #e11d48; color: #fff; border-color: #e11d48; }
        .remove-row-btn:active { transform: scale(0.94); }
        .remove-row-btn:disabled { opacity: .35; cursor: not-allowed; }
        .remove-row-btn:disabled:hover { background: #fff; color: #e11d48; }
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
                        <h1><i class="material-icons-outlined" style="font-size:26px;vertical-align:middle;margin-right:6px;color:#667eea;">sync_alt</i>Convert Pieces &harr; Packs</h1>
                        <p class="text-muted mb-0">Assemble loose pieces into whole packs, or break a pack back open — for one product or several at once.</p>
                    </div>

                    <div class="rmb-tabs">
                        <a href="input-stock-bundles.php" class="rmb-tab"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="rmb-tab"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="neksomo-piece-pack-convert.php" class="rmb-tab active"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
                    </div>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <form action="neksomo-piece-pack-convert-action.php" method="post" id="convertForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="card mb-3" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.06);border-radius:14px;overflow:hidden;">
                            <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);padding:16px 22px;">
                                <span style="color:#fff;font-weight:600;font-size:15px;">
                                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">store</i>
                                    Where is this conversion happening?
                                </span>
                            </div>
                            <div class="card-body" style="padding:20px 22px;">
                                <div class="row g-3">
                                    <div class="col-md-6 col-lg-4">
                                        <label class="form-label">Company Profile <span class="required">*</span></label>
                                        <select required name="godownid" id="godownSelect" class="form-control">
                                            <option value="" hidden>Select</option>
                                            <?php foreach ($godowns as $g): ?>
                                            <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="form-label">Godown (physical) <span class="required">*</span></label>
                                        <select required name="warehouse_id" id="warehouseSelect" class="form-control">
                                            <option value="" hidden>Select</option>
                                            <?php foreach ($warehouses as $wh): ?>
                                            <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card" style="border:none;box-shadow:0 2px 10px rgba(0,0,0,.06);border-radius:14px;overflow:hidden;">
                            <div style="background:linear-gradient(135deg, #0891b2 0%, #0e7490 100%);padding:16px 22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                                <span style="color:#fff;font-weight:600;font-size:15px;">
                                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px;">inventory_2</i>
                                    Products to Convert
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
                            <button type="submit" class="btn btn-lg" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border:none;color:#fff;font-weight:600;padding:10px 32px;border-radius:10px;box-shadow:0 4px 12px rgba(102,126,234,.35);">
                                <i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:4px;">sync_alt</i> Convert All
                            </button>
                        </div>
                    </form>

                    <!-- One row's markup, cloned by JS for each product added.
                         Kept as an inert <template> so its inputs are never
                         part of the actual form until cloned in. -->
                    <template id="productRowTemplate">
                        <div class="product-row mb-3" style="background:#f8f9fc;border:1px solid #eaecf5;border-radius:12px;padding:16px;">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label small text-muted mb-1">Product</label>
                                    <select required name="product_id[]" class="form-control product-select">
                                        <option value="" hidden>Select</option>
                                        <?php foreach ($products as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>" data-pieces-per-pack="<?= (int)$p['pieces_per_pack'] ?>">
                                            <?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$p['pieces_per_pack'] ?>/pack)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label small text-muted mb-1">Direction</label>
                                    <select required name="direction[]" class="form-control direction-select">
                                        <option value="pieces_to_pack">&#8599; Pieces &rarr; Pack (assemble)</option>
                                        <option value="pack_to_pieces">&#8600; Pack &rarr; Pieces (break open)</option>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-4">
                                    <label class="form-label small text-muted mb-1">No. of Packs</label>
                                    <input type="number" min="1" required name="pack_count[]" class="form-control pack-count-input" placeholder="e.g. 5">
                                </div>
                                <div class="col-lg-2 col-md-5">
                                    <label class="form-label small text-muted mb-1 d-block">Current Stock</label>
                                    <span class="current-stock-panel badge" style="background:#eef2ff;color:#4338ca;font-weight:600;font-size:12px;padding:8px 10px;white-space:normal;display:inline-block;line-height:1.4;">&mdash;</span>
                                </div>
                                <div class="col-lg-1 col-md-3 d-flex justify-content-md-end">
                                    <button type="button" class="remove-row-btn" title="Remove this product">
                                        <i class="material-icons-outlined" style="font-size:19px;">delete</i>
                                    </button>
                                </div>
                                <div class="col-12 bundle-picker-wrap" style="display:none;">
                                    <div style="height:1px;background:#eaecf5;margin:14px 0 14px;"></div>
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-8">
                                            <label class="form-label small text-muted mb-1">
                                                <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;margin-right:3px;color:#764ba2;">inventory_2</i>Raw Material Bundle <span style="color:#ef4444;">*</span>
                                            </label>
                                            <select class="form-control bundle-select" name="bundle_id[]">
                                                <option value="">— Select bundle —</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="bundle-empty-hint" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;font-size:12px;color:#b91c1c;line-height:1.4;">
                                                <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;margin-right:3px;">error_outline</i>No open bundle yet — add one via Input Stock &rarr; Raw Bundles.
                                            </div>
                                        </div>
                                    </div>
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

function escBd(str) { return $('<div>').text(str == null ? '' : str).html(); }

// Products mapped to a raw Neksomo product only ever assemble FROM that
// raw pool — there's no meaningful "break this finished pack back into
// raw pieces" operation, so Pack -> Pieces is hidden for them (see
// neksomo-piece-pack-convert-action.php's mapped-product path).
function updateDirectionOptions(rowEl, mapped) {
    var directionSelect = rowEl.querySelector('.direction-select');
    var packToPiecesOpt = directionSelect.querySelector('option[value="pack_to_pieces"]');
    packToPiecesOpt.disabled = !!mapped;
    packToPiecesOpt.hidden = !!mapped;
    if (mapped && directionSelect.value === 'pack_to_pieces') {
        directionSelect.value = 'pieces_to_pack';
    }
}

// Populates/hides the row's Raw Material Bundle picker — required for a
// mapped product's Pieces -> Pack conversion (see docs/superpowers/specs/
// 2026-09-22-raw-material-bundle-tracking-design.md). Conversion is
// blocked with no fallback when no open bundle exists yet.
// Renders each Select2 option as the bundle's label plus a small purple
// "N pc left" badge on the right, so the remaining count is scannable at
// a glance both in the closed selection and the open dropdown list.
function renderBundleOption(state) {
    if (!state.id) return state.text;
    var remaining = $(state.element).data('remaining');
    var $result = $('<span></span>').text(state.text);
    if (remaining !== undefined) {
        $('<span class="bundle-option-remaining"></span>').text(Number(remaining).toLocaleString('en-IN') + ' pc left').appendTo($result);
    }
    return $result;
}

function updateBundlePicker(rowEl, mapped, openBundles) {
    var wrap = rowEl.querySelector('.bundle-picker-wrap');
    var select = rowEl.querySelector('.bundle-select');
    var emptyHint = rowEl.querySelector('.bundle-empty-hint');

    if ($(select).data('select2')) {
        $(select).select2('destroy');
    }

    if (!mapped) {
        wrap.style.display = 'none';
        select.removeAttribute('required');
        select.value = '';
        return;
    }

    wrap.style.display = '';
    select.setAttribute('required', 'required');

    var currentVal = select.value;
    var html = '<option value="">— Select bundle —</option>';
    (openBundles || []).forEach(function (b) {
        html += '<option value="' + b.id + '" data-remaining="' + b.remaining_pieces + '">' + escBd(b.label) + '</option>';
    });
    select.innerHTML = html;
    if (currentVal && Array.from(select.options).some(function (o) { return o.value === currentVal; })) {
        select.value = currentVal;
    }

    var hasBundles = (openBundles || []).length > 0;
    emptyHint.style.display = hasBundles ? 'none' : '';
    select.disabled = !hasBundles;

    if (hasBundles) {
        $(select).select2({
            placeholder: 'Search a bundle…',
            width: '100%',
            templateResult: renderBundleOption,
            templateSelection: renderBundleOption
        });
    }
}

function refreshRowStock(rowEl) {
    var productId = rowEl.querySelector('.product-select').value;
    var godownId = document.getElementById('godownSelect').value;
    var warehouseId = document.getElementById('warehouseSelect').value;
    var panel = rowEl.querySelector('.current-stock-panel');
    if (!productId || !godownId || !warehouseId) {
        panel.textContent = '—';
        return;
    }
    panel.textContent = '…';
    fetch('get-piece-pack-stock.php?product_id=' + encodeURIComponent(productId) + '&godown_id=' + encodeURIComponent(godownId) + '&warehouse_id=' + encodeURIComponent(warehouseId))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) { panel.textContent = '—'; return; }
            updateDirectionOptions(rowEl, data.mapped);
            updateBundlePicker(rowEl, data.mapped, data.open_bundles);
            if (data.mapped) {
                panel.textContent = data.raw_pieces + ' raw pc available';
            } else {
                panel.textContent = data.closing_qty + ' pack(s) · ' + data.extra_pieces + ' pc';
            }
        })
        .catch(function () { panel.textContent = '—'; });
}

function refreshAllRowsStock() {
    rowsContainer.querySelectorAll('.product-row').forEach(refreshRowStock);
}

// Always keep at least one row — disable every row's delete button while
// only one remains, rather than letting a click silently do nothing.
function updateRemoveButtonsState() {
    var rows = rowsContainer.querySelectorAll('.product-row');
    var onlyOneLeft = rows.length <= 1;
    rows.forEach(function (row) {
        row.querySelector('.remove-row-btn').disabled = onlyOneLeft;
    });
}

function addProductRow() {
    var fragment = rowTemplate.content.cloneNode(true);
    var rowEl = fragment.querySelector('.product-row');
    var productSelect = rowEl.querySelector('.product-select');
    var bundleSelect = rowEl.querySelector('.bundle-select');
    rowEl.querySelector('.remove-row-btn').addEventListener('click', function () {
        $(productSelect).select2('destroy');
        if ($(bundleSelect).data('select2')) $(bundleSelect).select2('destroy');
        rowEl.remove();
        updateRemoveButtonsState();
    });
    rowsContainer.appendChild(fragment);

    // Select2 must init after the element is actually in the DOM, not
    // while it's still inside the detached template fragment.
    $(productSelect).select2({ placeholder: 'Search a product…', width: '100%' });
    $(productSelect).on('change', function () { refreshRowStock(rowEl); });
    updateRemoveButtonsState();
}

document.getElementById('addProductRowBtn').addEventListener('click', addProductRow);
['godownSelect', 'warehouseSelect'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', refreshAllRowsStock);
});

// Start with one row so the form is usable immediately.
addProductRow();

// Blocks submission if any mapped-product row is missing its required
// bundle selection — native `required` on a `display:none` field isn't
// reliably enforced by browsers, so this is re-checked explicitly.
document.getElementById('convertForm').addEventListener('submit', function (e) {
    var blocked = false;
    rowsContainer.querySelectorAll('.product-row').forEach(function (rowEl) {
        var wrap = rowEl.querySelector('.bundle-picker-wrap');
        if (wrap.style.display === 'none') return; // not a mapped row
        var select = rowEl.querySelector('.bundle-select');
        if (!select.value) blocked = true;
    });
    if (blocked) {
        e.preventDefault();
        alert('Please select a raw material bundle for every mapped product row before converting.');
    }
});
</script>
</body>
</html>
