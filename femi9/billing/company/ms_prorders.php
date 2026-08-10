<?php include("checksession.php"); error_reporting(0);
require_once("include/PermissionCheck.php"); requirePermission('ms');?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Marketing Staff > Product Orders : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">


    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
	<link href="../../assets/css/vlstyle.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <style>
        /* Product qty cell — hover (desktop) / tap (mobile) reveals the
           per-product breakdown, instead of one column per product. */
        .kpi-card-wrap { position: relative; display:inline-block; }
        .kpi-card-clickable { cursor:pointer; font-weight:700; }
        .kpi-detail-panel {
            display: none;
            position: absolute; top: 100%; right: 0; left: auto; z-index: 30;
            width: max-content; min-width: 220px; max-width: 320px;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
            margin-top: 6px; padding: 8px 12px; max-height: 220px; overflow-y: auto;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .kpi-card-wrap:hover .kpi-detail-panel,
        .kpi-detail-panel.show { display: block; }
        .kpi-detail-panel::before {
            content: ''; position: absolute; top: -6px; right: 20px;
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

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
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
                        <?php if (isset($_SESSION['successMessage'])): ?>
                        <div class="row"><div class="col"><div class="alert alert-success"><?=htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']);?></div></div></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['errorMessage'])): ?>
                        <div class="row"><div class="col"><div class="alert alert-danger"><?=htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']);?></div></div></div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col">
                                <div class="page-description">

								<?php
								if($_REQUEST['frdate']!=NULL)
								{
						$from_date=$_REQUEST['frdate'];
						$to_date=$_REQUEST['todate'];
								}
								else
								{
						$to_date=date("Y-m-d");
						$from_date=date("Y-m-d", strtotime("-2 days", strtotime($to_date)));;	
								}
						
						$se_msid=$_REQUEST['se_msid'] ?? '';
						// A manager's row on ms-team-shop-view.php links here with se_msids (their
						// whole subtree, comma-separated) so the totals shown there match what this
						// page reports — a single se_msid only ever covered that one person, silently
						// under-reporting for anyone with a team under them.
						$se_msids = [];
						if (!empty($_REQUEST['se_msids'])) {
						    $se_msids = array_values(array_unique(array_filter(array_map('intval', explode(',', $_REQUEST['se_msids'])))));
						} elseif ($se_msid !== '') {
						    $se_msids = [(int)$se_msid];
						}
						$isTeamMode = count($se_msids) > 1;
						$msIdListSql = implode(',', $se_msids ?: [0]);
						// "Total Get Order" link (view=all) shows every get-order — TP-assigned
						// or not, and that TP's invoice status — instead of just the ones still
						// needing company follow-up (the default/"Orders" link's tp_id IS NULL view).
						$showAllOrders = (($_REQUEST['view'] ?? '') === 'all');
						//
						$select_msDetails12="select * from marketing_staff where id='$se_msid'";
$fetch_msDetails12=mysqli_query($db_conn,$select_msDetails12);
$result_msDetails12=mysqli_fetch_array($fetch_msDetails12);

// Team-mode header — name of the manager whose subtree this is (always
// first in the ids list, see subtreeSumAndIds() in ms-team-shop-view.php).
$teamRootName = null;
if ($isTeamMode) {
    $resRoot = mysqli_query($db_conn, "SELECT ms_name FROM marketing_staff WHERE id=" . (int)$se_msids[0]);
    $rootRow = mysqli_fetch_assoc($resRoot);
    $teamRootName = $rootRow['ms_name'] ?? null;
}

// ── Per-DM / per-team summary cards ─────────────────────────────────────
// Same 6 KPI cards the DM sees on their own manage_order_product.php,
// shown here too once the company narrows the view down to one DM (or a
// whole manager's subtree) — scoped by $se_msids instead of the DM's own
// session id.
if (!empty($se_msids)) {
    $getOrderCount = 0; $noOrderCount = 0;
    $resGN = mysqli_query($db_conn,
        "SELECT new_order, COUNT(DISTINCT order_id) c FROM ms_orders
         WHERE ms_id IN ($msIdListSql) AND order_date BETWEEN '$from_date' AND '$to_date'
         GROUP BY new_order"
    );
    while ($gr = mysqli_fetch_assoc($resGN)) {
        if ($gr['new_order'] === 'yes') { $getOrderCount = (int)$gr['c']; }
        else { $noOrderCount = (int)$gr['c']; }
    }

    // Of the "get order" total above, how many already have a TP handling
    // them? Those don't show in the table below (see $whereExtra further
    // down) — they show on that TP's own Manage Field Orders page instead —
    // so this KPI is what explains the gap between this number and the
    // row count in the table.
    $assignedToTpCount = 0;
    $resAssigned = mysqli_query($db_conn,
        "SELECT COUNT(DISTINCT order_id) c FROM ms_orders
         WHERE ms_id IN ($msIdListSql) AND new_order='yes' AND tp_id IS NOT NULL
               AND order_date BETWEEN '$from_date' AND '$to_date'"
    );
    $arow = mysqli_fetch_assoc($resAssigned);
    $assignedToTpCount = (int)($arow['c'] ?? 0);
    $unassignedGetOrderCount = max(0, $getOrderCount - $assignedToTpCount);

    $productNeeded = [];
    $totalQtyNeeded = 0;
    $totalGetOrderValue = 0.0;
    $resPQ = mysqli_query($db_conn,
        "SELECT mo.pr_id, p.productName, p.outlet_price, SUM(mo.qty) totalqty,
                SUM(mo.qty * p.outlet_price * (1 - mo.discount_percentage/100)) totalvalue
         FROM ms_orders mo LEFT JOIN products p ON p.id=mo.pr_id
         WHERE mo.ms_id IN ($msIdListSql) AND mo.new_order='yes'
               AND mo.order_date BETWEEN '$from_date' AND '$to_date'
         GROUP BY mo.pr_id, p.productName, p.outlet_price ORDER BY totalqty DESC"
    );
    while ($pr = mysqli_fetch_assoc($resPQ)) {
        $qty = (int)$pr['totalqty'];
        $value = (float)($pr['totalvalue'] ?? 0);
        $productNeeded[] = ['name' => $pr['productName'] ?: '-', 'qty' => $qty, 'value' => $value];
        $totalQtyNeeded += $qty;
        $totalGetOrderValue += $value;
    }

    $invoiceIds = [];
    $resInv = mysqli_query($db_conn,
        "SELECT DISTINCT t.invoiced_inv_id
         FROM ms_orders o JOIN tp_orders t ON t.order_id=o.order_id
         WHERE o.ms_id IN ($msIdListSql) AND o.new_order='yes'
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

        $resVal = mysqli_query($db_conn, "SELECT inv_id, total, status FROM user_invoice WHERE inv_id IN ($idList)");
        $invTotals = []; $invStatus = [];
        while ($vr = mysqli_fetch_assoc($resVal)) {
            $invTotals[$vr['inv_id']] = (float)$vr['total'];
            $invStatus[$vr['inv_id']] = $vr['status'];
            $totalInvoiceValue += (float)$vr['total'];
        }

        $resRec = mysqli_query($db_conn, "SELECT inv_id, SUM(received) received FROM receipt WHERE inv_id IN ($idList) GROUP BY inv_id");
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

    function msProrders_getProductBreakdown($db_conn, array $invIds): array {
        if (empty($invIds)) { return []; }
        $idList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $invIds)) . "'";
        $res = mysqli_query($db_conn,
            "SELECT uii.pr_id, p.productName, SUM(uii.qty) totalqty
             FROM user_invoice_items uii LEFT JOIN products p ON p.id=uii.pr_id
             WHERE uii.inv_id IN ($idList)
             GROUP BY uii.pr_id, p.productName ORDER BY totalqty DESC"
        );
        $out = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $out[] = ['name' => $row['productName'] ?: '-', 'qty' => (int)$row['totalqty']];
        }
        return $out;
    }

    $partiallyPaidProducts = msProrders_getProductBreakdown($db_conn, $partiallyPaidInvIds);
    $voidedProducts = msProrders_getProductBreakdown($db_conn, $voidedInvIds);

    // ── Monthly Target ───────────────────────────────────────────────────
    // Same prorated-target card the DM sees on their own manage_order_product.php.
    $_chkTgtCol = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'monthly_target_amount'");
    if ($_chkTgtCol && $_chkTgtCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN monthly_target_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER manager_id");
    }
    // Team mode: sum every team member's own monthly target instead of one
    // person's — mirrors summing their get-order/invoice numbers above.
    $resTgt = mysqli_query($db_conn, "SELECT COALESCE(SUM(monthly_target_amount),0) t FROM marketing_staff WHERE id IN ($msIdListSql)");
    $tgtRow = mysqli_fetch_assoc($resTgt);
    $monthlyTarget = (float)($tgtRow['t'] ?? 0);
    $daysInMonth  = (int)date('t', strtotime($from_date));
    $perDayTarget = $daysInMonth > 0 ? $monthlyTarget / $daysInMonth : 0.0;
    $daysInRange  = (int)floor((strtotime($to_date) - strtotime($from_date)) / 86400) + 1;
    if ($daysInRange < 1) { $daysInRange = 1; }
    $targetForPeriod = $perDayTarget * $daysInRange;
    $targetAchievedAmt = $totalInvoiceValue;
    $targetPercent = $targetForPeriod > 0 ? min(100, ($targetAchievedAmt / $targetForPeriod) * 100) : 0;
}
						?>
						
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Marketing Staff > <?php echo $showAllOrders ? 'Total Get Orders (all, incl. TP-assigned)' : 'Product Orders'; ?><?php if ($isTeamMode): ?> &mdash; <?php echo htmlspecialchars($teamRootName ?? 'Team'); ?>'s team (<?php echo count($se_msids); ?>)<?php endif; ?></td>
									<td>
									<a href="ms_orders_pdf?frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&se_msid=<?=$se_msid;?>&&se_msids=<?=htmlspecialchars(implode(',', $se_msids));?>" title="Export" target="_blank"><img src="32-pdf.png"></a>
									</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						
						
						<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">View</label>
