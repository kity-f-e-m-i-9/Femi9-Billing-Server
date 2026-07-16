<?php
include("checksession.php");
include("config.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
error_reporting(0);

// ── Independent Territory-Partner B2B report ────────────────────────────
// Deliberately NOT sharing state/logic with Report-First-Page.php /
// Report-Details.php (which filter every other channel by a proper
// state_id/district_id foreign key). territory_partners has no such FK —
// branch_state/branch_pincode are free-typed text — so this page matches on
// that raw text instead of pretending it lines up with the `state` table.
// UI/table layout (seller/buyer columns + product-quantity matrix + totals
// row) is deliberately kept visually consistent with Report-Details.php.

if (isset($_GET['clear_filters']) || isset($_POST['clear_all'])) {
    unset($_SESSION['tpreport_branch_state']);
    unset($_SESSION['tpreport_branch_pincode']);
    unset($_SESSION['tpreport_from_date']);
    unset($_SESSION['tpreport_to_date']);
    unset($_SESSION['tpreport_buyer_type']);
    unset($_SESSION['tpreport_records_per_page']);
    header("Location: Report-TP-First-Page.php");
    exit;
}

// Selected state (required, carried in session so filters below can re-post)
$selected_branch_state = '';
if (isset($_POST['branch_state']) && $_POST['branch_state'] !== '') {
    $selected_branch_state = $_POST['branch_state'];
    $_SESSION['tpreport_branch_state'] = $selected_branch_state;
} elseif (isset($_SESSION['tpreport_branch_state'])) {
    $selected_branch_state = $_SESSION['tpreport_branch_state'];
}

if ($selected_branch_state === '') {
    header("Location: Report-TP-First-Page.php");
    exit;
}

// Optional pincode narrow-down
$selected_pincode = '';
if (isset($_POST['branch_pincode'])) {
    $selected_pincode = trim($_POST['branch_pincode']);
    $_SESSION['tpreport_branch_pincode'] = $selected_pincode;
} elseif (isset($_SESSION['tpreport_branch_pincode'])) {
    $selected_pincode = $_SESSION['tpreport_branch_pincode'];
}

// Buyer type (Shop / Customer)
$selected_buyer_type = '';
if (isset($_POST['buyer_type'])) {
    $selected_buyer_type = $_POST['buyer_type'];
    $_SESSION['tpreport_buyer_type'] = $selected_buyer_type;
} elseif (isset($_SESSION['tpreport_buyer_type'])) {
    $selected_buyer_type = $_SESSION['tpreport_buyer_type'];
}

// Date range (default last 7 days)
$to_date   = date('Y-m-d');
$from_date = date('Y-m-d', strtotime('-7 days'));
if (isset($_POST['frdate']) && $_POST['frdate'] !== '') { $from_date = $_POST['frdate']; $_SESSION['tpreport_from_date'] = $from_date; }
elseif (isset($_SESSION['tpreport_from_date'])) { $from_date = $_SESSION['tpreport_from_date']; }
if (isset($_POST['todate']) && $_POST['todate'] !== '') { $to_date = $_POST['todate']; $_SESSION['tpreport_to_date'] = $to_date; }
elseif (isset($_SESSION['tpreport_to_date'])) { $to_date = $_SESSION['tpreport_to_date']; }

// Show entries / search / page (session-carried like Report-Details.php)
$records_per_page = 20;
if (isset($_POST['records_per_page'])) { $records_per_page = (int)$_POST['records_per_page']; $_SESSION['tpreport_records_per_page'] = $records_per_page; }
elseif (isset($_SESSION['tpreport_records_per_page'])) { $records_per_page = (int)$_SESSION['tpreport_records_per_page']; }
if (!in_array($records_per_page, [20, 40, 60], true)) $records_per_page = 20;

$search  = isset($_GET['q']) ? trim($_GET['q']) : '';
$is_search = ($search !== '');
$page = max(1, (int)($_GET['page'] ?? $_POST['page'] ?? 1));

// ── Territory partners matching this branch_state (+ optional pincode) ──
$tp_ids = [];
$tp_names = [];
$tpWhere = "branch_state = ?";
$tpTypes = "s";
$tpParams = [$selected_branch_state];
if ($selected_pincode !== '') {
    $tpWhere .= " AND branch_pincode LIKE ?";
    $tpTypes .= "s";
    $tpParams[] = "%{$selected_pincode}%";
}
$tpStmt = $db_conn->prepare("SELECT id, tp_id, name, mobile, branch_pincode FROM territory_partners WHERE $tpWhere ORDER BY name ASC");
$tpStmt->bind_param($tpTypes, ...$tpParams);
$tpStmt->execute();
$tpRes = $tpStmt->get_result();
while ($tp = $tpRes->fetch_assoc()) {
    $tp_ids[] = (int)$tp['id'];
    $tp_names[(int)$tp['id']] = $tp;
}
$tpStmt->close();

$products = [];
$prodRes = mysqli_query($db_conn, "SELECT id, productName FROM products ORDER BY id ASC");
while ($p = mysqli_fetch_assoc($prodRes)) { $products[$p['id']] = $p['productName']; }

$rows = [];

if (!empty($tp_ids)) {
    $idList = implode(',', $tp_ids);

    // Shop bills (TP -> Shop)
    if ($selected_buyer_type === '' || $selected_buyer_type === 'shop') {
        $shopRes = mysqli_query($db_conn, "
            SELECT ui.inv_id, ui.inv_number, ui.date, ui.sub_total, ui.courier_charges, ui.total,
                   ui.from_user_id AS tp_id,
                   s.name AS buyer_name, s.mobile_number AS buyer_mobile, 'Shop' AS buyer_type
            FROM user_invoice ui
            JOIN shop s ON s.temp_id = ui.to_user_id
            WHERE ui.from_user_type='territory_partner' AND ui.from_user_id IN ($idList)
              AND ui.to_user_type='shop'
              AND ui.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' AND '" . $db_conn->real_escape_string($to_date) . "'
            ORDER BY ui.date DESC, ui.id DESC
        ");
        while ($r = mysqli_fetch_assoc($shopRes)) {
            $items = [];
            $itemRes = mysqli_query($db_conn, "SELECT pr_id, qty FROM user_invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $r['inv_id']) . "'");
            while ($it = mysqli_fetch_assoc($itemRes)) { $items[$it['pr_id']] = ($items[$it['pr_id']] ?? 0) + (int)$it['qty']; }
            $r['items'] = $items;
            $rows[] = $r;
        }
    }

    // Customer bills (TP -> Customer)
    if ($selected_buyer_type === '' || $selected_buyer_type === 'customer') {
        $custRes = mysqli_query($db_conn, "
            SELECT i.inv_id, i.inv_number, i.date, i.sub_total, i.courier_charges, i.total,
                   i.user_id AS tp_id,
                   COALESCE(c.name,'Walking Customer') AS buyer_name, COALESCE(c.mobile,'') AS buyer_mobile, 'Customer' AS buyer_type
            FROM invoice i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.user_type='territory_partner' AND i.user_id IN ($idList)
              AND i.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' AND '" . $db_conn->real_escape_string($to_date) . "'
            ORDER BY i.date DESC, i.id DESC
        ");
        while ($r = mysqli_fetch_assoc($custRes)) {
            $items = [];
            $itemRes = mysqli_query($db_conn, "SELECT pr_id, qty FROM invoice_items WHERE inv_id='" . mysqli_real_escape_string($db_conn, $r['inv_id']) . "'");
            while ($it = mysqli_fetch_assoc($itemRes)) { $items[$it['pr_id']] = ($items[$it['pr_id']] ?? 0) + (int)$it['qty']; }
            $r['items'] = $items;
            $rows[] = $r;
        }
    }

    usort($rows, fn($a, $b) => strcmp($b['date'] . $b['inv_id'], $a['date'] . $a['inv_id']));
}

// ── Search (server-side, in-memory — dataset per state is modest) ──
if ($is_search) {
    $needle = mb_strtolower($search);
    $rows = array_values(array_filter($rows, function ($r) use ($needle, $tp_names, $products) {
        $tp = $tp_names[$r['tp_id']] ?? null;
        $hay = mb_strtolower(implode(' ', [
            $r['inv_number'], $r['buyer_name'], $r['buyer_mobile'],
            $tp['name'] ?? '', $tp['tp_id'] ?? '',
            implode(' ', array_map(fn($pid) => $products[$pid] ?? '', array_keys($r['items']))),
        ]));
        return mb_strpos($hay, $needle) !== false;
    }));
}

$total_records = count($rows);
$total_pages = max(1, (int)ceil($total_records / max(1, $records_per_page)));
if ($is_search) { $total_pages = 1; $page = 1; }
$page = min($page, $total_pages);
$offset = ($page - 1) * $records_per_page;
$page_rows = $is_search ? $rows : array_slice($rows, $offset, $records_per_page);

$total_amount_all = array_sum(array_column($rows, 'total'));
$total_bills_all = count($rows);
$qparam = urlencode($search);

$title = "Report - B2B (Territory Partner)";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo $title;?> : <?php echo $business_name;?></title>

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
        #overflowon { width: 100%; overflow-x: auto; }
        .table th { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); font-weight: 600; white-space: nowrap; font-size: 14px; padding: 12px 8px; border-color: #dee2e6; color: #495057; }
        .table td { white-space: nowrap; font-size: 13px; padding: 10px 8px; vertical-align: middle; border-color: #dee2e6; }
        .product-col { background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%); text-align: center; min-width: 80px; font-weight: 500; }
        .table-bordered { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
        .table-hover tbody tr:hover { background-color: rgba(0,123,255,0.05); transition: background-color 0.2s ease; }
        .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-radius: 12px; }
        .card-header { background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0; }
        .pagination .page-link { border-radius: 6px; margin: 0 2px; border: none; background: #f8f9fa; color: #495057; font-weight: 500; }
        .pagination .page-item.active .page-link { background: #0d6efd; color: white; box-shadow: 0 2px 4px rgba(13,110,253,0.3); }
        .pagination .page-link:hover { background: #e9ecef; color: #495057; }
        .form-select-sm { border-radius: 6px; border-color: #ced4da; font-size: 13px; }
        .alert { border-radius: 8px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    </style>
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
            <?php include("app-header.php");?>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">

                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td>B2B Sales Report - <?=htmlspecialchars($selected_branch_state);?> (Territory Partner)</td>
                                                <td><a href="Report-TP-First-Page.php">&#8592; Go Back</a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Filters -->
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header"><h5 class="card-title">Advanced Filters</h5></div>
                                    <div class="card-body">
                                        <form method="post" action="Report-TP-Details.php" id="filterForm">
                                            <input type="hidden" name="branch_state" value="<?=htmlspecialchars($selected_branch_state);?>">
                                            <div class="row mb-3">
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">From Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="frdate" value="<?=$from_date;?>" class="form-control" required>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">To Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="todate" value="<?=$to_date;?>" class="form-control" required>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Pincode contains</label>
                                                    <input type="text" name="branch_pincode" value="<?=htmlspecialchars($selected_pincode);?>" class="form-control" placeholder="e.g. 6270">
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-2">
                                                    <label class="form-label">Buyer Type</label>
                                                    <select name="buyer_type" class="form-control">
                                                        <option value="">All Buyer Types</option>
                                                        <option value="shop" <?=$selected_buyer_type=='shop'?'selected':'';?>>Shop</option>
                                                        <option value="customer" <?=$selected_buyer_type=='customer'?'selected':'';?>>Customer</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button type="submit" class="btn btn-primary"><i class="material-icons">search</i> Apply Filters</button>
                                                <a href="?clear_filters=1" class="btn btn-secondary"><i class="material-icons">refresh</i> Reset All</a>
                                            </div>
                                            <?php
                                            $active_filters = [];
                                            $active_filters[] = "State: " . htmlspecialchars($selected_branch_state);
                                            if ($selected_pincode !== '') $active_filters[] = "Pincode: " . htmlspecialchars($selected_pincode);
                                            if ($selected_buyer_type !== '') $active_filters[] = "Buyer Type: " . ucfirst($selected_buyer_type);
                                            if ($search !== '') $active_filters[] = "Search: \u{201c}" . htmlspecialchars($search) . "\u{201d}";
                                            ?>
                                            <div class="alert alert-success mb-0 mt-3"><strong>Active Filters:</strong> <?=implode(' | ', $active_filters);?></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card"><div class="card-body">
                                    <div style="font-size:12px;color:#898781;text-transform:uppercase;">Territory Partners in <?=htmlspecialchars($selected_branch_state);?></div>
                                    <div style="font-size:22px;font-weight:700;"><?=count($tp_ids);?></div>
                                </div></div>
                            </div>
                            <div class="col-md-4">
                                <div class="card"><div class="card-body">
                                    <div style="font-size:12px;color:#898781;text-transform:uppercase;">Total Bills</div>
                                    <div style="font-size:22px;font-weight:700;"><?=$total_bills_all;?></div>
                                </div></div>
                            </div>
                            <div class="col-md-4">
                                <div class="card"><div class="card-body">
                                    <div style="font-size:12px;color:#898781;text-transform:uppercase;">Total Amount</div>
                                    <div style="font-size:22px;font-weight:700;">&#8377;<?=inr_format($total_amount_all, 2);?></div>
                                </div></div>
                            </div>
                        </div>

                        <!-- Report Table -->
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-header py-3 bg-white border-0">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <form method="post" action="Report-TP-Details.php" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="branch_state" value="<?=htmlspecialchars($selected_branch_state);?>">
                                                <input type="hidden" name="frdate" value="<?=$from_date;?>">
                                                <input type="hidden" name="todate" value="<?=$to_date;?>">
                                                <input type="hidden" name="branch_pincode" value="<?=htmlspecialchars($selected_pincode);?>">
                                                <input type="hidden" name="buyer_type" value="<?=$selected_buyer_type;?>">
                                                <input type="hidden" name="page" value="1">
                                                <label class="mb-0 text-muted">Show:</label>
                                                <select name="records_per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                                    <option value="20" <?=$records_per_page==20?'selected':'';?>>20</option>
                                                    <option value="40" <?=$records_per_page==40?'selected':'';?>>40</option>
                                                    <option value="60" <?=$records_per_page==60?'selected':'';?>>60</option>
                                                </select>
                                                <label class="mb-0 text-muted">entries</label>
                                            </form>
                                            <div class="d-flex align-items-center gap-3">
                                                <form method="get" action="Report-TP-Details.php" class="d-flex align-items-center gap-2">
                                                    <input type="hidden" name="page" value="1">
                                                    <input type="text" name="q" value="<?=htmlspecialchars($search);?>" class="form-control form-control-sm" placeholder="Search invoice, buyer, TP, product..." style="min-width:280px">
                                                </form>
                                                <div class="text-muted small">
                                                    <?php if ($is_search): ?>Showing all matches<?php else: ?>Page <?=$page;?> of <?=$total_pages;?><?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                    <?php if (empty($page_rows)): ?>
                                        <p class="text-center text-muted py-4">No bills found for this state/date range.</p>
                                    <?php else: ?>
                                        <div id="overflowon">
                                            <table class="table table-bordered table-hover table-sm">
                                                <thead>
                                                    <tr>
                                                        <th rowspan="2">S.No</th>
                                                        <th rowspan="2">Territory Partner (Seller)</th>
                                                        <th rowspan="2">Invoice Number</th>
                                                        <th rowspan="2">Buyer Type</th>
                                                        <th rowspan="2">Buyer Details</th>
                                                        <th rowspan="2">Date</th>
                                                        <th rowspan="2">Sub Total</th>
                                                        <th rowspan="2">Courier Charge</th>
                                                        <th rowspan="2">Total Amount</th>
                                                        <th colspan="<?=count($products);?>" style="text-align:center; background:#e3f2fd;">Product Quantities</th>
                                                    </tr>
                                                    <tr>
                                                        <?php foreach ($products as $pr_id => $pr_name): ?>
                                                        <th class="product-col" title="<?=htmlspecialchars($pr_name);?>">
                                                            <?php $short_name = strlen($pr_name) > 30 ? substr($pr_name, 0, 27) . '...' : $pr_name; echo htmlspecialchars($short_name); ?>
                                                        </th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $serial = $offset + 1;
                                                    $page_subtotal = 0; $page_courier = 0; $page_total = 0;
                                                    $product_totals = array_fill_keys(array_keys($products), 0);
                                                    foreach ($page_rows as $r):
                                                        $tp = $tp_names[$r['tp_id']] ?? ['name' => 'N/A', 'tp_id' => '', 'mobile' => ''];
                                                        $page_subtotal += $r['sub_total'];
                                                        $page_courier  += $r['courier_charges'];
                                                        $page_total    += $r['total'];
                                                    ?>
                                                    <tr>
                                                        <td><?=$serial++;?></td>
                                                        <td>
                                                            <strong><?=htmlspecialchars($tp['name']);?></strong><br/>
                                                            <small>(<?=htmlspecialchars($tp['tp_id']);?>)</small><br/>
                                                            <small><?=htmlspecialchars($tp['mobile']);?></small>
                                                        </td>
                                                        <td><?=htmlspecialchars($r['inv_number']);?></td>
                                                        <td><?=$r['buyer_type'];?></td>
                                                        <td>
                                                            <?=htmlspecialchars($r['buyer_name']);?><br/>
                                                            <small><b>M:</b> <?=htmlspecialchars($r['buyer_mobile']);?></small>
                                                        </td>
                                                        <td><?=date("d/M/Y", strtotime($r['date']));?></td>
                                                        <td align="right"><strong>&#8377;<?=inr_format($r['sub_total'], 2);?></strong></td>
                                                        <td align="right"><strong>&#8377;<?=inr_format($r['courier_charges'], 2);?></strong></td>
                                                        <td align="right"><strong>&#8377;<?=inr_format($r['total'], 2);?></strong></td>
                                                        <?php foreach ($products as $pr_id => $pr_name):
                                                            $qty = $r['items'][$pr_id] ?? 0;
                                                            $product_totals[$pr_id] += $qty;
                                                        ?>
                                                        <td align="center" class="product-col">
                                                            <?php if ($qty > 0): ?><strong><?=$qty;?></strong><?php else: ?><span style="color:#ccc;">-</span><?php endif; ?>
                                                        </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr style="background:#e9ecef; font-weight:bold;">
                                                        <th colspan="6" align="right">Page Total (Sales):</th>
                                                        <th align="right">&#8377;<?=inr_format($page_subtotal, 2);?></th>
                                                        <th align="right">&#8377;<?=inr_format($page_courier, 2);?></th>
                                                        <th align="right">&#8377;<?=inr_format($page_total, 2);?></th>
                                                        <?php foreach ($product_totals as $total): ?>
                                                        <th align="center" class="product-col"><?=$total;?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        <!-- Pagination -->
                                        <?php if (!$is_search && $total_pages > 1): ?>
                                        <nav aria-label="Page navigation" class="mt-3">
                                            <ul class="pagination justify-content-center">
                                                <?php if ($page > 1): ?>
                                                <li class="page-item"><a class="page-link" href="?page=<?=($page-1);?>&q=<?=$qparam;?>">Previous</a></li>
                                                <?php endif; ?>
                                                <?php
                                                $start_page = max(1, $page - 2);
                                                $end_page = min($total_pages, $page + 2);
                                                if ($start_page > 1) {
                                                    echo '<li class="page-item"><a class="page-link" href="?page=1&q='.$qparam.'">1</a></li>';
                                                    if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?=($i == $page) ? 'active' : '';?>"><a class="page-link" href="?page=<?=$i;?>&q=<?=$qparam;?>"><?=$i;?></a></li>
                                                <?php endfor;
                                                if ($end_page < $total_pages) {
                                                    if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&q='.$qparam.'">' . $total_pages . '</a></li>';
                                                }
                                                ?>
                                                <?php if ($page < $total_pages): ?>
                                                <li class="page-item"><a class="page-link" href="?page=<?=($page+1);?>&q=<?=$qparam;?>">Next</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                        <?php endif; ?>

                                        <p class="text-center text-muted mt-3">
                                            <?php if ($is_search): ?>
                                                Showing all <?=$total_records;?> matching entries
                                            <?php else: ?>
                                                Showing <?=($offset+1);?> to <?=min($offset+$records_per_page, $total_records);?> of <?=$total_records;?> entries
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
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
</body>

</html>
