<?php
// "DM Orders" — Get Orders a DM placed where no TP covers the shop's area
// and the DM explicitly chose this Channel Partner (marketing/add_order.php's
// "Assign To CP" dropdown, 2026-11-12). The CP can review the product lines
// and adjust qty here (with a required reason) before Company finally
// invoices it (company/ms-order-invoice-add.php, which reads straight off
// whatever's current in ms_orders — including any edit saved here).
// 2026-11-13, user request.
include("checksession.php");
include("config.php");
require_once(__DIR__ . '/../shared/MsOrderEdits.php');
error_reporting(0);

$cpId = (int)$Login_user_IDvl;
msOrderEditsEnsureSchema($db_conn);

// assigned_cp_id self-migrating guard, same as marketing/order_action_get.php.
$chkCpCol = $db_conn->query("SHOW COLUMNS FROM ms_orders LIKE 'assigned_cp_id'");
if ($chkCpCol && $chkCpCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE ms_orders ADD COLUMN assigned_cp_id INT UNSIGNED NULL DEFAULT NULL AFTER tp_id");
}

// Date range + status filters -- status (Completed/Pending) can't be pushed
// into this SQL since "Completed" means "has a user_invoice row", which is
// only known after the invoiced-lookup query further down, so it's applied
// in PHP once $invoicedIds is built. Date range filters the SQL itself since
// order_date is a real column here.
function validateDate_dmo(?string $date): ?string {
    if (empty($date)) return null;
    $ts = strtotime($date);
    return ($ts === false) ? null : date('Y-m-d', $ts);
}
$filterFrom = validateDate_dmo($_GET['frdate'] ?? null);
$filterTo   = validateDate_dmo($_GET['todate'] ?? null);
if ($filterFrom !== null && $filterTo !== null && $filterFrom > $filterTo) {
    [$filterFrom, $filterTo] = [$filterTo, $filterFrom];
}
$filterStatus = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, ['all', 'completed', 'pending'], true)) $filterStatus = 'all';

$dateWhere = '';
$dateParams = [];
$dateTypes = '';
if ($filterFrom !== null) { $dateWhere .= ' AND order_date >= ?'; $dateParams[] = $filterFrom; $dateTypes .= 's'; }
if ($filterTo !== null)   { $dateWhere .= ' AND order_date <= ?'; $dateParams[] = $filterTo;   $dateTypes .= 's'; }

$stmt = $db_conn->prepare(
    "SELECT order_id, shop_id, ms_id, order_date, MIN(id) AS first_id
     FROM ms_orders
     WHERE assigned_cp_id = ? AND new_order = 'yes' AND tp_id IS NULL{$dateWhere}
     GROUP BY order_id, shop_id, ms_id, order_date
     ORDER BY order_date DESC, first_id DESC"
);
$stmt->bind_param('i' . $dateTypes, $cpId, ...$dateParams);
$stmt->execute();
$orderHeads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$shopIds = array_unique(array_column($orderHeads, 'shop_id'));
$shopMeta = [];
if (!empty($shopIds)) {
    $idList = implode(',', array_map('intval', $shopIds));
    $r = $db_conn->query("SELECT id, name, mobile_number FROM ms_shop WHERE id IN ($idList)");
    while ($s = $r->fetch_assoc()) { $shopMeta[$s['id']] = $s; }
}

$msIds = array_unique(array_column($orderHeads, 'ms_id'));
$msMeta = [];
if (!empty($msIds)) {
    $idList = implode(',', array_map('intval', $msIds));
    $r = $db_conn->query("SELECT id, ms_name FROM marketing_staff WHERE id IN ($idList)");
    while ($m = $r->fetch_assoc()) { $msMeta[$m['id']] = $m; }
}

