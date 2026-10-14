<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

// Read-only, product-consolidated view of raw-material-bundles-manage.php's
// data — one row per raw product instead of one card per bundle, so the
// overall picture (how many bundles, how many still open, how much has
// carried forward / gone excess) is visible at a glance without scrolling
// through every individual bundle. Deliberately a separate page: the
// per-bundle Manage Bundles page is left untouched.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin', 'stockviewer'], true)) {
    header("Location: dashboard.php");
    exit;
}

$allBundles = get_raw_material_bundles($db_conn);

// Godown/Warehouse dropdowns are built from the full unfiltered set, so
// picking one never makes the other's options disappear from the list.
$godownOptions = [];
$warehouseOptions = [];
foreach ($allBundles as $b) {
    $godownOptions[$b['company_godown_id']] = $b['gname'];
    if ($b['warehouse_code']) {
        $warehouseOptions[$b['warehouse_code']] = $b['warehouse_code'];
    }
}
asort($godownOptions, SORT_STRING | SORT_FLAG_CASE);
asort($warehouseOptions, SORT_STRING | SORT_FLAG_CASE);

$fromDate = $_GET['from'] ?? '';
$toDate   = $_GET['to'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) { $fromDate = ''; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate))   { $toDate = ''; }
$godownFilterId = filter_var($_GET['godown_id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
$warehouseFilter = trim((string) ($_GET['warehouse'] ?? ''));
$searchTerm = trim((string) ($_GET['search'] ?? ''));

$bundles = array_values(array_filter($allBundles, function ($b) use ($fromDate, $toDate, $godownFilterId, $warehouseFilter, $searchTerm) {
    if ($godownFilterId > 0 && $b['company_godown_id'] !== $godownFilterId) return false;
    if ($warehouseFilter !== '' && $b['warehouse_code'] !== $warehouseFilter) return false;
    $createdDate = substr($b['created_at'], 0, 10);
    if ($fromDate !== '' && $createdDate < $fromDate) return false;
    if ($toDate !== '' && $createdDate > $toDate) return false;
    if ($searchTerm !== '') {
        $haystack = mb_strtolower($b['label'] . ' ' . $b['product_name'] . ' ' . $b['gname'] . ' ' . ($b['warehouse_code'] ?? ''));
        if (mb_strpos($haystack, mb_strtolower($searchTerm)) === false) return false;
    }
    return true;
}));

$byProduct = [];
foreach ($bundles as $b) {
    $pid = $b['raw_product_id'];
    if (!isset($byProduct[$pid])) {
        $byProduct[$pid] = [
            'product_name'     => $b['product_name'],
            'godowns'          => [],
            'total'            => 0,
            'open'             => 0,
            'closed'           => 0,
            'carried'          => 0,
            'excess'           => 0,
            'nominal_pieces'   => 0,
            'open_remaining'   => 0,
            'damaged_pieces'   => 0,
            'extra_pieces'     => 0,
        ];
    }
    $p = &$byProduct[$pid];
    $p['godowns'][$b['gname']] = true;
    $p['total']++;
    $p['nominal_pieces'] += $b['nominal_pieces'];
    $p['damaged_pieces'] += $b['damaged_pieces'];
    $p['extra_pieces']   += $b['added_extra_pieces'];
    if ($b['status'] === 'open') {
        $p['open']++;
        $p['open_remaining'] += max($b['remaining_pieces'], 0);
    } else {
        $p['closed']++;
        if ($b['carried_to_bundle_id'] !== null) $p['carried']++;
        if ($b['variance_label'] !== null && strpos($b['variance_label'], 'Excess') !== false) $p['excess']++;
    }
    unset($p);
}
uasort($byProduct, fn($a, $b) => strcasecmp($a['product_name'], $b['product_name']));

$totalProducts = count($byProduct);
$totalBundles  = count($bundles);
$totalOpen     = array_sum(array_column($byProduct, 'open'));
$totalCarried  = array_sum(array_column($byProduct, 'carried'));
$totalExcess   = array_sum(array_column($byProduct, 'excess'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Bundles by Product : <?php echo $business_name; ?></title>

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

        .ata-filters { display:flex; align-items:flex-end; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
        .ata-filter-field { display:flex; flex-direction:column; gap:5px; flex:1 1 160px; min-width:150px; max-width:220px; }
        .ata-filter-field label { font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.02em; }
        .ata-filter-field select, .ata-filter-field input {
            border:1px solid #dde1ea; border-radius:8px; height:38px; font-size:13.5px; width:100%;
            padding:0 12px; color:#344054; background:#fff;
        }
        .ata-filter-field select:focus, .ata-filter-field input:focus { border-color: var(--ata-tp-1); box-shadow: 0 0 0 3px rgba(102,126,234,.15); outline:none; }
        .ata-filter-apply-btn {
            border:none; background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff;
            font-size:13px; font-weight:600; padding:0 18px; height:38px; border-radius:8px; cursor:pointer;
        }
        .ata-filter-clear-btn {
            display:inline-flex; align-items:center; justify-content:center; gap:4px;
            border:1px solid #e5e7eb; background:#fff; color:#6b7280; font-size:13px; font-weight:500;
            padding:0 14px; height:38px; border-radius:8px; cursor:pointer; transition:background .15s;
            white-space:nowrap; text-decoration:none;
        }
        .ata-filter-clear-btn:hover { background:#f3f4f6; color:#374151; text-decoration:none; }

        .pbp-card { background:#fff; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; }
        .pbp-table { width:100%; border-collapse:separate; border-spacing:0; }
        .pbp-table thead th {
            padding:12px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
            color:#9ca3af; background:#fafbfc; border-bottom:1px solid #eef0f3; white-space:nowrap;
        }
        .pbp-table thead th.num, .pbp-table tbody td.num { text-align:right; }
        .pbp-table tbody td { padding:14px 16px; vertical-align:middle; border-bottom:1px solid #f1f5f9; font-size:13.5px; color:#344054; }
        .pbp-table tbody tr:last-child td { border-bottom:none; }
        .pbp-table tbody tr:hover { background:#fafbff; }
        .pbp-product { font-weight:600; color:#1f2937; }
        .pbp-godowns { font-size:11.5px; color:#9ca3af; margin-top:2px; }
        .pbp-pill { display:inline-block; padding:2px 9px; border-radius:10px; font-size:12px; font-weight:600; }
        .pbp-pill.open { background:#dbeafe; color:#1e40af; }
        .pbp-pill.zero { background:#f3f4f6; color:#9ca3af; }
        .pbp-pill.carried { background:#fee2e2; color:#991b1b; }
        .pbp-pill.excess { background:#fef3c7; color:#92400e; }
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
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">inventory_2</i>Manage Bundles by Product</h1>
                    </div>
                    <p class="ata-intro">Every raw product's bundles rolled up into one row — total, open, carried forward and excess counts, at a glance.</p>

                    <div class="ata-nav-tabs">
                        <a href="raw-material-bundles-by-product.php" class="ata-nav-tab active"><i class="material-icons-outlined">inventory_2</i> Manage Bundles by Product</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="raw-material-bundle-transfer.php" class="ata-nav-tab"><i class="material-icons-outlined">swap_horiz</i> Bundle Transfer</a>
                    </div>

                    <?php if ($totalBundles > 0): ?>
                    <div class="ata-summary">
                        <div class="ata-stat">
                            <div class="num"><?php echo $totalProducts; ?></div>
                            <div class="lbl">Products</div>
                        </div>
                        <div class="ata-stat">
                            <div class="num"><?php echo $totalBundles; ?></div>
                            <div class="lbl">Total Bundles</div>
                        </div>
                        <div class="ata-stat open">
                            <div class="num"><?php echo $totalOpen; ?></div>
                            <div class="lbl">Open</div>
                        </div>
                        <div class="ata-stat short">
                            <div class="num"><?php echo $totalCarried; ?></div>
                            <div class="lbl">Carried Forward</div>
                        </div>
                        <div class="ata-stat excess">
                            <div class="num"><?php echo $totalExcess; ?></div>
                            <div class="lbl">Closed Excess</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="get" class="ata-filters">
                        <div class="ata-filter-field search-field">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Bundle, product, godown…" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="ata-filter-field">
                            <label>From Date</label>
                            <input type="date" name="from" value="<?php echo htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="ata-filter-field">
                            <label>To Date</label>
                            <input type="date" name="to" value="<?php echo htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="ata-filter-field">
                            <label>Godown</label>
                            <select name="godown_id">
                                <option value="">All Godowns</option>
                                <?php foreach ($godownOptions as $gid => $gname): ?>
                                <option value="<?php echo (int) $gid; ?>" <?php echo $godownFilterId === $gid ? 'selected' : ''; ?>><?php echo htmlspecialchars($gname, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ata-filter-field">
                            <label>Warehouse</label>
                            <select name="warehouse">
                                <option value="">All Warehouses</option>
                                <?php foreach ($warehouseOptions as $wc): ?>
                                <option value="<?php echo htmlspecialchars($wc, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $warehouseFilter === $wc ? 'selected' : ''; ?>><?php echo htmlspecialchars($wc, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="ata-filter-apply-btn">Apply</button>
                        <?php if ($fromDate !== '' || $toDate !== '' || $godownFilterId > 0 || $warehouseFilter !== '' || $searchTerm !== ''): ?>
                        <a href="raw-material-bundles-by-product.php" class="ata-filter-clear-btn"><i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">close</i> Clear</a>
                        <?php endif; ?>
                    </form>

                    <?php if (empty($byProduct)): ?>
                        <div class="alert alert-info">No bundles match this filter.</div>
                    <?php else: ?>
                    <div class="pbp-card">
                        <table class="pbp-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="num">Total</th>
                                    <th class="num">Open</th>
                                    <th class="num">Closed</th>
                                    <th class="num">Carried Fwd</th>
                                    <th class="num">Closed Excess</th>
                                    <th class="num">Nominal Pieces</th>
                                    <th class="num">Open Remaining</th>
                                    <th class="num">Damaged</th>
                                    <th class="num">Extra Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($byProduct as $p): $godownList = implode(', ', array_keys($p['godowns'])); ?>
                                <tr>
                                    <td>
                                        <div class="pbp-product"><?php echo htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="pbp-godowns"><?php echo htmlspecialchars($godownList, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td class="num"><?php echo number_format($p['total']); ?></td>
                                    <td class="num"><span class="pbp-pill <?php echo $p['open'] > 0 ? 'open' : 'zero'; ?>"><?php echo number_format($p['open']); ?></span></td>
                                    <td class="num"><?php echo number_format($p['closed']); ?></td>
                                    <td class="num"><span class="pbp-pill <?php echo $p['carried'] > 0 ? 'carried' : 'zero'; ?>"><?php echo number_format($p['carried']); ?></span></td>
                                    <td class="num"><span class="pbp-pill <?php echo $p['excess'] > 0 ? 'excess' : 'zero'; ?>"><?php echo number_format($p['excess']); ?></span></td>
                                    <td class="num"><?php echo number_format($p['nominal_pieces']); ?></td>
                                    <td class="num"><?php echo number_format($p['open_remaining']); ?></td>
                                    <td class="num"><?php echo number_format($p['damaged_pieces']); ?></td>
                                    <td class="num"><?php echo number_format($p['extra_pieces']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

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
</body>
</html>
