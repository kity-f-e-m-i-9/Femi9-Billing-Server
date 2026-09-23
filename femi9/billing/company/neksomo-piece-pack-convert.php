<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/MachineCodes.php");
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

$machineCodes = get_active_machine_codes($db_conn);
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
        :root {
            --ata-tp-1: #667eea;
            --ata-tp-2: #764ba2;
            --ata-ot-1: #0891b2;
            --ata-ot-2: #0e7490;
        }
        .ata-page-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:6px; }
        .ata-page-head h1 { font-size:20px; font-weight:600; color:#1f2937; margin:0; display:flex; align-items:center; }
        .ata-icon-badge {
            display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px;
            border-radius:10px; margin-right:10px; flex:0 0 auto;
            background:linear-gradient(135deg, rgba(102,126,234,.14) 0%, rgba(118,75,162,.14) 100%);
        }
        .ata-icon-badge i { font-size:19px; color:var(--ata-tp-1); }
        .ata-intro { color:#6b7280; font-size:13.5px; line-height:1.55; margin-bottom:18px; }
        .ata-card { border:1px solid #eef0f3; border-radius:14px; box-shadow:0 1px 3px rgba(16,24,40,.04); overflow:hidden; }

        .ata-section-head {
            display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
            padding:14px 20px; border-bottom:1px solid #eef0f3;
            background:linear-gradient(135deg, rgba(102,126,234,.05) 0%, rgba(118,75,162,.05) 100%);
        }
        .ata-section-title { display:flex; align-items:center; gap:8px; font-size:14.5px; font-weight:600; color:#1f2937; }
        .ata-section-title i { font-size:19px; color:var(--ata-tp-1); }

        .ata-btn { display:inline-flex; align-items:center; gap:6px; border:none; border-radius:9px; color:#fff; font-size:13px; font-weight:500; padding:8px 14px; cursor:pointer; transition:filter .15s, transform .1s; }
        .ata-btn:active { transform:translateY(1px); }
        .ata-btn:hover { filter:brightness(1.06); color:#fff; }
        .ata-btn-tp { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); box-shadow:0 2px 6px rgba(102,126,234,.3); }
        .ata-btn-ot { background:linear-gradient(135deg, var(--ata-ot-1) 0%, var(--ata-ot-2) 100%); box-shadow:0 2px 6px rgba(8,145,178,.3); }
        .ata-btn-submit { background:linear-gradient(135deg,#22c55e 0%,#15803d 100%); box-shadow:0 2px 8px rgba(21,128,61,.3); font-size:14px; padding:10px 22px; }

        .ata-nav-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .ata-nav-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:500; text-decoration:none; transition:filter .15s, background .15s; }
        .ata-nav-tab i { font-size:17px; }
        .ata-nav-tab.active { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .ata-nav-tab:not(.active) { background:#f3f4f6; color:#4b5563; }
        .ata-nav-tab:not(.active):hover { background:#e5e7eb; color:#1f2937; }

        .ata-field label { display:block; font-size:11.5px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.02em; margin-bottom:6px; }
        .ata-field select, .ata-field input {
            border:1px solid #dde1ea; border-radius:9px; height:46px; font-size:14.5px; width:100%;
            padding:0 36px 0 14px; color:#344054; background:#fff;
        }
        .ata-field input { padding:0 14px; }
        .ata-field select {
            appearance:none; -webkit-appearance:none; -moz-appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 14px center;
        }
        .ata-field select:focus, .ata-field input:focus { border-color: var(--ata-tp-1); box-shadow: 0 0 0 3px rgba(102,126,234,.15); outline:none; }
        .ata-scope-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; padding:18px 20px; }

        .ata-rows { display:flex; flex-direction:column; gap:12px; padding:18px 20px 22px; }
        .ata-row-card {
            border:1px solid #eef0f3; border-left:3px solid #d1d5db; border-radius:12px;
            padding:14px 16px; background:#fff; transition:box-shadow .15s, border-left-color .15s;
        }
        .ata-row-card:hover { box-shadow:0 2px 10px rgba(16,24,40,.07); }
        .ata-row-card.dir-to-pack { border-left-color: var(--ata-tp-1); }
        .ata-row-card.dir-to-pieces { border-left-color: var(--ata-ot-1); }
        .ata-row-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; align-items:end; }
        .ata-row-grid .ata-field-wide { grid-column: span 2; }
        .ata-stock-chip {
            display:inline-flex; align-items:center; gap:6px; height:46px; border-radius:9px; padding:0 14px;
            font-size:12.5px; font-weight:600; background:#eef2ff; color:#4338ca; line-height:1.3; white-space:normal;
        }
        .ata-stock-chip i { font-size:16px; flex:0 0 auto; }
        .ata-stock-chip.is-empty { background:#f3f4f6; color:#9ca3af; }

        .ata-remove-btn {
            width: 46px; height: 46px; border-radius: 50%; border: 1px solid #fecdd3;
            background: #fff; color: #e11d48; display: inline-flex;
            align-items: center; justify-content: center; padding: 0; cursor:pointer;
            transition: background .15s, color .15s, transform .1s;
        }
        .ata-remove-btn:hover { background: #e11d48; color: #fff; border-color: #e11d48; }
        .ata-remove-btn:active { transform: scale(0.94); }
        .ata-remove-btn:disabled { opacity: .35; cursor: not-allowed; }
        .ata-remove-btn:disabled:hover { background: #fff; color: #e11d48; }

        .ata-bundle-strip { display:none; margin-top:14px; padding-top:14px; border-top:1px dashed #eaecf5; }
        .ata-bundle-row { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; }
        .ata-bundle-row .ata-field { flex:1 1 280px; min-width:220px; margin:0; }
        .ata-bundle-label { display:flex; align-items:center; gap:4px; margin-bottom:4px; }
        .ata-bundle-label i { font-size:15px; color:var(--ata-tp-2); }
        .ata-bundle-close-btn {
            display:inline-flex; align-items:center; gap:5px; height:46px; padding:0 18px;
            border-radius:9px; border:1px solid #fecaca; background:#fff5f5; color:#b91c1c;
            font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap; flex:0 0 auto;
            transition:background .15s, opacity .15s;
        }
        .ata-bundle-close-btn:hover:not(:disabled) { background:#fee2e2; }
        .ata-bundle-close-btn:disabled { opacity:.4; cursor:not-allowed; }
        .ata-bundle-empty-hint { flex:1 1 260px; background:#fef2f2; border:1px solid #fecaca; border-radius:9px; padding:0 14px; height:46px; display:none; align-items:center; font-size:12.5px; color:#b91c1c; line-height:1.3; }
        .ata-bundle-empty-hint i { font-size:14px; vertical-align:middle; margin-right:3px; }

        .select2-container { width:100% !important; }
        .select2-container--default .select2-selection--single {
            border: 1px solid #dde1ea; border-radius: 9px; height: 46px; padding: 0 36px 0 14px;
            display:flex; align-items:center; background:#fff;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.4; padding: 0; color: #344054; font-size:14.5px; width:100%; overflow:hidden; text-overflow:ellipsis; }
        .select2-container--default .select2-selection--single .select2-selection__placeholder { color:#8a94a6; }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height:100%; width:26px; top:0; right:5px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #6b7280 transparent transparent transparent;
            border-width: 5px 4px 0 4px;
        }
        .select2-container--default.select2-container--open .select2-selection--single { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
        .select2-dropdown { border-radius: 9px; border-color: #dde1ea; box-shadow: 0 8px 24px rgba(0,0,0,.12); }
        .select2-search--dropdown .select2-search__field { border-radius: 6px; border: 1px solid #dde1ea; padding: 7px 10px; font-size:13.5px; }

        .ata-bundle-row .select2-container--default.select2-container--open .select2-selection--single { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
        .bundle-option-remaining { display: inline-block; font-size: 10.5px; font-weight: 600; color: #6d28d9; background: #ede9fe; border-radius: 6px; padding: 1px 7px; margin-left: 8px; white-space: nowrap; flex-shrink:0; }
        .select2-results__option .bundle-option-remaining { float: right; margin-top: 2px; }

        @keyframes spin { to { transform: rotate(360deg); } }
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
                    <div class="ata-page-head">
                        <h1><span class="ata-icon-badge"><i class="material-icons-outlined">sync_alt</i></span>Convert Pieces &harr; Packs</h1>
                    </div>
                    <p class="ata-intro">Assemble loose pieces into whole packs, or break a pack back open — for one product or several at once.</p>

                    <div class="ata-nav-tabs">
                        <a href="input-stock-bundles.php" class="ata-nav-tab"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="neksomo-piece-pack-convert.php" class="ata-nav-tab active"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
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

                        <div class="ata-card card mb-3">
                            <div class="ata-section-head">
                                <div class="ata-section-title"><i class="material-icons-outlined">store</i> Where is this conversion happening?</div>
                            </div>
                            <div class="ata-scope-grid">
                                <div class="ata-field">
                                    <label>Company Profile <span class="required">*</span></label>
                                    <select required name="godownid" id="godownSelect">
                                        <option value="" hidden>Select</option>
                                        <?php foreach ($godowns as $g): ?>
                                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ata-field">
                                    <label>Godown (physical) <span class="required">*</span></label>
                                    <select required name="warehouse_id" id="warehouseSelect">
                                        <option value="" hidden>Select</option>
                                        <?php foreach ($warehouses as $wh): ?>
                                        <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="ata-card card">
                            <div class="ata-section-head">
                                <div class="ata-section-title"><i class="material-icons-outlined">inventory_2</i> Products to Convert</div>
                                <div style="display:flex; gap:8px;">
                                    <button type="button" class="ata-btn ata-btn-tp" id="manageMachineCodesBtn">
                                        <i class="material-icons-outlined" style="font-size:15px;">settings</i> Manage Machine Codes
                                    </button>
                                    <button type="button" class="ata-btn ata-btn-ot" id="addProductRowBtn">
                                        <i class="material-icons" style="font-size:15px;">add</i> Add Another Product
                                    </button>
                                </div>
                            </div>
                            <div class="ata-rows" id="productRows"></div>
                        </div>

                        <div class="d-flex justify-content-end mt-3 mb-4">
                            <button type="submit" class="ata-btn ata-btn-submit">
                                <i class="material-icons" style="font-size:17px;">sync_alt</i> Convert All
                            </button>
                        </div>
                    </form>

                    <!-- One row's markup, cloned by JS for each product added.
                         Kept as an inert <template> so its inputs are never
                         part of the actual form until cloned in. -->
                    <template id="productRowTemplate">
                        <div class="product-row ata-row-card">
                            <div class="ata-row-grid">
                                <div class="ata-field ata-field-wide">
                                    <label>Product</label>
                                    <select required name="product_id[]" class="product-select">
                                        <option value="" hidden>Select</option>
                                        <?php foreach ($products as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>" data-pieces-per-pack="<?= (int)$p['pieces_per_pack'] ?>">
                                            <?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$p['pieces_per_pack'] ?>/pack)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ata-field">
                                    <label>Direction</label>
                                    <select required name="direction[]" class="direction-select">
                                        <option value="pieces_to_pack">&#8599; Pieces &rarr; Pack</option>
                                        <option value="pack_to_pieces">&#8600; Pack &rarr; Pieces</option>
                                    </select>
                                </div>
                                <div class="ata-field">
                                    <label>No. of Packs</label>
                                    <input type="number" min="1" required name="pack_count[]" class="pack-count-input" placeholder="e.g. 5">
                                </div>
                                <div class="ata-field">
                                    <label>Machine Code</label>
                                    <select name="machine_code_id[]" class="machine-code-select">
                                        <option value="">— None —</option>
                                        <?php foreach ($machineCodes as $mc): ?>
                                        <option value="<?= (int)$mc['id'] ?>"><?= htmlspecialchars($mc['code'], ENT_QUOTES, 'UTF-8') ?><?= $mc['name'] ? ' - ' . htmlspecialchars($mc['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ata-field">
                                    <label>Current Stock</label>
                                    <span class="current-stock-panel ata-stock-chip is-empty"><i class="material-icons-outlined">inventory</i>&mdash;</span>
                                </div>
                                <div class="ata-field" style="flex:0 0 auto;width:auto;display:flex;justify-content:flex-end;">
                                    <label>&nbsp;</label>
                                    <button type="button" class="ata-remove-btn" title="Remove this product">
                                        <i class="material-icons-outlined" style="font-size:19px;">delete</i>
                                    </button>
                                </div>
                            </div>
                            <div class="bundle-picker-wrap ata-bundle-strip">
                                <div class="ata-bundle-row">
                                    <div class="ata-field">
                                        <label class="ata-bundle-label"><i class="material-icons-outlined">inventory_2</i>Raw Material Bundle <span style="color:#ef4444;">*</span></label>
                                        <select class="bundle-select" name="bundle_id[]">
                                            <option value="">— Select bundle —</option>
                                        </select>
                                    </div>
                                    <button type="button" class="ata-bundle-close-btn bundle-close-btn" disabled title="Select a bundle first">
                                        <i class="material-icons-outlined" style="font-size:15px;">close</i> Close Bundle
                                    </button>
                                    <div class="bundle-empty-hint ata-bundle-empty-hint">
                                        <i class="material-icons-outlined">error_outline</i>No open bundle yet — add one via Input Stock &rarr; Raw Bundles.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Manage Machine Codes modal: bootstrap-style overlay, kept
                         simple (no bootstrap JS dependency) since it's just a
                         small add/edit/delete list. -->
                    <div id="machineCodeModalBackdrop" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:1050; align-items:center; justify-content:center; padding:20px;">
                        <div style="background:#fff; border-radius:14px; width:100%; max-width:480px; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.25);">
                            <div class="ata-section-head" style="border-radius:14px 14px 0 0;">
                                <div class="ata-section-title"><i class="material-icons-outlined">precision_manufacturing</i> Manage Machine Codes</div>
                                <button type="button" id="machineCodeModalClose" style="border:none; background:none; cursor:pointer; color:#6b7280;"><i class="material-icons">close</i></button>
                            </div>
                            <div style="padding:16px 20px; overflow-y:auto; flex:1 1 auto;">
                                <div style="display:flex; gap:8px; margin-bottom:14px;">
                                    <input type="text" id="mcFormCode" placeholder="Code (e.g. M-01)" style="flex:1 1 120px; border:1px solid #dde1ea; border-radius:8px; height:40px; padding:0 12px; font-size:13.5px;">
                                    <input type="text" id="mcFormName" placeholder="Name (optional)" style="flex:2 1 160px; border:1px solid #dde1ea; border-radius:8px; height:40px; padding:0 12px; font-size:13.5px;">
                                    <input type="hidden" id="mcFormId" value="">
                                    <button type="button" id="mcFormSubmit" class="ata-btn ata-btn-tp" style="height:40px;">Add</button>
                                    <button type="button" id="mcFormCancelEdit" style="display:none; height:40px; padding:0 12px; border:1px solid #dde1ea; border-radius:8px; background:#fff; cursor:pointer;">Cancel</button>
                                </div>
                                <div id="mcFormError" style="display:none; color:#b91c1c; font-size:12.5px; margin-bottom:10px;"></div>
                                <div id="machineCodeList" style="display:flex; flex-direction:column; gap:6px;"></div>
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
    updateRowDirectionAccent(rowEl);
}

// Purely visual: tints the row card's left border to match the chosen
// direction (purple for assembling into packs, teal for breaking packs
// back open) so a long list of rows stays scannable at a glance.
function updateRowDirectionAccent(rowEl) {
    var direction = rowEl.querySelector('.direction-select').value;
    rowEl.classList.toggle('dir-to-pack', direction === 'pieces_to_pack');
    rowEl.classList.toggle('dir-to-pieces', direction === 'pack_to_pieces');
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

    wrap.style.display = 'block';
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

    updateCloseBundleButton(rowEl);
    $(select).off('change.closeBtn').on('change.closeBtn', function () { updateCloseBundleButton(rowEl); });
}

// Enables/disables the row's "Close Bundle" button based on whether a
// bundle is currently selected, and updates its title with the
// remaining-piece count so the operator sees at a glance what they're
// about to close.
function updateCloseBundleButton(rowEl) {
    var select = rowEl.querySelector('.bundle-select');
    var closeBtn = rowEl.querySelector('.bundle-close-btn');
    var selectedOption = select.options[select.selectedIndex];
    var bundleId = select.value;

    if (!bundleId || !selectedOption) {
        closeBtn.disabled = true;
        closeBtn.title = 'Select a bundle first';
        return;
    }

    var remaining = selectedOption.getAttribute('data-remaining');
    closeBtn.disabled = false;
    closeBtn.title = 'Close this bundle (' + remaining + ' pc remaining will carry forward)';
}

// Closes the row's currently-selected bundle right from the Convert
// page — same close_raw_material_bundle() carry-forward logic as
// Manage Bundles, just without leaving this page. Re-fetches the row's
// stock/bundle list afterward so the closed bundle disappears from the
// picker and any carry-forward target's updated count shows up.
function closeSelectedBundle(rowEl) {
    var select = rowEl.querySelector('.bundle-select');
    var closeBtn = rowEl.querySelector('.bundle-close-btn');
    var bundleId = select.value;
    var selectedOption = select.options[select.selectedIndex];
    if (!bundleId || !selectedOption) return;

    var remaining = selectedOption.getAttribute('data-remaining');
    var label = selectedOption.textContent;
    var confirmMsg = 'Close ' + label + '?' + (parseInt(remaining, 10) > 0
        ? ' Remaining ' + remaining + ' pc will automatically carry forward into another bundle.'
        : '') + ' This cannot be undone.';
    if (!confirm(confirmMsg)) return;

    closeBtn.disabled = true;
    closeBtn.innerHTML = '<i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;animation:spin 1s linear infinite;">refresh</i> Closing…';

    var formData = new URLSearchParams();
    formData.set('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    formData.set('bundle_id', bundleId);

    fetch('close-raw-material-bundle.php', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                refreshRowStock(rowEl);
            } else {
                alert('Could not close that bundle — it may already be closed.');
                closeBtn.disabled = false;
                closeBtn.innerHTML = '<i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">close</i> Close Bundle';
            }
        })
        .catch(function () {
            alert('An error occurred closing the bundle. Please try again.');
            closeBtn.disabled = false;
            closeBtn.innerHTML = '<i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">close</i> Close Bundle';
        });
}

function refreshRowStock(rowEl) {
    var productId = rowEl.querySelector('.product-select').value;
    var godownId = document.getElementById('godownSelect').value;
    var warehouseId = document.getElementById('warehouseSelect').value;
    var panel = rowEl.querySelector('.current-stock-panel');
    if (!productId || !godownId || !warehouseId) {
        panel.classList.add('is-empty');
        panel.lastChild.textContent = '—';
        return;
    }
    panel.classList.add('is-empty');
    panel.lastChild.textContent = '…';
    fetch('get-piece-pack-stock.php?product_id=' + encodeURIComponent(productId) + '&godown_id=' + encodeURIComponent(godownId) + '&warehouse_id=' + encodeURIComponent(warehouseId))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) { panel.classList.add('is-empty'); panel.lastChild.textContent = '—'; return; }
            updateDirectionOptions(rowEl, data.mapped);
            updateBundlePicker(rowEl, data.mapped, data.open_bundles);
            panel.classList.remove('is-empty');
            if (data.mapped) {
                panel.lastChild.textContent = data.raw_pieces + ' raw pc available';
            } else {
                panel.lastChild.textContent = data.closing_qty + ' pack(s) · ' + data.extra_pieces + ' pc';
            }
        })
        .catch(function () { panel.classList.add('is-empty'); panel.lastChild.textContent = '—'; });
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
        row.querySelector('.ata-remove-btn').disabled = onlyOneLeft;
    });
}

function addProductRow() {
    var fragment = rowTemplate.content.cloneNode(true);
    var rowEl = fragment.querySelector('.product-row');
    var productSelect = rowEl.querySelector('.product-select');
    var bundleSelect = rowEl.querySelector('.bundle-select');
    rowEl.querySelector('.ata-remove-btn').addEventListener('click', function () {
        $(productSelect).select2('destroy');
        if ($(bundleSelect).data('select2')) $(bundleSelect).select2('destroy');
        rowEl.remove();
        updateRemoveButtonsState();
    });
    rowEl.querySelector('.bundle-close-btn').addEventListener('click', function () {
        closeSelectedBundle(rowEl);
    });
    rowsContainer.appendChild(fragment);

    // Select2 must init after the element is actually in the DOM, not
    // while it's still inside the detached template fragment.
    $(productSelect).select2({ placeholder: 'Search a product…', width: '100%' });
    $(productSelect).on('change', function () { refreshRowStock(rowEl); });
    rowEl.querySelector('.direction-select').addEventListener('change', function () { updateRowDirectionAccent(rowEl); });
    updateRowDirectionAccent(rowEl);
    updateRemoveButtonsState();
}

// Manage Machine Codes modal: list/add/edit/delete against
// machine-code-manage.php, then refresh every row's dropdown so newly
// added/renamed/removed codes show up immediately without a page reload.
var mcModalBackdrop = document.getElementById('machineCodeModalBackdrop');
var mcList = document.getElementById('machineCodeList');
var mcFormCode = document.getElementById('mcFormCode');
var mcFormName = document.getElementById('mcFormName');
var mcFormId = document.getElementById('mcFormId');
var mcFormSubmit = document.getElementById('mcFormSubmit');
var mcFormCancelEdit = document.getElementById('mcFormCancelEdit');
var mcFormError = document.getElementById('mcFormError');
var csrfToken = document.querySelector('input[name="csrf_token"]').value;

function mcPostAction(action, extraFields) {
    var formData = new URLSearchParams();
    formData.set('action', action);
    formData.set('csrf_token', csrfToken);
    Object.keys(extraFields || {}).forEach(function (k) { formData.set(k, extraFields[k]); });
    return fetch('machine-code-manage.php', { method: 'POST', body: formData }).then(function (r) { return r.json(); });
}

function resetMcForm() {
    mcFormId.value = '';
    mcFormCode.value = '';
    mcFormName.value = '';
    mcFormSubmit.textContent = 'Add';
    mcFormCancelEdit.style.display = 'none';
    mcFormError.style.display = 'none';
}

function renderMachineCodeRow(mc) {
    var row = document.createElement('div');
    row.style.cssText = 'display:flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid #eef0f3; border-radius:8px;';
    var label = document.createElement('div');
    label.style.cssText = 'flex:1 1 auto; font-size:13.5px; color:#344054;';
    label.textContent = mc.code + (mc.name ? ' - ' + mc.name : '');
    var editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.title = 'Edit';
    editBtn.style.cssText = 'border:none; background:none; cursor:pointer; color:#6b7280;';
    editBtn.innerHTML = '<i class="material-icons-outlined" style="font-size:18px;">edit</i>';
    editBtn.addEventListener('click', function () {
        mcFormId.value = mc.id;
        mcFormCode.value = mc.code;
        mcFormName.value = mc.name || '';
        mcFormSubmit.textContent = 'Save';
        mcFormCancelEdit.style.display = '';
        mcFormError.style.display = 'none';
    });
    var delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.title = 'Delete';
    delBtn.style.cssText = 'border:none; background:none; cursor:pointer; color:#e11d48;';
    delBtn.innerHTML = '<i class="material-icons-outlined" style="font-size:18px;">delete</i>';
    delBtn.addEventListener('click', function () {
        if (!confirm('Delete machine code "' + mc.code + '"? Rows already using it keep their history.')) return;
        mcPostAction('delete', { id: mc.id }).then(function (data) {
            if (data.success) { loadMachineCodeList(); refreshMachineCodeDropdowns(); }
            else alert(data.error || 'Could not delete.');
        });
    });
    row.appendChild(label);
    row.appendChild(editBtn);
    row.appendChild(delBtn);
    return row;
}

function loadMachineCodeList() {
    mcPostAction('list', {}).then(function (data) {
        mcList.innerHTML = '';
        (data.machine_codes || []).forEach(function (mc) {
            if (!mc.is_active) return;
            mcList.appendChild(renderMachineCodeRow(mc));
        });
        if (!mcList.children.length) {
            mcList.innerHTML = '<div style="color:#9ca3af; font-size:12.5px;">No machine codes yet — add one above.</div>';
        }
    });
}

// Rebuilds every product row's Machine Code <select> from the current
// active list, preserving each row's existing selection where it still exists.
function refreshMachineCodeDropdowns() {
    mcPostAction('list', {}).then(function (data) {
        var active = (data.machine_codes || []).filter(function (mc) { return mc.is_active; });
        rowsContainer.querySelectorAll('.machine-code-select').forEach(function (select) {
            var currentVal = select.value;
            var html = '<option value="">— None —</option>';
            active.forEach(function (mc) {
                html += '<option value="' + mc.id + '">' + escBd(mc.code) + (mc.name ? ' - ' + escBd(mc.name) : '') + '</option>';
            });
            select.innerHTML = html;
            if (currentVal && Array.from(select.options).some(function (o) { return o.value === currentVal; })) {
                select.value = currentVal;
            }
        });
    });
}

document.getElementById('manageMachineCodesBtn').addEventListener('click', function () {
    resetMcForm();
    loadMachineCodeList();
    mcModalBackdrop.style.display = 'flex';
});
document.getElementById('machineCodeModalClose').addEventListener('click', function () { mcModalBackdrop.style.display = 'none'; });
mcModalBackdrop.addEventListener('click', function (e) { if (e.target === mcModalBackdrop) mcModalBackdrop.style.display = 'none'; });
mcFormCancelEdit.addEventListener('click', resetMcForm);

mcFormSubmit.addEventListener('click', function () {
    var code = mcFormCode.value.trim();
    if (!code) { mcFormError.textContent = 'Machine code is required.'; mcFormError.style.display = ''; return; }
    var isEdit = !!mcFormId.value;
    mcPostAction(isEdit ? 'edit' : 'add', { id: mcFormId.value, code: code, name: mcFormName.value.trim() }).then(function (data) {
        if (data.success) {
            resetMcForm();
            loadMachineCodeList();
            refreshMachineCodeDropdowns();
        } else {
            mcFormError.textContent = data.error || 'Could not save.';
            mcFormError.style.display = '';
        }
    });
});

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