<select id="viewModeSelect" class="form-control" onchange="onViewModeChange(this.value)">
    <option value="company" <?=($se_msid==NULL)?'selected':''?>>All DMs (Company)</option>
    <option value="dm" <?=($se_msid!=NULL)?'selected':''?>>Specific DM</option>
</select>
</div>
<?php if ($isTeamMode): ?>
<div id="searchleftcont" class="ms-picker-wrap">
<label class="form-label">Team</label>
<div class="form-control" style="background:#f8fafc;">Viewing <b><?php echo htmlspecialchars($teamRootName ?? 'Team'); ?></b>'s team &mdash; <?php echo count($se_msids); ?> people. Re-searching below switches to a single DM.</div>
</div>
<?php else: ?>
<div id="searchleftcont" class="ms-picker-wrap" style="<?=($se_msid==NULL)?'display:none;':''?>">
<label class="form-label">Marketing Staff</label>
<select name="se_msid" id="msPickerSelect" class="form-control">
<?php if($se_msid==NULL){?>
<option value="" hidden="">Select</option>
<?php }else{?>
<option value="<?=$se_msid;?>" hidden=""><?=strtoupper($result_msDetails12['ms_name']);?>, <?=$result_msDetails12['ms_mobile'];?></option>
<?php }?>
<?php $select_msDetailsOPT="select * from marketing_staff order by ms_name asc";
$fetch_msDetailsOPT=mysqli_query($db_conn,$select_msDetailsOPT);
while($result_msDetailsOPT=mysqli_fetch_array($fetch_msDetailsOPT))
{
?>
<option value="<?=$result_msDetailsOPT['id'];?>"><?=strtoupper($result_msDetailsOPT['ms_name']);?>, <?=$result_msDetailsOPT['ms_mobile'];?></option>
<?php }?>
</select>
</div>
<?php endif; ?>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>
<div id="searchbuttoncont">
<button type="button" onclick="Javascript:window.location='ms_prorders';" style="margin-left:10px;" class="btn btn-primary"><i class="material-icons"></i>Reset</button>
</div>

							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>

							<?php if (!empty($se_msids)): ?>
							<div class="row mb-3">
							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card-wrap">
							            <div class="mskpi-card mskpi-card-clickable">
							                <i class="material-icons-outlined mskpi-ico">receipt_long</i>
							                <div class="mskpi-t">Invoices Placed</div>
							                <div class="mskpi-v"><?php echo (int)$invoiceCount; ?></div>
							                <div class="mskpi-sub"><?php echo (int)$totalQtyNeeded; ?> products needed &middot; hover / tap for details</div>
							            </div>
							            <div class="mskpi-detail-panel">
							                <div class="mskpi-detail-title">Products needed</div>
							                <?php if (empty($productNeeded)): ?>
							                    <div class="mskpi-detail-empty">No products in this range.</div>
							                <?php else: foreach ($productNeeded as $pn): ?>
							                    <div class="mskpi-detail-row"><span><?php echo htmlspecialchars($pn['name']); ?></span><span class="mskpi-detail-qty"><?php echo (int)$pn['qty']; ?></span></div>
							                <?php endforeach; endif; ?>
							            </div>
							        </div>
							    </div>

							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card-wrap">
							        	<div class="mskpi-card mskpi-card-clickable">
							        		<i class="material-icons-outlined mskpi-ico">shopping_cart</i>
							        		<div class="mskpi-t">Get Order Value</div>
							        		<div class="mskpi-v">&#8377;<?php echo inr_format($totalGetOrderValue, 2); ?></div>
							        		<div class="mskpi-sub">Estimated &middot; hover / tap for details</div>
							        	</div>
							        	<div class="mskpi-detail-panel">
							        		<div class="mskpi-detail-title">Estimated value by product</div>
							        		<?php if (empty($productNeeded)): ?>
							        			<div class="mskpi-detail-empty">No products in this range.</div>
							        		<?php else: foreach ($productNeeded as $pn): ?>
							        			<div class="mskpi-detail-row"><span><?php echo htmlspecialchars($pn['name']); ?></span><span class="mskpi-detail-qty">&#8377;<?php echo inr_format($pn['value'], 2); ?></span></div>
							        		<?php endforeach; endif; ?>
							        	</div>
							        </div>
							    </div>

							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card">
							            <i class="material-icons-outlined mskpi-ico">payments</i>
							            <div class="mskpi-t">Total Invoice Value</div>
							            <div class="mskpi-v">&#8377;<?php echo inr_format($totalInvoiceValue, 2); ?></div>
							        </div>
							    </div>

							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card">
							            <i class="material-icons-outlined mskpi-ico">check_circle</i>
							            <div class="mskpi-t">Fully Paid</div>
							            <div class="mskpi-v"><?php echo (int)$fullyPaidCount; ?></div>
							            <div class="mskpi-sub">&#8377;<?php echo inr_format($fullyPaidValue, 2); ?></div>
							        </div>
							    </div>

							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card-wrap">
							            <div class="mskpi-card mskpi-card-clickable">
							                <i class="material-icons-outlined mskpi-ico">hourglass_bottom</i>
							                <div class="mskpi-t">Partially Paid</div>
							                <div class="mskpi-v"><?php echo (int)$partiallyPaidCount; ?></div>
							                <div class="mskpi-sub">&#8377;<?php echo inr_format($partiallyPaidReceived, 2); ?> received &middot; hover / tap for details</div>
							            </div>
							            <div class="mskpi-detail-panel">
							                <div class="mskpi-detail-title">Products in these invoices</div>
							                <?php if (empty($partiallyPaidProducts)): ?>
							                    <div class="mskpi-detail-empty">No partially paid invoices in this range.</div>
							                <?php else: foreach ($partiallyPaidProducts as $pp): ?>
							                    <div class="mskpi-detail-row"><span><?php echo htmlspecialchars($pp['name']); ?></span><span class="mskpi-detail-qty"><?php echo (int)$pp['qty']; ?></span></div>
							                <?php endforeach; endif; ?>
							            </div>
							        </div>
							    </div>

							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card-wrap">
							            <div class="mskpi-card mskpi-card-clickable">
							                <i class="material-icons-outlined mskpi-ico">cancel</i>
							                <div class="mskpi-t">Voided</div>
							                <div class="mskpi-v"><?php echo (int)$voidedCount; ?></div>
							                <div class="mskpi-sub">&#8377;<?php echo inr_format($voidedValue, 2); ?> voided &middot; hover / tap for details</div>
							            </div>
							            <div class="mskpi-detail-panel">
							                <div class="mskpi-detail-title">Products in voided invoices</div>
							                <?php if (empty($voidedProducts)): ?>
							                    <div class="mskpi-detail-empty">No voided invoices in this range.</div>
							                <?php else: foreach ($voidedProducts as $vp): ?>
							                    <div class="mskpi-detail-row"><span><?php echo htmlspecialchars($vp['name']); ?></span><span class="mskpi-detail-qty"><?php echo (int)$vp['qty']; ?></span></div>
							                <?php endforeach; endif; ?>
							            </div>
							        </div>
							    </div>

							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card">
							            <i class="material-icons-outlined mskpi-ico">assignment_turned_in</i>
							            <div class="mskpi-t">Get Order / No Order</div>
							            <div class="mskpi-v"><span style="color:#0ca30c;"><?php echo (int)$getOrderCount; ?></span> / <span style="color:#d03b3b;"><?php echo (int)$noOrderCount; ?></span></div>
							            <div class="mskpi-sub"><?php echo $showAllOrders ? ($getOrderCount . ' in table below (all)') : ($unassignedGetOrderCount . ' in table below'); ?> &middot; <?php echo $assignedToTpCount; ?> picked up by a TP</div>
							        </div>
							    </div>

							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card" style="--kpi-accent:#f59e0b;">
							            <i class="material-icons-outlined mskpi-ico">flag</i>
							            <div class="mskpi-t">Monthly Target</div>
							            <?php if ($monthlyTarget <= 0): ?>
							                <div class="mskpi-v" style="font-size:15px;color:#9ca3af;">Not set</div>
							                <div class="mskpi-sub">Set from Update Marketing Staff</div>
							            <?php else: ?>
							                <div class="mskpi-v">&#8377;<?php echo inr_format($targetAchievedAmt, 2); ?> <span style="font-size:13px;color:#9ca3af;">/ &#8377;<?php echo inr_format($targetForPeriod, 2); ?></span></div>
							                <div class="mskpi-sub">
							                    <?php echo number_format($targetPercent, 1); ?>% achieved for this range &middot;
							                    Per day: &#8377;<?php echo inr_format($perDayTarget, 2); ?>
							                    <br/>Monthly Target: &#8377;<?php echo inr_format($monthlyTarget, 2); ?>
							                </div>
							            <?php endif; ?>
							        </div>
							    </div>
							</div>
							<style>
							    .mskpi-card {
							        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
							        padding: 18px 20px; position: relative; overflow: hidden;
							        height: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
							    }
							    .mskpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background: #667eea; }
							    .mskpi-card .mskpi-ico {
							        width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center;
							        background: #eef1fd; color: #667eea; font-size:16px;
							        position:absolute; right:14px; top:14px;
							    }
							    .mskpi-card .mskpi-t { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight:600; color: #6b7280; padding-right:38px; }
							    .mskpi-card .mskpi-v { font-size: 22px; font-weight: 700; margin-top: 6px; color: #111827; }
							    .mskpi-card .mskpi-sub { font-size: 11px; color: #6b7280; margin-top: 4px; }
							    .mskpi-card-clickable { cursor: pointer; }
							    .mskpi-card-clickable:hover { border-color: #667eea; }
							    .mskpi-card-wrap { position: relative; }
							    .mskpi-detail-panel {
							        display: none;
							        position: absolute; top: 100%; left: 0; right: auto; z-index: 30;
							        width: max-content; min-width: 220px; max-width: 320px;
							        background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
							        margin-top: 6px; padding: 8px 12px; max-height: 220px; overflow-y: auto;
							        box-shadow: 0 6px 16px rgba(0,0,0,0.12);
							    }
							    .mskpi-card-wrap:hover .mskpi-detail-panel,
							    .mskpi-detail-panel.show { display: block; }
							    .mskpi-detail-panel::before {
							        content: ''; position: absolute; top: -6px; left: 20px;
							        width: 11px; height: 11px; background: #fff; border-left: 1px solid #e5e7eb; border-top: 1px solid #e5e7eb;
							        transform: rotate(45deg);
							    }
							    .mskpi-detail-title {
							        font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
							        color: #9ca3af; padding-bottom: 6px; margin-bottom: 4px; border-bottom: 1px solid #f0f1f2;
							    }
							    .mskpi-detail-row { display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:12.5px; padding:6px 0; border-bottom:1px solid #f6f6f5; color:#374151; }
							    .mskpi-detail-row:last-child { border-bottom:none; }
							    .mskpi-detail-qty {
							        font-weight: 700; font-size: 11.5px; color: #374151; background: #f0f1f2;
							        padding: 2px 9px; border-radius: 6px; min-width: 20px; text-align: center;
							    }
							    .mskpi-detail-empty { font-size:12px; color:#9ca3af; padding:6px 0; }
							</style>
							<script>
							document.addEventListener('click', function(e) {
							    var card = e.target.closest('.mskpi-card-wrap .mskpi-card-clickable');
							    if (card) {
							        var panel = card.parentElement.querySelector('.mskpi-detail-panel');
							        if (panel) {
							            var isOpen = panel.classList.contains('show');
							            document.querySelectorAll('.mskpi-detail-panel.show').forEach(function(p) { p.classList.remove('show'); });
							            if (!isOpen) { panel.classList.add('show'); }
							        }
							        e.stopPropagation();
							        return;
							    }
							    document.querySelectorAll('.mskpi-detail-panel.show').forEach(function(p) { p.classList.remove('show'); });
							});
							</script>
							<?php endif; ?>

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
                              <div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
													<th>Marketing Staff</th>
													<th>Shop Name</th>
													<th>Shop Contact Number</th>
													<th>Address</th>
													<th>Date</th>
													<th>Product Qty</th>
													<?php if ($showAllOrders): ?>
													<th>Assigned TP</th>
													<th>TP Invoice Status</th>
													<?php endif; ?>
													<th>Invoice</th>
                                                </tr>
                                            </thead>

											<tbody>
<?php
// Default view: only orders with no TP assigned — this report exists so the
// company can pick up DM orders that don't have anyone else already handling
// them (e.g. no TP covers that area yet). Orders already assigned to a TP
// show up in that TP's own field-order pipeline instead, not here.
// "Total Get Order" view (view=all): every get-order regardless of TP
// assignment, with the assigned TP + their invoice status shown per row.
//
// Everything below is batched once instead of the old per-row (and
// per-row-per-product) queries, which made this page very slow to load.
$whereExtra = $showAllOrders ? "" : " and tp_id IS NULL";
if ($from_date != NULL) { $whereExtra = " and order_date between '$from_date' and '$to_date'" . $whereExtra; }
if (!empty($se_msids))  { $whereExtra .= " and ms_id IN ($msIdListSql)"; }

$allProducts = [];
$resAllProd = mysqli_query($db_conn, "SELECT id, productName FROM products ORDER BY id ASC");
while ($apr = mysqli_fetch_assoc($resAllProd)) { $allProducts[] = $apr; }

$orderIdsInRange = [];
$orderMeta = [];
$qtyMap = [];
$shopIds = [];
$msIds = [];
$resOrders = mysqli_query($db_conn, "SELECT * FROM ms_orders WHERE new_order='yes'$whereExtra ORDER BY order_date DESC, order_id DESC");
while ($orow = mysqli_fetch_assoc($resOrders)) {
    $oid = $orow['order_id'];
    if (!isset($orderMeta[$oid])) {
        $orderMeta[$oid] = $orow;
        $orderIdsInRange[] = $oid;
        if (!empty($orow['shop_id'])) { $shopIds[$orow['shop_id']] = true; }
        if (!empty($orow['ms_id']))   { $msIds[$orow['ms_id']] = true; }
    }
    $qtyMap[$oid][$orow['pr_id']] = (int)$orow['qty'];
}

$shopMeta = [];
if (!empty($shopIds)) {
    $shopIdList = implode(',', array_map('intval', array_keys($shopIds)));
    $resShops = mysqli_query($db_conn, "SELECT * FROM ms_shop WHERE id IN ($shopIdList)");
    while ($srow = mysqli_fetch_assoc($resShops)) { $shopMeta[$srow['id']] = $srow; }
}

$msMeta = [];
if (!empty($msIds)) {
    $msIdList = implode(',', array_map('intval', array_keys($msIds)));
    $resMs = mysqli_query($db_conn, "SELECT * FROM marketing_staff WHERE id IN ($msIdList)");
    while ($mrow = mysqli_fetch_assoc($resMs)) { $msMeta[$mrow['id']] = $mrow; }
}

// Has this order already been picked up and invoiced directly by the company?
$invoicedMap = [];
if (!empty($orderIdsInRange)) {
    $oidList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $orderIdsInRange)) . "'";
    $resInvoiced = mysqli_query($db_conn, "SELECT source_ms_order_id FROM user_invoice WHERE source_ms_order_id IN ($oidList)");
    while ($ir = mysqli_fetch_assoc($resInvoiced)) { $invoicedMap[$ir['source_ms_order_id']] = true; }
}

// "Total Get Order" view only — which TP (if any) picked up each order, and
// whether that TP has actually submitted the invoice yet. A tp_orders row
// only gets a `receipt` once "Submit Invoice" is clicked (see
// shop-invoice-submit.php) — same rule territory-partner/manage-orders.php
// uses to distinguish "Continue Invoice" (still mid-way) from "Completed".
$tpNameMap = [];
$tpInvoiceStatusMap = [];
if ($showAllOrders && !empty($orderIdsInRange)) {
    $tpIds = [];
    foreach ($orderIdsInRange as $oid) {
        $tpid = $orderMeta[$oid]['tp_id'] ?? null;
        if (!empty($tpid)) { $tpIds[$tpid] = true; }
    }
    if (!empty($tpIds)) {
        $tpIdList = implode(',', array_map('intval', array_keys($tpIds)));
        $resTp = mysqli_query($db_conn, "SELECT id, name FROM territory_partners WHERE id IN ($tpIdList)");
        while ($trow = mysqli_fetch_assoc($resTp)) { $tpNameMap[$trow['id']] = $trow['name']; }
    }

    $oidList2 = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $orderIdsInRange)) . "'";
    $resTpOrders = mysqli_query($db_conn, "SELECT order_id, invoiced_inv_id FROM tp_orders WHERE order_id IN ($oidList2) AND invoiced_inv_id IS NOT NULL AND invoiced_inv_id <> ''");
    $tpInvIdByOrder = [];
    $tpInvIds = [];
    while ($tor = mysqli_fetch_assoc($resTpOrders)) {
        $tpInvIdByOrder[$tor['order_id']] = $tor['invoiced_inv_id'];
        $tpInvIds[$tor['invoiced_inv_id']] = true;
    }
    $completedTpInvIds = [];
    if (!empty($tpInvIds)) {
        $tpInvIdList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), array_keys($tpInvIds))) . "'";
        $resReceipt = mysqli_query($db_conn, "SELECT DISTINCT inv_id FROM receipt WHERE inv_id IN ($tpInvIdList)");
        while ($rr = mysqli_fetch_assoc($resReceipt)) { $completedTpInvIds[$rr['inv_id']] = true; }
    }
    foreach ($orderIdsInRange as $oid) {
        $tpid = $orderMeta[$oid]['tp_id'] ?? null;
        if (empty($tpid)) { $tpInvoiceStatusMap[$oid] = null; continue; }
        if (!isset($tpInvIdByOrder[$oid])) { $tpInvoiceStatusMap[$oid] = 'not_invoiced'; continue; }
        $tpInvoiceStatusMap[$oid] = isset($completedTpInvIds[$tpInvIdByOrder[$oid]]) ? 'completed' : 'pending';
    }
}

