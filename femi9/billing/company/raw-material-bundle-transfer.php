<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/RawMaterialBundles.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

// Same audience as the rest of the bundle pages (Godown/stockviewer
// login, neksomo, admin).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin', 'stockviewer'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// AJAX: open bundles at a source (product + company profile + warehouse),
// newest first (LIFO).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    header('Content-Type: application/json');
    $rp = filter_var($_GET['raw_product_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $cg = filter_var($_GET['company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $wh = filter_var($_GET['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
    if (!$rp || !$cg || !is_godown_allowed($db_conn, $cg)) {
        echo json_encode([]);
        exit;
    }
    echo json_encode(get_transferable_raw_material_bundles($db_conn, $rp, $cg, $wh));
    exit;
}

$rawProducts = $db_conn->query(
    "SELECT id, productName FROM products WHERE temp_id LIKE 'NKS-%' ORDER BY productName ASC"
)->fetch_all(MYSQLI_ASSOC);

$godowns = $db_conn->query(
    "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC"
)->fetch_all(MYSQLI_ASSOC);

$warehouses = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);

$batches = get_raw_material_bundle_transfer_batches($db_conn, 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bundle Transfer : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --ata-tp-1: #667eea;
            --ata-tp-2: #764ba2;
        }
        .ata-page-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .ata-page-head h1 { font-size:20px; font-weight:600; color:#1f2937; margin:0; display:flex; align-items:center; }
        .ata-intro { color:#6b7280; font-size:13.5px; line-height:1.55; margin-bottom:16px; }
        .ata-card { border:1px solid #eef0f3; border-radius:14px; box-shadow:0 1px 3px rgba(16,24,40,.04); }

        .ata-section-head { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; padding:14px 20px; border-bottom:1px solid #eef0f3; }
        .ata-section-title { display:flex; align-items:center; gap:8px; font-size:14.5px; font-weight:600; color:#1f2937; }
        .ata-section-title i { font-size:19px; color:var(--ata-tp-1); }

        .ata-nav-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .ata-nav-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:500; text-decoration:none; transition:filter .15s, background .15s; }
        .ata-nav-tab i { font-size:17px; }
        .ata-nav-tab.active { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .ata-nav-tab:not(.active) { background:#f3f4f6; color:#4b5563; }
        .ata-nav-tab:not(.active):hover { background:#e5e7eb; color:#1f2937; }

        .ata-hint-box { background:#f8f9fc; border:1px solid #eaecf5; border-radius:10px; padding:12px 16px; font-size:12.5px; color:#6b7280; margin-bottom:20px; display:flex; gap:10px; align-items:flex-start; }
        .ata-hint-box i { color:var(--ata-tp-1); font-size:18px; flex-shrink:0; margin-top:1px; }

        .ata-form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; padding:20px; }
        .ata-field { grid-column: span 1; }
        .ata-field.ata-field-full { grid-column: 1 / -1; }
        .ata-field label { display:block; font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.02em; margin-bottom:4px; }
        .ata-field select, .ata-field input { border:1px solid #dde1ea; border-radius:8px; height:40px; font-size:13.5px; width:100%; padding:6px 10px; }
        .ata-field select:focus, .ata-field input:focus { border-color: var(--ata-tp-1); box-shadow: 0 0 0 3px rgba(102,126,234,.15); outline:none; }

        .ata-btn { display:inline-flex; align-items:center; gap:6px; border:none; border-radius:9px; color:#fff; font-size:13px; font-weight:500; padding:8px 14px; cursor:pointer; transition:filter .15s, transform .1s; }
        .ata-btn:active { transform:translateY(1px); }
        .ata-btn:hover { filter:brightness(1.06); color:#fff; }
        .ata-btn-tp { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); box-shadow:0 2px 6px rgba(102,126,234,.3); }
        .ata-btn-submit { font-size:14px; padding:10px 22px; }
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
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">swap_horiz</i>Bundle Transfer</h1>
                    </div>
                    <p class="ata-intro">Move whole open bundles from one company profile + warehouse to another. Each bundle starts fresh at the destination with a new bundle number.</p>

                    <div class="ata-nav-tabs">
                        <a href="input-stock-bundles.php" class="ata-nav-tab"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="raw-material-bundle-transfer.php" class="ata-nav-tab active"><i class="material-icons-outlined">swap_horiz</i> Bundle Transfer</a>
                        <a href="neksomo-piece-pack-convert.php" class="ata-nav-tab"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
                        <a href="raw-material-bundles-report.php" class="ata-nav-tab"><i class="material-icons-outlined">assessment</i> Bundle Report</a>
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
                        <div class="col-lg-10 col-xl-9">
                            <div class="ata-card card">
                                <div class="ata-section-head">
                                    <div class="ata-section-title"><i class="material-icons-outlined">swap_horiz</i> New Transfer</div>
                                </div>
                                <form id="btForm" action="raw-material-bundle-transfer-action.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="transfer">

                                    <div class="ata-form-grid">
                                        <div class="ata-field ata-field-full">
                                            <label>Raw Product <span style="color:#ef4444;">*</span></label>
                                            <select required name="raw_product_id" id="btProduct">
                                                <option value="" hidden>Select Raw Product</option>
                                                <?php foreach ($rawProducts as $p): ?>
                                                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ata-field">
                                            <label>From Company Profile <span style="color:#ef4444;">*</span></label>
                                            <select required name="from_company_godown_id" id="btFromCg">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($godowns as $g): ?>
                                                <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ata-field">
                                            <label>From Warehouse <span style="color:#ef4444;">*</span></label>
                                            <select required name="from_warehouse_id" id="btFromWh">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($warehouses as $wh): ?>
                                                <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ata-field">
                                            <label>To Company Profile <span style="color:#ef4444;">*</span></label>
                                            <select required name="to_company_godown_id" id="btToCg">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($godowns as $g): ?>
                                                <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ata-field">
                                            <label>To Warehouse <span style="color:#ef4444;">*</span></label>
                                            <select required name="to_warehouse_id" id="btToWh">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($warehouses as $wh): ?>
                                                <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div style="padding:0 20px 20px;">
                                        <div class="ata-hint-box" id="btHint">
                                            <i class="material-icons-outlined">info</i>
                                            <div>Pick the product, source and destination. The source's open bundles will be listed newest first (last in, first out).</div>
                                        </div>

                                        <div id="btListWrap" hidden>
                                            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px;">
                                                <div class="ata-field" style="max-width:220px;">
                                                    <label>Select newest N bundles</label>
                                                    <input type="number" min="0" id="btNewestN" placeholder="e.g. 3">
                                                </div>
                                                <div style="font-size:13px;color:#4338ca;font-weight:500;" id="btSummary"></div>
                                            </div>
                                            <div style="overflow-x:auto;">
                                                <table class="table table-sm" style="font-size:13px;">
                                                    <thead><tr><th style="width:40px;"></th><th>Bundle</th><th style="text-align:right;">Nominal</th><th style="text-align:right;">Remaining</th><th>Created</th></tr></thead>
                                                    <tbody id="btRows"></tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <button type="submit" class="ata-btn ata-btn-tp ata-btn-submit" id="btSubmit" disabled>
                                            <i class="material-icons" style="font-size:17px;">swap_horiz</i> Transfer Selected Bundles
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div class="ata-card card" style="margin-top:20px;">
                                <div class="ata-section-head">
                                    <div class="ata-section-title"><i class="material-icons-outlined">history</i> Transfer History</div>
                                </div>
                                <div style="overflow-x:auto;padding:0 12px 12px;">
                                    <table class="table table-sm" style="font-size:13px;">
                                        <thead>
                                            <tr><th>Date</th><th>Product</th><th>From</th><th>To</th><th>Bundles</th><th style="text-align:right;">Pieces</th><th>By</th><th></th></tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!$batches): ?>
                                            <tr><td colspan="8" class="text-muted" style="padding:16px;">No transfers yet.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($batches as $b):
                                            $srcNos = explode(',', $b['source_nos']);
                                            $newNos = explode(',', $b['new_nos']);
                                            $pairs = [];
                                            foreach ($srcNos as $i => $sno) { $pairs[] = '#' . (int)$sno . ' → #' . (int)($newNos[$i] ?? 0); }
                                        ?>
                                            <tr<?= $b['status'] === 'undone' ? ' style="opacity:.55;"' : '' ?>>
                                                <td><?= date('d-M-Y H:i', strtotime($b['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($b['productName'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($b['from_gname'], ENT_QUOTES, 'UTF-8') ?><?= $b['from_wh'] ? ' · ' . htmlspecialchars($b['from_wh'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                                <td><?= htmlspecialchars($b['to_gname'], ENT_QUOTES, 'UTF-8') ?><?= $b['to_wh'] ? ' · ' . htmlspecialchars($b['to_wh'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                                <td><?= (int)$b['bundle_count'] ?><br><span style="color:#9ca3af;font-size:11px;"><?= htmlspecialchars(implode(', ', $pairs), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                <td style="text-align:right;"><?= number_format((int)$b['pieces']) ?></td>
                                                <td><?= htmlspecialchars((string)$b['created_by'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                <?php if ($b['status'] === 'undone'): ?>
                                                    <span class="text-muted small">Undone<?= $b['undone_by'] ? ' by ' . htmlspecialchars($b['undone_by'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                                                <?php elseif ($b['undoable']): ?>
                                                    <form method="post" action="raw-material-bundle-transfer-action.php" style="display:inline;" onsubmit="return confirm('Undo this transfer? The new bundle(s) will be removed and the original bundle(s) reopened.');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="action" value="undo">
                                                        <input type="hidden" name="ref_id" value="<?= htmlspecialchars($b['ref_id'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Undo</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small" title="The new bundle(s) have been used or changed">Can't undo</span>
                                                <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
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
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var product = $('btProduct'), fromCg = $('btFromCg'), fromWh = $('btFromWh'),
        toCg = $('btToCg'), toWh = $('btToWh'), rows = $('btRows'), wrap = $('btListWrap'),
        hint = $('btHint'), submit = $('btSubmit'), summary = $('btSummary'), newestN = $('btNewestN');

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function checkboxes() { return rows.querySelectorAll('input.bt-check'); }

    function updateSummary() {
        var n = 0, pcs = 0;
        checkboxes().forEach(function (c) { if (c.checked) { n++; pcs += parseInt(c.dataset.remaining, 10); } });
        summary.textContent = n ? (n + ' bundle(s) selected · ' + pcs.toLocaleString('en-IN') + ' pieces') : '';
        submit.disabled = n === 0 || !toCg.value || !toWh.value;
    }

    function load() {
        wrap.hidden = true; rows.innerHTML = ''; submit.disabled = true;
        if (!product.value || !fromCg.value || !fromWh.value) {
            hint.hidden = false; return;
        }
        fetch('raw-material-bundle-transfer.php?action=list&raw_product_id=' + encodeURIComponent(product.value)
            + '&company_godown_id=' + encodeURIComponent(fromCg.value)
            + '&warehouse_id=' + encodeURIComponent(fromWh.value), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (list) {
                hint.hidden = list.length > 0;
                if (!list.length) {
                    hint.querySelector('div').textContent = 'No open bundles at this source for the selected product.';
                    return;
                }
                list.forEach(function (b) {
                    var tr = document.createElement('tr');
                    var date = b.created_at ? b.created_at.substring(0, 10) : '';
                    tr.innerHTML = '<td>' + (b.eligible
                        ? '<input type="checkbox" class="bt-check" name="bundle_ids[]" value="' + b.id + '" data-remaining="' + b.remaining_pieces + '">'
                        : '') + '</td>'
                        + '<td>Bundle #' + b.bundle_no + (b.eligible ? '' : ' <span class="text-muted small">(no pieces — not transferable)</span>') + '</td>'
                        + '<td style="text-align:right;">' + b.nominal_pieces.toLocaleString('en-IN') + '</td>'
                        + '<td style="text-align:right;">' + b.remaining_pieces.toLocaleString('en-IN') + '</td>'
                        + '<td>' + esc(date) + '</td>';
                    if (!b.eligible) tr.style.opacity = '.5';
                    rows.appendChild(tr);
                });
                wrap.hidden = false;
                checkboxes().forEach(function (c) { c.addEventListener('change', updateSummary); });
                updateSummary();
            })
            .catch(function () {
                hint.hidden = false;
                hint.querySelector('div').textContent = 'Could not load bundles. Please try again.';
            });
    }

    [product, fromCg, fromWh].forEach(function (el) { el.addEventListener('change', load); });
    [toCg, toWh].forEach(function (el) { el.addEventListener('change', updateSummary); });

    // LIFO helper: the list is already newest-first, so ticking the first N
    // eligible rows selects the newest N.
    newestN.addEventListener('input', function () {
        var n = parseInt(newestN.value, 10) || 0, i = 0;
        checkboxes().forEach(function (c) { c.checked = i < n; i++; });
        updateSummary();
    });

    $('btForm').addEventListener('submit', function (e) {
        if (fromCg.value === toCg.value && fromWh.value === toWh.value) {
            e.preventDefault();
            alert('Destination must differ from the source company profile + warehouse.');
            return;
        }
        var txt = summary.textContent || 'the selected bundles';
        if (!confirm('Transfer ' + txt + '? Each bundle will be closed here and recreated with a new number at the destination.')) {
            e.preventDefault();
        }
    });
})();
</script>
</body>
</html>
