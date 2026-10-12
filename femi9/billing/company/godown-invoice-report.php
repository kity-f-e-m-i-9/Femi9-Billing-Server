<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
require_once("include/GodownAccess.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Every invoice fulfilled out of one Company Profile's stock — Territory
// Partner, OT Channel, and Channel Partner invoices combined into one
// place, both product-wise (how much of each product went out, split by
// type) and invoice-wise (every individual invoice line). Built to verify
// exactly how much of a product already left a godown before deciding
// whether/how to undo an Auto Transfer run against it.
$godownOptions = $db_conn->query("SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

$defaultGodownId = 0;
foreach ($godownOptions as $g) {
    if ($g['gname'] === 'FEMI NAYAN LLP') { $defaultGodownId = (int) $g['id']; break; }
}
if (!$defaultGodownId && !empty($godownOptions)) { $defaultGodownId = (int) $godownOptions[0]['id']; }

$godownId = filter_var($_GET['godown_id'] ?? $defaultGodownId, FILTER_VALIDATE_INT) ?: $defaultGodownId;
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate   = $_GET['to_date']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) $fromDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate))   $toDate   = date('Y-m-d');
if ($fromDate > $toDate) { [$fromDate, $toDate] = [$toDate, $fromDate]; }

$lines = []; // one row per invoice line, across all 3 types

if ($godownId) {
    $tpStmt = $db_conn->prepare(
        "SELECT ti.invoice_number, ti.invoice_date, tp.name AS partner_name, tp.tp_id AS partner_code,
                tii.product_id, p.productName, tii.quantity AS qty, tii.amount
         FROM tp_invoices ti
         INNER JOIN tp_invoice_items tii ON tii.tp_invoice_id = ti.id
         INNER JOIN territory_partners tp ON tp.id = ti.territory_partner_id
         INNER JOIN products p ON p.id = tii.product_id
         WHERE ti.source_godown_id = ? AND ti.invoice_date BETWEEN ? AND ?
         ORDER BY ti.invoice_date DESC, ti.id DESC"
    );
    $tpStmt->bind_param('iss', $godownId, $fromDate, $toDate);
    $tpStmt->execute();
    foreach ($tpStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $lines[] = [
            'type'          => 'TP',
            'invoice_number'=> $r['invoice_number'],
            'date'          => $r['invoice_date'],
            'partner'       => $r['partner_name'] . ' (' . $r['partner_code'] . ')',
            'product_id'    => (int) $r['product_id'],
            'product_name'  => $r['productName'],
            'qty'           => (int) $r['qty'],
            'amount'        => (float) $r['amount'],
        ];
    }
    $tpStmt->close();

    $cpStmt = $db_conn->prepare(
        "SELECT ci.invoice_number, ci.invoice_date, cp.name AS partner_name, cp.cp_id AS partner_code,
                cii.product_id, p.productName, cii.quantity AS qty, cii.amount
         FROM cp_invoices ci
         INNER JOIN cp_invoice_items cii ON cii.cp_invoice_id = ci.id
         INNER JOIN channel_partners cp ON cp.id = ci.channel_partner_id
         INNER JOIN products p ON p.id = cii.product_id
         WHERE ci.source_godown_id = ? AND ci.invoice_date BETWEEN ? AND ?
         ORDER BY ci.invoice_date DESC, ci.id DESC"
    );
    $cpStmt->bind_param('iss', $godownId, $fromDate, $toDate);
    $cpStmt->execute();
    foreach ($cpStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $lines[] = [
            'type'          => 'CP',
            'invoice_number'=> $r['invoice_number'],
            'date'          => $r['invoice_date'],
            'partner'       => $r['partner_name'] . ' (' . $r['partner_code'] . ')',
            'product_id'    => (int) $r['product_id'],
            'product_name'  => $r['productName'],
            'qty'           => (int) $r['qty'],
            'amount'        => (float) $r['amount'],
        ];
    }
    $cpStmt->close();

    // OT Channel has no source-godown column on the invoice header — the
    // godown lives on each ot_sales line (os.godownid) instead, same field
    // Auto Transfer's own demand query (get_auto_transfer_requirements())
    // filters on. Only 'confirmed' sales count as actually invoiced out —
    // 'draft' rows are still pending orders, not yet fulfilled.
    $otStmt = $db_conn->prepare(
        "SELECT osi.inv_number, os.date, os.customer_name,
                os.prid AS product_id, p.productName, os.qty, os.total AS amount
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         INNER JOIN products p ON p.id = os.prid
         WHERE os.godownid = ? AND osi.status = 'confirmed' AND os.date BETWEEN ? AND ?
         ORDER BY os.date DESC, os.id DESC"
    );
    $otStmt->bind_param('iss', $godownId, $fromDate, $toDate);
    $otStmt->execute();
    foreach ($otStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $lines[] = [
            'type'          => 'OT',
            'invoice_number'=> $r['inv_number'] ?: '—',
            'date'          => $r['date'],
            'partner'       => $r['customer_name'] ?: 'Walk-in',
            'product_id'    => (int) $r['product_id'],
            'product_name'  => $r['productName'],
            'qty'           => (int) $r['qty'],
            'amount'        => (float) $r['amount'],
        ];
    }
    $otStmt->close();

    usort($lines, fn($a, $b) => strcmp($b['date'], $a['date']));
}

