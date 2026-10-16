<?php
// MS's own daily shop-visit summary — same District x Taluk breakdown as
// the company-side consolidated report, scoped to just this MS's own
// ms_orders activity. No manual entry: every add_order.php visit already
// writes this data automatically.
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/MsDailyVisitReport.php';
error_reporting(0);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');

$allRows = getMsDailyVisitRows($db_conn, $from, $to, ['ms_id' => (int) $markeingSTFID]);

// "No Order" visits always show in their own modal, below — the main
// table shows only actual orders by default; checking "Include No-Order
// Visits" merges them back in.
$orderRows = array_values(array_filter($allRows, fn($r) => $r['visit_type'] === 'order'));
$noOrderRows = array_values(array_filter($allRows, fn($r) => $r['visit_type'] === 'no_order'));
$includeNoOrder = !empty($_GET['include_no_order']);
$rows = $includeNoOrder ? $allRows : $orderRows;

function fv($val) { return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8'); }

$grandTotal = ['visits' => 0, 'new_visits' => 0, 'existing_visits' => 0, 'napkin_value' => 0.0, 'diaper_value' => 0.0, 'total_value' => 0.0];
foreach ($rows as $r) {
    $grandTotal['visits']++;
    if ($r['is_new']) { $grandTotal['new_visits']++; } else { $grandTotal['existing_visits']++; }
    $grandTotal['napkin_value'] += $r['napkin_value'];
    $grandTotal['diaper_value'] += $r['diaper_value'];
    $grandTotal['total_value']  += $r['total_value'];
}

// Pagination — 10 rows per page. Grand Total above is from the full $rows
// set before slicing, so it always covers the whole filtered range.
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Daily Visit Report : <?php echo fv($business_name ?? 'Femi9'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
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
        /* Every control in the filter bar pinned to the exact same
           height/baseline so the row lines up cleanly. */
        .mdv-filter-row .form-control,
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

                    <h1 style="font-size:20px;font-weight:600;color:#1f2937;margin-bottom:16px;">My Daily Visit Report</h1>

                    <div class="card mdv-card mb-3">
                        <div class="card-body">
                            <form method="get" class="row g-3 align-items-end mdv-filter-row">
                                <div class="col-md-3">
                                    <label class="form-label">From</label>
                                    <input type="date" name="from" class="form-control" value="<?php echo fv($from); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">To</label>
                                    <input type="date" name="to" class="form-control" value="<?php echo fv($to); ?>">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">Filter</button>
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
</body>
</html>
