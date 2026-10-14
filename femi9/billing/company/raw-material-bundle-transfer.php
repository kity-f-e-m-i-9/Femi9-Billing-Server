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

// AJAX endpoints (JSON): source summary + a page of the list, and a
// server-side preview of what a count/range rule would transfer.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($_GET['action'] ?? '', ['list', 'preview'], true)) {
    header('Content-Type: application/json');
    $rp = filter_var($_GET['raw_product_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $cg = filter_var($_GET['company_godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $wh = filter_var($_GET['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
    if (!$rp || !$cg || !is_godown_allowed($db_conn, $cg)) {
        echo json_encode(['error' => 'invalid_source']);
        exit;
    }
    if ($_GET['action'] === 'list') {
        $q = filter_var($_GET['q'] ?? '', FILTER_VALIDATE_INT) ?: null;
        $page = get_transferable_raw_material_bundles_page(
            $db_conn, $rp, $cg, $wh, $q,
            (int) ($_GET['limit'] ?? 100), (int) ($_GET['offset'] ?? 0)
        );
        echo json_encode([
            'summary'  => get_transferable_bundle_summary($db_conn, $rp, $cg, $wh),
            'rows'     => $page['rows'],
            'total'    => $page['total'],
            'has_more' => $page['has_more'],
            'cap'      => RM_BUNDLE_TRANSFER_CAP,
        ]);
        exit;
    }
    try {
        $sel = select_transferable_raw_material_bundles(
            $db_conn, $rp, $cg, $wh, (string) ($_GET['mode'] ?? ''),
            (int) ($_GET['n'] ?? 0), (int) ($_GET['from'] ?? 0), (int) ($_GET['to'] ?? 0)
        );
        echo json_encode(['count' => count($sel['ids']), 'pieces' => $sel['pieces'], 'overflow' => $sel['overflow'], 'cap' => RM_BUNDLE_TRANSFER_CAP]);
    } catch (StockException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
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
    <link href="../../assets/css/bundle-mobile.css?v=20261014" rel="stylesheet">
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

        .bt-source-summary { font-size:14px; font-weight:600; color:#1f2937; margin-bottom:12px; }
        .bt-source-summary small { display:block; font-weight:400; color:#6b7280; font-size:12.5px; }
        .bt-modes { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
        .bt-mode { margin:0; cursor:pointer; }
        .bt-mode input { position:absolute; opacity:0; pointer-events:none; }
        .bt-mode span { display:inline-block; padding:9px 16px; min-height:40px; border:1px solid #dde1ea; border-radius:999px; font-size:13.5px; font-weight:600; color:#4b5563; background:#fff; }
        .bt-mode input:checked + span { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; border-color:transparent; }
        .bt-mode input:focus-visible + span { outline:2px solid var(--ata-tp-1); outline-offset:2px; }
        .bt-panel[hidden] { display:none; }
        .bt-range { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 220px)); gap:12px; }
        .bt-preview { margin-top:14px; padding:10px 14px; border-radius:10px; background:#eef2ff; color:#4338ca; font-size:13.5px; font-weight:600; }
        .bt-preview:empty { display:none; }
        .bt-preview.is-error { background:#fef2f2; color:#b91c1c; }
        .bt-list-scroll { max-height:52vh; overflow:auto; border:1px solid #eef0f3; border-radius:10px; }
        .bt-list-foot { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:8px; }
        .ata-chip { border:1px solid #c7d2fe; background:#f5f6ff; color:#4338ca; border-radius:999px; font-size:12.5px; font-weight:600; padding:7px 14px; min-height:36px; cursor:pointer; }
        .bt-bar { position:sticky; bottom:0; z-index:20; margin-top:14px; padding:10px 14px; background:#fff; border:1px solid #eef0f3; border-radius:12px; box-shadow:0 -4px 14px rgba(16,24,40,.08); }
        .bt-bar .bt-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .bt-bar .bt-bar-summary { display:block; font-size:13.5px; font-weight:600; color:#4338ca; }
        .bt-bar .ata-btn-submit:disabled { opacity:.5; cursor:not-allowed; }
        @media (max-width: 767px) {
            .bt-bar { padding:0; border:0; box-shadow:none; margin:0; background:transparent; }
            .bt-modes { gap:6px; }
            .bt-mode span { padding:9px 12px; }
            .bt-list-scroll { max-height:46vh; }
        }
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
                                            <div>Pick the product and the source company profile + warehouse to see what can be transferred.</div>
                                        </div>

                                        <div id="btSelectWrap" hidden>
                                            <div class="bt-source-summary" id="btSourceSummary"></div>

                                            <div class="bt-modes" role="radiogroup" aria-label="How to choose bundles">
                                                <label class="bt-mode"><input type="radio" name="selection_mode" value="newest" checked><span>Newest N</span></label>
                                                <label class="bt-mode"><input type="radio" name="selection_mode" value="oldest"><span>Oldest N</span></label>
                                                <label class="bt-mode"><input type="radio" name="selection_mode" value="range"><span>Number range</span></label>
                                                <label class="bt-mode"><input type="radio" name="selection_mode" value="ids"><span>Pick individually</span></label>
                                            </div>

                                            <div class="bt-panel" data-mode="newest oldest">
                                                <div class="ata-field" style="max-width:260px;">
                                                    <label id="btCountLabel">How many bundles (newest first)</label>
                                                    <input type="number" inputmode="numeric" min="1" max="2000" name="count_n" id="btCountN" placeholder="e.g. 300">
                                                </div>
                                            </div>
                                            <div class="bt-panel" data-mode="range" hidden>
                                                <div class="bt-range">
                                                    <div class="ata-field"><label>From bundle no.</label><input type="number" inputmode="numeric" min="1" name="range_from" id="btRangeFrom" placeholder="e.g. 12"></div>
                                                    <div class="ata-field"><label>To bundle no.</label><input type="number" inputmode="numeric" min="1" name="range_to" id="btRangeTo" placeholder="e.g. 480"></div>
                                                </div>
                                            </div>
                                            <div class="bt-panel" data-mode="ids" hidden>
                                                <div class="ata-field" style="max-width:260px;margin-bottom:10px;">
                                                    <label>Find bundle no.</label>
                                                    <input type="number" inputmode="numeric" min="1" id="btSearch" placeholder="Type a bundle number">
                                                </div>
                                                <div class="bt-list-scroll">
                                                    <table class="table table-sm" style="font-size:13px;">
                                                        <thead><tr><th style="width:40px;"></th><th>Bundle</th><th style="text-align:right;">Nominal</th><th style="text-align:right;">Remaining</th><th>Created</th></tr></thead>
                                                        <tbody id="btRows"></tbody>
                                                    </table>
                                                </div>
                                                <div class="bt-list-foot">
                                                    <span id="btListInfo" class="text-muted small"></span>
                                                    <button type="button" class="ata-chip" id="btMore" hidden>Show 100 more</button>
                                                </div>
                                            </div>

                                            <div class="bt-preview" id="btPreview"></div>
                                        </div>
                                        <div id="btPickedInputs"></div>
                                    </div>
                                </form>
                            </div>

                            <div class="bt-bar">
                                <div class="bt-actions">
                                    <span class="bm-bar-summary bt-bar-summary" id="btBarSummary">Nothing selected yet</span>
                                    <button type="submit" form="btForm" class="ata-btn ata-btn-tp ata-btn-submit" id="btSubmit" disabled>
                                        <i class="material-icons" style="font-size:17px;">swap_horiz</i> Transfer
                                    </button>
                                </div>
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
<script src="../../assets/js/bundle-mobile.js?v=20261014"></script>
<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var product = $('btProduct'), fromCg = $('btFromCg'), fromWh = $('btFromWh'),
        toCg = $('btToCg'), toWh = $('btToWh'), rows = $('btRows'),
        hint = $('btHint'), selectWrap = $('btSelectWrap'), sourceSummary = $('btSourceSummary'),
        preview = $('btPreview'), submit = $('btSubmit'), barSummary = $('btBarSummary'),
        countN = $('btCountN'), rangeFrom = $('btRangeFrom'), rangeTo = $('btRangeTo'),
        search = $('btSearch'), moreBtn = $('btMore'), listInfo = $('btListInfo'),
        pickedInputs = $('btPickedInputs');

    var CAP = 2000, PICK_CAP = 200, PAGE = 100;
    var selected = new Map();           // id -> remaining (Pick individually)
    var listOffset = 0, listQuery = '', listToken = 0, previewToken = 0, previewTimer = null;
    var previewState = { count: 0, pieces: 0, ok: false };

    function fmt(n) { return Number(n).toLocaleString('en-IN'); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function mode() { return document.querySelector('input[name="selection_mode"]:checked').value; }
    function sourceReady() { return !!(product.value && fromCg.value && fromWh.value); }
    function qs(extra) {
        return 'raw-material-bundle-transfer.php?raw_product_id=' + encodeURIComponent(product.value)
            + '&company_godown_id=' + encodeURIComponent(fromCg.value)
            + '&warehouse_id=' + encodeURIComponent(fromWh.value) + (extra || '');
    }

    // ── bar + submit state ───────────────────────────────────────────
    function refreshBar() {
        var m = mode(), text = 'Nothing selected yet', ok = false;
        if (m === 'ids') {
            if (selected.size) {
                var pcs = 0; selected.forEach(function (v) { pcs += v; });
                text = selected.size + ' bundle(s) · ' + fmt(pcs) + ' pc';
                ok = selected.size <= PICK_CAP;
            }
        } else if (previewState.ok && previewState.count > 0) {
            text = previewState.count + ' bundle(s) · ' + fmt(previewState.pieces) + ' pc';
            ok = true;
        }
        barSummary.textContent = text;
        submit.disabled = !(ok && toCg.value && toWh.value && sourceReady());
    }

    // ── rule modes: server-side preview ──────────────────────────────
    function showPreview(msg, isError) {
        preview.textContent = msg || '';
        preview.classList.toggle('is-error', !!isError);
    }
    function schedulePreview() {
        clearTimeout(previewTimer);
        previewState = { count: 0, pieces: 0, ok: false };
        var m = mode();
        if (m === 'ids' || !sourceReady()) { showPreview(''); refreshBar(); return; }
        var extra;
        if (m === 'range') {
            var f = parseInt(rangeFrom.value, 10), t = parseInt(rangeTo.value, 10);
            if (!(f > 0) || !(t >= f)) { showPreview(''); refreshBar(); return; }
            extra = '&action=preview&mode=range&from=' + f + '&to=' + t;
        } else {
            var n = parseInt(countN.value, 10);
            if (!(n > 0)) { showPreview(''); refreshBar(); return; }
            extra = '&action=preview&mode=' + m + '&n=' + n;
        }
        refreshBar();
        var token = ++previewToken;
        previewTimer = setTimeout(function () {
            fetch(qs(extra), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (token !== previewToken) return;
                    if (d.error) { showPreview(d.error, true); previewState = { count: 0, pieces: 0, ok: false }; }
                    else if (d.overflow) {
                        showPreview('More than ' + fmt(d.cap) + ' bundles match — narrow the range or lower the count (max ' + fmt(d.cap) + ' per transfer).', true);
                        previewState = { count: 0, pieces: 0, ok: false };
                    } else if (d.count === 0) {
                        showPreview('No transferable bundles match.', true);
                        previewState = { count: 0, pieces: 0, ok: false };
                    } else {
                        showPreview('Will transfer ' + fmt(d.count) + ' bundle(s) · ' + fmt(d.pieces) + ' pieces.');
                        previewState = { count: d.count, pieces: d.pieces, ok: true };
                    }
                    refreshBar();
                })
                .catch(function () { if (token === previewToken) { showPreview('Could not check — try again.', true); refreshBar(); } });
        }, 300);
    }

    // ── pick-individually list (paged) ───────────────────────────────
    function renderRow(b) {
        var tr = document.createElement('tr');
        var date = b.created_at ? b.created_at.substring(0, 10) : '';
        var isSel = selected.has(b.id);
        tr.innerHTML = '<td><input type="checkbox" class="bt-check" value="' + b.id + '" data-remaining="' + b.remaining_pieces + '"' + (isSel ? ' checked' : '') + '></td>'
            + '<td>Bundle #' + b.bundle_no + '</td>'
            + '<td style="text-align:right;">' + fmt(b.nominal_pieces) + '</td>'
            + '<td style="text-align:right;">' + fmt(b.remaining_pieces) + '</td>'
            + '<td>' + esc(date) + '</td>';
        tr.querySelector('.bt-check').addEventListener('change', function (e) {
            if (e.target.checked) {
                if (selected.size >= PICK_CAP) {
                    e.target.checked = false;
                    alert('You can hand-pick at most ' + PICK_CAP + ' bundles. Use "Newest N", "Oldest N" or "Number range" for larger transfers.');
                    return;
                }
                selected.set(b.id, b.remaining_pieces);
            } else {
                selected.delete(b.id);
            }
            refreshBar();
        });
        return tr;
    }
    function loadList(append) {
        if (!sourceReady()) return;
        if (!append) { listOffset = 0; rows.innerHTML = ''; }
        var token = ++listToken;
        fetch(qs('&action=list&limit=' + PAGE + '&offset=' + listOffset + (listQuery ? '&q=' + encodeURIComponent(listQuery) : '')), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (token !== listToken || d.error) return;
                d.rows.forEach(function (b) { rows.appendChild(renderRow(b)); });
                listOffset += d.rows.length;
                moreBtn.hidden = !d.has_more;
                listInfo.textContent = d.total
                    ? ('Showing ' + fmt(Math.min(listOffset, d.total)) + ' of ' + fmt(d.total) + ' (newest first)')
                    : 'No matching bundles.';
            });
    }

    // ── source load ──────────────────────────────────────────────────
    function loadSource() {
        selected.clear(); rows.innerHTML = ''; selectWrap.hidden = true; showPreview('');
        previewState = { count: 0, pieces: 0, ok: false };
        refreshBar();
        if (!sourceReady()) { hint.hidden = false; hint.querySelector('div').textContent = 'Pick the product and the source company profile + warehouse to see what can be transferred.'; return; }
        fetch(qs('&action=list&limit=' + PAGE + '&offset=0'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) { hint.hidden = false; hint.querySelector('div').textContent = 'You are not authorized for this source.'; return; }
                var s = d.summary;
                if (!s.eligible_count) {
                    hint.hidden = false;
                    hint.querySelector('div').textContent = s.open_total
                        ? ('This source has ' + fmt(s.open_total) + ' open bundle(s), but none have pieces remaining.')
                        : 'No open bundles at this source for the selected product.';
                    return;
                }
                hint.hidden = true;
                selectWrap.hidden = false;
                sourceSummary.innerHTML = fmt(s.eligible_count) + ' transferable bundle' + (s.eligible_count === 1 ? '' : 's') + ' · ' + fmt(s.eligible_pieces) + ' pc'
                    + (s.open_total > s.eligible_count ? '<small>' + fmt(s.open_total - s.eligible_count) + ' more open bundle(s) have no pieces left.</small>' : '');
                listOffset = 0;
                d.rows.forEach(function (b) { rows.appendChild(renderRow(b)); });
                listOffset = d.rows.length;
                moreBtn.hidden = !d.has_more;
                listInfo.textContent = 'Showing ' + fmt(listOffset) + ' of ' + fmt(d.total) + ' (newest first)';
                applyMode();
            })
            .catch(function () { hint.hidden = false; hint.querySelector('div').textContent = 'Could not load bundles. Please try again.'; });
    }

    function applyMode() {
        var m = mode();
        document.querySelectorAll('.bt-panel').forEach(function (p) {
            p.hidden = p.getAttribute('data-mode').split(' ').indexOf(m) === -1;
        });
        $('btCountLabel').textContent = m === 'oldest' ? 'How many bundles (oldest first)' : 'How many bundles (newest first)';
        // Rule modes never post per-bundle checkboxes.
        pickedInputs.innerHTML = '';
        schedulePreview();
        refreshBar();
    }

    [product, fromCg, fromWh].forEach(function (el) { el.addEventListener('change', loadSource); });
    [toCg, toWh].forEach(function (el) { el.addEventListener('change', refreshBar); });
    document.querySelectorAll('input[name="selection_mode"]').forEach(function (r) { r.addEventListener('change', applyMode); });
    [countN, rangeFrom, rangeTo].forEach(function (el) { el.addEventListener('input', schedulePreview); });
    moreBtn.addEventListener('click', function () { loadList(true); });
    var searchTimer = null;
    search.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { listQuery = search.value.trim(); loadList(false); }, 300);
    });

    $('btForm').addEventListener('submit', function (e) {
        if (fromCg.value === toCg.value && fromWh.value === toWh.value) {
            e.preventDefault();
            alert('Destination must differ from the source company profile + warehouse.');
            return;
        }
        var m = mode();
        if (m === 'ids') {
            pickedInputs.innerHTML = '';
            selected.forEach(function (_, id) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = 'bundle_ids[]'; i.value = id;
                pickedInputs.appendChild(i);
            });
        } else {
            pickedInputs.innerHTML = '';
        }
        var txt = barSummary.textContent || 'the selected bundles';
        if (!confirm('Transfer ' + txt + '? Each bundle will be closed here and recreated with a new number at the destination.')) {
            e.preventDefault();
        }
    });
})();
</script>
</body>
</html>
