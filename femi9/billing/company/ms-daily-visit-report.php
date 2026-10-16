<?php
// Company-side consolidated view of Marketing Staff daily shop-visit
// activity — District x Taluk, Total/Existing/New shops visited, and
// Femi9 (Napkin) / Lumi9 (Diaper) order value, built from ms_orders +
// ms_shop (see shared/MsDailyVisitReport.php for the full logic/caveats:
// no Firka data on ms_shop, so Taluk is the finest grouping level here).
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once __DIR__ . '/../shared/MsDailyVisitReport.php';
require_once __DIR__ . '/../marketing/include/MsDistrictScope.php';
error_reporting(0);

$today = date('Y-m-d');
$from  = $_GET['from'] ?? date('Y-m-01');
$to    = $_GET['to']   ?? date('Y-m-t');
$msFilter = (int) ($_GET['ms_id'] ?? 0);
$districtFilter = (int) ($_GET['district_node_id'] ?? 0);

$msList = $db_conn->query("SELECT id, ms_name FROM marketing_staff WHERE deleted_at IS NULL ORDER BY ms_name ASC")->fetch_all(MYSQLI_ASSOC);
$districtList = $db_conn->query("SELECT id, name FROM partner_location_nodes WHERE depth = 3 AND parent_id = (SELECT id FROM partner_location_nodes WHERE depth = 2 AND name = 'Tamilnadu' LIMIT 1) ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$districtNameToId = [];
foreach ($districtList as $d) { $districtNameToId[$d['name']] = (int) $d['id']; }

// Each MS's own assigned district(s), resolved to district_node_id, so the
// District dropdown can auto-fill the moment an MS is picked — only when
// that MS has exactly one assigned district (if they cover several, the
// report stays unfiltered across all of them rather than guessing wrong).
$msAutoDistrict = [];
foreach ($msList as $m) {
    $names = getMsAssignedDistrictNames($db_conn, (int) $m['id']);
    $ids = array_values(array_unique(array_filter(array_map(fn($n) => $districtNameToId[$n] ?? 0, $names))));
    if (count($ids) === 1) { $msAutoDistrict[(int) $m['id']] = $ids[0]; }
}

$allRows = getMsDailyVisitRows($db_conn, $from, $to, ['ms_id' => $msFilter, 'district_node_id' => $districtFilter]);

// "No Order" visits (MS visited a shop but took no order) always show in
// their own modal, below — they used to clutter the main table with
// ₹0.00/"Not Captured" rows for every such visit. The main table shows
// only actual orders by default; checking "Include No-Order Visits"
// merges them back in, matching the earlier inline behaviour.
$orderRows = array_values(array_filter($allRows, fn($r) => $r['visit_type'] === 'order'));
$noOrderRows = array_values(array_filter($allRows, fn($r) => $r['visit_type'] === 'no_order'));
$includeNoOrder = !empty($_GET['include_no_order']);
$rows = $includeNoOrder ? $allRows : $orderRows;

$grandTotal = ['visits' => 0, 'new_visits' => 0, 'existing_visits' => 0, 'napkin_value' => 0.0, 'diaper_value' => 0.0, 'total_value' => 0.0];
foreach ($rows as $r) {
    $grandTotal['visits']++;
    if ($r['is_new']) { $grandTotal['new_visits']++; } else { $grandTotal['existing_visits']++; }
    $grandTotal['napkin_value'] += $r['napkin_value'];
    $grandTotal['diaper_value'] += $r['diaper_value'];
    $grandTotal['total_value']  += $r['total_value'];
}

// Pagination — 10 rows per page. Grand Total above is computed from the
// full $rows set before slicing, so it always reflects the whole filtered
// range, not just the visible page.
$perPage = 10;
$totalRows = count($rows);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
$pageRows = array_slice($rows, ($page - 1) * $perPage, $perPage);

function mdvPageUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MS Daily Visit Report : <?php echo fv($business_name ?? 'Femi9'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <style>
        .mdv-card { border:none; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        .mdv-table-wrap { border-radius:12px; border:1px solid #e5e7eb; overflow-x:auto; -webkit-overflow-scrolling:touch; }
        .mdv-table { margin-bottom:0; font-size:13px; }
        .mdv-table thead th {
            background:linear-gradient(180deg,#4f6ef7,#3d5ae0); color:#fff; font-weight:600;
            white-space:nowrap; border:none; padding:12px 14px; text-align:center;
        }
        .mdv-table tbody td { padding:10px 14px; vertical-align:middle; border-color:#eef0f4; }
        .mdv-table tbody tr:nth-child(even) { background:#f8f9fc; }
        .mdv-table tbody tr:hover { background:#eef2ff; }
        .mdv-num { text-align:center; }
        .mdv-pill { display:inline-block; min-width:30px; padding:3px 10px; border-radius:20px; font-weight:600; font-size:12.5px; }
        .mdv-pill-total { background:#e5e7eb; color:#374151; }
        .mdv-pill-existing { background:#dbeafe; color:#1d4ed8; }
        .mdv-pill-new { background:#dcfce7; color:#15803d; }
        .mdv-pill-vacant { background:#fee2e2; color:#b91c1c; }
        .mdv-pill-uncaptured { background:#fef3c7; color:#92400e; }
        .mdv-money { text-align:right; font-weight:600; color:#1f2937; white-space:nowrap; }
        .mdv-grand-row td { background:#fff7e0 !important; font-weight:700; border-top:2px solid #f0c141; }
        .select2-container .select2-selection--single { height:40px; border-radius:8px; border-color:#ced4da; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:38px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height:38px; }
        /* Every control in the filter bar (date inputs, Select2 boxes,
           buttons) pinned to the exact same height/baseline so the whole
           row lines up, instead of each control's own default height. */
        .mdv-filter-row .form-control,
        .mdv-filter-row .select2-container,
        .mdv-filter-row .btn { height:40px; }
        .mdv-filter-row .btn { display:inline-flex; align-items:center; justify-content:center; line-height:1; }
        @media (max-width: 575.98px) {
            .mdv-table { font-size:11.5px; }
            .mdv-table thead th, .mdv-table tbody td { padding:8px; }
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

                    <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin-bottom:16px;">MS Daily Visit Report</h1>

                    <div class="card mdv-card mb-3">
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end mdv-filter-row">
                                <div class="col-md-2">
                                    <label class="form-label">From</label>
                                    <input type="date" name="from" class="form-control" value="<?php echo fv($from); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">To</label>
                                    <input type="date" name="to" class="form-control" value="<?php echo fv($to); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Marketing Staff</label>
                                    <select name="ms_id" id="ms_filter" class="form-control">
                                        <option value="0">— All —</option>
                                        <?php foreach ($msList as $m): ?>
                                        <option value="<?php echo (int) $m['id']; ?>" data-district="<?php echo (int) ($msAutoDistrict[(int) $m['id']] ?? 0); ?>" <?php echo $msFilter === (int) $m['id'] ? 'selected' : ''; ?>><?php echo fv($m['ms_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">District</label>
                                    <select name="district_node_id" id="district_filter" class="form-control">
                                        <option value="0">— All —</option>
                                        <?php foreach ($districtList as $d): ?>
                                        <option value="<?php echo (int) $d['id']; ?>" <?php echo $districtFilter === (int) $d['id'] ? 'selected' : ''; ?>><?php echo fv($d['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="ms-daily-visit-report-export.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&ms_id=<?php echo $msFilter; ?>&district_node_id=<?php echo $districtFilter; ?>&include_no_order=<?php echo $includeNoOrder ? 1 : 0; ?>" class="btn btn-success">Excel</a>
                                </div>
                                <div class="col-12 d-flex align-items-center justify-content-end flex-wrap gap-3 mt-3 pt-3" style="border-top:1px solid #eef0f4;">
                                    <div class="form-check mb-0">
                                        <input type="checkbox" class="form-check-input" id="include_no_order" name="include_no_order" value="1" <?php echo $includeNoOrder ? 'checked' : ''; ?> onchange="this.form.submit()">
                                        <label class="form-check-label" for="include_no_order">Include No-Order Visits in table</label>
                                    </div>
                                    <?php if (!empty($noOrderRows)): ?>
                                    <button type="button" class="btn btn-warning btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#noOrderModal">
                                        View No-Order Visits (<?php echo count($noOrderRows); ?>)
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card mdv-card">
                        <div class="card-body">
                            <div class="mdv-table-wrap">
                            <table class="table mdv-table mb-0">
                                <thead>
                                    <tr>
                                        <th>S.No</th><th>Date</th><th>Shop Name</th><th>District</th><th>Taluk</th>
                                        <th>Firka</th><th>TP Name</th><th>Visit Type</th>
                                        <th>Femi9 (Napkin)<br>Value</th><th>Lumi9 (Diaper)<br>Value</th><th>Total Order<br>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                    <tr><td colspan="11" class="text-center text-muted py-4">No visits in this range.</td></tr>
                                    <?php else: $i = ($page - 1) * $perPage + 1; foreach ($pageRows as $r): ?>
                                    <tr>
                                        <td class="mdv-num"><?php echo $i++; ?></td>
                                        <td><?php echo fv(date('d-M-Y', strtotime($r['date']))); ?></td>
                                        <td><?php echo fv($r['shop_name']); ?></td>
                                        <td><?php echo fv($r['district']); ?></td>
                                        <td><?php echo fv($r['taluk']); ?></td>
                                        <td><?php echo fv($r['firka']); ?></td>
                                        <td><?php if ($r['tp_name'] === 'Vacant'): ?><span class="mdv-pill mdv-pill-vacant">Vacant</span><?php elseif ($r['tp_name'] === 'Not Captured'): ?><span class="mdv-pill mdv-pill-uncaptured">Not Captured</span><?php else: ?><?php echo fv($r['tp_name']); ?><?php endif; ?></td>
                                        <td class="mdv-num"><span class="mdv-pill <?php echo $r['is_new'] ? 'mdv-pill-new' : 'mdv-pill-existing'; ?>"><?php echo $r['is_new'] ? 'New' : 'Existing'; ?></span></td>
                                        <td class="mdv-money">₹<?php echo number_format($r['napkin_value'], 2); ?></td>
                                        <td class="mdv-money">₹<?php echo number_format($r['diaper_value'], 2); ?></td>
                                        <td class="mdv-money">₹<?php echo number_format($r['total_value'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="mdv-grand-row">
                                        <td colspan="7" class="text-end">Grand Total (all pages) — <?php echo (int) $grandTotal['visits']; ?> visits (<?php echo (int) $grandTotal['new_visits']; ?> new, <?php echo (int) $grandTotal['existing_visits']; ?> existing)</td>
                                        <td></td>
                                        <td class="mdv-money">₹<?php echo number_format($grandTotal['napkin_value'], 2); ?></td>
                                        <td class="mdv-money">₹<?php echo number_format($grandTotal['diaper_value'], 2); ?></td>
                                        <td class="mdv-money">₹<?php echo number_format($grandTotal['total_value'], 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            </div>
                            <?php if ($totalPages > 1): ?>
                            <nav class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                                <span class="text-muted" style="font-size:13px;">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalRows; ?> rows)</span>
                                <ul class="pagination mb-0 flex-wrap">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo fv(mdvPageUrl(1)); ?>">First</a>
                                    </li>
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo fv(mdvPageUrl($page - 1)); ?>">Previous</a>
                                    </li>
                                    <?php foreach (mdvPageWindow($page, $totalPages, 5) as $p): ?>
                                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo fv(mdvPageUrl($p)); ?>"><?php echo $p; ?></a>
                                    </li>
                                    <?php endforeach; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo fv(mdvPageUrl($page + 1)); ?>">Next</a>
                                    </li>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo fv(mdvPageUrl($totalPages)); ?>">Last</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($noOrderRows)): ?>
                    <div class="modal fade" id="noOrderModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">No-Order Visits (<?php echo count($noOrderRows); ?>)</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr><th>Date</th><th>Shop Name</th><th>District</th><th>Taluk</th><th>TP Name</th><th>Visit Type</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($noOrderRows as $r): ?>
                                            <tr>
                                                <td><?php echo fv(date('d-M-Y', strtotime($r['date']))); ?></td>
                                                <td><?php echo fv($r['shop_name']); ?></td>
                                                <td><?php echo fv($r['district']); ?></td>
                                                <td><?php echo fv($r['taluk']); ?></td>
                                                <td><?php if ($r['tp_name'] === 'Vacant'): ?><span class="mdv-pill mdv-pill-vacant">Vacant</span><?php elseif ($r['tp_name'] === 'Not Captured'): ?><span class="mdv-pill mdv-pill-uncaptured">Not Captured</span><?php else: ?><?php echo fv($r['tp_name']); ?><?php endif; ?></td>
                                                <td><span class="mdv-pill <?php echo $r['is_new'] ? 'mdv-pill-new' : 'mdv-pill-existing'; ?>"><?php echo $r['is_new'] ? 'New' : 'Existing'; ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
$(function () {
    $('#ms_filter').select2({ placeholder: 'All Marketing Staff', allowClear: true, width: '100%' });
    $('#district_filter').select2({ placeholder: 'All Districts', allowClear: true, width: '100%' });

    // Auto-fill District the moment an MS with exactly one assigned
    // district is picked — each <option> carries that district's id in
    // data-district (0 when the MS has none/several, left alone then).
    $('#ms_filter').on('change', function () {
        var districtId = $(this).find(':selected').data('district');
        if (districtId) {
            $('#district_filter').val(String(districtId)).trigger('change');
        }
    });
});
</script>
</body>
</html>
