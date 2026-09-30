<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/RawMaterialBundles.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching the other pages in this feature family.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

// Filters — all optional, read from GET so the page is bookmarkable/shareable
// and export_bundle_report.php can reuse the exact same query string.
$filterProductId = filter_var($_GET['product_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$filterGodownId  = filter_var($_GET['godown_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$filterStatus    = in_array($_GET['status'] ?? '', ['open', 'closed'], true) ? $_GET['status'] : null;
$filterType      = in_array($_GET['type'] ?? '', ['conversion', 'damage', 'extra'], true) ? $_GET['type'] : null;
$filterDateFrom  = trim((string) ($_GET['date_from'] ?? '')) ?: null;
$filterDateTo    = trim((string) ($_GET['date_to'] ?? '')) ?: null;
if ($filterDateFrom && !\DateTime::createFromFormat('Y-m-d', $filterDateFrom)) $filterDateFrom = null;
if ($filterDateTo && !\DateTime::createFromFormat('Y-m-d', $filterDateTo)) $filterDateTo = null;

$activeTab = ($_GET['tab'] ?? 'summary') === 'transactions' ? 'transactions' : 'summary';

$bundleRows = get_bundle_report_summary($db_conn, $filterProductId, $filterGodownId, $filterStatus, $filterDateFrom, $filterDateTo);
$txnRows    = get_bundle_report_transactions($db_conn, $filterProductId, $filterGodownId, $filterType, $filterDateFrom, $filterDateTo);

// Product/godown lists for the filter dropdowns — pulled from the
// unfiltered bundle set so options never disappear just because the
// current filter combination happens to exclude them.
$allBundlesForFilters = get_raw_material_bundles($db_conn);
$productOptions = [];
$godownOptions  = [];
foreach ($allBundlesForFilters as $b) {
    $productOptions[$b['raw_product_id']] = $b['product_name'];
    $godownOptions[$b['company_godown_id']] = $b['gname'];
}
asort($productOptions);
asort($godownOptions);

// KPI roll-up across the (filtered) bundle summary rows.
$kpiTotalBundles   = count($bundleRows);
$kpiOpenBundles    = 0;
$kpiNominalTotal   = 0;
$kpiRemainingTotal = 0;
$kpiDamagedTotal   = 0;
$kpiExtraTotal     = 0;
$kpiPacksTotal     = 0;
$kpiConversionsTotal = 0;
foreach ($bundleRows as $b) {
    if ($b['status'] === 'open') $kpiOpenBundles++;
    $kpiNominalTotal   += $b['nominal_pieces'];
    $kpiRemainingTotal += max(0, $b['remaining_pieces']);
    $kpiDamagedTotal   += $b['damaged_pieces'];
    $kpiExtraTotal     += $b['added_extra_pieces'];
    $kpiPacksTotal     += $b['packs_made_total'];
    $kpiConversionsTotal += $b['conversions_count'];
}
$kpiUtilizationPct = $kpiNominalTotal > 0 ? round((($kpiNominalTotal - $kpiRemainingTotal) / $kpiNominalTotal) * 100) : 0;
$kpiDamagePct = $kpiNominalTotal > 0 ? round(($kpiDamagedTotal / $kpiNominalTotal) * 100, 1) : 0;

// Filters only — deliberately excludes 'tab' so callers that append their
// own ?tab=... (the tab links, the Export CSV link) never end up with two
// conflicting tab params in the same URL (PHP would silently take the
// last one, which broke switching tabs when this included tab too).
$filterQueryString = http_build_query(array_filter([
    'product_id' => $filterProductId,
    'godown_id'  => $filterGodownId,
    'status'     => $filterStatus,
    'type'       => $filterType,
    'date_from'  => $filterDateFrom,
    'date_to'    => $filterDateTo,
]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bundle Report : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
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

        .ata-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:20px; }
        .ata-kpi { border:1px solid #eef0f3; border-radius:12px; padding:14px 16px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.04); }
        .ata-kpi .num { font-size:22px; font-weight:700; color:#1f2937; line-height:1.2; }
        .ata-kpi .lbl { font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.03em; margin-top:3px; }
        .ata-kpi.warn .num { color:#b91c1c; }
        .ata-kpi.good .num { color:#166534; }
        .ata-kpi.info .num { color:#1e40af; }

        .ata-filters { display:flex; align-items:flex-end; gap:10px; margin-bottom:18px; flex-wrap:wrap; background:#fafbfc; border:1px solid #eef0f3; border-radius:12px; padding:14px 16px; }
        .ata-filter-field { display:flex; flex-direction:column; gap:5px; flex:1 1 160px; min-width:140px; max-width:220px; }
        .ata-filter-field label { font-size:11px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.02em; }
        .ata-filter-field select, .ata-filter-field input {
            border:1px solid #dde1ea; border-radius:8px; height:36px; font-size:13px; width:100%;
            padding:0 10px; color:#344054; background:#fff;
        }
        .ata-filter-actions { display:flex; gap:8px; }
        .ata-btn { border:none; border-radius:8px; height:36px; padding:0 16px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
        .ata-btn-primary { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; }
        .ata-btn-outline { background:#fff; border:1px solid #dde1ea; color:#4b5563; }
        .ata-btn-export { background:#166534; color:#fff; }

        .ata-report-tabs { display:flex; gap:6px; margin-bottom:14px; border-bottom:1px solid #e5e7eb; }
        .ata-report-tab { padding:9px 16px; font-size:13.5px; font-weight:600; color:#6b7280; text-decoration:none; border-bottom:2px solid transparent; }
        .ata-report-tab.active { color:var(--ata-tp-1); border-bottom-color:var(--ata-tp-1); }

        .ata-table-wrap { overflow-x:auto; background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.05); }
        table.ata-report-table { width:100%; border-collapse:separate; border-spacing:0; }
        table.ata-report-table thead th { background:#f8fafc; color:#475569; font-weight:600; font-size:11.5px; text-transform:uppercase; letter-spacing:.4px; padding:10px 12px; border-bottom:2px solid #e5e7eb; white-space:nowrap; text-align:left; }
        table.ata-report-table tbody td { padding:9px 12px; vertical-align:middle; border-bottom:1px solid #f1f5f9; font-size:13px; color:#1e293b; white-space:nowrap; }
        table.ata-report-table tbody tr:last-child td { border-bottom:none; }
        table.ata-report-table tbody tr:hover { background:#fafbfc; }

        .badge-open { background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600; }
        .badge-closed { background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600; }
        .badge-short { background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600; }
        .badge-excess { background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600; }
        .badge-exact { background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600; }
        .badge-type-conversion { background:#e0f2fe;color:#075985;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600; }
        .badge-type-damage { background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600; }
        .badge-type-extra { background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600; }
        .qty-pos { color:#166534; font-weight:600; }
        .qty-neg { color:#991b1b; font-weight:600; }

        .ata-empty-state { text-align:center; padding:40px 20px; color:#9ca3af; font-size:13.5px; }
        .ata-empty-state i { font-size:32px; display:block; margin-bottom:8px; color:#d1d5db; }
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
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">assessment</i>Bundle Report</h1>
                    </div>
                    <p class="ata-intro">Consolidated view of raw material bundle stock and every conversion/damage/extra transaction against it.</p>

                    <div class="ata-nav-tabs">
                        <a href="input-stock-bundles.php" class="ata-nav-tab"><i class="material-icons-outlined">add_box</i> Input Stock</a>
                        <a href="raw-material-bundles-manage.php" class="ata-nav-tab"><i class="material-icons-outlined">list_alt</i> Manage Bundles</a>
                        <a href="neksomo-piece-pack-convert.php" class="ata-nav-tab"><i class="material-icons-outlined">sync_alt</i> Convert Pieces &harr; Packs</a>
                        <a href="manage-piece-pack-conversions.php" class="ata-nav-tab"><i class="material-icons-outlined">history</i> Manage Conversions</a>
                        <a href="raw-material-bundles-report.php" class="ata-nav-tab active"><i class="material-icons-outlined">assessment</i> Bundle Report</a>
                        <a href="manage-covers.php" class="ata-nav-tab"><i class="material-icons-outlined">layers</i> Covers</a>
                        <a href="manage-cartons.php" class="ata-nav-tab"><i class="material-icons-outlined">inbox</i> Cartons</a>
                    </div>

                    <div class="ata-kpi-grid">
                        <div class="ata-kpi">
                            <div class="num"><?php echo number_format($kpiTotalBundles); ?></div>
                            <div class="lbl">Bundles</div>
                        </div>
                        <div class="ata-kpi info">
                            <div class="num"><?php echo number_format($kpiOpenBundles); ?></div>
                            <div class="lbl">Open</div>
                        </div>
                        <div class="ata-kpi">
                            <div class="num"><?php echo number_format($kpiNominalTotal); ?></div>
                            <div class="lbl">Nominal Pieces</div>
                        </div>
                        <div class="ata-kpi">
                            <div class="num"><?php echo number_format($kpiRemainingTotal); ?></div>
                            <div class="lbl">Remaining Pieces</div>
                        </div>
                        <div class="ata-kpi good">
                            <div class="num"><?php echo $kpiUtilizationPct; ?>%</div>
                            <div class="lbl">Utilization</div>
                        </div>
                        <div class="ata-kpi warn">
                            <div class="num"><?php echo number_format($kpiDamagedTotal); ?></div>
                            <div class="lbl">Damaged (<?php echo $kpiDamagePct; ?>%)</div>
                        </div>
                        <div class="ata-kpi">
                            <div class="num"><?php echo number_format($kpiExtraTotal); ?></div>
                            <div class="lbl">Extra Found</div>
                        </div>
                        <div class="ata-kpi info">
                            <div class="num"><?php echo number_format($kpiPacksTotal); ?></div>
                            <div class="lbl">Packs Produced</div>
                        </div>
                        <div class="ata-kpi">
                            <div class="num"><?php echo number_format($kpiConversionsTotal); ?></div>
                            <div class="lbl">Conversions</div>
                        </div>
                    </div>

                    <form method="get" class="ata-filters" id="reportFilterForm">
                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="ata-filter-field">
                            <label>Product</label>
                            <select name="product_id">
                                <option value="">All Products</option>
                                <?php foreach ($productOptions as $pid => $pname): ?>
                                <option value="<?= (int) $pid ?>" <?= $filterProductId === $pid ? 'selected' : '' ?>><?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ata-filter-field">
                            <label>Godown</label>
                            <select name="godown_id">
                                <option value="">All Godowns</option>
                                <?php foreach ($godownOptions as $gid => $gname): ?>
                                <option value="<?= (int) $gid ?>" <?= $filterGodownId === $gid ? 'selected' : '' ?>><?= htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($activeTab === 'summary'): ?>
                        <div class="ata-filter-field">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All</option>
                                <option value="open" <?= $filterStatus === 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="closed" <?= $filterStatus === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <div class="ata-filter-field">
                            <label>Type</label>
                            <select name="type">
                                <option value="">All</option>
                                <option value="conversion" <?= $filterType === 'conversion' ? 'selected' : '' ?>>Conversion</option>
                                <option value="damage" <?= $filterType === 'damage' ? 'selected' : '' ?>>Damage</option>
                                <option value="extra" <?= $filterType === 'extra' ? 'selected' : '' ?>>Extra Found</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="ata-filter-field">
                            <label>From</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="ata-filter-field">
                            <label>To</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="ata-filter-actions">
                            <button type="submit" class="ata-btn ata-btn-primary"><i class="material-icons-outlined" style="font-size:16px;">filter_alt</i>Apply</button>
                            <a href="raw-material-bundles-report.php?tab=<?php echo $activeTab; ?>" class="ata-btn ata-btn-outline">Clear</a>
                            <a href="export-bundle-report.php?tab=<?php echo $activeTab; ?>&<?php echo $filterQueryString; ?>" class="ata-btn ata-btn-export"><i class="material-icons-outlined" style="font-size:16px;">download</i>Export CSV</a>
                        </div>
                    </form>

                    <div class="ata-report-tabs">
                        <a href="?tab=summary&<?php echo $filterQueryString; ?>" class="ata-report-tab <?php echo $activeTab === 'summary' ? 'active' : ''; ?>">By Bundle</a>
                        <a href="?tab=transactions&<?php echo $filterQueryString; ?>" class="ata-report-tab <?php echo $activeTab === 'transactions' ? 'active' : ''; ?>">By Transaction</a>
                    </div>

                    <?php if ($activeTab === 'summary'): ?>
                        <?php if (empty($bundleRows)): ?>
                        <div class="ata-empty-state"><i class="material-icons-outlined">search_off</i>No bundles match these filters.</div>
                        <?php else: ?>
                        <div class="ata-table-wrap">
                        <table class="ata-report-table">
                            <thead>
                                <tr>
                                    <th>Bundle</th>
                                    <th>Product</th>
                                    <th>Godown</th>
                                    <th style="text-align:right;">Nominal</th>
                                    <th style="text-align:right;">Remaining</th>
                                    <th style="text-align:right;">Damaged</th>
                                    <th style="text-align:right;">Extra</th>
                                    <th style="text-align:right;">Conversions</th>
                                    <th style="text-align:right;">Packs Made</th>
                                    <th>Status</th>
                                    <th>Variance</th>
                                    <th>Created</th>
                                    <th>Closed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bundleRows as $b):
                                    $isCarried = $b['carried_to_bundle_id'] !== null;
                                    $isExcess = $b['variance_label'] !== null && strpos($b['variance_label'], 'Excess') !== false;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($b['label'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($b['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($b['gname'], ENT_QUOTES, 'UTF-8'); ?><?php echo $b['warehouse_code'] ? ' · ' . htmlspecialchars($b['warehouse_code'], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                    <td style="text-align:right;"><?php echo number_format($b['nominal_pieces']); ?></td>
                                    <td style="text-align:right;"><?php echo number_format(max($b['remaining_pieces'], 0)); ?></td>
                                    <td style="text-align:right;"><?php echo $b['damaged_pieces'] > 0 ? '<span class="qty-neg">' . number_format($b['damaged_pieces']) . '</span>' : '—'; ?></td>
                                    <td style="text-align:right;"><?php echo $b['added_extra_pieces'] > 0 ? '<span class="qty-pos">' . number_format($b['added_extra_pieces']) . '</span>' : '—'; ?></td>
                                    <td style="text-align:right;"><?php echo number_format($b['conversions_count']); ?></td>
                                    <td style="text-align:right;"><?php echo number_format($b['packs_made_total']); ?></td>
                                    <td><span class="<?php echo $b['status'] === 'open' ? 'badge-open' : 'badge-closed'; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                                    <td>
                                        <?php if ($b['variance_label'] !== null): ?>
                                        <span class="<?php echo $isCarried ? 'badge-short' : ($isExcess ? 'badge-excess' : 'badge-exact'); ?>"><?php echo htmlspecialchars($b['variance_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td><?php echo date('d-M-Y', strtotime($b['created_at'])); ?><?php echo $b['created_by'] ? '<br><span style="color:#9ca3af;font-size:11px;">' . htmlspecialchars($b['created_by'], ENT_QUOTES, 'UTF-8') . '</span>' : ''; ?></td>
                                    <td><?php echo $b['closed_at'] ? date('d-M-Y', strtotime($b['closed_at'])) : '—'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (empty($txnRows)): ?>
                        <div class="ata-empty-state"><i class="material-icons-outlined">search_off</i>No transactions match these filters.</div>
                        <?php else: ?>
                        <div class="ata-table-wrap">
                        <table class="ata-report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Bundle</th>
                                    <th>Product</th>
                                    <th>Godown</th>
                                    <th style="text-align:right;">Qty</th>
                                    <th>Detail</th>
                                    <th>Ref</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($txnRows as $t): ?>
                                <tr>
                                    <td><?php echo date('d-M-Y H:i', strtotime($t['created_at'])); ?></td>
                                    <td><span class="badge-type-<?php echo $t['type']; ?>"><?php echo ucfirst($t['type']); ?></span></td>
                                    <td>Bundle #<?php echo (int) $t['bundle_id']; ?></td>
                                    <td><?php echo htmlspecialchars($t['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($t['gname'], ENT_QUOTES, 'UTF-8'); ?><?php echo $t['warehouse_code'] ? ' · ' . htmlspecialchars($t['warehouse_code'], ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                    <td style="text-align:right;" class="<?php echo $t['qty'] > 0 ? 'qty-pos' : 'qty-neg'; ?>"><?php echo ($t['qty'] > 0 ? '+' : '') . number_format($t['qty']); ?></td>
                                    <td><?php echo htmlspecialchars($t['detail'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="color:#9ca3af;font-size:11.5px;"><?php echo htmlspecialchars($t['ref_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($t['created_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <?php endif; ?>
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
</body>
</html>