foreach ($orderIdsInRange as $orderid) {
$result_product_list = $orderMeta[$orderid];

$shop_id = $result_product_list['shop_id'];
$result_shopcatt = $shopMeta[$shop_id] ?? [];

$ms_id = $result_product_list['ms_id'];
$result_msDetails = $msMeta[$ms_id] ?? [];

$orderProducts = $qtyMap[$orderid] ?? [];
$orderTotalQty = array_sum($orderProducts);
?>

                                               <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=htmlspecialchars($result_msDetails['ms_name'] ?? '');?><br/>
					<?=htmlspecialchars($result_msDetails['ms_mobile'] ?? '');?>
					</td>
					<td><?=htmlspecialchars($result_shopcatt['name'] ?? '');?></td>
					<td><?=htmlspecialchars($result_shopcatt['mobile_number'] ?? '');?></td>
					<td><?=ucwords($result_shopcatt["address"] ?? '');?></td>
					<td><?=date("d/m/Y",strtotime($result_product_list["order_date"]));?></td>
					<td>
						<div class="kpi-card-wrap">
							<div class="kpi-card-clickable"><?=(int)$orderTotalQty;?> qty</div>
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
					<?php if ($showAllOrders):
						$tpid = $result_product_list['tp_id'] ?? null;
						$tpStatus = $tpInvoiceStatusMap[$orderid] ?? null;
					?>
					<td><?php echo empty($tpid) ? '<span class="text-muted">&mdash; not picked up &mdash;</span>' : htmlspecialchars($tpNameMap[$tpid] ?? ('TP #' . $tpid)); ?></td>
					<td>
						<?php if (empty($tpid)): ?>
						<span class="text-muted">&mdash;</span>
						<?php elseif ($tpStatus === 'completed'): ?>
						<span class="badge badge-style-bordered badge-success">Completed</span>
						<?php elseif ($tpStatus === 'pending'): ?>
						<span class="badge badge-style-bordered badge-warning">Invoice Pending</span>
						<?php else: ?>
						<span class="badge badge-style-bordered badge-secondary">Not Invoiced Yet</span>
						<?php endif; ?>
					</td>
					<?php endif; ?>
					<td>
						<?php if (isset($invoicedMap[$orderid])): ?>
						<a href="ms-order-invoice-add.php?order_id=<?=urlencode($orderid)?>" class="btn btn-sm btn-outline-secondary">View Invoice</a>
						<?php else: ?>
						<a href="ms-order-invoice-add.php?order_id=<?=urlencode($orderid)?>" class="btn btn-sm btn-primary"><i class="material-icons" style="font-size:14px;vertical-align:middle;">receipt_long</i> Invoice</a>
						<?php endif; ?>
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
    <script>
    function onViewModeChange(mode) {
        var wrap = document.querySelector('.ms-picker-wrap');
        var sel  = document.getElementById('msPickerSelect');
        if (mode === 'company') {
            if (wrap) { wrap.style.display = 'none'; }
            if (sel)  { sel.value = ''; }
        } else {
            if (wrap) { wrap.style.display = ''; }
        }
    }

    // Touch devices have no :hover, so tapping a product-qty cell toggles
    // its detail panel open/closed instead. Tapping elsewhere closes it.
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
</body>

</html>