// Which of these orders are already invoiced -- shown as a status badge,
// not actionable here any more (edits are blocked post-invoice).
$orderIds = array_column($orderHeads, 'order_id');
$invoicedIds = [];
if (!empty($orderIds)) {
    $idList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string($v), $orderIds)) . "'";
    $r = $db_conn->query("SELECT source_ms_order_id FROM user_invoice WHERE source_ms_order_id IN ($idList)");
    while ($row = $r->fetch_assoc()) { $invoicedIds[$row['source_ms_order_id']] = true; }
}

// Which orders have an edit history -- small indicator in the list.
$editedIds = [];
if (!empty($orderIds)) {
    $idList = "'" . implode("','", array_map(fn($v) => $db_conn->real_escape_string($v), $orderIds)) . "'";
    $r = $db_conn->query("SELECT DISTINCT order_id FROM ms_order_edits WHERE order_id IN ($idList)");
    while ($row = $r->fetch_assoc()) { $editedIds[$row['order_id']] = true; }
}

// Status filter applied here, not in SQL -- "Completed" = has a user_invoice
// row (the $invoicedIds lookup above), "Pending" = doesn't, and that lookup
// itself depends on $orderHeads already being fetched.
if ($filterStatus !== 'all') {
    $orderHeads = array_values(array_filter($orderHeads, function ($oh) use ($filterStatus, $invoicedIds) {
        $isInvoiced = isset($invoicedIds[$oh['order_id']]);
        return $filterStatus === 'completed' ? $isInvoiced : !$isInvoiced;
    }));
}

// Pagination — applied last, after the date/status filters above, same
// windowed-page-number convention (max 5 numbered buttons + an ellipsis)
// used elsewhere in this app (e.g. district-tp-stock-report.php).
$dmoPerPage = 10;
$dmoTotalCount = count($orderHeads);
$dmoTotalPages = max(1, (int)ceil($dmoTotalCount / $dmoPerPage));
$dmoPage = max(1, min($dmoTotalPages, (int)($_GET['page'] ?? 1)));
$dmoPageRows = array_slice($orderHeads, ($dmoPage - 1) * $dmoPerPage, $dmoPerPage);

