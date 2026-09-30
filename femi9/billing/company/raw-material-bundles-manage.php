<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching the Convert Pieces<->Packs page this feature feeds into.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'history') {
    header('Content-Type: application/json');
    $bundleId = (int) ($_GET['bundle_id'] ?? 0);
    echo json_encode($bundleId > 0 ? get_bundle_transaction_history($db_conn, $bundleId) : []);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['close', 'damage', 'add_extra', 'reopen', 'delete_adjustment'], true)) {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
        header("Location: raw-material-bundles-manage.php");
        exit;
    }
    $bundleId = (int) ($_POST['bundle_id'] ?? 0);
    $actingUser = $_SESSION['LOGIN_USER'] ?? 'system';

    try {
        if ($_POST['action'] === 'close') {
            // close_raw_material_bundle() locks the bundle row (and
            // possibly a merge-target row) with FOR UPDATE, then may
            // INSERT a new carry-forward bundle before marking this one
            // closed — wrapped in a transaction so a failure partway
            // through never leaves a bundle half-closed with its
            // leftover unaccounted for.
            $db_conn->begin_transaction();
            try {
                $closed = $bundleId > 0 && close_raw_material_bundle($db_conn, $bundleId, $actingUser);
                $db_conn->commit();
            } catch (\Throwable $e) {
                $db_conn->rollback();
                throw $e;
            }
            if ($closed) {
                $_SESSION['sucMessage'] = "Bundle closed.";
            } else {
                $_SESSION['errorMessage'] = "Could not close that bundle — it may already be closed.";
            }
        } elseif ($_POST['action'] === 'damage') {
            $qty = filter_var($_POST['qty'] ?? '', FILTER_VALIDATE_INT) ?: 0;
            $reasonId = filter_var($_POST['reason_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
            $note = trim((string) ($_POST['note'] ?? ''));
            $note = $note !== '' ? mb_substr($note, 0, 255) : null;
            if ($bundleId > 0 && $qty > 0 && $reasonId) {
                record_damaged_pieces($db_conn, $bundleId, $qty, $note, $actingUser, $reasonId);
                $_SESSION['sucMessage'] = "$qty damaged piece(s) recorded.";
            } else {
                $_SESSION['errorMessage'] = "Please select a reason and enter a valid damaged quantity.";
            }
        } elseif ($_POST['action'] === 'add_extra') {
            $qty = filter_var($_POST['qty'] ?? '', FILTER_VALIDATE_INT) ?: 0;
            $reasonId = filter_var($_POST['reason_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
            if ($bundleId > 0 && $qty > 0 && $reasonId) {
                add_extra_pieces_to_bundle($db_conn, $bundleId, $qty, $actingUser, $reasonId);
                $_SESSION['sucMessage'] = "$qty extra piece(s) added to the bundle.";
            } else {
                $_SESSION['errorMessage'] = "Please select a reason and enter a valid quantity.";
            }
        } elseif ($_POST['action'] === 'reopen') {
            // reopen_raw_material_bundle() locks the bundle (and its
            // carry-forward target, if any) with FOR UPDATE and reverses
            // the carry-forward itself — wrapped in a transaction so a
            // failure partway through never leaves the carry half-undone.
            $db_conn->begin_transaction();
            try {
                $reopened = $bundleId > 0 && reopen_raw_material_bundle($db_conn, $bundleId);
                $db_conn->commit();
            } catch (\Throwable $e) {
                $db_conn->rollback();
                throw $e;
            }
            if ($reopened) {
                $_SESSION['sucMessage'] = "Bundle reopened.";
            } else {
                $_SESSION['errorMessage'] = "Could not reopen that bundle.";
            }
        } elseif ($_POST['action'] === 'delete_adjustment') {
            $adjustmentId = (int) ($_POST['adjustment_id'] ?? 0);
            if ($adjustmentId > 0) {
                $result = delete_bundle_adjustment($db_conn, $adjustmentId);
                $_SESSION['sucMessage'] = ucfirst($result['type']) . " entry of {$result['qty']} piece(s) removed.";
            } else {
                $_SESSION['errorMessage'] = "Invalid adjustment entry.";
            }
        }
    } catch (StockException $e) {
        $_SESSION['errorMessage'] = $e->getMessage();
    }

    header("Location: raw-material-bundles-manage.php");
    exit;
}

$bundles = get_raw_material_bundles($db_conn);
$damageReasons = get_active_bundle_reasons($db_conn, 'damage');
$extraReasons  = get_active_bundle_reasons($db_conn, 'extra');

$totalBundles  = count($bundles);
$openCount     = 0;
$carriedCount  = 0;
$excessCount   = 0;
$productNames   = [];
$godownNames    = [];
$warehouseCodes = [];
foreach ($bundles as $b) {
    if ($b['status'] === 'open') $openCount++;
    if ($b['carried_to_bundle_id'] !== null) $carriedCount++;
    if ($b['variance_label'] !== null && strpos($b['variance_label'], 'Excess') !== false) $excessCount++;
    $productNames[$b['product_name']] = true;
    $godownNames[$b['gname']] = true;
    if ($b['warehouse_code']) {
        $warehouseCodes[$b['warehouse_code']] = true;
    }
}
$productNames   = array_keys($productNames);
$godownNames    = array_keys($godownNames);
$warehouseCodes = array_keys($warehouseCodes);
sort($productNames);
sort($godownNames);
sort($warehouseCodes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Raw Bundles : <?php echo $business_name; ?></title>

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

        .ata-nav-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .ata-nav-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:500; text-decoration:none; transition:filter .15s, background .15s; }
        .ata-nav-tab i { font-size:17px; }
        .ata-nav-tab.active { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .ata-nav-tab:not(.active) { background:#f3f4f6; color:#4b5563; }
        .ata-nav-tab:not(.active):hover { background:#e5e7eb; color:#1f2937; }

        .ata-summary { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
        .ata-stat { flex:1 1 140px; border:1px solid #eef0f3; border-radius:12px; padding:12px 16px; background:#fafbfc; }
        .ata-stat .num { font-size:20px; font-weight:700; color:#1f2937; line-height:1.2; }
        .ata-stat .lbl { font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.03em; margin-top:2px; }
        .ata-stat.open .num { color:#1e40af; }
        .ata-stat.short .num { color:#991b1b; }
        .ata-stat.excess .num { color:#92400e; }

        .badge-open { background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-closed { background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-short { background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-excess { background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }
        .badge-exact { background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600; }

        .ata-filters { display:flex; align-items:flex-end; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
        .ata-filter-btn { border:1px solid #e5e7eb; background:#fff; color:#4b5563; font-size:13px; font-weight:500; padding:6px 14px; border-radius:8px; cursor:pointer; transition:background .15s; height:38px; }
        .ata-filter-btn:hover { background:#f3f4f6; }
        .ata-filter-btn.active { background:#1f2937; color:#fff; border-color:#1f2937; }
        .ata-filter-status-group { display:flex; gap:8px; }

        .ata-filter-field { display:flex; flex-direction:column; gap:5px; flex:1 1 200px; min-width:160px; max-width:260px; }
        .ata-filter-field label { font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.02em; }
        .ata-filter-field select, .ata-filter-field input {
            border:1px solid #dde1ea; border-radius:8px; height:38px; font-size:13.5px; width:100%;
            padding:0 32px 0 12px; color:#344054; background:#fff;
        }
        .ata-filter-field input { padding:0 12px 0 34px; background-repeat:no-repeat; background-position:10px center; }
        .ata-filter-field.search-field { position:relative; }
        .ata-filter-field.search-field i { position:absolute; left:10px; bottom:10px; font-size:18px; color:#9ca3af; pointer-events:none; }
        .ata-filter-field.search-field input { padding-left:34px; }
        .ata-filter-field select {
            appearance:none; -webkit-appearance:none; -moz-appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 12px center;
        }
        .ata-filter-field select:focus, .ata-filter-field input:focus { border-color: var(--ata-tp-1); box-shadow: 0 0 0 3px rgba(102,126,234,.15); outline:none; }
        .ata-filter-clear-btn { border:1px solid #e5e7eb; background:#fff; color:#6b7280; font-size:13px; font-weight:500; padding:0 14px; height:38px; border-radius:8px; cursor:pointer; transition:background .15s; white-space:nowrap; }
        .ata-filter-clear-btn:hover { background:#f3f4f6; }
        .ata-empty-state { text-align:center; padding:40px 20px; color:#9ca3af; font-size:13.5px; display:none; }
        .ata-empty-state i { font-size:32px; display:block; margin-bottom:8px; color:#d1d5db; }

        .ata-bundle-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(270px, 1fr)); gap:14px; }
        .ata-bundle-card { border:1px solid #eef0f3; border-radius:14px; padding:16px 18px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.04); transition:box-shadow .15s; }
        .ata-bundle-card:hover { box-shadow:0 4px 14px rgba(0,0,0,.07); }
        .ata-bundle-card.is-short { border-color:#fecaca; }
        .ata-bundle-card.is-excess { border-color:#fde68a; }
        .ata-bc-top { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:8px; }
        .ata-bc-title { font-weight:600; font-size:14px; color:#1f2937; }
        .ata-bc-product { font-size:12.5px; color:#6b7280; margin-bottom:12px; }
        .ata-bc-meta { font-size:12px; color:#9ca3af; margin-bottom:10px; }
        .ata-bc-bar-wrap { background:#f1f5f9; border-radius:6px; height:8px; overflow:hidden; margin-bottom:6px; }
        .ata-bc-bar { height:100%; background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); border-radius:6px; }
        .ata-bc-bar.is-short { background:#f87171; }
        .ata-bc-bar.is-excess { background:#fbbf24; }
        .ata-bc-qty { font-size:12.5px; color:#4b5563; margin-bottom:12px; display:flex; justify-content:space-between; }
        .ata-bc-footer { display:flex; justify-content:space-between; align-items:center; margin-top:8px; }
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
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">list_alt</i>Manage Raw Bundles</h1>
                    </div>
                    <p class="ata-intro">Every raw material bundle, its remaining pieces, and variance once closed.</p>

                    <div class="ata-nav-tabs">
                        <a href="input-stock-bundles.php" class="ata-nav-tab"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab active"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="neksomo-piece-pack-convert.php" class="ata-nav-tab"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
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

                    <?php if ($totalBundles > 0): ?>
                    <div class="ata-summary">
                        <div class="ata-stat">
                            <div class="num"><?php echo $totalBundles; ?></div>
                            <div class="lbl">Total Bundles</div>
                        </div>
                        <div class="ata-stat open">
                            <div class="num"><?php echo $openCount; ?></div>
                            <div class="lbl">Open</div>
                        </div>
                        <div class="ata-stat short">
                            <div class="num"><?php echo $carriedCount; ?></div>
                            <div class="lbl">Carried Forward</div>
                        </div>
                        <div class="ata-stat excess">
                            <div class="num"><?php echo $excessCount; ?></div>
                            <div class="lbl">Closed Excess</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($bundles)): ?>
                        <div class="alert alert-info">No bundles recorded yet.</div>
                    <?php else: ?>
                    <div class="ata-filters">
                        <div class="ata-filter-field search-field">
                            <label>Search</label>
                            <i class="material-icons-outlined">search</i>
                            <input type="text" id="bundleSearchInput" placeholder="Bundle, product, godown…">
                        </div>
                        <div class="ata-filter-field">
                            <label>Product</label>
                            <select id="bundleProductFilter">
                                <option value="">All Products</option>
                                <?php foreach ($productNames as $pn): ?>
                                <option value="<?= htmlspecialchars($pn, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pn, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ata-filter-field">
                            <label>Godown</label>
                            <select id="bundleGodownFilter">
                                <option value="">All Godowns</option>
                                <?php foreach ($godownNames as $gn): ?>
                                <option value="<?= htmlspecialchars($gn, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($gn, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ata-filter-field">
                            <label>Warehouse</label>
                            <select id="bundleWarehouseFilter">
                                <option value="">All Warehouses</option>
                                <?php foreach ($warehouseCodes as $wc): ?>
                                <option value="<?= htmlspecialchars($wc, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($wc, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ata-filter-status-group">
                            <button type="button" class="ata-filter-btn active" data-filter="all">All (<?php echo $totalBundles; ?>)</button>
                            <button type="button" class="ata-filter-btn" data-filter="open">Open (<?php echo $openCount; ?>)</button>
                            <button type="button" class="ata-filter-btn" data-filter="closed">Closed (<?php echo $totalBundles - $openCount; ?>)</button>
                        </div>
                        <button type="button" class="ata-filter-clear-btn" id="bundleFilterClearBtn">
                            <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">close</i> Clear
                        </button>
                    </div>

                    <div class="ata-empty-state" id="bundleEmptyState">
                        <i class="material-icons-outlined">search_off</i>
                        No bundles match your filters.
                    </div>

                    <div class="ata-bundle-grid" id="bundleGrid">
                        <?php foreach ($bundles as $b):
                            $isCarried = $b['carried_to_bundle_id'] !== null;
                            $isExcess = $b['variance_label'] !== null && strpos($b['variance_label'], 'Excess') !== false;
                            $cardExtraClass = $isCarried ? ' is-short' : ($isExcess ? ' is-excess' : '');
                            $pct = $b['nominal_pieces'] > 0 ? max(0, min(100, round((($b['nominal_pieces'] - max($b['remaining_pieces'], 0)) / $b['nominal_pieces']) * 100))) : 0;
                            $barExtraClass = $cardExtraClass;
                            $searchBlob = mb_strtolower($b['label'] . ' ' . $b['product_name'] . ' ' . $b['gname'] . ' ' . ($b['warehouse_code'] ?? ''));
                        ?>
                        <div class="ata-bundle-card<?php echo $cardExtraClass; ?>"
                             data-status="<?php echo $b['status']; ?>"
                             data-product="<?php echo htmlspecialchars($b['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                             data-godown="<?php echo htmlspecialchars($b['gname'], ENT_QUOTES, 'UTF-8'); ?>"
                             data-warehouse="<?php echo htmlspecialchars($b['warehouse_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                             data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>"
                             data-bundle-id="<?php echo (int) $b['id']; ?>"
                             data-bundle-label="<?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>"
                             role="button" tabindex="0"
                             onclick="openBundleHistoryModal(event, <?php echo (int) $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>')"
                             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openBundleHistoryModal(event, <?php echo (int) $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>');}"
                             style="cursor:pointer;">
                            <div class="ata-bc-top">
                                <div>
                                    <div class="ata-bc-title"><?php echo htmlspecialchars($b['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="ata-bc-product"><?php echo htmlspecialchars($b['product_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <span class="<?php echo $b['status'] === 'open' ? 'badge-open' : 'badge-closed'; ?>"><?php echo ucfirst($b['status']); ?></span>
                            </div>

                            <div class="ata-bc-meta">
                                <?php echo htmlspecialchars($b['gname'], ENT_QUOTES, 'UTF-8'); ?><?php echo $b['warehouse_code'] ? ' · ' . htmlspecialchars($b['warehouse_code'], ENT_QUOTES, 'UTF-8') : ''; ?>
                            </div>

                            <div class="ata-bc-bar-wrap"><div class="ata-bc-bar<?php echo $barExtraClass; ?>" style="width:<?php echo $pct; ?>%;"></div></div>
                            <div class="ata-bc-qty">
                                <span><?php echo number_format(max($b['remaining_pieces'], 0)); ?> left</span>
                                <span>of <?php echo number_format($b['nominal_pieces']); ?> nominal</span>
                            </div>

                            <?php if ($b['damaged_pieces'] > 0 || $b['added_extra_pieces'] > 0): ?>
                            <div style="font-size:11.5px;color:#9ca3af;margin-bottom:8px;">
                                <?php if ($b['damaged_pieces'] > 0): ?><span style="color:#b91c1c;">&#9888; <?php echo number_format($b['damaged_pieces']); ?> damaged</span><?php endif; ?>
                                <?php if ($b['damaged_pieces'] > 0 && $b['added_extra_pieces'] > 0): ?> &middot; <?php endif; ?>
                                <?php if ($b['added_extra_pieces'] > 0): ?><span style="color:#b45309;">+<?php echo number_format($b['added_extra_pieces']); ?> added</span><?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($isCarried): ?>
                            <div style="font-size:11.5px;color:#6b7280;margin-bottom:8px;">
                                <i class="material-icons-outlined" style="font-size:13px;vertical-align:middle;">arrow_forward</i> Carried into Bundle #<?php echo (int) $b['carried_to_bundle_id']; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($b['carried_from_bundle_id'] !== null): ?>
                            <div style="font-size:11.5px;color:#6b7280;margin-bottom:8px;">
                                <i class="material-icons-outlined" style="font-size:13px;vertical-align:middle;">arrow_back</i> Carried from Bundle #<?php echo (int) $b['carried_from_bundle_id']; ?>
                            </div>
                            <?php endif; ?>

                            <div class="ata-bc-footer">
                                <?php if ($b['variance_label'] !== null): ?>
                                    <span class="<?php echo $isCarried ? 'badge-short' : ($isExcess ? 'badge-excess' : 'badge-exact'); ?>"><?php echo htmlspecialchars($b['variance_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small"><?php echo date('d M Y', strtotime($b['created_at'])); ?></span>
                                <?php endif; ?>

                                <?php if ($b['status'] === 'open'): ?>
                                <div class="btn-group" onclick="event.stopPropagation();">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Record damaged pieces" onclick="openBundleActionModal(<?php echo (int) $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>', 'damage')">
                                        <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">report_problem</i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Add extra pieces found in this bundle" onclick="openBundleActionModal(<?php echo (int) $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>', 'add_extra')">
                                        <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">add_circle_outline</i>
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Close <?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>? Remaining: <?php echo $b['remaining_pieces']; ?> piece(s)<?php echo $b['remaining_pieces'] > 0 ? ' — this will automatically carry forward into another open bundle (or a new one)' : ''; ?>. This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="close">
                                        <input type="hidden" name="bundle_id" value="<?php echo (int) $b['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Close</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <div style="display:flex;align-items:center;gap:8px;" onclick="event.stopPropagation();">
                                        <span class="text-muted small"><?php echo htmlspecialchars($b['closed_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Reopen <?php echo htmlspecialchars(addslashes($b['label']), ENT_QUOTES, 'UTF-8'); ?>?<?php echo $b['carried_to_bundle_id'] !== null ? ' This will also pull back the leftover pieces that were carried forward to Bundle #' . (int) $b['carried_to_bundle_id'] . '.' : ''; ?>');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="reopen">
                                            <input type="hidden" name="bundle_id" value="<?php echo (int) $b['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Reopen this bundle">
                                                <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">lock_open</i> Reopen
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shared modal for the two quick bundle adjustments — Record Damage /
     Add Extra Pieces both post to this same page (action="damage" or
     "add_extra"), only the copy/icon changes based on which one was
     clicked (see openBundleActionModal()). -->
<div class="modal fade" id="bundleActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="bundleActionForm">
                <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                    <h6 class="modal-title" id="bundleActionModalTitle" style="font-weight:600;color:#1f2937;"></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding:16px 22px;">
                    <p class="text-muted small mb-3" id="bundleActionModalDesc"></p>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" id="bundleActionField" value="">
                    <input type="hidden" name="bundle_id" id="bundleActionBundleId" value="">
                    <div class="mb-3">
                        <label class="form-label" id="bundleActionReasonLabel">Reason</label>
                        <select required name="reason_id" id="bundleActionReason" class="form-select"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" id="bundleActionQtyLabel">Quantity</label>
                        <input type="number" min="1" required name="qty" id="bundleActionQty" class="form-control" placeholder="e.g. 10">
                    </div>
                    <div class="mb-1" id="bundleActionNoteWrap">
                        <label class="form-label">Note (optional)</label>
                        <input type="text" name="note" maxlength="255" class="form-control" placeholder="e.g. torn during handling">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="bundleActionSubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Reasons modal: shared by both the Damaged Pieces and Extra
     Pieces Found reason dropdowns in bundleActionModal above — which
     list it edits is set at open time (see openReasonManageModal()),
     same pattern (and same bundle-reason-manage.php endpoint) as
     neksomo-piece-pack-convert.php's own reason modal. -->
<div id="reasonModalBackdrop" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:1060; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#fff; border-radius:14px; width:100%; max-width:440px; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-bottom:1px solid #e9ecef; border-radius:14px 14px 0 0;">
            <div style="font-weight:600; color:#1f2937; display:flex; align-items:center; gap:8px;"><i class="material-icons-outlined" id="reasonModalIcon">report</i> <span id="reasonModalTitle">Manage Damage Reasons</span></div>
            <button type="button" id="reasonModalClose" style="border:none; background:none; cursor:pointer; color:#6b7280;"><i class="material-icons">close</i></button>
        </div>
        <div style="padding:16px 20px; overflow-y:auto; flex:1 1 auto;">
            <div style="display:flex; gap:8px; margin-bottom:14px;">
                <input type="text" id="reasonFormLabel" placeholder="Reason (e.g. Torn during handling)" style="flex:1 1 auto; border:1px solid #dde1ea; border-radius:8px; height:40px; padding:0 12px; font-size:13.5px;">
                <input type="hidden" id="reasonFormId" value="">
                <button type="button" id="reasonFormSubmit" class="btn btn-primary btn-sm" style="height:40px;">Add</button>
                <button type="button" id="reasonFormCancelEdit" style="display:none; height:40px; padding:0 12px; border:1px solid #dde1ea; border-radius:8px; background:#fff; cursor:pointer;">Cancel</button>
            </div>
            <div id="reasonFormError" style="display:none; color:#b91c1c; font-size:12.5px; margin-bottom:10px;"></div>
            <div id="reasonList" style="display:flex; flex-direction:column; gap:6px;"></div>
        </div>
    </div>
</div>

<!-- Transaction history popup — shows every event recorded against one
     bundle (creation/carry-in, conversions drawn from it, damage/extra
     adjustments, close) as a single chronological timeline. Content is
     fetched on demand via ?action=history so the page itself stays light
     even with many bundles/transactions. -->
<div class="modal fade" id="bundleHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:560px;">
        <div class="modal-content" style="border-radius:14px; border:none; box-shadow:0 10px 40px rgba(0,0,0,.15);">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef; padding:18px 22px;">
                <h6 class="modal-title" id="bundleHistoryModalTitle" style="font-weight:600;color:#1f2937;font-size:15px;"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:20px 22px;">
                <div id="bundleHistoryLoading" style="text-align:center;padding:30px 0;color:#9ca3af;font-size:13px;">
                    <i class="material-icons-outlined" style="font-size:26px;display:block;margin-bottom:6px;animation:ata-spin 1s linear infinite;">autorenew</i>
                    Loading…
                </div>
                <div id="bundleHistoryError" class="alert alert-danger" style="display:none;font-size:13px;"></div>
                <div id="bundleHistoryEmpty" style="display:none;text-align:center;padding:30px 0;color:#9ca3af;font-size:13px;">
                    <i class="material-icons-outlined" style="font-size:26px;display:block;margin-bottom:6px;color:#d1d5db;">history_toggle_off</i>
                    No transactions recorded yet.
                </div>
                <ul id="bundleHistoryTimeline" class="ata-timeline" style="display:none;"></ul>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes ata-spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }

    .ata-timeline { list-style:none; margin:0; padding:0; position:relative; }
    .ata-timeline::before { content:''; position:absolute; left:15px; top:6px; bottom:6px; width:2px; background:#eef0f3; }
    .ata-tl-item { position:relative; padding:0 0 20px 42px; }
    .ata-tl-item:last-child { padding-bottom:0; }
    .ata-tl-dot { position:absolute; left:7px; top:2px; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#fff; border:2px solid #d1d5db; z-index:1; }
    .ata-tl-dot i { font-size:11px; }
    .ata-tl-item.tl-created .ata-tl-dot,
    .ata-tl-item.tl-carried_in .ata-tl-dot { border-color:var(--ata-tp-1); color:var(--ata-tp-1); }
    .ata-tl-item.tl-conversion .ata-tl-dot { border-color:#0ea5e9; color:#0ea5e9; }
    .ata-tl-item.tl-damage .ata-tl-dot { border-color:#ef4444; color:#ef4444; }
    .ata-tl-item.tl-extra .ata-tl-dot { border-color:#f59e0b; color:#f59e0b; }
    .ata-tl-item.tl-closed .ata-tl-dot { border-color:#6b7280; color:#6b7280; }
    .ata-tl-detail { font-size:13.5px; color:#1f2937; line-height:1.5; }
    .ata-tl-meta { font-size:11.5px; color:#9ca3af; margin-top:3px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .ata-tl-qty { font-weight:600; font-size:12px; padding:1px 8px; border-radius:10px; }
    .ata-tl-qty.pos { background:#dcfce7; color:#166534; }
    .ata-tl-qty.neg { background:#fee2e2; color:#991b1b; }
    .ata-tl-del-btn { border:none; background:transparent; color:#d1d5db; cursor:pointer; padding:0 0 0 6px; vertical-align:middle; line-height:1; }
    .ata-tl-del-btn:hover { color:#ef4444; }
    .ata-tl-del-btn i { font-size:16px; vertical-align:middle; }
</style>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
var csrfToken = document.querySelector('input[name="csrf_token"]').value;
var reasonCache = {
    damage: <?php echo json_encode($damageReasons, JSON_HEX_TAG | JSON_HEX_APOS); ?>,
    extra:  <?php echo json_encode($extraReasons, JSON_HEX_TAG | JSON_HEX_APOS); ?>
};
var currentActionModalType = 'damage';

var statusFilter = 'all';
var searchInput = document.getElementById('bundleSearchInput');
var productFilter = document.getElementById('bundleProductFilter');
var godownFilter = document.getElementById('bundleGodownFilter');
var warehouseFilter = document.getElementById('bundleWarehouseFilter');
var emptyState = document.getElementById('bundleEmptyState');
var bundleCards = document.querySelectorAll('.ata-bundle-card');

function applyFilters() {
    var searchTerm = searchInput.value.trim().toLowerCase();
    var product = productFilter.value;
    var godown = godownFilter.value;
    var warehouse = warehouseFilter.value;
    var visibleCount = 0;

    bundleCards.forEach(function (card) {
        var matchesStatus = statusFilter === 'all' || card.getAttribute('data-status') === statusFilter;
        var matchesProduct = !product || card.getAttribute('data-product') === product;
        var matchesGodown = !godown || card.getAttribute('data-godown') === godown;
        var matchesWarehouse = !warehouse || card.getAttribute('data-warehouse') === warehouse;
        var matchesSearch = !searchTerm || card.getAttribute('data-search').indexOf(searchTerm) !== -1;
        var show = matchesStatus && matchesProduct && matchesGodown && matchesWarehouse && matchesSearch;
        card.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
}

document.querySelectorAll('.ata-filter-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.ata-filter-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        statusFilter = btn.getAttribute('data-filter');
        applyFilters();
    });
});

searchInput.addEventListener('input', applyFilters);
productFilter.addEventListener('change', applyFilters);
godownFilter.addEventListener('change', applyFilters);
warehouseFilter.addEventListener('change', applyFilters);

document.getElementById('bundleFilterClearBtn').addEventListener('click', function () {
    searchInput.value = '';
    productFilter.value = '';
    godownFilter.value = '';
    warehouseFilter.value = '';
    statusFilter = 'all';
    document.querySelectorAll('.ata-filter-btn').forEach(function (b) { b.classList.remove('active'); });
    document.querySelector('.ata-filter-btn[data-filter="all"]').classList.add('active');
    applyFilters();
});

// Rebuilds the reason <select> from reasonCache[type], preserving the
// current selection when it's still valid — same convention as
// refreshReasonDropdowns() on neksomo-piece-pack-convert.php, just for
// this page's single dropdown instead of many cloned rows.
function populateActionReasonSelect(type) {
    var select = document.getElementById('bundleActionReason');
    var currentVal = select.value;
    var html = '<option value="" hidden>— Select reason —</option>';
    (reasonCache[type] || []).forEach(function (r) {
        html += '<option value="' + r.id + '">' + escBdMgr(r.label) + '</option>';
    });
    html += '<option value="__manage__">+ Manage Reasons…</option>';
    select.innerHTML = html;
    if (currentVal && currentVal !== '__manage__' && Array.from(select.options).some(function (o) { return o.value === currentVal; })) {
        select.value = currentVal;
    }
}

function escBdMgr(str) {
    var d = document.createElement('div');
    d.textContent = str == null ? '' : str;
    return d.innerHTML;
}

function openBundleActionModal(bundleId, bundleLabel, action) {
    document.getElementById('bundleActionField').value = action;
    document.getElementById('bundleActionBundleId').value = bundleId;
    document.getElementById('bundleActionQty').value = '';

    var titleEl = document.getElementById('bundleActionModalTitle');
    var descEl = document.getElementById('bundleActionModalDesc');
    var reasonLabelEl = document.getElementById('bundleActionReasonLabel');
    var qtyLabelEl = document.getElementById('bundleActionQtyLabel');
    var noteWrap = document.getElementById('bundleActionNoteWrap');
    var submitBtn = document.getElementById('bundleActionSubmitBtn');

    currentActionModalType = action === 'damage' ? 'damage' : 'extra';
    populateActionReasonSelect(currentActionModalType);

    if (action === 'damage') {
        titleEl.textContent = 'Record Damaged Pieces — ' + bundleLabel;
        descEl.textContent = 'These pieces are removed from the bundle\'s remaining count and can never become a pack.';
        reasonLabelEl.textContent = 'Damage Reason';
        qtyLabelEl.textContent = 'Damaged Quantity';
        noteWrap.style.display = '';
        submitBtn.textContent = 'Record Damage';
        submitBtn.className = 'btn btn-danger btn-sm';
    } else {
        titleEl.textContent = 'Add Extra Pieces — ' + bundleLabel;
        descEl.textContent = 'Use this only when conversion stopped at 0 remaining but you know this bundle physically still has material left.';
        reasonLabelEl.textContent = 'Extra-Found Reason';
        qtyLabelEl.textContent = 'Extra Quantity Found';
        noteWrap.style.display = 'none';
        submitBtn.textContent = 'Add Pieces';
        submitBtn.className = 'btn btn-primary btn-sm';
    }

    var modal = new bootstrap.Modal(document.getElementById('bundleActionModal'));
    modal.show();
}

document.getElementById('bundleActionReason').addEventListener('change', function () {
    if (this.value === '__manage__') {
        this.value = '';
        openReasonManageModal(currentActionModalType);
    }
});

// Manage Reasons modal: list/add/edit/delete against bundle-reason-
// manage.php, shared by both the Damaged Pieces and Extra Pieces Found
// reason dropdowns — same flow as neksomo-piece-pack-convert.php's own
// reason modal, ported here so this page's single-entry damage/extra
// form gets the same reason-dropdown UX.
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
            if (data.success) { loadReasonList(); refreshReasonCacheAndDropdown(); }
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
// and rebuilds the action modal's dropdown so a reason just added/
// renamed/removed shows up immediately without a page reload.
function refreshReasonCacheAndDropdown() {
    reasonPostAction('list', {}).then(function (data) {
        reasonCache[reasonModalType] = (data.reasons || []).filter(function (r) { return r.is_active; });
        if (currentActionModalType === reasonModalType) {
            populateActionReasonSelect(reasonModalType);
        }
    });
}

function openReasonManageModal(type) {
    reasonModalType = type;
    reasonModalTitle.textContent = type === 'damage' ? 'Manage Damage Reasons' : 'Manage Extra-Found Reasons';
    reasonModalIcon.textContent = type === 'damage' ? 'report' : 'add_circle_outline';
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
            refreshReasonCacheAndDropdown();
        } else {
            reasonFormError.textContent = data.error || 'Could not save.';
            reasonFormError.style.display = '';
        }
    });
});

var ATA_TL_ICONS = {
    created:     'add_box',
    carried_in:  'arrow_back',
    conversion:  'sync_alt',
    damage:      'report_problem',
    extra:       'add_circle_outline',
    closed:      'lock'
};

function ataFormatHistoryDate(iso) {
    var d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
           ' · ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

function openBundleHistoryModal(evt, bundleId, bundleLabel) {
    if (evt) evt.stopPropagation();

    document.getElementById('bundleHistoryModalTitle').textContent = 'Transaction History — ' + bundleLabel;

    var loadingEl = document.getElementById('bundleHistoryLoading');
    var errorEl = document.getElementById('bundleHistoryError');
    var emptyEl = document.getElementById('bundleHistoryEmpty');
    var timelineEl = document.getElementById('bundleHistoryTimeline');

    loadingEl.style.display = '';
    errorEl.style.display = 'none';
    emptyEl.style.display = 'none';
    timelineEl.style.display = 'none';
    timelineEl.innerHTML = '';

    var modal = new bootstrap.Modal(document.getElementById('bundleHistoryModal'));
    modal.show();

    fetch('raw-material-bundles-manage.php?action=history&bundle_id=' + encodeURIComponent(bundleId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (res) {
            if (!res.ok) throw new Error('Request failed (' + res.status + ')');
            return res.json();
        })
        .then(function (events) {
            loadingEl.style.display = 'none';
            if (!events || events.length === 0) {
                emptyEl.style.display = '';
                return;
            }
            events.forEach(function (ev) {
                var li = document.createElement('li');
                li.className = 'ata-tl-item tl-' + ev.type;

                var qtyHtml = '';
                if (ev.qty !== null && ev.qty !== undefined) {
                    var qtyCls = ev.qty > 0 ? 'pos' : (ev.qty < 0 ? 'neg' : '');
                    var qtySign = ev.qty > 0 ? '+' : '';
                    qtyHtml = '<span class="ata-tl-qty ' + qtyCls + '">' + qtySign + ev.qty + '</span>';
                }

                var metaParts = [ataFormatHistoryDate(ev.created_at)];
                if (ev.created_by) metaParts.push('by ' + ev.created_by);
                if (ev.ref_id) metaParts.push('ref ' + ev.ref_id);

                var deleteHtml = '';
                if ((ev.type === 'damage' || ev.type === 'extra') && ev.adjustment_id) {
                    deleteHtml = '<button type="button" class="ata-tl-del-btn" title="Remove this entry" ' +
                        'onclick="deleteBundleAdjustment(' + ev.adjustment_id + ', this)">' +
                        '<i class="material-icons-outlined">delete_outline</i></button>';
                }

                li.innerHTML =
                    '<span class="ata-tl-dot"><i class="material-icons-outlined">' + (ATA_TL_ICONS[ev.type] || 'circle') + '</i></span>' +
                    '<div class="ata-tl-detail">' + ev.detail.replace(/</g, '&lt;') + ' ' + qtyHtml + deleteHtml + '</div>' +
                    '<div class="ata-tl-meta">' + metaParts.map(function (p) { return p.replace(/</g, '&lt;'); }).join(' &middot; ') + '</div>';

                timelineEl.appendChild(li);
            });
            timelineEl.style.display = '';
        })
        .catch(function (err) {
            loadingEl.style.display = 'none';
            errorEl.style.display = '';
            errorEl.textContent = 'Could not load transaction history. ' + err.message;
        });
}

// Submits the delete via a real POST (rather than fetch) so the existing
// PHP redirect-with-flash-message handler runs unchanged and the page
// reloads showing the corrected bundle totals — the modal's own state is
// necessarily lost on reload, same tradeoff as every other action here.
function deleteBundleAdjustment(adjustmentId, btnEl) {
    if (!confirm('Remove this entry? The bundle\'s remaining/damaged/extra totals will be corrected accordingly.')) {
        return;
    }
    var form = document.createElement('form');
    form.method = 'post';
    form.action = 'raw-material-bundles-manage.php';
    form.innerHTML =
        '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
        '<input type="hidden" name="action" value="delete_adjustment">' +
        '<input type="hidden" name="adjustment_id" value="' + adjustmentId + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>
