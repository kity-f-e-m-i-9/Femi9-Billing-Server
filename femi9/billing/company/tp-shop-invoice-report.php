<?php
// TP Shop-wise Invoice Report — 2026-11-27, user-requested ("terriotry
// panrter entha entha shop ku invoiece potu irukangalo athu kattanum,
// total varanum, return/remove panni iruntha antha value kazhichi apram
// total varanum"). Same From/To Date + District + TP Name + Product Type
// filter shape as Retail-Report-Details-TP.php — a combination that did not
// already exist anywhere else in the app (confirmed via a search across
// stockist/super-stockist/company before building this).
//
// Row grain is per-INVOICE (not per-shop) — 2026-11-27 v2, user-corrected:
// originally grouped by shop with Return/Removed columns on every row, but
// user asked for (a) S.No + a real Invoice Number column + pagination, and
// (b) Return/Removed NOT cluttering every row — instead a separate "Return
// / Removed" tab listing only the invoices that actually have one, each
// tab keeping its own total ("return remove ella shop ku venam thaniya tha
// tab mathiri vechu... total ellam verthu than").
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../territory-partner/include/ShopInvoiceHistory.php';
error_reporting(0);

if (isset($_POST['clear_all'])) {
    unset($_SESSION['tpsir_from_date'], $_SESSION['tpsir_to_date'], $_SESSION['tpsir_district_id'],
          $_SESSION['tpsir_tp_id'], $_SESSION['tpsir_product_type']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$to_date   = date('Y-m-d');
$from_date = date('Y-m-d', strtotime('-30 days'));
if (!empty($_POST['frdate'])) { $from_date = $_POST['frdate']; $_SESSION['tpsir_from_date'] = $from_date; }
elseif (!empty($_SESSION['tpsir_from_date'])) { $from_date = $_SESSION['tpsir_from_date']; }
if (!empty($_POST['todate'])) { $to_date = $_POST['todate']; $_SESSION['tpsir_to_date'] = $to_date; }
elseif (!empty($_SESSION['tpsir_to_date'])) { $to_date = $_SESSION['tpsir_to_date']; }

$selected_district_id = 0;
if (isset($_POST['district_id'])) {
    $selected_district_id = (int)$_POST['district_id'];
    if ($selected_district_id > 0) { $_SESSION['tpsir_district_id'] = $selected_district_id; }
    else { unset($_SESSION['tpsir_district_id']); }
} elseif (!empty($_SESSION['tpsir_district_id'])) {
    $selected_district_id = (int)$_SESSION['tpsir_district_id'];
}

$selected_tp_id = 0;
if (isset($_POST['tp_id'])) {
    $selected_tp_id = (int)$_POST['tp_id'];
    if ($selected_tp_id > 0) { $_SESSION['tpsir_tp_id'] = $selected_tp_id; }
    else { unset($_SESSION['tpsir_tp_id']); }
} elseif (!empty($_SESSION['tpsir_tp_id'])) {
    $selected_tp_id = (int)$_SESSION['tpsir_tp_id'];
}

$selected_product_type = '';
if (isset($_POST['product_type'])) {
    $selected_product_type = in_array($_POST['product_type'], ['napkin', 'diaper'], true) ? $_POST['product_type'] : '';
    if ($selected_product_type) { $_SESSION['tpsir_product_type'] = $selected_product_type; }
    else { unset($_SESSION['tpsir_product_type']); }
} elseif (!empty($_SESSION['tpsir_product_type'])) {
    $selected_product_type = $_SESSION['tpsir_product_type'];
}

$Report_LABLE = "Territory Partner — Shop-wise Invoice Report";

$districts = [];
$distRes = $db_conn->query("
    SELECT id, name FROM partner_location_nodes
    WHERE depth = 3 AND parent_id = (SELECT id FROM partner_location_nodes WHERE depth = 2 AND name = 'Tamilnadu' LIMIT 1)
    ORDER BY name ASC
");
if ($distRes) { while ($d = $distRes->fetch_assoc()) { $districts[] = $d; } }

$tpOptions = [];
$tpCondition = '';
if ($selected_district_id > 0) {
    $tpCondition = " AND EXISTS (
        SELECT 1 FROM territory_partner_locations tpl
        INNER JOIN partner_location_nodes f  ON f.id = tpl.location_id
        INNER JOIN partner_location_nodes t  ON t.id = f.parent_id
        INNER JOIN partner_location_nodes dv ON dv.id = t.parent_id
        WHERE tpl.territory_partner_id = tp.id AND dv.parent_id = " . (int)$selected_district_id . "
    )";
}
$tpRes = $db_conn->query("
    SELECT tp.id, CONVERT(tp.name USING utf8mb4) COLLATE utf8mb4_general_ci AS name, tp.tp_id AS tp_code
    FROM territory_partners tp
    WHERE tp.deleted_at IS NULL" . $tpCondition . "
    ORDER BY name ASC
");
if ($tpRes) { while ($t = $tpRes->fetch_assoc()) { $tpOptions[] = $t; } }

$allRows       = [];   // every invoice in range, keyed by inv_id
$returnRows    = [];   // subset of $allRows where return_amount or removed_amount > 0
$invoiceTotal  = 0.0;  // Tab 1's own total (Invoice Amount only)
$returnTotals  = ['return_amount' => 0.0, 'removed_amount' => 0.0, 'net_amount' => 0.0]; // Tab 2's own totals
$tpNameForHeader = '';
$multiTp = false; // whether more than one TP is in scope (shows a TP column) -- 2026-11-27 v3, user-requested: Territory Partner is now OPTIONAL; picking only a District (no TP) combines every TP under that District instead of forcing a single TP choice ("ella tps ku oru district full ah kuda na paapen").

// Scope of TP ids this report runs against: a single explicitly-picked TP,
// or every TP $tpOptions already resolved for the selected District (or
// every active TP at all, if neither District nor TP was picked).
$tpIdsInScope = [];
if ($selected_tp_id > 0) {
    $tpIdsInScope = [$selected_tp_id];
} else {
    $tpIdsInScope = array_map(fn($t) => (int)$t['id'], $tpOptions);
}

if (!empty($tpIdsInScope)) {
    $multiTp = count($tpIdsInScope) > 1;
    if ($selected_tp_id > 0) {
        $tpRow = $db_conn->query("SELECT CONVERT(name USING utf8mb4) COLLATE utf8mb4_general_ci AS name, tp_id AS tp_code FROM territory_partners WHERE id=" . (int)$selected_tp_id)->fetch_assoc();
        $tpNameForHeader = $tpRow ? ($tpRow['tp_code'] . ' — ' . $tpRow['name']) : '';
    } else {
        $districtLabel = 'All Districts';
        foreach ($districts as $d) { if ((int)$d['id'] === $selected_district_id) { $districtLabel = $d['name']; break; } }
        $tpNameForHeader = $districtLabel . ' — All TPs (' . count($tpIdsInScope) . ')';
    }

    $catJoin = '';
    if ($selected_product_type !== '') {
        $catJoin = " AND EXISTS (
            SELECT 1 FROM user_invoice_items uii2 JOIN products p2 ON p2.id = uii2.pr_id
            WHERE uii2.inv_id = ui.inv_id AND p2.category = '" . $db_conn->real_escape_string($selected_product_type) . "'
        )";
    }

    $tpIdList = implode(',', $tpIdsInScope);
    $stmt = $db_conn->prepare("
        SELECT ui.inv_id, ui.inv_number, ui.to_user_id AS shop_temp_id, COALESCE(s.name,'(unknown shop)') AS shop_name,
            COALESCE(CONCAT(tp.tp_id, ' — ', CONVERT(tp.name USING utf8mb4) COLLATE utf8mb4_general_ci), '') AS tp_label,
            COALESCE((
                SELECT SUM(uii.total) FROM user_invoice_items uii JOIN products p ON p.id = uii.pr_id
                WHERE uii.inv_id = ui.inv_id AND p.category = 'napkin'
            ), 0) AS napkin_total,
            COALESCE((
                SELECT SUM(uii.total) FROM user_invoice_items uii JOIN products p ON p.id = uii.pr_id
                WHERE uii.inv_id = ui.inv_id AND p.category = 'diaper'
            ), 0) AS diaper_total
        FROM user_invoice ui
        LEFT JOIN shop s ON s.temp_id = ui.to_user_id
        LEFT JOIN territory_partners tp ON tp.id = ui.from_user_id
        WHERE ui.from_user_type='territory_partner' AND ui.from_user_id IN ($tpIdList) AND ui.to_user_type='shop'
          AND ui.`date` BETWEEN ? AND ?" . $catJoin . "
        ORDER BY s.name ASC, ui.inv_number ASC
    ");
    $stmt->bind_param('ss', $from_date, $to_date);
    $stmt->execute();
    $invRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($invRows as $r) {
        $amt = $selected_product_type === 'napkin' ? (float)$r['napkin_total']
             : ($selected_product_type === 'diaper' ? (float)$r['diaper_total']
             : ((float)$r['napkin_total'] + (float)$r['diaper_total']));
        $allRows[$r['inv_id']] = [
            'inv_number'      => $r['inv_number'],
            'shop_name'       => $r['shop_name'],
            'tp_label'        => $r['tp_label'],
            'buyer_category'  => 'Shop',
            'invoice_amount'  => $amt,
            'return_amount'   => 0.0,
            'removed_amount'  => 0.0,
        ];
        $invoiceTotal += $amt;
    }

    // Plain Customer invoices (the separate `invoice`/`invoice_items` table
    // — a TP's walk-in/customer sale, not a Shop one) — 2026-11-27 v4,
    // user-requested ("category nu vechu athula shop customer nu vechu
    // kudu"). Same napkin/diaper split + date range + Product Type scoping
    // as the Shop query above, just against the customer-invoice schema.
    // Removed Amount is intentionally always 0 for these — confirmed
    // against real data that shop_invoice_change_log (the Removed-amount
    // source) never has a single row joining to this `invoice` table, i.e.
    // that tracking mechanism only ever existed for Shop invoices.
    $custCatJoin = '';
    if ($selected_product_type !== '') {
        $custCatJoin = " AND EXISTS (
            SELECT 1 FROM invoice_items ii2 JOIN products p2 ON p2.id = ii2.pr_id
            WHERE ii2.inv_id = i.inv_id AND p2.category = '" . $db_conn->real_escape_string($selected_product_type) . "'
        )";
    }
    $stmtCust = $db_conn->prepare("
        SELECT i.inv_id, i.inv_number, COALESCE(c.name,'(unknown customer)') AS cust_name,
            COALESCE(CONCAT(tp.tp_id, ' — ', CONVERT(tp.name USING utf8mb4) COLLATE utf8mb4_general_ci), '') AS tp_label,
            COALESCE((
                SELECT SUM(ii.total) FROM invoice_items ii JOIN products p ON p.id = ii.pr_id
                WHERE ii.inv_id = i.inv_id AND p.category = 'napkin'
            ), 0) AS napkin_total,
            COALESCE((
                SELECT SUM(ii.total) FROM invoice_items ii JOIN products p ON p.id = ii.pr_id
                WHERE ii.inv_id = i.inv_id AND p.category = 'diaper'
            ), 0) AS diaper_total
        FROM invoice i
        LEFT JOIN customers c ON c.id = i.customer_id
        LEFT JOIN territory_partners tp ON tp.id = i.user_id
        WHERE i.user_type='territory_partner' AND i.user_id IN ($tpIdList) AND i.deleted_at IS NULL
          AND i.`date` BETWEEN ? AND ?" . $custCatJoin . "
        ORDER BY c.name ASC, i.inv_number ASC
    ");
    $stmtCust->bind_param('ss', $from_date, $to_date);
    $stmtCust->execute();
    $custInvRows = $stmtCust->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCust->close();

    foreach ($custInvRows as $r) {
        $amt = $selected_product_type === 'napkin' ? (float)$r['napkin_total']
             : ($selected_product_type === 'diaper' ? (float)$r['diaper_total']
             : ((float)$r['napkin_total'] + (float)$r['diaper_total']));
        $allRows[$r['inv_id']] = [
            'inv_number'      => $r['inv_number'],
            'shop_name'       => $r['cust_name'],
            'tp_label'        => $r['tp_label'],
            'buyer_category'  => 'Customer',
            'invoice_amount'  => $amt,
            'return_amount'   => 0.0,
            'removed_amount'  => 0.0,
        ];
        $invoiceTotal += $amt;
    }

    // Removed Amount per invoice — not category-split when a Product Type
    // filter is active (a Void row logs the whole invoice at once, no
    // pr_id), same simplification already accepted for the dashboard's own
    // per-category removed-amount hover.
    $removedByInv = getShopInvoiceRemovedAmountByInvId($db_conn, array_keys($allRows));
    foreach ($removedByInv as $invId => $removed) {
        if (isset($allRows[$invId])) { $allRows[$invId]['removed_amount'] += $removed; }
    }

    // Return Amount per invoice — user_return_stock_items.invnumber IS the
    // invoice's own inv_id (confirmed against real data), so this groups
    // straight onto the same key as $allRows, no shop-level join needed.
    // inv_id is globally unique across every TP, so combining several TPs'
    // scope into one IN(...) list here is still safe — no cross-TP collision
    // risk on the GROUP BY.
    $retCatJoin = '';
    if ($selected_product_type !== '') {
        $retCatJoin = " AND EXISTS (SELECT 1 FROM products p3 WHERE p3.id = r.prid AND p3.category = '" . $db_conn->real_escape_string($selected_product_type) . "')";
    }
    $retRes = $db_conn->query("
        SELECT r.invnumber AS inv_id, SUM(r.subtotal) AS amt
        FROM user_return_stock_items r
        WHERE r.to_usertype='territory_partner' AND r.to_userid IN ($tpIdList)
          AND r.from_usertype='shop' AND r.deleted_at IS NULL
          AND r.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' AND '" . $db_conn->real_escape_string($to_date) . "'" . $retCatJoin . "
        GROUP BY r.invnumber
    ");
    if ($retRes) {
        while ($rr = $retRes->fetch_assoc()) {
            // A return against an invoice outside this exact date range (or
            // already not in scope for the current Product Type filter) is
            // intentionally left out — same "shops invoiced in this range"
            // scoping already documented on the invoice-level query above.
            if (isset($allRows[$rr['inv_id']])) { $allRows[$rr['inv_id']]['return_amount'] = (float)$rr['amt']; }
        }
    }

    // Same Return Amount lookup, Customer side (from_usertype='customer').
    $retCustRes = $db_conn->query("
        SELECT r.invnumber AS inv_id, SUM(r.subtotal) AS amt
        FROM user_return_stock_items r
        WHERE r.to_usertype='territory_partner' AND r.to_userid IN ($tpIdList)
          AND r.from_usertype='customer' AND r.deleted_at IS NULL
          AND r.date BETWEEN '" . $db_conn->real_escape_string($from_date) . "' AND '" . $db_conn->real_escape_string($to_date) . "'" . $retCatJoin . "
        GROUP BY r.invnumber
    ");
    if ($retCustRes) {
        while ($rr = $retCustRes->fetch_assoc()) {
            if (isset($allRows[$rr['inv_id']])) { $allRows[$rr['inv_id']]['return_amount'] = (float)$rr['amt']; }
        }
    }

    foreach ($allRows as $invId => &$row) {
        $row['net_amount'] = $row['invoice_amount'] - $row['return_amount'] - $row['removed_amount'];
        if ($row['return_amount'] > 0 || $row['removed_amount'] > 0) {
            $returnTotals['return_amount']  += $row['return_amount'];
            $returnTotals['removed_amount'] += $row['removed_amount'];
            $returnTotals['net_amount']     += $row['net_amount'];
            $returnRows[] = $row + ['inv_id' => $invId];
        }
    }
    unset($row);
    $allRows = array_values($allRows);
}
$netGrandTotal = $invoiceTotal - $returnTotals['return_amount'] - $returnTotals['removed_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $Report_LABLE; ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        .table th { background:#f8f9fa; font-weight:600; white-space:nowrap; font-size:14px; padding:12px 8px; }
        .table td { font-size:13px; padding:10px 8px; vertical-align:middle; }
        .card { border:none; box-shadow:0 2px 12px rgba(0,0,0,.08); border-radius:12px; }
        .tr-total { font-weight:700; background:#eef2ff; }
        .tpsir-stat { border-radius:10px; padding:14px 16px; text-align:center; }
        .tpsir-stat h4 { margin:0; font-weight:700; font-size:19px; }
        .tpsir-stat span { font-size:12px; color:#6b7280; }
        .tpsir-pagination { display:flex; align-items:center; gap:10px; margin-top:12px; }
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
                                <h1><?php echo $Report_LABLE; ?></h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card mb-3">
                                <div class="card-header"><h5 class="card-title mb-0">Filters</h5></div>
                                <div class="card-body">
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES); ?>">
                                        <div class="row mb-3">
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">From Date <span class="text-danger">*</span></label>
                                                <input type="date" name="frdate" value="<?php echo htmlspecialchars($from_date, ENT_QUOTES); ?>" class="form-control" required>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">To Date <span class="text-danger">*</span></label>
                                                <input type="date" name="todate" value="<?php echo htmlspecialchars($to_date, ENT_QUOTES); ?>" class="form-control" required>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">District</label>
                                                <select name="district_id" id="district_filter" class="form-control" onchange="this.form.submit()" style="width:100%;">
                                                    <option value="0">All Districts</option>
                                                    <?php foreach ($districts as $d): ?>
                                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo $selected_district_id === (int)$d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">Territory Partner</label>
                                                <select name="tp_id" id="tp_filter" class="form-control" style="width:100%;">
                                                    <option value="0">All TPs (in selected District)</option>
                                                    <?php foreach ($tpOptions as $t): ?>
                                                    <option value="<?php echo (int)$t['id']; ?>" <?php echo $selected_tp_id === (int)$t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['tp_code'] . ' — ' . $t['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">Product Type</label>
                                                <select name="product_type" id="product_type_filter" class="form-control" style="width:100%;">
                                                    <option value="">All Products</option>
                                                    <option value="napkin" <?php echo $selected_product_type === 'napkin' ? 'selected' : ''; ?>>Napkin</option>
                                                    <option value="diaper" <?php echo $selected_product_type === 'diaper' ? 'selected' : ''; ?>>Diaper</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 col-sm-12 mb-2 d-flex align-items-end gap-2">
                                                <button type="submit" class="btn btn-primary"><i class="material-icons">search</i> Search</button>
                                                <button type="submit" name="clear_all" value="1" class="btn btn-secondary">Reset</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($tpIdsInScope)): ?>
                    <div class="row mb-3">
                        <div class="col-6 col-md-3 mb-2">
                            <div class="tpsir-stat" style="background:#dbeafe;">
                                <h4>&#8377;<?php echo number_format($invoiceTotal, 2); ?></h4>
                                <span>Total Invoice Amount</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="tpsir-stat" style="background:#fef3c7;">
                                <h4>&#8377;<?php echo number_format($returnTotals['return_amount'], 2); ?></h4>
                                <span>Total Return Amount</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="tpsir-stat" style="background:#fee2e2;">
                                <h4>&#8377;<?php echo number_format($returnTotals['removed_amount'], 2); ?></h4>
                                <span>Total Removed Amount</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="tpsir-stat" style="background:#dcfce7;">
                                <h4>&#8377;<?php echo number_format($netGrandTotal, 2); ?></h4>
                                <span>Net Grand Total</span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-3"><?php echo htmlspecialchars($tpNameForHeader); ?> — <?php echo htmlspecialchars($from_date); ?> to <?php echo htmlspecialchars($to_date); ?><?php echo $selected_product_type !== '' ? ' (' . ucfirst($selected_product_type) . ' only)' : ''; ?></h5>
                                    <ul class="nav nav-tabs card-header-tabs mt-3" id="tpsirTabs">
                                        <li class="nav-item"><a class="nav-link active" data-tab="invoicesTab" href="#">All Invoices (<?php echo count($allRows); ?>)</a></li>
                                        <li class="nav-item"><a class="nav-link" data-tab="returnsTab" href="#">Return / Removed (<?php echo count($returnRows); ?>)</a></li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div id="invoicesTab">
                                    <?php if (empty($allRows)): ?>
                                    <div class="alert alert-info mb-0">No invoices found for this selection in the selected period.</div>
                                    <?php else: ?>
                                    <div style="overflow-x:auto;">
                                    <table class="table table-bordered table-hover" id="invoicesTable">
                                        <thead>
                                            <tr>
                                                <th>S.No</th>
                                                <?php if ($multiTp): ?><th>Territory Partner</th><?php endif; ?>
                                                <th>Name</th>
                                                <th>Category</th>
                                                <th>Invoice Number</th>
                                                <th class="text-end">Invoice Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allRows as $i => $row): ?>
                                            <tr>
                                                <td><?php echo $i + 1; ?></td>
                                                <?php if ($multiTp): ?><td><?php echo htmlspecialchars($row['tp_label']); ?></td><?php endif; ?>
                                                <td><?php echo htmlspecialchars($row['shop_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['buyer_category']); ?></td>
                                                <td><?php echo htmlspecialchars($row['inv_number']); ?></td>
                                                <td class="text-end">&#8377;<?php echo number_format($row['invoice_amount'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="tr-total">
                                                <td colspan="<?php echo $multiTp ? 5 : 4; ?>">TOTAL</td>
                                                <td class="text-end">&#8377;<?php echo number_format($invoiceTotal, 2); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    </div>
                                    <div class="tpsir-pagination" data-paginate-for="invoicesTable"></div>
                                    <?php endif; ?>
                                    </div>

                                    <div id="returnsTab" style="display:none;">
                                    <?php if (empty($returnRows)): ?>
                                    <div class="alert alert-info mb-0">No Return/Removed activity for this selection in the selected period.</div>
                                    <?php else: ?>
                                    <div style="overflow-x:auto;">
                                    <table class="table table-bordered table-hover" id="returnsTable">
                                        <thead>
                                            <tr>
                                                <th>S.No</th>
                                                <?php if ($multiTp): ?><th>Territory Partner</th><?php endif; ?>
                                                <th>Name</th>
                                                <th>Category</th>
                                                <th>Invoice Number</th>
                                                <th class="text-end">Return Amount</th>
                                                <th class="text-end">Removed Amount</th>
                                                <th class="text-end">Net Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($returnRows as $i => $row): ?>
                                            <tr>
                                                <td><?php echo $i + 1; ?></td>
                                                <?php if ($multiTp): ?><td><?php echo htmlspecialchars($row['tp_label']); ?></td><?php endif; ?>
                                                <td><?php echo htmlspecialchars($row['shop_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['buyer_category']); ?></td>
                                                <td><?php echo htmlspecialchars($row['inv_number']); ?></td>
                                                <td class="text-end">&#8377;<?php echo number_format($row['return_amount'], 2); ?></td>
                                                <td class="text-end">&#8377;<?php echo number_format($row['removed_amount'], 2); ?></td>
                                                <td class="text-end"><strong>&#8377;<?php echo number_format($row['net_amount'], 2); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="tr-total">
                                                <td colspan="<?php echo $multiTp ? 5 : 4; ?>">TOTAL</td>
                                                <td class="text-end">&#8377;<?php echo number_format($returnTotals['return_amount'], 2); ?></td>
                                                <td class="text-end">&#8377;<?php echo number_format($returnTotals['removed_amount'], 2); ?></td>
                                                <td class="text-end">&#8377;<?php echo number_format($returnTotals['net_amount'], 2); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    </div>
                                    <div class="tpsir-pagination" data-paginate-for="returnsTable"></div>
                                    <?php endif; ?>
                                    </div>
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
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
    $(function () {
        $('#district_filter').select2({ placeholder: 'All Districts', allowClear: true, width: '100%' });
        $('#tp_filter').select2({ width: '100%' });
        $('#product_type_filter').select2({ width: '100%' });

        // Tab switch — plain show/hide, no extra assets needed.
        $('#tpsirTabs a').on('click', function (e) {
            e.preventDefault();
            $('#tpsirTabs a').removeClass('active');
            $(this).addClass('active');
            $('#invoicesTab, #returnsTab').hide();
            $('#' + $(this).data('tab')).show();
        });

        // Client-side pagination — the row data is already fully rendered
        // server-side (no extra round trip), JS just shows/hides 25 rows at
        // a time per table. 2026-11-27, user-requested.
        var PAGE_SIZE = 25;
        $('.tpsir-pagination').each(function () {
            var tableId = $(this).data('paginate-for');
            var $table = $('#' + tableId);
            var $rows = $table.find('tbody tr');
            var total = $rows.length;
            if (total <= PAGE_SIZE) { return; }
            var pageCount = Math.ceil(total / PAGE_SIZE);
            var current = 1;
            var $bar = $(this);

            function render() {
                $rows.hide().slice((current - 1) * PAGE_SIZE, current * PAGE_SIZE).show();
                $bar.find('.tpsir-page-label').text('Page ' + current + ' of ' + pageCount);
                $bar.find('.tpsir-prev').prop('disabled', current === 1);
                $bar.find('.tpsir-next').prop('disabled', current === pageCount);
            }

            $bar.html(
                '<button type="button" class="btn btn-sm btn-outline-secondary tpsir-prev">Previous</button>' +
                '<span class="tpsir-page-label text-muted"></span>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary tpsir-next">Next</button>'
            );
            $bar.find('.tpsir-prev').on('click', function () { if (current > 1) { current--; render(); } });
            $bar.find('.tpsir-next').on('click', function () { if (current < pageCount) { current++; render(); } });
            render();
        });
    });
</script>
</body>
</html>
