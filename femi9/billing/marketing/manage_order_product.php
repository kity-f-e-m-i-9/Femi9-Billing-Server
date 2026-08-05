<?php include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");
error_reporting(0);

// A manager (SM/ASM) can view a report scoped to any person in their own
// downline via ?view_ms_id=<id> — enforced server-side against their real
// subtree, never trusted from the request alone. Defaults to viewing your
// own orders, same as before.
$viewMsId = (int)$markeingSTFID;
$viewingSelf = true;
$viewMsName = '';
if (isset($_REQUEST['view_ms_id']) && $_REQUEST['view_ms_id'] !== '') {
    $requestedMsId = (int)$_REQUEST['view_ms_id'];
    if ($requestedMsId !== (int)$markeingSTFID) {
        $allowedSubtree = getMsSubtreeIds($db_conn, (int)$markeingSTFID);
        if (in_array($requestedMsId, $allowedSubtree, true)) {
            $viewMsId = $requestedMsId;
            $viewingSelf = false;
        }
    }
}
if (!$viewingSelf) {
    $stmtName = $db_conn->prepare("SELECT ms_name FROM marketing_staff WHERE id=?");
    $stmtName->bind_param('i', $viewMsId);
    $stmtName->execute();
    $viewMsName = $stmtName->get_result()->fetch_assoc()['ms_name'] ?? '';
    $stmtName->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage Orders (Product Orders) : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">\
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
    <style>
        .tp-status-box {
            background:#fff; border:1px solid #e5e7eb; border-radius:8px;
            padding:10px 12px; min-width:200px; box-shadow:0 1px 2px rgba(0,0,0,0.03);
        }
        .tp-status-box .badge { font-size:11px; font-weight:600; padding:4px 9px; border-radius:6px; white-space:nowrap; }
        .tp-status-header { display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .tp-status-header-amt { font-size:12px; font-weight:700; color:#374151; white-space:nowrap; }
        .tp-status-detail {
            margin-top:8px; padding-top:8px; border-top:1px solid #eef0f3;
            display:flex; flex-direction:column; gap:4px;
        }
        .tp-status-pending { font-size:11px; color:#6b7280; }
        .tp-status-diff { font-size:11px; color:#6b7280; }
        .tp-status-link a { font-size:11px; font-weight:600; text-decoration:none; }
        .tp-status-link a:hover { text-decoration:underline; }
    </style>
</head>

<body>


<?php
// Check for error message in session
if (isset($_SESSION['successMessage'])) {
$successMessage = $_SESSION['successMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $successMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['successMessage']); } ?>

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
								
								<?php
									if($_REQUEST['frdate']==NULL && $_REQUEST['todate']==NULL)
									{
										$from_date=date("Y-m-d");
										$to_date=date("Y-m-d");

									}else{
										$from_date=$_REQUEST['frdate'];
										$to_date=$_REQUEST['todate'];
									}

// ── Date-range summary cards ────────────────────────────────────────────────
// Get Order vs No Order counts (this DM's own submissions in range).
$getOrderCount = 0; $noOrderCount = 0;
$resGN = $db_conn->query(
    "SELECT new_order, COUNT(DISTINCT order_id) c FROM ms_orders
     WHERE ms_id='" . mysqli_real_escape_string($db_conn, $viewMsId) . "' AND order_date BETWEEN '$from_date' AND '$to_date'
     GROUP BY new_order"
);
while ($gr = mysqli_fetch_assoc($resGN)) {
    if ($gr['new_order'] === 'yes') { $getOrderCount = (int)$gr['c']; }
    else { $noOrderCount = (int)$gr['c']; }
}

// Product-wise qty needed across all "Get Order" visits in range (what the
// DM asked for, regardless of whether the TP has invoiced it yet). Value is
// an estimate — qty * the product's outlet_price (the same default rate
// order-to-invoice.php pre-fills when the TP actually invoices it) — not
// what the TP ends up billing, which can differ (custom rate, discount).
$productNeeded = [];
$totalQtyNeeded = 0;
$totalGetOrderValue = 0.0;
$resPQ = $db_conn->query(
    "SELECT mo.pr_id, p.productName, p.outlet_price, SUM(mo.qty) totalqty,
            SUM(mo.qty * p.outlet_price * (1 - mo.discount_percentage/100)) totalvalue
     FROM ms_orders mo LEFT JOIN products p ON p.id=mo.pr_id
     WHERE mo.ms_id='" . mysqli_real_escape_string($db_conn, $viewMsId) . "' AND mo.new_order='yes'
           AND mo.order_date BETWEEN '$from_date' AND '$to_date'
     GROUP BY mo.pr_id ORDER BY totalqty DESC"
);
while ($pr = mysqli_fetch_assoc($resPQ)) {
    $qty = (int)$pr['totalqty'];
    $value = (float)($pr['totalvalue'] ?? 0);
    $productNeeded[] = ['name' => $pr['productName'] ?: '-', 'qty' => $qty, 'value' => $value];
    $totalQtyNeeded += $qty;
    $totalGetOrderValue += $value;
}

// Which of those visits the TP has actually turned into an invoice.
$invoiceIds = [];
$resInv = $db_conn->query(
    "SELECT DISTINCT t.invoiced_inv_id
     FROM ms_orders o JOIN tp_orders t ON t.order_id=o.order_id
     WHERE o.ms_id='" . mysqli_real_escape_string($db_conn, $viewMsId) . "' AND o.new_order='yes'
           AND o.order_date BETWEEN '$from_date' AND '$to_date'
           AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''"
);
while ($ir = mysqli_fetch_assoc($resInv)) { $invoiceIds[] = $ir['invoiced_inv_id']; }

$invoiceCount = count($invoiceIds);
$totalInvoiceValue = 0;
$fullyPaidCount = 0; $fullyPaidValue = 0;
$partiallyPaidCount = 0; $partiallyPaidReceived = 0; $partiallyPaidInvIds = [];
$voidedCount = 0; $voidedValue = 0; $voidedInvIds = [];

if (!empty($invoiceIds)) {
    $idList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $invoiceIds)) . "'";

    $resVal = $db_conn->query("SELECT inv_id, total, status FROM user_invoice WHERE inv_id IN ($idList)");
    $invTotals = []; $invStatus = [];
    while ($vr = mysqli_fetch_assoc($resVal)) {
        $invTotals[$vr['inv_id']] = (float)$vr['total'];
        $invStatus[$vr['inv_id']] = $vr['status'];
        $totalInvoiceValue += (float)$vr['total'];
    }

    $resRec = $db_conn->query("SELECT inv_id, SUM(received) received FROM receipt WHERE inv_id IN ($idList) GROUP BY inv_id");
    $received = [];
    while ($rr = mysqli_fetch_assoc($resRec)) { $received[$rr['inv_id']] = (float)$rr['received']; }

    foreach ($invTotals as $iid => $tot) {
        if (($invStatus[$iid] ?? '') === 'cancelled') {
            $voidedCount++; $voidedValue += $tot; $voidedInvIds[] = $iid;
            continue;
        }
        $rec = $received[$iid] ?? 0;
        if ($rec >= $tot && $tot > 0) {
            $fullyPaidCount++; $fullyPaidValue += $tot;
        } elseif ($rec > 0) {
            $partiallyPaidCount++; $partiallyPaidReceived += $rec; $partiallyPaidInvIds[] = $iid;
        }
    }
}

// Product-wise qty breakdown for a set of invoice ids — used by both the
// Partially Paid and Voided cards' tap-to-expand panels.
function getProductBreakdownForInvoices($db_conn, array $invIds): array {
    if (empty($invIds)) { return []; }
    $idList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $invIds)) . "'";
    $res = $db_conn->query(
        "SELECT uii.pr_id, p.productName, SUM(uii.qty) totalqty
         FROM user_invoice_items uii LEFT JOIN products p ON p.id=uii.pr_id
         WHERE uii.inv_id IN ($idList)
         GROUP BY uii.pr_id ORDER BY totalqty DESC"
    );
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = ['name' => $row['productName'] ?: '-', 'qty' => (int)$row['totalqty']];
    }
    return $out;
}

$partiallyPaidProducts = getProductBreakdownForInvoices($db_conn, $partiallyPaidInvIds);
$voidedProducts = getProductBreakdownForInvoices($db_conn, $voidedInvIds);

// ── Batch data for the order table below ────────────────────────────────────
// The table used to run several queries PER ORDER ROW (tp_orders lookup,
// invoice lookup, receipt check, change-log diff) PLUS one query per
// product per row just to fill in the qty columns — with many orders and
// many products that's thousands of round-trips and is why this page was
// slow to load. Everything needed is fetched once here instead, keyed by
// order_id/pr_id/inv_id, and the table below just looks values up in PHP.

$allProducts = [];
$productPriceMap = [];
$resAllProd = $db_conn->query("SELECT id, productName, outlet_price FROM products ORDER BY id ASC");
while ($apr = mysqli_fetch_assoc($resAllProd)) {
    $allProducts[] = $apr;
    $productPriceMap[(int)$apr['id']] = (float)$apr['outlet_price'];
}

$viewMsIdEsc = mysqli_real_escape_string($db_conn, $viewMsId);

// One row per order line (shop_id/date/etc repeat per line, qty is per pr_id) —
// build both the order-level metadata map and the per-product qty map from it.
$orderIdsInRange = [];
$orderMeta = [];
$qtyMap = [];
$shopIds = [];
$orderValueMap = [];
$resOrders = $db_conn->query(
    "SELECT order_id, shop_id, order_date, marketing_tool, latitude, longitude, pr_id, qty, discount_percentage
     FROM ms_orders
     WHERE ms_id='$viewMsIdEsc' AND new_order='yes' AND order_date BETWEEN '$from_date' AND '$to_date'
     ORDER BY order_date DESC, order_id DESC"
);
while ($orow = mysqli_fetch_assoc($resOrders)) {
    $oid = $orow['order_id'];
    if (!isset($orderMeta[$oid])) {
        $orderMeta[$oid] = $orow;
        $orderIdsInRange[] = $oid;
        if (!empty($orow['shop_id'])) { $shopIds[$orow['shop_id']] = true; }
    }
    $qtyMap[$oid][$orow['pr_id']] = (int)$orow['qty'];
    $lineQty = (int)$orow['qty'];
    $linePrice = $productPriceMap[(int)$orow['pr_id']] ?? 0;
    $lineDiscount = (float)($orow['discount_percentage'] ?? 0);
    $orderValueMap[$oid] = ($orderValueMap[$oid] ?? 0) + ($lineQty * $linePrice * (1 - $lineDiscount / 100));
}

$shopMeta = [];
if (!empty($shopIds)) {
    $shopIdList = implode(',', array_map('intval', array_keys($shopIds)));
    $resShops = $db_conn->query("SELECT * FROM ms_shop WHERE id IN ($shopIdList)");
    while ($srow = mysqli_fetch_assoc($resShops)) { $shopMeta[$srow['id']] = $srow; }
}

$tpOrderMeta = [];
if (!empty($orderIdsInRange)) {
    $oidList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $orderIdsInRange)) . "'";
    $resTp = $db_conn->query("SELECT order_id, tp_id, invoiced_inv_id, voided_at, void_reason FROM tp_orders WHERE order_id IN ($oidList)");
    while ($trow = mysqli_fetch_assoc($resTp)) {
        if (!isset($tpOrderMeta[$trow['order_id']])) { $tpOrderMeta[$trow['order_id']] = $trow; }
    }
}