// Product-wise summary, split by type — answers "how much of this
// product already left, and through which channel" at a glance.
$productSummary = [];
foreach ($lines as $l) {
    $pid = $l['product_id'];
    if (!isset($productSummary[$pid])) {
        $productSummary[$pid] = ['product_name' => $l['product_name'], 'tp' => 0, 'ot' => 0, 'cp' => 0, 'total' => 0];
    }
    $productSummary[$pid][strtolower($l['type'])] += $l['qty'];
    $productSummary[$pid]['total'] += $l['qty'];
}
uasort($productSummary, fn($a, $b) => $b['total'] <=> $a['total']);

$grandTotalQty = array_sum(array_column($lines, 'qty'));
$grandTotalAmt = array_sum(array_column($lines, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Godown Invoice Report : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        .gir-filter-card { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; }
        .gir-filter-card .form-label { color:#fff; font-weight:500; margin-bottom:4px; font-size:12.5px; }
        .gir-filter-card .form-control { background:rgba(255,255,255,0.95); border:none; border-radius:6px; font-size:13px; height:36px; }
        .gir-btn-filter { background:#fff; color:#667eea; border:none; border-radius:6px; padding:7px 18px; font-size:13px; font-weight:600; height:36px; cursor:pointer; }
        .gir-stat-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
        .gir-stat { flex:1 1 150px; background:#fff; border:1px solid #eef0f3; border-radius:10px; padding:12px 16px; }
        .gir-stat .num { font-size:20px; font-weight:700; color:#1f2937; }
        .gir-stat .lbl { font-size:11px; color:#9ca3af; text-transform:uppercase; }
        .gir-type-pill { padding:2px 9px; border-radius:20px; font-size:10.5px; font-weight:700; letter-spacing:.3px; }
        .gir-type-tp { background:#eef2ff; color:#4338ca; }
        .gir-type-ot { background:#ecfeff; color:#0e7490; }
        .gir-type-cp { background:#ecfdf5; color:#065f46; }
        .gir-tabs { display:flex; gap:6px; margin-bottom:14px; border-bottom:1px solid #e5e7eb; }
        .gir-tab { padding:9px 16px; font-size:13.5px; font-weight:600; color:#6b7280; text-decoration:none; border-bottom:2px solid transparent; cursor:pointer; }
        .gir-tab.active { color:#667eea; border-bottom-color:#667eea; }
        .gir-tabpanel { display:none; }
        .gir-tabpanel.active { display:block; }
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
                                <h1>Godown Invoice Report</h1>
                            </div>
                        </div>
                    </div>

                    <div class="gir-filter-card">
                        <form method="GET" action="">
                            <div class="row align-items-end g-2">
                                <div class="col-lg-4 col-sm-6">
                                    <label class="form-label">Company Profile</label>
                                    <select name="godown_id" class="form-control">
                                        <?php foreach ($godownOptions as $g): ?>
                                        <option value="<?php echo (int) $g['id']; ?>" <?php echo $godownId == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['gname']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>">
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>">
                                </div>
                                <div class="col-lg-2 col-sm-6">
                                    <button type="submit" class="gir-btn-filter">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="gir-stat-row">
                        <div class="gir-stat"><div class="num"><?php echo count($lines); ?></div><div class="lbl">Invoice Lines</div></div>
                        <div class="gir-stat"><div class="num"><?php echo (int) $grandTotalQty; ?></div><div class="lbl">Total Qty</div></div>
                        <div class="gir-stat"><div class="num">&#8377;<?php echo inr_format($grandTotalAmt, 2); ?></div><div class="lbl">Total Amount</div></div>
                        <div class="gir-stat"><div class="num"><?php echo count($productSummary); ?></div><div class="lbl">Products</div></div>
                    </div>

                    <div class="gir-tabs">
                        <a class="gir-tab active" data-tab="product">Product-wise</a>
                        <a class="gir-tab" data-tab="invoice">Invoice-wise</a>
                    </div>

                    <div class="gir-tabpanel active" id="tab-product">
                        <div class="card">
                            <div class="card-body">
                                <div style="overflow-x:auto;">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th class="text-right">TP Qty</th>
                                                <th class="text-right">OT Qty</th>
                                                <th class="text-right">CP Qty</th>
                                                <th class="text-right">Total Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($productSummary)): ?>
                                            <tr><td colspan="5" class="text-center text-muted">No invoices in this date range.</td></tr>
                                            <?php else: foreach ($productSummary as $s): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                                                <td class="text-right"><?php echo (int) $s['tp']; ?></td>
                                                <td class="text-right"><?php echo (int) $s['ot']; ?></td>
                                                <td class="text-right"><?php echo (int) $s['cp']; ?></td>
                                                <td class="text-right"><b><?php echo (int) $s['total']; ?></b></td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="gir-tabpanel" id="tab-invoice">
                        <div class="card">
                            <div class="card-body">
                                <div style="overflow-x:auto;">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Invoice #</th>
                                                <th>Date</th>
                                                <th>Partner / Customer</th>
                                                <th>Product</th>
                                                <th class="text-right">Qty</th>
                                                <th class="text-right">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($lines)): ?>
                                            <tr><td colspan="7" class="text-center text-muted">No invoices in this date range.</td></tr>
                                            <?php else: foreach ($lines as $l): ?>
                                            <tr>
                                                <td><span class="gir-type-pill gir-type-<?php echo strtolower($l['type']); ?>"><?php echo htmlspecialchars($l['type']); ?></span></td>
                                                <td><code><?php echo htmlspecialchars($l['invoice_number']); ?></code></td>
                                                <td><?php echo htmlspecialchars($l['date']); ?></td>
                                                <td><?php echo htmlspecialchars($l['partner']); ?></td>
                                                <td><?php echo htmlspecialchars($l['product_name']); ?></td>
                                                <td class="text-right"><?php echo (int) $l['qty']; ?></td>
                                                <td class="text-right">&#8377;<?php echo inr_format($l['amount'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; endif; ?>
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

<script>
document.querySelectorAll('.gir-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.gir-tab').forEach(function (t) { t.classList.remove('active'); });
        document.querySelectorAll('.gir-tabpanel').forEach(function (p) { p.classList.remove('active'); });
        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
});
</script>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