function dmoPageLink(int $page, ?string $filterFrom, ?string $filterTo, string $filterStatus): string {
    $qs = [];
    if ($filterFrom !== null) { $qs['frdate'] = $filterFrom; }
    if ($filterTo !== null) { $qs['todate'] = $filterTo; }
    if ($filterStatus !== 'all') { $qs['status'] = $filterStatus; }
    $qs['page'] = $page;
    return 'dm-orders.php?' . http_build_query($qs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DM Orders : <?php echo $business_name; ?></title>
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
        /* Theme colors matched to main.min.css: primary #2269f5, success #4bad48, warning #ff9500. */
        body { font-family: 'Poppins', sans-serif; }
        .card { border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:none; margin-bottom:20px; }
        .dmo-empty { color:#9ca3af; font-size:14px; padding:30px; text-align:center; }
        .dmo-row { padding:14px 20px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
        .dmo-row:last-child { border-bottom:none; }
        .dmo-shop { font-weight:700; font-size:14.5px; color:#1f2937; word-break:break-word; }
        .dmo-meta { font-size:12.5px; color:#6b7280; margin-top:2px; }
        .dmo-filter-bar { background:#fff; border-radius:10px; padding:16px 18px; box-shadow:0 2px 10px rgba(0,0,0,.07); margin-bottom:20px; }
        @media (max-width: 575px) {
            .dmo-row { justify-content:flex-start; }
            .dmo-row .btn-view { width:100%; text-align:center; }
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
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>DM Orders</h1>
                            </div>
                        </div>
                    </div>

                    <div class="dmo-filter-bar">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label mb-1">From Date</label>
                                <input type="date" name="frdate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterFrom ?? ''); ?>">
                            </div>
                            <div class="col-auto">
                                <label class="form-label mb-1">To Date</label>
                                <input type="date" name="todate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filterTo ?? ''); ?>">
                            </div>
                            <div class="col-auto">
                                <label class="form-label mb-1">Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="material-icons" style="vertical-align:middle;font-size:16px">search</i> Filter
                                </button>
                                <a href="dm-orders.php" class="btn btn-secondary btn-sm ms-1">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-body" style="padding:0;">
                            <?php if (empty($orderHeads)): ?>
                            <div class="dmo-empty">No DM orders match this filter.</div>
                            <?php else: foreach ($dmoPageRows as $oh):
                                $shop = $shopMeta[$oh['shop_id']] ?? [];
                                $ms = $msMeta[$oh['ms_id']] ?? [];
                                $isInvoiced = isset($invoicedIds[$oh['order_id']]);
                                $isEdited = isset($editedIds[$oh['order_id']]);
                            ?>
                            <div class="dmo-row">
                                <div>
                                    <div class="dmo-shop">
                                        <?php echo htmlspecialchars($shop['name'] ?? '-'); ?>
                                        <?php if ($isInvoiced): ?><span class="badge badge-style-bordered badge-success" style="margin-left:8px;vertical-align:middle;">Invoiced</span><?php endif; ?>
                                        <?php if ($isEdited): ?><span class="badge badge-style-bordered badge-warning" style="margin-left:8px;vertical-align:middle;">Edited</span><?php endif; ?>
                                    </div>
                                    <div class="dmo-meta">
                                        <?php echo htmlspecialchars($shop['mobile_number'] ?? ''); ?> &middot;
                                        <?php echo date('d M Y', strtotime($oh['order_date'])); ?> &middot;
                                        DM: <?php echo htmlspecialchars($ms['ms_name'] ?? '-'); ?>
                                    </div>
                                </div>
                                <?php if ($isInvoiced): ?>
                                <a href="dm-order-invoice-view.php?order_id=<?php echo urlencode($oh['order_id']); ?>" class="btn btn-sm btn-primary btn-view" target="_blank">View Invoice</a>
                                <?php else: ?>
                                <a href="dm-order-view.php?order_id=<?php echo urlencode($oh['order_id']); ?>" class="btn btn-sm btn-primary btn-view">View / Edit</a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <?php if ($dmoTotalPages > 1): ?>
                    <nav aria-label="DM Orders pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item<?php echo $dmoPage <= 1 ? ' disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars(dmoPageLink(max(1, $dmoPage - 1), $filterFrom, $filterTo, $filterStatus)); ?>">Previous</a>
                            </li>
                            <?php
                            $dmoWindow = 5;
                            $dmoStart = max(1, $dmoPage - (int)floor($dmoWindow / 2));
                            $dmoEnd = min($dmoTotalPages, $dmoStart + $dmoWindow - 1);
                            $dmoStart = max(1, min($dmoStart, $dmoEnd - $dmoWindow + 1));
                            if ($dmoStart > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars(dmoPageLink(1, $filterFrom, $filterTo, $filterStatus)); ?>">1</a></li>
                            <?php if ($dmoStart > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($p = $dmoStart; $p <= $dmoEnd; $p++): ?>
                            <li class="page-item<?php echo $p === $dmoPage ? ' active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars(dmoPageLink($p, $filterFrom, $filterTo, $filterStatus)); ?>"><?php echo $p; ?></a>
                            </li>
                            <?php endfor; ?>
                            <?php if ($dmoEnd < $dmoTotalPages): ?>
                            <?php if ($dmoEnd < $dmoTotalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars(dmoPageLink($dmoTotalPages, $filterFrom, $filterTo, $filterStatus)); ?>"><?php echo $dmoTotalPages; ?></a></li>
                            <?php endif; ?>
                            <li class="page-item<?php echo $dmoPage >= $dmoTotalPages ? ' disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars(dmoPageLink(min($dmoTotalPages, $dmoPage + 1), $filterFrom, $filterTo, $filterStatus)); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
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