$diffCountsByInv = [];
if (!empty($invoiceIds)) {
    $idList2 = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $invoiceIds)) . "'";
    $resDiff = $db_conn->query(
        "SELECT inv_id, change_type, COUNT(*) c FROM shop_invoice_change_log
         WHERE inv_id IN ($idList2) AND change_type IN ('added','removed','qty_changed')
         GROUP BY inv_id, change_type"
    );
    while ($dr = mysqli_fetch_assoc($resDiff)) {
        $diffCountsByInv[$dr['inv_id']][$dr['change_type']] = (int)$dr['c'];
    }
}

// ── Monthly Target ──────────────────────────────────────────────────────────
// Fixed monthly target amount set per-DM (Company -> Update Marketing Staff).
// Prorated to the selected date range by per-day target (monthly target /
// days in that month) so the card stays meaningful whether the DM filters a
// single day, a week, or the full month.
$_chkTgtCol = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'monthly_target_amount'");
if ($_chkTgtCol && $_chkTgtCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN monthly_target_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER manager_id");
}
$monthlyTarget = 0.0;
$stmtTgt = $db_conn->prepare("SELECT monthly_target_amount FROM marketing_staff WHERE id=?");
$stmtTgt->bind_param('i', $viewMsId);
$stmtTgt->execute();
$tgtRow = $stmtTgt->get_result()->fetch_assoc();
$stmtTgt->close();
$monthlyTarget = (float)($tgtRow['monthly_target_amount'] ?? 0);

