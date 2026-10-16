<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/MachineCodes.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin', 'stockviewer'], true)) {
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
$damageReasons = get_active_bundle_reasons($db_conn, 'damage');
$extraReasons  = get_active_bundle_reasons($db_conn, 'extra');
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
        .ata-row-grid {
            display:grid;
            grid-template-columns: 1.6fr 0.9fr 1.3fr 1.1fr 1.3fr 1.3fr auto;
            gap:10px; align-items:end;
        }
        .ata-row-grid .ata-field-wide { grid-column: span 2; }
        .ata-field-action { width:auto; }
        @media (max-width: 900px) {
            .ata-row-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
            .ata-row-grid .ata-field-wide { grid-column: span 2; }
        }
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
        .ata-bundle-row { display:grid; grid-template-columns: 2.6fr 1fr; gap:10px; align-items:end; }
        .ata-bundle-row .ata-field { margin:0; }
        @media (max-width: 700px) {
            .ata-bundle-row { grid-template-columns: 1fr; }
        }
        .ata-bundle-label { display:flex; align-items:center; gap:4px; margin-bottom:6px; }
        .ata-bundle-label i { font-size:15px; color:var(--ata-tp-2); }
        .ata-bundle-close-btn {
            display:inline-flex; align-items:center; justify-content:center; gap:5px; height:46px; width:100%; padding:0 18px;
            border-radius:9px; border:1px solid #fecaca; background:#fff5f5; color:#b91c1c;
            font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap;
            transition:background .15s, opacity .15s;
        }
        .ata-bundle-close-btn:hover:not(:disabled) { background:#fee2e2; }
        .ata-bundle-close-btn:disabled { opacity:.4; cursor:not-allowed; }
        .ata-bundle-empty-hint { grid-column: 1 / -1; background:#fef2f2; border:1px solid #fecaca; border-radius:9px; padding:0 14px; height:46px; display:none; align-items:center; font-size:12.5px; color:#b91c1c; line-height:1.3; }
        .ata-bundle-empty-hint i { font-size:14px; vertical-align:middle; margin-right:3px; }
        .ata-add-bundle-btn { display:flex; align-items:center; justify-content:center; gap:6px; width:100%; margin-top:12px; border:1.5px dashed #a5b4fc; background:#f5f6ff; color:#4338ca; border-radius:10px; min-height:46px; padding:0 12px; font-size:13px; font-weight:600; cursor:pointer; transition:background .15s; }
        .ata-add-bundle-btn:hover { background:#eaecff; }
        .ata-add-bundle-btn.is-hidden { display:none !important; }
        .bundle-remaining-after.is-ok { color:#4338ca; }
        .bundle-remaining-after.is-short { color:#b91c1c; font-weight:600; }

        .ata-adjust-group { margin-top:16px; }
        .ata-adjust-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
        .ata-adjust-title { font-size:12px; font-weight:600; color:#4b5563; text-transform:uppercase; letter-spacing:.02em; }
        .cover-balance-chip { text-transform:none; font-weight:500; color:#6b7280; font-size:11.5px; margin-left:4px; }
        .cover-balance-chip.is-low { color:#b91c1c; font-weight:600; }
        .ata-adjust-add-btn {
            display:inline-flex; align-items:center; gap:3px; border:1px dashed #c7d2fe; background:#f5f6ff;
            color:#4338ca; font-size:12px; font-weight:600; padding:5px 10px; border-radius:7px; cursor:pointer;
            transition:background .15s;
        }
        .ata-adjust-add-btn:hover { background:#eef0ff; }
        .ata-adjust-rows { display:flex; flex-direction:column; gap:8px; }
        .ata-adjust-rows:empty { display:none; }
        .ata-adjust-entry-row { display:grid; grid-template-columns: 2fr 1fr auto; gap:10px; align-items:center; }
        .ata-adjust-entry-row .ata-field { margin:0; }
        .ata-adjust-entry-row select, .ata-adjust-entry-row input {
            border:1px solid #dde1ea; border-radius:8px; height:40px; font-size:13.5px; width:100%;
            padding:0 12px; color:#344054; background:#fff;
        }
        .ata-adjust-entry-row select:focus, .ata-adjust-entry-row input:focus { border-color: var(--ata-tp-1); box-shadow: 0 0 0 3px rgba(102,126,234,.15); outline:none; }
        .ata-adjust-remove-btn {
            width:40px; height:40px; border-radius:8px; border:1px solid #fecdd3; background:#fff; color:#e11d48;
            display:inline-flex; align-items:center; justify-content:center; padding:0; cursor:pointer;
            transition: background .15s, color .15s;
        }
        .ata-adjust-remove-btn:hover { background:#e11d48; color:#fff; border-color:#e11d48; }

        /* Entry rows are shorter (40px) than the main product-row fields
           (46px) — the reason select's Select2 container inherits the
           global 46px sizing below, so it's overridden here to match its
           own row's Qty input and Remove button height. */
        .ata-adjust-entry-row .select2-container--default .select2-selection--single {
            height:40px !important; padding:0 32px 0 12px !important; border-radius:8px !important; font-size:13.5px;
        }
        .ata-adjust-entry-row .select2-selection__rendered { font-size:13.5px !important; }

        .select2-container { width:100% !important; margin:0 !important; }
        .select2-container--default .select2-selection--single {
            box-sizing: border-box !important;
            border: 1px solid #dde1ea !important; border-radius: 9px !important; height: 46px !important;
            padding: 0 36px 0 14px !important; margin: 0 !important;
            display:flex; align-items:center; background:#fff !important;
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
        .select2-container--default.select2-container--open .select2-selection--single { border-color: #667eea !important; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
        .select2-dropdown { border-radius: 9px; border-color: #dde1ea; box-shadow: 0 8px 24px rgba(0,0,0,.12); }
        .select2-search--dropdown .select2-search__field { border-radius: 6px; border: 1px solid #dde1ea; padding: 7px 10px; font-size:13.5px; }

        .ata-bundle-row .select2-container--default.select2-container--open .select2-selection--single { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.15); }
        .bundle-option-remaining { display: inline-block; font-size: 10.5px; font-weight: 600; color: #6d28d9; background: #ede9fe; border-radius: 6px; padding: 1px 7px; margin-left: 8px; white-space: nowrap; flex-shrink:0; }
        .select2-results__option .bundle-option-remaining { float: right; margin-top: 2px; }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Mobile-first refinements ─────────────────────────────── */
        .ata-line-head { display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
        .ata-line-head[hidden] { display:none; }
        .ata-line-tag { font-size:11.5px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:#4338ca; background:#eef2ff; border-radius:999px; padding:3px 10px; }
        .ata-line-total { font-size:12.5px; font-weight:600; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:999px; padding:3px 10px; }
        .ata-line-total:empty { display:none; }

        /* An "Another bundle" line: compact continuation of the product above */
        .ata-row-card.is-extra-line { margin-top:-6px; margin-left:14px; border-left-width:3px; border-left-style:dashed; background:#fafbff; }
        .ata-row-card.is-extra-line .ata-field-product,
        .ata-row-card.is-extra-line .ata-field-direction,
        .ata-row-card.is-extra-line .ata-field-machine,
        .ata-row-card.is-extra-line .ata-field-stock { display:none !important; }
        .ata-row-card.is-extra-line .ata-row-grid { grid-template-columns: 1fr auto; }

        .ata-adjust-details { margin-top:14px; border:1px solid #eef0f3; border-radius:10px; background:#fbfbfd; }
        .ata-adjust-details > summary { display:flex; align-items:center; gap:8px; padding:11px 14px; cursor:pointer; list-style:none; font-size:13px; font-weight:600; color:#374151; min-height:44px; }
        .ata-adjust-details > summary::-webkit-details-marker { display:none; }
        .ata-adjust-details > summary::after { content:'expand_more'; font-family:'Material Icons Outlined','Material Icons'; margin-left:auto; font-size:20px; color:#9ca3af; transition:transform .15s; }
        .ata-adjust-details[open] > summary::after { transform:rotate(180deg); }
        .ata-adjust-hint { font-weight:400; font-size:12px; color:#9ca3af; }
        .ata-adjust-badge { background:#4338ca; color:#fff; border-radius:999px; font-size:11px; font-weight:700; padding:1px 8px; }
        .ata-adjust-badge[hidden] { display:none; }
        .ata-adjust-details > .ata-adjust-group { margin:0; padding:0 14px 14px; }
        .ata-adjust-details > .ata-adjust-group:first-of-type { margin-top:2px; }

        .bundle-remaining-after { padding:9px 12px; border-radius:9px; background:#eef2ff; line-height:1.4; }
        .bundle-remaining-after.is-short { background:#fef2f2; }

        .ata-submit-bar { display:flex; align-items:center; justify-content:flex-end; gap:14px; margin:14px 0 24px; }
        .ata-submit-summary { font-size:13px; color:#6b7280; font-weight:500; }

        @media (max-width: 767px) {
            .ata-nav-tabs { flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; margin-left:-4px; margin-right:-4px; padding:0 4px 4px; }
            .ata-nav-tabs::-webkit-scrollbar { display:none; }
            .ata-nav-tab { white-space:nowrap; flex:0 0 auto; padding:10px 14px; }
            .ata-section-head { padding:12px 14px; }
            .ata-section-head > div[style*="display:flex"] { width:100%; }
            .ata-section-head > div[style*="display:flex"] .ata-btn { flex:1 1 0; justify-content:center; min-height:44px; }
            .ata-scope-grid { grid-template-columns:1fr; padding:14px; }
            .ata-rows { padding:14px 12px 18px; }
            .ata-row-card { padding:12px; }
            .ata-row-grid { grid-template-columns: 1fr 1fr; }
            .ata-row-grid .ata-field-wide,
            .ata-row-grid .ata-field-machine,
            .ata-row-grid .ata-field-stock,
            .ata-row-grid .ata-field-action { grid-column: 1 / -1; }
            .ata-row-grid .ata-field-action { display:flex; justify-content:flex-end; gap:8px; align-items:center; }
            .ata-row-grid .ata-field-action label { display:none; }
            .ata-row-card.is-extra-line { margin-left:8px; }
            .ata-row-card.is-extra-line .ata-row-grid { grid-template-columns: 1fr auto; }
            .ata-row-card.is-extra-line .ata-row-grid .ata-field-action { grid-column:auto; }
            .ata-adjust-entry-row { grid-template-columns: 1fr auto; }
            .ata-adjust-entry-row > .ata-field:first-child { grid-column: 1 / -1; }
            .ata-adjust-hint { display:none; }
            .ata-submit-bar { position:sticky; bottom:0; z-index:20; margin:14px -12px 0; padding:10px 14px calc(10px + env(safe-area-inset-bottom)); background:#fff; border-top:1px solid #e5e7eb; box-shadow:0 -4px 14px rgba(16,24,40,.08); justify-content:space-between; }
            .ata-submit-bar .ata-btn-submit { min-height:46px; flex:0 0 auto; }
            .ata-intro { font-size:13px; }
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
                        <h1><span class="ata-icon-badge"><i class="material-icons-outlined">sync_alt</i></span>Convert Pieces &harr; Packs</h1>
                    </div>
                    <p class="ata-intro">Assemble loose pieces into whole packs, or break a pack back open — for one product or several at once. Use &ldquo;Another bundle&rdquo; to convert one product from several bundles in one go.</p>

                    <div class="ata-nav-tabs">
                        <a href="input-stock-bundles.php" class="ata-nav-tab"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="raw-material-bundle-transfer.php" class="ata-nav-tab"><i class="material-icons-outlined">swap_horiz</i> Bundle Transfer</a>
                        <a href="neksomo-piece-pack-convert.php" class="ata-nav-tab active"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
                        <a href="manage-piece-pack-conversions.php" class="ata-nav-tab"><i class="material-icons-outlined">history</i> Manage Conversions</a>
                        <a href="raw-material-bundles-report.php" class="ata-nav-tab"><i class="material-icons-outlined">assessment</i> Bundle Report</a>
                        <a href="manage-covers.php" class="ata-nav-tab"><i class="material-icons-outlined">layers</i> Covers</a>
                        <a href="manage-cartons.php" class="ata-nav-tab"><i class="material-icons-outlined">inbox</i> Cartons</a>
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
                                <div class="ata-field">
                                    <label>Conversion Date <span class="required">*</span></label>
                                    <input type="date" required name="conversion_date" id="conversionDateInput" max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
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

                        <div class="ata-submit-bar">
                            <div class="ata-submit-summary" id="convertSummary">No products yet</div>
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
                            <div class="ata-line-head" hidden>
                                <span class="ata-line-tag"></span>
                                <span class="ata-line-total"></span>
                            </div>
                            <div class="ata-row-grid">
                                <div class="ata-field ata-field-wide ata-field-product">
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
                                <div class="ata-field ata-field-direction">
                                    <label>Direction</label>
                                    <select required name="direction[]" class="direction-select">
                                        <option value="pieces_to_pack">&#8599; Pieces &rarr; Pack</option>
                                        <option value="pack_to_pieces">&#8600; Pack &rarr; Pieces</option>
                                    </select>
                                </div>
                                <div class="ata-field">
                                    <label class="pack-count-label">Total Packs Produced</label>
                                    <input type="number" min="1" required name="pack_count[]" class="pack-count-input" placeholder="e.g. 5">
                                </div>
                                <div class="ata-field ata-field-machine">
                                    <label>Machine Code</label>
                                    <select name="machine_code_id[]" class="machine-code-select">
                                        <option value="">— None —</option>
                                        <?php foreach ($machineCodes as $mc): ?>
                                        <option value="<?= (int)$mc['id'] ?>"><?= htmlspecialchars($mc['code'], ENT_QUOTES, 'UTF-8') ?><?= $mc['name'] ? ' - ' . htmlspecialchars($mc['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ata-field ata-field-stock">
                                    <label>Current Stock</label>
                                    <span class="current-stock-panel ata-stock-chip is-empty"><i class="material-icons-outlined">inventory</i>&mdash;</span>
                                </div>
                                <div class="ata-field ata-field-action">
                                    <label>&nbsp;</label>
                                    <button type="button" class="ata-remove-btn" title="Remove this product">
                                        <i class="material-icons-outlined" style="font-size:19px;">delete</i>
                                    </button>
                                </div>
                            </div>
                            <div class="bundle-picker-wrap ata-bundle-strip">
                                <div class="ata-bundle-row">
                                    <div class="ata-field ata-field-bundle">
                                        <label class="ata-bundle-label"><i class="material-icons-outlined">inventory_2</i>Raw Material Bundle <span style="color:#ef4444;">*</span></label>
                                        <select class="bundle-select" name="bundle_id[]">
                                            <option value="">— Select bundle —</option>
                                        </select>
                                    </div>
                                    <div class="ata-field ata-field-action">
                                        <label class="ata-bundle-label">&nbsp;</label>
                                        <button type="button" class="ata-bundle-close-btn bundle-close-btn" disabled title="Select a bundle first">
                                            <i class="material-icons-outlined" style="font-size:15px;">close</i> Close Bundle
                                        </button>
                                    </div>
                                    <div class="bundle-remaining-after" style="grid-column:1/-1;display:none;font-size:12.5px;"></div>
                                    <div class="bundle-empty-hint ata-bundle-empty-hint">
                                        <i class="material-icons-outlined">error_outline</i>No open bundle or bucket pieces yet — add a bundle via Input Stock &rarr; Raw Bundles.
                                    </div>
                                </div>

                                <details class="ata-adjust-details">
                                <summary>
                                    <i class="material-icons-outlined" style="font-size:17px;">tune</i>
                                    <span>Adjustments</span>
                                    <span class="ata-adjust-hint">damaged · extra · covers</span>
                                    <span class="ata-adjust-badge" hidden>0</span>
                                </summary>
                                <div class="ata-adjust-group" data-adjust-type="damage">
                                    <div class="ata-adjust-head">
                                        <span class="ata-adjust-title"><i class="material-icons-outlined" style="font-size:16px;vertical-align:middle;color:#b91c1c;">report</i> Damaged Pieces</span>
                                        <button type="button" class="ata-adjust-add-btn" data-adjust-type="damage">
                                            <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">add</i> Add damaged entry
                                        </button>
                                    </div>
                                    <div class="ata-adjust-rows" data-adjust-type="damage"></div>
                                </div>

                                <div class="ata-adjust-group" data-adjust-type="extra">
                                    <div class="ata-adjust-head">
                                        <span class="ata-adjust-title"><i class="material-icons-outlined" style="font-size:16px;vertical-align:middle;color:#b45309;">add_circle_outline</i> Extra Pieces Found</span>
                                        <button type="button" class="ata-adjust-add-btn" data-adjust-type="extra">
                                            <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">add</i> Add extra entry
                                        </button>
                                    </div>
                                    <div class="ata-adjust-rows" data-adjust-type="extra"></div>
                                </div>

                                <div class="ata-adjust-group" data-adjust-type="cover_damage">
                                    <div class="ata-adjust-head">
                                        <span class="ata-adjust-title"><i class="material-icons-outlined" style="font-size:16px;vertical-align:middle;color:#b91c1c;">layers</i> Damaged Covers <span class="cover-balance-chip"></span></span>
                                        <button type="button" class="ata-adjust-add-btn" data-adjust-type="cover_damage">
                                            <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">add</i> Add damaged cover entry
                                        </button>
                                    </div>
                                    <div class="ata-adjust-rows" data-adjust-type="cover_damage"></div>
                                </div>
                                </details>
                                <button type="button" class="ata-add-bundle-btn add-bundle-btn" style="display:none;" title="Convert this product from another bundle too (adds a new line below)">
                                    <i class="material-icons-outlined" style="font-size:18px;">add_circle_outline</i> Add another bundle for this product
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- One damaged/extra adjustment entry row, cloned by JS for
                         each entry added under a mapped product's bundle strip.
                         data-adjust-type on the row (set at clone time) decides
                         which reason list/field names it uses — damage_reason_id[]
                         + damaged_qty[] vs extra_reason_id[] + extra_qty[]. -->
                    <template id="adjustEntryRowTemplate">
                        <div class="ata-adjust-entry-row">
                            <div class="ata-field">
                                <select class="adjust-reason-select" required>
                                    <option value="" hidden>— Select reason —</option>
                                    <option value="__manage__" class="adjust-manage-option">+ Manage Reasons…</option>
                                </select>
                            </div>
                            <div class="ata-field ata-adjust-qty-field">
                                <input type="number" min="1" required class="adjust-qty-input" placeholder="Qty">
                            </div>
                            <button type="button" class="ata-adjust-remove-btn" title="Remove this entry">
                                <i class="material-icons-outlined" style="font-size:17px;">delete</i>
                            </button>
                        </div>
                    </template>

                    <!-- Manage Reasons modal: shared by both the Damaged Pieces and
                         Extra Pieces Found reason dropdowns — which list it edits
                         is set at open time (see openReasonManageModal()), same
                         bootstrap-free overlay pattern as Manage Machine Codes. -->
                    <div id="reasonModalBackdrop" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:1050; align-items:center; justify-content:center; padding:20px;">
                        <div style="background:#fff; border-radius:14px; width:100%; max-width:440px; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.25);">
                            <div class="ata-section-head" style="border-radius:14px 14px 0 0;">
                                <div class="ata-section-title"><i class="material-icons-outlined" id="reasonModalIcon">report</i> <span id="reasonModalTitle">Manage Damage Reasons</span></div>
                                <button type="button" id="reasonModalClose" style="border:none; background:none; cursor:pointer; color:#6b7280;"><i class="material-icons">close</i></button>
                            </div>
                            <div style="padding:16px 20px; overflow-y:auto; flex:1 1 auto;">
                                <div style="display:flex; gap:8px; margin-bottom:14px;">
                                    <input type="text" id="reasonFormLabel" placeholder="Reason (e.g. Torn during handling)" style="flex:1 1 auto; border:1px solid #dde1ea; border-radius:8px; height:40px; padding:0 12px; font-size:13.5px;">
                                    <input type="hidden" id="reasonFormId" value="">
                                    <button type="button" id="reasonFormSubmit" class="ata-btn ata-btn-tp" style="height:40px;">Add</button>
                                    <button type="button" id="reasonFormCancelEdit" style="display:none; height:40px; padding:0 12px; border:1px solid #dde1ea; border-radius:8px; background:#fff; cursor:pointer;">Cancel</button>
                                </div>
                                <div id="reasonFormError" style="display:none; color:#b91c1c; font-size:12.5px; margin-bottom:10px;"></div>
                                <div id="reasonList" style="display:flex; flex-direction:column; gap:6px;"></div>
                            </div>
                        </div>
                    </div>

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

function updateBundlePicker(rowEl, mapped, openBundles, bucketPieces) {
    var wrap = rowEl.querySelector('.bundle-picker-wrap');
    var select = rowEl.querySelector('.bundle-select');
    var emptyHint = rowEl.querySelector('.bundle-empty-hint');

    if ($(select).data('select2')) {
        $(select).select2('destroy');
    }

    var addBundleBtn = rowEl.querySelector('.add-bundle-btn');
    if (addBundleBtn) addBundleBtn.style.display = mapped ? '' : 'none';

    if (!mapped) {
        wrap.style.display = 'none';
        select.removeAttribute('required');
        select.value = '';
        // Clear any damaged/extra entries left over from when this row
        // was mapped to a different, bundle-sourced product — they'd
        // otherwise still submit (and fail validation) for a row that
        // no longer even shows the bundle strip. Each entry's reason
        // <select> is a Select2 instance, so it's torn down before its
        // row is discarded rather than just wiping the container's
        // innerHTML out from under it.
        rowEl.querySelectorAll('.ata-adjust-rows').forEach(function (el) {
            el.querySelectorAll('.adjust-reason-select').forEach(function (s) {
                if ($(s).data('select2')) { $(s).select2('destroy'); }
            });
            el.innerHTML = '';
        });
        return;
    }

    wrap.style.display = 'block';
    select.setAttribute('required', 'required');

    var currentVal = select.value;
    var html = '<option value="">— Select bundle —</option>';
    bucketPieces = parseInt(bucketPieces, 10) || 0;
    if (bucketPieces > 0) {
        html += '<option value="bucket" data-remaining="' + bucketPieces + '" data-bucket="1">Loose pieces bucket</option>';
    }
    (openBundles || []).forEach(function (b) {
        html += '<option value="' + b.id + '" data-remaining="' + b.remaining_pieces + '">' + escBd(b.label) + '</option>';
    });
    select.innerHTML = html;
    if (currentVal && Array.from(select.options).some(function (o) { return o.value === currentVal; })) {
        select.value = currentVal;
    }

    var hasBundles = (openBundles || []).length > 0 || bucketPieces > 0;
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
    $(select).off('change.bucket').on('change.bucket', function () { toggleBucketMode(rowEl); });
    toggleBucketMode(rowEl);
}

// The loose-pieces bucket has no per-bundle damage/extra bookkeeping, so
// those two entry groups are hidden (and cleared) while it's selected.
function toggleBucketMode(rowEl) {
    var select = rowEl.querySelector('.bundle-select');
    var isBucket = select.value === 'bucket';
    ['damage', 'extra'].forEach(function (t) {
        var g = rowEl.querySelector('.ata-adjust-group[data-adjust-type="' + t + '"]');
        if (!g) return;
        g.style.display = isBucket ? 'none' : '';
        if (isBucket) {
            g.querySelectorAll('.adjust-reason-select').forEach(function (s) {
                if ($(s).data('select2')) { $(s).select2('destroy'); }
            });
            var rows = g.querySelector('.ata-adjust-rows');
            if (rows) rows.innerHTML = '';
        }
    });
    updateCloseBundleButton(rowEl);
    updateRemainingAfter(rowEl);
    syncSiblingBundleOptions();
}

// Read-only "Remaining after this conversion" line: the picked bundle's
// (or bucket's) current pieces − packs × pieces/pack − damaged + extra,
// recomputed live. Red when the entered packs need more than it holds
// (the server then makes only the full packs that fit).
function updateRemainingAfter(rowEl) {
    var el = rowEl.querySelector('.bundle-remaining-after');
    if (!el) return;
    var select = rowEl.querySelector('.bundle-select');
    var opt = select.options[select.selectedIndex];
    if (!select.value || !opt || opt.getAttribute('data-remaining') === null) {
        el.style.display = 'none';
        return;
    }
    var remaining = parseInt(opt.getAttribute('data-remaining'), 10) || 0;
    var ppp = parseInt($(rowEl.querySelector('.product-select')).find('option:selected').data('pieces-per-pack'), 10) || 0;
    var packs = parseInt(rowEl.querySelector('.pack-count-input').value, 10) || 0;
    function sumQty(type) {
        var t = 0;
        rowEl.querySelectorAll('.ata-adjust-rows[data-adjust-type="' + type + '"] .adjust-qty-input').forEach(function (i) {
            t += parseInt(i.value, 10) || 0;
        });
        return t;
    }
    var damaged = sumQty('damage');
    var extra = sumQty('extra');
    var after = remaining - (packs * ppp) - damaged + extra;
    el.style.display = '';
    if (after < 0) {
        var avail = remaining - damaged + extra;
        el.className = 'bundle-remaining-after is-short';
        el.textContent = 'Short by ' + Math.abs(after).toLocaleString('en-IN') + ' pc — only ' + Math.max(0, Math.floor(avail / (ppp || 1))) + ' pack(s) can be made from this bundle.';
    } else {
        el.className = 'bundle-remaining-after is-ok';
        el.textContent = 'Remaining after this conversion: ' + after.toLocaleString('en-IN') + ' pc';
    }
}

// A bundle can only be picked once per product within a batch — disable
// it in the sibling lines of the same product once another line has it.
function syncSiblingBundleOptions() {
    var rows = Array.from(rowsContainer.querySelectorAll('.product-row'));
    rows.forEach(function (rowEl) {
        var productId = rowEl.querySelector('.product-select').value;
        var select = rowEl.querySelector('.bundle-select');
        var takenElsewhere = {};
        rows.forEach(function (other) {
            if (other === rowEl) return;
            if (other.querySelector('.product-select').value !== productId) return;
            var v = other.querySelector('.bundle-select').value;
            if (v) takenElsewhere[v] = true;
        });
        Array.from(select.options).forEach(function (o) {
            if (!o.value) return;
            o.disabled = !!takenElsewhere[o.value];
        });
    });
}

// Enables/disables the row's "Close Bundle" button based on whether a
// bundle is currently selected, and updates its title with the
// remaining-piece count so the operator sees at a glance what they're
// about to close. Also always restores the button's normal label —
// closeSelectedBundle() swaps it to a "Closing…" spinner for the
// duration of its fetch, and this is the function that runs afterward
// (via refreshRowStock -> updateBundlePicker), so it's the one place
// that must undo that swap or the spinner is left stuck on screen.
function updateCloseBundleButton(rowEl) {
    var select = rowEl.querySelector('.bundle-select');
    var closeBtn = rowEl.querySelector('.bundle-close-btn');
    var selectedOption = select.options[select.selectedIndex];
    var bundleId = select.value;

    closeBtn.innerHTML = '<i class="material-icons-outlined" style="font-size:15px;">close</i> Close Bundle';

    if (!bundleId || !selectedOption) {
        closeBtn.disabled = true;
        closeBtn.title = 'Select a bundle first';
        return;
    }

    if (bundleId === 'bucket') {
        closeBtn.disabled = true;
        closeBtn.title = 'The loose-pieces bucket is not a bundle and cannot be closed';
        return;
    }

    var remaining = selectedOption.getAttribute('data-remaining');
    closeBtn.disabled = false;
    closeBtn.title = 'Close this bundle (' + remaining + ' pc remaining will move to the loose-pieces bucket)';
}

// In-memory cache of each reason type's active list, seeded from PHP so
// the first render needs no round trip; refreshed from bundle-reason-
// manage.php after any add/edit/delete in the Manage Reasons modal so
// every row's dropdowns (and the modal's own list) stay in sync without
// a page reload.
var reasonCache = {
    damage: <?php echo json_encode($damageReasons, JSON_HEX_TAG | JSON_HEX_APOS); ?>,
    extra: <?php echo json_encode($extraReasons, JSON_HEX_TAG | JSON_HEX_APOS); ?>
};
// Cover damage reuses the same damage_reason_master list as raw-material
// damage (it's still "damage", just of covers rather than pieces) — see
// include/ProductCovers.php's record_damaged_covers(). Kept as its own
// cache key so refreshReasonDropdowns('cover_damage') and the entry-name
// stamping below can treat it as a distinct adjust type from 'damage'.
reasonCache.cover_damage = reasonCache.damage;
var adjustEntryRowTemplate = document.getElementById('adjustEntryRowTemplate');

// Rebuilds every rendered reason <select>'s options from reasonCache[type],
// preserving each select's current selection where it still exists (same
// pattern as refreshMachineCodeDropdowns()). The "+ Manage Reasons…"
// option is always appended last so it never gets treated as a real
// selectable reason. Each select is a Select2 (same searchable style as
// the Product picker — see addAdjustEntryRow()), so it must be torn
// down and reinitialized around the innerHTML rebuild rather than just
// swapping options under it.
function refreshReasonDropdowns(type) {
    document.querySelectorAll('.adjust-reason-select[data-adjust-type="' + type + '"]').forEach(function (select) {
        var currentVal = select.value;
        if ($(select).data('select2')) {
            $(select).select2('destroy');
        }
        var html = '<option value="" hidden>— Select reason —</option>';
        (reasonCache[type] || []).forEach(function (r) {
            html += '<option value="' + r.id + '">' + escBd(r.label) + '</option>';
        });
        html += '<option value="__manage__" class="adjust-manage-option">+ Manage Reasons…</option>';
        select.innerHTML = html;
        if (currentVal && currentVal !== '__manage__' && Array.from(select.options).some(function (o) { return o.value === currentVal; })) {
            select.value = currentVal;
        }
        $(select).select2({ placeholder: 'Search a reason…', width: '100%' });
    });
}

// Adds one damaged/extra entry row (reason + qty + remove) under the
// given row's adjust group. The entry's <select>/<input> get no `name`
// at this point — see assignAdjustEntryNames(), called right before
// submit, which is what actually stamps damaged_reason_id[rowIndex][]
// / damaged_qty[rowIndex][] (rowIndex = the product row's live position
// in productRows). Naming it eagerly here would desync the moment any
// earlier product row is removed, since array positions shift but a
// previously-stamped name wouldn't.
function addAdjustEntryRow(rowEl, type) {
    var rowsContainerEl = rowEl.querySelector('.ata-adjust-rows[data-adjust-type="' + type + '"]');
    var fragment = adjustEntryRowTemplate.content.cloneNode(true);
    var entryEl = fragment.querySelector('.ata-adjust-entry-row');
    var reasonSelect = entryEl.querySelector('.adjust-reason-select');

    reasonSelect.setAttribute('data-adjust-type', type);

    entryEl.querySelector('.ata-adjust-remove-btn').addEventListener('click', function () {
        if ($(reasonSelect).data('select2')) {
            $(reasonSelect).select2('destroy');
        }
        entryEl.remove();
        updateAdjustBadge(rowEl);
    });
    // Picking "+ Manage Reasons…" should open the modal, not actually
    // select it as a reason — jQuery's .val('').trigger('change') (not
    // a plain DOM .value = '') is required here so Select2's own
    // rendered selection box updates too; setting the native <select>'s
    // .value alone leaves Select2's UI still showing "+ Manage
    // Reasons…" as selected even though the underlying value reset.
    $(reasonSelect).on('change', function () {
        if (reasonSelect.value === '__manage__') {
            $(reasonSelect).val('').trigger('change');
            openReasonManageModal(type);
        }
    });

    rowsContainerEl.appendChild(fragment);
    refreshReasonDropdowns(type);
    updateAdjustBadge(rowEl);
    var det = rowEl.querySelector('.ata-adjust-details');
    if (det) det.open = true;
}

// Stamps every rendered adjust-entry row's <select>/<input> name
// attributes from the product rows' CURRENT DOM order, so
// damaged_reason_id[rowIndex][]/damaged_qty[rowIndex][] (and the extra_
// equivalents) always line up with that same rowIndex's product_id[]/
// bundle_id[] on the server — run right before every form submit
// (see the convertForm submit handler) rather than at add-entry time,
// so removing an earlier product row never leaves stale indices behind.
function assignAdjustEntryNames() {
    var namePrefixes = { damage: 'damaged', extra: 'extra', cover_damage: 'cover_damaged' };
    Array.from(rowsContainer.children).forEach(function (rowEl, rowIndex) {
        Object.keys(namePrefixes).forEach(function (type) {
            var namePrefix = namePrefixes[type];
            rowEl.querySelectorAll('.ata-adjust-rows[data-adjust-type="' + type + '"] .ata-adjust-entry-row').forEach(function (entryEl) {
                entryEl.querySelector('.adjust-reason-select').setAttribute('name', namePrefix + '_reason_id[' + rowIndex + '][]');
                entryEl.querySelector('.adjust-qty-input').setAttribute('name', namePrefix + '_qty[' + rowIndex + '][]');
            });
        });
    });
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
        ? ' Remaining ' + remaining + ' pc will move to the loose-pieces bucket.'
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

// Shows the row's current cover balance next to "Damaged Covers" so the
// operator can see at a glance whether enough covers exist before they
// try to convert — purely informational, consume_covers() in
// include/ProductCovers.php is still the real (server-side) gate.
function updateCoverBalanceChip(rowEl, mapped, coverBalance) {
    var chip = rowEl.querySelector('.cover-balance-chip');
    if (!mapped || coverBalance === undefined) {
        chip.textContent = '';
        chip.classList.remove('is-low');
        return;
    }
    chip.textContent = '(' + Number(coverBalance).toLocaleString('en-IN') + ' cover(s) available)';
    chip.classList.toggle('is-low', coverBalance <= 0);
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
            updateBundlePicker(rowEl, data.mapped, data.open_bundles, data.bucket_pieces);
            updateCoverBalanceChip(rowEl, data.mapped, data.cover_balance);
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

function addProductRow(opts) {
    opts = (opts && opts.afterEl) ? opts : {};
    var fragment = rowTemplate.content.cloneNode(true);
    var rowEl = fragment.querySelector('.product-row');
    var productSelect = rowEl.querySelector('.product-select');
    var bundleSelect = rowEl.querySelector('.bundle-select');
    rowEl.querySelector('.ata-remove-btn').addEventListener('click', function () {
        $(productSelect).select2('destroy');
        if ($(bundleSelect).data('select2')) $(bundleSelect).select2('destroy');
        rowEl.querySelectorAll('.adjust-reason-select').forEach(function (s) {
            if ($(s).data('select2')) { $(s).select2('destroy'); }
        });
        rowEl.remove();
        updateRemoveButtonsState();
        syncSiblingBundleOptions();
        refreshRowChrome();
    });
    rowEl.querySelector('.bundle-close-btn').addEventListener('click', function () {
        closeSelectedBundle(rowEl);
    });
    rowEl.querySelector('.add-bundle-btn').addEventListener('click', function () {
        addProductRow({
            afterEl: rowEl,
            productId: productSelect.value,
            machineCodeId: rowEl.querySelector('.machine-code-select').value
        });
    });
    rowEl.querySelectorAll('.ata-adjust-add-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            addAdjustEntryRow(rowEl, btn.getAttribute('data-adjust-type'));
        });
    });
    if (opts.afterEl) {
        rowEl.classList.add('is-extra-line');
        opts.afterEl.parentNode.insertBefore(fragment, opts.afterEl.nextSibling);
    } else {
        rowsContainer.appendChild(fragment);
    }

    // Select2 must init after the element is actually in the DOM, not
    // while it's still inside the detached template fragment.
    $(productSelect).select2({ placeholder: 'Search a product…', width: '100%' });
    $(productSelect).on('change', function () {
        refreshRowStock(rowEl);
        // Changing a group's head product re-points its "another bundle" lines.
        if (!rowEl.classList.contains('is-extra-line')) {
            var g = buildGroups().filter(function (x) { return x.head === rowEl; })[0];
            if (g) g.lines.slice(1).forEach(function (line) {
                var sel = line.querySelector('.product-select');
                if (sel.value !== productSelect.value) { $(sel).val(productSelect.value).trigger('change'); }
            });
        }
        refreshRowChrome();
    });
    rowEl.querySelector('.machine-code-select').addEventListener('change', function () {
        if (rowEl.classList.contains('is-extra-line')) return;
        var g = buildGroups().filter(function (x) { return x.head === rowEl; })[0];
        if (g) g.lines.slice(1).forEach(function (line) {
            line.querySelector('.machine-code-select').value = rowEl.querySelector('.machine-code-select').value;
        });
    });
    rowEl.querySelector('.direction-select').addEventListener('change', function () { updateRowDirectionAccent(rowEl); });
    updateRowDirectionAccent(rowEl);
    updateRemoveButtonsState();
    refreshRowChrome();

    // "Another bundle" clone: same product (and machine code) preselected;
    // the product change handler above then loads its bundles.
    if (opts.productId) {
        if (opts.machineCodeId) { rowEl.querySelector('.machine-code-select').value = opts.machineCodeId; }
        $(productSelect).val(opts.productId).trigger('change');
    }
}

// ── Grouped bundle lines, adjustment badges and the summary bar ──────
function productRows() { return Array.from(rowsContainer.querySelectorAll('.product-row')); }

function productLabelOf(rowEl) {
    var opt = rowEl.querySelector('.product-select');
    var o = opt.options[opt.selectedIndex];
    return o && opt.value ? o.text.replace(/\s*\(\d+\/pack\)\s*$/, '').trim() : 'Select a product';
}

// A "group" is a head row plus the "Another bundle" lines that follow it.
function buildGroups() {
    var groups = [], head = null;
    productRows().forEach(function (r) {
        if (!r.classList.contains('is-extra-line') || !head) {
            r.classList.remove('is-extra-line');
            head = { head: r, lines: [r] };
            groups.push(head);
        } else {
            head.lines.push(r);
        }
    });
    return groups;
}

function refreshGroupLabels() {
    buildGroups().forEach(function (g) {
        g.lines.forEach(function (r, i) {
            var b = r.querySelector('.add-bundle-btn');
            if (b) b.classList.toggle('is-hidden', i !== g.lines.length - 1);
        });
        var n = g.lines.length, total = 0;
        g.lines.forEach(function (r) { total += parseInt(r.querySelector('.pack-count-input').value, 10) || 0; });
        g.lines.forEach(function (r, i) {
            var head = r.querySelector('.ata-line-head');
            var tag = r.querySelector('.ata-line-tag');
            var tot = r.querySelector('.ata-line-total');
            var packLabel = r.querySelector('.pack-count-label');
            if (n === 1) {
                head.hidden = true;
                packLabel.textContent = 'Total Packs Produced';
                return;
            }
            head.hidden = false;
            tag.textContent = productLabelOf(g.head) + ' · bundle ' + (i + 1) + ' of ' + n;
            tot.textContent = i === 0 ? ('Total ' + total.toLocaleString('en-IN') + ' pack(s)') : '';
            packLabel.textContent = 'Packs from this bundle';
        });
    });
}

function updateConvertSummary() {
    var el = document.getElementById('convertSummary');
    if (!el) return;
    var groups = buildGroups();
    var packs = 0;
    productRows().forEach(function (r) { packs += parseInt(r.querySelector('.pack-count-input').value, 10) || 0; });
    el.textContent = groups.length
        ? (groups.length + ' product' + (groups.length === 1 ? '' : 's') + ' · ' + packs.toLocaleString('en-IN') + ' pack(s)')
        : 'No products yet';
}

function updateAdjustBadge(rowEl) {
    var badge = rowEl.querySelector('.ata-adjust-badge');
    if (!badge) return;
    var n = rowEl.querySelectorAll('.ata-adjust-entry-row').length;
    badge.textContent = n;
    badge.hidden = n === 0;
}

function refreshRowChrome() {
    refreshGroupLabels();
    updateConvertSummary();
}

// Live "remaining after" + sibling bundle locking for any edit inside a
// product row (pack count, damaged/extra qty, bundle choice).
rowsContainer.addEventListener('input', function (e) {
    var rowEl = e.target.closest('.product-row');
    if (rowEl) { updateRemainingAfter(rowEl); refreshRowChrome(); }
});
rowsContainer.addEventListener('change', function (e) {
    var rowEl = e.target.closest('.product-row');
    if (rowEl) { updateRemainingAfter(rowEl); syncSiblingBundleOptions(); }
});

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

// Manage Reasons modal: list/add/edit/delete against
// bundle-reason-manage.php, shared by both the Damaged Pieces and Extra
// Pieces Found reason dropdowns — reasonModalType tracks which list
// (damage|extra) the currently-open modal is editing, set by
// openReasonManageModal(). Same list/add/edit/delete flow as Manage
// Machine Codes above, just parameterized by type instead of a fixed
// endpoint/table.
var reasonModalBackdrop = document.getElementById('reasonModalBackdrop');
var reasonModalTitle = document.getElementById('reasonModalTitle');
var reasonModalIcon = document.getElementById('reasonModalIcon');
var reasonList = document.getElementById('reasonList');
var reasonFormLabel = document.getElementById('reasonFormLabel');
var reasonFormId = document.getElementById('reasonFormId');
var reasonFormSubmit = document.getElementById('reasonFormSubmit');
var reasonFormCancelEdit = document.getElementById('reasonFormCancelEdit');
var reasonFormError = document.getElementById('reasonFormError');
var reasonModalType = 'damage';

function reasonPostAction(action, extraFields) {
    var formData = new URLSearchParams();
    formData.set('action', action);
    formData.set('type', reasonModalType);
    formData.set('csrf_token', csrfToken);
    Object.keys(extraFields || {}).forEach(function (k) { formData.set(k, extraFields[k]); });
    return fetch('bundle-reason-manage.php', { method: 'POST', body: formData }).then(function (r) { return r.json(); });
}

function resetReasonForm() {
    reasonFormId.value = '';
    reasonFormLabel.value = '';
    reasonFormSubmit.textContent = 'Add';
    reasonFormCancelEdit.style.display = 'none';
    reasonFormError.style.display = 'none';
}

function renderReasonRow(r) {
    var row = document.createElement('div');
    row.style.cssText = 'display:flex; align-items:center; gap:8px; padding:8px 10px; border:1px solid #eef0f3; border-radius:8px;';
    var label = document.createElement('div');
    label.style.cssText = 'flex:1 1 auto; font-size:13.5px; color:#344054;';
    label.textContent = r.label;
    var editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.title = 'Edit';
    editBtn.style.cssText = 'border:none; background:none; cursor:pointer; color:#6b7280;';
    editBtn.innerHTML = '<i class="material-icons-outlined" style="font-size:18px;">edit</i>';
    editBtn.addEventListener('click', function () {
        reasonFormId.value = r.id;
        reasonFormLabel.value = r.label;
        reasonFormSubmit.textContent = 'Save';
        reasonFormCancelEdit.style.display = '';
        reasonFormError.style.display = 'none';
    });
    var delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.title = 'Delete';
    delBtn.style.cssText = 'border:none; background:none; cursor:pointer; color:#e11d48;';
    delBtn.innerHTML = '<i class="material-icons-outlined" style="font-size:18px;">delete</i>';
    delBtn.addEventListener('click', function () {
        if (!confirm('Delete reason "' + r.label + '"? Entries already using it keep their history.')) return;
        reasonPostAction('delete', { id: r.id }).then(function (data) {
            if (data.success) { loadReasonList(); refreshReasonCacheAndDropdowns(); }
            else alert(data.error || 'Could not delete.');
        });
    });
    row.appendChild(label);
    row.appendChild(editBtn);
    row.appendChild(delBtn);
    return row;
}

function loadReasonList() {
    reasonPostAction('list', {}).then(function (data) {
        reasonList.innerHTML = '';
        (data.reasons || []).forEach(function (r) {
            if (!r.is_active) return;
            reasonList.appendChild(renderReasonRow(r));
        });
        if (!reasonList.children.length) {
            reasonList.innerHTML = '<div style="color:#9ca3af; font-size:12.5px;">No reasons yet — add one above.</div>';
        }
    });
}

// Re-fetches the active list for reasonModalType, updates reasonCache,
// and rebuilds every rendered row's dropdown of that type (see
// refreshReasonDropdowns()) so a reason just added/renamed/removed
// shows up immediately in every product row without a page reload.
function refreshReasonCacheAndDropdowns() {
    reasonPostAction('list', {}).then(function (data) {
        var active = (data.reasons || []).filter(function (r) { return r.is_active; });
        reasonCache[reasonModalType] = active;
        refreshReasonDropdowns(reasonModalType);
        // 'damage' and 'cover_damage' share the same underlying
        // damage_reason_master list (see reasonCache.cover_damage's
        // setup above) — a reason added/edited/removed via either
        // entry's "+ Manage Reasons…" must refresh both, since the
        // modal itself always talks to the backend as plain 'damage'.
        if (reasonModalType === 'damage') {
            reasonCache.cover_damage = active;
            refreshReasonDropdowns('cover_damage');
        }
    });
}

function openReasonManageModal(type) {
    // cover_damage has no backend reason type of its own — it reuses
    // damage_reason_master (see reasonCache.cover_damage's setup above),
    // so the modal always talks to the server as plain 'damage'.
    var backendType = type === 'cover_damage' ? 'damage' : type;
    reasonModalType = backendType;
    reasonModalTitle.textContent = backendType === 'damage' ? 'Manage Damage Reasons' : 'Manage Extra-Found Reasons';
    reasonModalIcon.textContent = backendType === 'damage' ? 'report' : 'add_circle_outline';
    resetReasonForm();
    loadReasonList();
    reasonModalBackdrop.style.display = 'flex';
}

document.getElementById('reasonModalClose').addEventListener('click', function () { reasonModalBackdrop.style.display = 'none'; });
reasonModalBackdrop.addEventListener('click', function (e) { if (e.target === reasonModalBackdrop) reasonModalBackdrop.style.display = 'none'; });
reasonFormCancelEdit.addEventListener('click', resetReasonForm);

reasonFormSubmit.addEventListener('click', function () {
    var label = reasonFormLabel.value.trim();
    if (!label) { reasonFormError.textContent = 'Reason is required.'; reasonFormError.style.display = ''; return; }
    var isEdit = !!reasonFormId.value;
    reasonPostAction(isEdit ? 'edit' : 'add', { id: reasonFormId.value, label: label }).then(function (data) {
        if (data.success) {
            resetReasonForm();
            loadReasonList();
            refreshReasonCacheAndDropdowns();
        } else {
            reasonFormError.textContent = data.error || 'Could not save.';
            reasonFormError.style.display = '';
        }
    });
});

document.getElementById('addProductRowBtn').addEventListener('click', function () { addProductRow(); });
['godownSelect', 'warehouseSelect'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', refreshAllRowsStock);
});

// Start with one row so the form is usable immediately.
addProductRow();

// Blocks submission if any mapped-product row is missing its required
// bundle selection, or any damaged/extra entry row is missing a reason
// or a positive quantity — native `required` on a field inside a
// `display:none` wrapper, or one whose `name` is assigned only at
// submit time, isn't reliably enforced by browsers, so this is
// re-checked explicitly. Entry names are stamped from live DOM order
// right before this validation runs (see assignAdjustEntryNames()).
document.querySelectorAll('#convertForm .ata-btn-submit').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.ata-adjust-details').forEach(function (d) {
            if (d.querySelector('.ata-adjust-entry-row')) d.open = true;
        });
    });
});

document.getElementById('convertForm').addEventListener('submit', function (e) {
    assignAdjustEntryNames();

    var blocked = false;
    rowsContainer.querySelectorAll('.product-row').forEach(function (rowEl) {
        var wrap = rowEl.querySelector('.bundle-picker-wrap');
        if (wrap.style.display === 'none') return; // not a mapped row
        var select = rowEl.querySelector('.bundle-select');
        if (!select.value) blocked = true;

        rowEl.querySelectorAll('.ata-adjust-entry-row').forEach(function (entryEl) {
            var reasonVal = entryEl.querySelector('.adjust-reason-select').value;
            var qtyVal = parseInt(entryEl.querySelector('.adjust-qty-input').value, 10);
            if (!reasonVal || reasonVal === '__manage__' || !(qtyVal > 0)) blocked = true;
        });
    });
    if (blocked) {
        e.preventDefault();
        alert('Please select a raw material bundle for every mapped product row, and a reason + quantity for every damaged/extra entry, before converting.');
    }
});
</script>
</body>
</html>