$daysInMonth  = (int)date('t', strtotime($from_date));
$perDayTarget = $daysInMonth > 0 ? $monthlyTarget / $daysInMonth : 0.0;
$daysInRange  = (int)floor((strtotime($to_date) - strtotime($from_date)) / 86400) + 1;
if ($daysInRange < 1) { $daysInRange = 1; }
$targetForPeriod = $perDayTarget * $daysInRange;
$targetAchievedAmt = $totalInvoiceValue;
$targetPercent = $targetForPeriod > 0 ? min(100, ($targetAchievedAmt / $targetForPeriod) * 100) : 0;
					?>

                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage Orders <font size="3">(Product Orders)</font></td>
									<td>
									<a href="manager_order_csv?frd=<?=$from_date;?>&&tod=<?=$to_date;?><?=!$viewingSelf ? '&&view_ms_id='.(int)$viewMsId : '';?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a>
									</td>
									</tr>
									</table>
									</h1>
									<?php if (!$viewingSelf): ?>
									<div class="alert alert-info" style="margin-top:10px;">
										Viewing orders for <b><?=htmlspecialchars($viewMsName ?: 'this team member')?></b>
										&mdash; <a href="manage_order_product.php">Back to my own orders</a>
									</div>
									<?php endif; ?>
                                </div>
                            </div>
                        </div>


<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
<input type="hidden" name="view_ms_id" value="<?=(int)$viewMsId;?>">
<?php if (!$viewingSelf): ?><input type="hidden" name="__keep_view" value="1"><?php endif; ?>

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>
<div id="searchbuttoncont">
<a href="manage_order_product.php<?=!$viewingSelf ? '?view_ms_id='.(int)$viewMsId : '';?>" style="margin-left:10px;" class="btn btn-primary">Reset</a>
</div>

							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>

<div class="row mb-3">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card-wrap">
            <div class="kpi-card kpi-card-clickable">
                <i class="material-icons-outlined kpi-ico">receipt_long</i>
                <div class="kpi-t">Invoices Placed</div>
                <div class="kpi-v"><?php echo (int)$invoiceCount; ?></div>
                <div class="kpi-sub"><?php echo (int)$totalQtyNeeded; ?> products needed &middot; hover / tap for details</div>
            </div>
            <div class="kpi-detail-panel">
                <div class="kpi-detail-title">Products needed</div>
                <?php if (empty($productNeeded)): ?>
                    <div class="kpi-detail-empty">No products in this range.</div>
                <?php else: foreach ($productNeeded as $pn): ?>
                    <div class="kpi-detail-row"><span><?php echo htmlspecialchars($pn['name']); ?></span><span class="kpi-detail-qty"><?php echo (int)$pn['qty']; ?></span></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card-wrap">
            <div class="kpi-card kpi-card-clickable">
                <i class="material-icons-outlined kpi-ico">shopping_cart</i>
                <div class="kpi-t">Get Order Value</div>
                <div class="kpi-v">&#8377;<?php echo inr_format($totalGetOrderValue, 2); ?></div>
                <div class="kpi-sub">Estimated &middot; hover / tap for details</div>
            </div>
            <div class="kpi-detail-panel">
                <div class="kpi-detail-title">Estimated value by product</div>
                <?php if (empty($productNeeded)): ?>
                    <div class="kpi-detail-empty">No products in this range.</div>
                <?php else: foreach ($productNeeded as $pn): ?>
                    <div class="kpi-detail-row"><span><?php echo htmlspecialchars($pn['name']); ?></span><span class="kpi-detail-qty">&#8377;<?php echo inr_format($pn['value'], 2); ?></span></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card">
            <i class="material-icons-outlined kpi-ico">payments</i>
            <div class="kpi-t">Total Invoice Value</div>
            <div class="kpi-v">&#8377;<?php echo inr_format($totalInvoiceValue, 2); ?></div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card">
            <i class="material-icons-outlined kpi-ico">check_circle</i>
            <div class="kpi-t">Fully Paid</div>
            <div class="kpi-v"><?php echo (int)$fullyPaidCount; ?></div>
            <div class="kpi-sub">&#8377;<?php echo inr_format($fullyPaidValue, 2); ?></div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card-wrap">
            <div class="kpi-card kpi-card-clickable">
                <i class="material-icons-outlined kpi-ico">hourglass_bottom</i>
                <div class="kpi-t">Partially Paid</div>
                <div class="kpi-v"><?php echo (int)$partiallyPaidCount; ?></div>
                <div class="kpi-sub">&#8377;<?php echo inr_format($partiallyPaidReceived, 2); ?> received &middot; hover / tap for details</div>
            </div>
            <div class="kpi-detail-panel">
                <div class="kpi-detail-title">Products in these invoices</div>
                <?php if (empty($partiallyPaidProducts)): ?>
                    <div class="kpi-detail-empty">No partially paid invoices in this range.</div>
                <?php else: foreach ($partiallyPaidProducts as $pp): ?>
                    <div class="kpi-detail-row"><span><?php echo htmlspecialchars($pp['name']); ?></span><span class="kpi-detail-qty"><?php echo (int)$pp['qty']; ?></span></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card-wrap">
            <div class="kpi-card kpi-card-clickable">
                <i class="material-icons-outlined kpi-ico">cancel</i>
                <div class="kpi-t">Voided</div>
                <div class="kpi-v"><?php echo (int)$voidedCount; ?></div>
                <div class="kpi-sub">&#8377;<?php echo inr_format($voidedValue, 2); ?> voided &middot; hover / tap for details</div>
            </div>
            <div class="kpi-detail-panel">
                <div class="kpi-detail-title">Products in voided invoices</div>
                <?php if (empty($voidedProducts)): ?>
                    <div class="kpi-detail-empty">No voided invoices in this range.</div>
                <?php else: foreach ($voidedProducts as $vp): ?>
                    <div class="kpi-detail-row"><span><?php echo htmlspecialchars($vp['name']); ?></span><span class="kpi-detail-qty"><?php echo (int)$vp['qty']; ?></span></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card">
            <i class="material-icons-outlined kpi-ico">assignment_turned_in</i>
            <div class="kpi-t">Get Order / No Order</div>
            <div class="kpi-v"><span style="color:#0ca30c;"><?php echo (int)$getOrderCount; ?></span> / <span style="color:#d03b3b;"><?php echo (int)$noOrderCount; ?></span></div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="kpi-card" style="--kpi-accent:#f59e0b;">
            <i class="material-icons-outlined kpi-ico">flag</i>
            <div class="kpi-t">Monthly Target</div>
            <?php if ($monthlyTarget <= 0): ?>
                <div class="kpi-v" style="font-size:15px;color:#9ca3af;">Not set</div>
                <div class="kpi-sub">Set from Company &rarr; Update Marketing Staff</div>
            <?php else: ?>
                <div class="kpi-v">&#8377;<?php echo inr_format($targetAchievedAmt, 2); ?> <span style="font-size:13px;color:#9ca3af;">/ &#8377;<?php echo inr_format($targetForPeriod, 2); ?></span></div>
                <div class="kpi-sub">
                    <?php echo number_format($targetPercent, 1); ?>% achieved for this range &middot;
                    Per day: &#8377;<?php echo inr_format($perDayTarget, 2); ?>
                    <br/>Monthly Target: &#8377;<?php echo inr_format($monthlyTarget, 2); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .kpi-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
        padding: 18px 20px; position: relative; overflow: hidden;
        height: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .kpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background: var(--kpi-accent, #667eea); }
    .kpi-card .kpi-ico {
        width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center;
        background: var(--kpi-tint, #eef1fd); color: var(--kpi-accent, #667eea); font-size:16px;
        position:absolute; right:14px; top:14px;
    }
    .kpi-card .kpi-t { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight:600; color: #6b7280; padding-right:38px; }
    .kpi-card .kpi-v { font-size: 22px; font-weight: 700; margin-top: 6px; color: #111827; }
    .kpi-card .kpi-sub { font-size: 11px; color: #6b7280; margin-top: 4px; }
    .kpi-card-clickable { cursor: pointer; }
    .kpi-card-clickable:hover { border-color: var(--kpi-accent, #667eea); }

    /* Desktop: hover on the card reveals the panel as a floating overlay
       (doesn't push other cards' layout). Mobile: no hover, so a tap toggles
       a `.show` class via JS instead (see script below). */
    .kpi-card-wrap { position: relative; }
    .kpi-detail-panel {
        display: none;
        position: absolute; top: 100%; left: 0; right: auto; z-index: 30;
        width: max-content; min-width: 220px; max-width: 320px;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
        margin-top: 6px; padding: 8px 12px; max-height: 220px; overflow-y: auto;
        box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    }
    .kpi-card-wrap:hover .kpi-detail-panel,
    .kpi-detail-panel.show { display: block; }
    .kpi-detail-panel::before {
        content: ''; position: absolute; top: -6px; left: 20px;
        width: 11px; height: 11px; background: #fff; border-left: 1px solid #e5e7eb; border-top: 1px solid #e5e7eb;
        transform: rotate(45deg);
    }
    .kpi-detail-title {
        font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
        color: #9ca3af; padding-bottom: 6px; margin-bottom: 4px; border-bottom: 1px solid #f0f1f2;
    }
    .kpi-detail-row { display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:12.5px; padding:6px 0; border-bottom:1px solid #f6f6f5; color:#374151; }
    .kpi-detail-row:last-child { border-bottom:none; }
    .kpi-detail-qty {
        font-weight: 700; font-size: 11.5px; color: #374151; background: #f0f1f2;
        padding: 2px 9px; border-radius: 6px; min-width: 20px; text-align: center;
    }
    .kpi-detail-empty { font-size:12px; color:#9ca3af; padding:6px 0; }
</style>
<script>
// Touch devices have no :hover, so tapping a KPI card (or a product-qty cell
// in the table below, which reuses the same wrap/panel classes) toggles its
// detail panel open/closed instead. Delegated on document since the table
// rows are rendered further down the page, after this script runs.
document.addEventListener('click', function(e) {
    var card = e.target.closest('.kpi-card-wrap .kpi-card-clickable');
    if (card) {
        var panel = card.parentElement.querySelector('.kpi-detail-panel');
        if (panel) {
            var isOpen = panel.classList.contains('show');
            document.querySelectorAll('.kpi-detail-panel.show').forEach(function(p) { p.classList.remove('show'); });
            if (!isOpen) { panel.classList.add('show'); }
        }
        e.stopPropagation();
        return;
    }
    document.querySelectorAll('.kpi-detail-panel.show').forEach(function(p) { p.classList.remove('show'); });
});
</script>

<?php
//----Continuos Serial Number In Next Page.......................
$num_rec_per_page=30;
if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=1; }; 
 $start_from = ($page-1) * $num_rec_per_page; 
$i= $start_from;
//---------------------------------------------------------------
//echo ++$i; 
?>
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									<div style="background:#fff;overflow:scroll;width:100%;">
                              <table id="datatable1" width="100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
													<th>Shop Name</th>
													<th>Shop Contact Number</th>
													<th>Address</th>
													<th>Date</th>
													<th>Marketing Tool</th>
													<th>Products</th>
													<th>Order Value</th>

													<th>Location</th>
													<th>TP Status</th>
													<th>Edit</th>
                                                </tr>
                                            </thead>

											<tbody>
<?php

foreach ($orderIdsInRange as $orderid) {
$result_product_list = $orderMeta[$orderid];

//shop category
$shop_id=$result_product_list['shop_id'];
$result_shopcatt = $shopMeta[$shop_id] ?? [];

// TP status — what (if anything) happened to this order on the TP side
// after it was assigned (bridged into tp_orders by order_action_get.php).
// Rendered as a small card: a header line (status badge, plus an amount on
// the right only when there's an actual amount to show) and, below it, any
// secondary detail lines (payment breakdown, what changed, View Details).
// Skipping the right-hand amount when there isn't one keeps the header a
// clean single line instead of an empty/awkward split.
// All looked up from the batch maps built above the table — no query here.
$tpRow = $tpOrderMeta[$orderid] ?? null;

$headerAmt = '';
$detailLines = [];

if (!$tpRow) {
    $headerBadge = "<span class='tp-status-pending'>&mdash;</span>";
    $detailLines[] = "<div class='tp-status-pending'>Not assigned to a TP</div>";
} elseif (!empty($tpRow['voided_at'])) {
    // TP voided the order before ever converting it to an invoice — no
    // stock was ever touched for this (nothing to reverse), it's purely
    // "the shop didn't want this product" being recorded back to the DM.
    $headerBadge = "<span class='badge badge-style-bordered badge-danger'>Cancelled (before invoicing)</span>";
    $detailLines[] = "<div class='tp-status-pending'>No stock affected</div>";
    if (!empty($tpRow['void_reason'])) {
        $detailLines[] = "<div class='tp-status-pending'>Reason: " . htmlspecialchars($tpRow['void_reason']) . "</div>";
    }
} elseif (empty($tpRow['invoiced_inv_id'])) {
    $headerBadge = "<span class='badge badge-style-bordered badge-warning'>Pending with TP</span>";
    $detailLines[] = "<div class='tp-status-pending'>Not yet invoiced</div>";
} else {
    $invId = $tpRow['invoiced_inv_id'];
    $invTotal = $invTotals[$invId] ?? 0.0;
    $invStatusVal = $invStatus[$invId] ?? '';
    $hasReceipt = isset($received[$invId]);

    $diffCounts = $diffCountsByInv[$invId] ?? [];
    $diffParts = [];
    if (!empty($diffCounts['added']))       { $diffParts[] = '+' . $diffCounts['added'] . ' added'; }
    if (!empty($diffCounts['removed']))     { $diffParts[] = '-' . $diffCounts['removed'] . ' removed'; }
    if (!empty($diffCounts['qty_changed'])) { $diffParts[] = $diffCounts['qty_changed'] . ' qty changed'; }
    if (!empty($diffParts)) { $detailLines[] = "<div class='tp-status-diff'>" . htmlspecialchars(implode(', ', $diffParts)) . "</div>"; }

    $viewDetailsLink = "<div class='tp-status-link'><a href='order-invoice-status.php?order_id=" . urlencode($orderid) . "' target='_blank'>View Details &rarr;</a></div>";

    if ($invStatusVal === 'cancelled') {
        $headerBadge = "<span class='badge badge-style-bordered badge-danger'>Voided</span>";
        $headerAmt   = "<span class='tp-status-header-amt'>Was billed &#8377;" . inr_format($invTotal, 2) . "</span>";
        $detailLines[] = $viewDetailsLink;
    } elseif ($hasReceipt) {
        // Same payment-status logic as the TP's own shop-manage-invoice.php
        // (Not Paid / Fully Paid / Partially Paid) so the DM sees exactly
        // what the TP sees.
        $totalReceived = $received[$invId] ?? 0.0;
        if ($totalReceived <= 0) {
            $headerBadge = "<span class='badge badge-style-bordered badge-danger'>Not Paid</span>";
        } elseif ($totalReceived >= $invTotal) {
            $headerBadge = "<span class='badge badge-style-bordered badge-success'>Fully Paid</span>";
        } else {
            $headerBadge = "<span class='badge badge-style-bordered badge-warning'>Partially Paid</span>";
            $detailLines[] = "<div class='tp-status-pending'>&#8377;" . inr_format($totalReceived, 2) . " paid, &#8377;" . inr_format($invTotal - $totalReceived, 2) . " pending</div>";
        }
        $headerAmt = "<span class='tp-status-header-amt'>Billed &#8377;" . inr_format($invTotal, 2) . "</span>";
        $detailLines[] = $viewDetailsLink;
    } else {
        $headerBadge = "<span class='badge badge-style-bordered badge-primary'>Invoice in Progress</span>";
        $headerAmt   = "<span class='tp-status-header-amt'>Draft &#8377;" . inr_format($invTotal, 2) . "</span>";
        $detailLines[] = $viewDetailsLink;
    }
}

$headerHtml = $headerAmt !== ''
    ? "<div class='tp-status-header'>{$headerBadge}{$headerAmt}</div>"
    : "<div class='tp-status-header'>{$headerBadge}</div>";
$detailHtml = !empty($detailLines) ? "<div class='tp-status-detail'>" . implode('', $detailLines) . "</div>" : '';

$tpStatusHtml = "<div class='tp-status-box'>{$headerHtml}{$detailHtml}</div>";
?>
                                            
                                               <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=$result_shopcatt['name'];?></td>
					<td><?=$result_shopcatt['mobile_number'];?></td>
					<td><?=ucwords($result_shopcatt["address"]);?></td>
					
					<td><?=date("d/m/Y",strtotime($result_product_list["order_date"]));?></td>
					<td><?=$result_product_list["marketing_tool"];?></td>
					
					
					<!------------------------PRODUCT WISE SALES QTY------------------------------->
				<?php
					$orderProducts = $qtyMap[$orderid] ?? [];
					$orderTotalQty = array_sum($orderProducts);
				?>
				<td>
					<div class="kpi-card-wrap">
						<div class="kpi-card-clickable" style="cursor:pointer; font-weight:700;"><?=(int)$orderTotalQty;?> qty</div>
						<div class="kpi-detail-panel">
							<div class="kpi-detail-title">Products in this order</div>
							<?php if (empty($orderProducts)): ?>
							<div class="kpi-detail-empty">No products.</div>
							<?php else: foreach ($allProducts as $apr):
								$q = $orderProducts[$apr['id']] ?? 0;
								if ($q <= 0) { continue; }
							?>
							<div class="kpi-detail-row"><span><?=htmlspecialchars($apr['productName']);?></span><span class="kpi-detail-qty"><?=(int)$q;?></span></div>
							<?php endforeach; endif; ?>
						</div>
					</div>
				</td>
				<!-------------------------------------------------------------------->

					<td><b>&#8377;<?=inr_format($orderValueMap[$orderid] ?? 0, 2);?></b></td>

					<td>
					<?php if($result_product_list["latitude"]!=NULL && $result_product_list["longitude"]!=NULL){?>
					<a href="https://www.google.com/maps?q=<?=$result_product_list["latitude"];?>,<?=$result_product_list["longitude"];?>" target="_blank" class="btn btn-sm btn-secondary">View Location</a>
					<?php }else{ echo "---"; }?>
					</td>

					<td><?=$tpStatusHtml;?></td>

			<td>
			<a href="edit_order_product.php?orderid=<?php echo $orderid;?>&&actionupdate" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Details">
			<img src="../../assets/images/edit-32.png"/></a>
			</td>
													
	
                                                </tr>
                                           
										<?php }?>
										
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
    </div>

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
	<script src="../../assets/plugins/lightbox/fslightbox.js"></script>
	<script src="../../assets/js/pages/lightbox.js"></script>
</body>

</html>