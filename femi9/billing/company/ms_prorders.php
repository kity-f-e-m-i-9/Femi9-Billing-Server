<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); error_reporting(0);
require_once("include/PermissionCheck.php"); requirePermission('ms');

// Single source of truth for the 3-state Pending / In Progress / Completed
// rule used by the summary cards, the row badge, and (as a SQL mirror —
// can't share PHP code with a WHERE clause) the status filter further down.
// Previously re-derived independently in all three places; keeping this one
// function is what the summary cards and the row badge now both call, so a
// future rule change only needs to happen here for those two. 2026-11-21,
// code-review finding (the SQL filter at $whereExtra below must still be
// kept in sync by hand — flagged there with a comment pointing back here).
function msProrderStatus(bool $hasInvoice, bool $isCpFulfilled, bool $hasReceipt): string {
    if (!$hasInvoice) { return 'pending'; }
    if ($isCpFulfilled || $hasReceipt) { return 'completed'; }
    return 'in_progress';
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
            <?php include((($Login_user_TYPEvl ?? '') === 'salesbdm') ? '../salesbdm/logo.php' : 'logo.php');?>
            <?php include((($Login_user_TYPEvl ?? '') === 'salesbdm') ? '../salesbdm/femi_menu.php' : 'femi_menu.php');?>
        </div>
		
        <div class="app-container">
            
          <?php include((($Login_user_TYPEvl ?? '') === 'salesbdm') ? '../salesbdm/app-header.php' : 'app-header.php');?>
			
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <?php if (isset($_SESSION['successMessage'])): $msSuccessMsg = $_SESSION['successMessage']; unset($_SESSION['successMessage']); ?>
                        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1/dist/sweetalert2.min.css">
                        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1"></script>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                title: 'Success',
                                html: '<div style="font-size:16px;color:#374151;"><?=addslashes(htmlspecialchars($msSuccessMsg))?></div>',
                                icon: 'success',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#16a34a',
                                customClass: { popup: 'ms-notify-popup' }
                            });
                        });
                        </script>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['errorMessage'])): $msErrorMsg = $_SESSION['errorMessage']; unset($_SESSION['errorMessage']); ?>
                        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1/dist/sweetalert2.min.css">
                        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1"></script>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                title: 'Error',
                                html: '<div style="font-size:16px;color:#374151;"><?=addslashes(htmlspecialchars($msErrorMsg))?></div>',
                                icon: 'error',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#dc2626',
                                customClass: { popup: 'ms-notify-popup' }
                            });
                        });
                        </script>
                        <?php endif; ?>
                        <style>
                        .ms-notify-popup { border-radius: 20px !important; padding: 28px !important; }
                        </style>
                        <div class="row">
                            <div class="col">
                                <div class="page-description">

								<?php
								if($_REQUEST['frdate']!=NULL)
								{
						$from_date=(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_REQUEST['frdate'])) ? (string)($_REQUEST['frdate']) : '');
						$to_date=(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_REQUEST['todate'])) ? (string)($_REQUEST['todate']) : '');
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
						// A Sales BDM session may only view orders for marketing staff whose own
						// assigned district falls inside their own assigned districts — se_msid(s)
						// arrives as a tamperable GET/POST param, so re-check here even though
						// bdm-ms-shop-view.php only ever links to staff already in scope.
						if (($Login_user_TYPEvl ?? '') === 'salesbdm') {
						    require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
						    require_once __DIR__ . '/../marketing/include/AssignedLocations.php';
						    $_bdmDistrictsMs = array_map(fn($n) => mb_strtolower(trim($n)), getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID));
						    $se_msids = array_values(array_filter($se_msids, function ($msId) use ($db_conn, $_bdmDistrictsMs) {
						        foreach (getMsAssignedDistricts($db_conn, $msId) as $d) {
						            if (in_array(mb_strtolower(trim($d['name'])), $_bdmDistrictsMs, true)) { return true; }
						        }
						        return false;
						    }));
						    $se_msid = $se_msids[0] ?? '';
						}
						$isTeamMode = count($se_msids) > 1;
						$msIdListSql = implode(',', $se_msids ?: [0]);
						// "Total Get Order" link (view=all) shows every get-order — TP-assigned
						// or not, and that TP's invoice status — instead of just the ones still
						// needing company follow-up (the default/"Orders" link's tp_id IS NULL view).
						$showAllOrders = (($_REQUEST['view'] ?? '') === 'all');
						// Status filter -- "Completed" = already invoiced (a user_invoice row
						// exists for this order), "Pending" = not yet. Same binary signal the
						// Invoice column's own "Invoice"/"View Invoice" button already shows
						// per row, just surfaced as its own filterable column/dropdown too.
						$filterStatus = $_REQUEST['status'] ?? 'all';
						if (!in_array($filterStatus, ['all', 'completed', 'in_progress', 'pending'], true)) { $filterStatus = 'all'; }
						//
						$select_msDetails12="select * from marketing_staff where id=?";
$fetch_msDetails12=db_q($db_conn,$select_msDetails12,[$se_msid]);
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
         WHERE ms_id IN ($msIdListSql) AND order_date BETWEEN '".db_esc($from_date)."' AND '".db_esc($to_date)."'
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
               AND order_date BETWEEN '".db_esc($from_date)."' AND '".db_esc($to_date)."'"
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
               AND mo.order_date BETWEEN '".db_esc($from_date)."' AND '".db_esc($to_date)."'
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
               AND o.order_date BETWEEN '".db_esc($from_date)."' AND '".db_esc($to_date)."'
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

// ── Pending / In Progress / Completed amount cards, CP vs Company ──────────
// Same 3-state rule as the Status column/filter further down (no invoice =
// Pending; Company-fulfilled + not yet receipted = In Progress; CP-fulfilled
// or receipted = Completed), but as a summed ₹ amount instead of a per-row
// badge, split by who it's going through. In Progress is always 100%
// Company — a CP-fulfilled invoice deducts the CP's own stock immediately
// at creation and is Completed from the moment it exists, so it never
// passes through this bucket. Pending has no invoice yet, so its amount is
// the same product-price estimate used elsewhere on this page, split by the
// DM's own assigned_cp_id choice instead of actual fulfillment (unknown
// until invoiced). Deliberately NOT nested inside the `if (!empty($se_msids))`
// block above (unlike the older KPI cards) — the "All DMs (Company)" view
// (View dropdown's default) has an empty $se_msids and skips that whole
// block, which would otherwise make these 3 cards vanish in the one view
// most staff actually use day to day. 2026-11-17, user request + follow-up
// bug report ("entha page la vechu iruka" — cards weren't showing here).
$whereExtraForCards = $showAllOrders ? "" : " AND tp_id IS NULL";
$msIdFilterForCards = !empty($se_msids) ? " AND ms_id IN ($msIdListSql)" : "";
$resCardOrders = mysqli_query($db_conn, "
    SELECT DISTINCT order_id, assigned_cp_id FROM ms_orders
    WHERE new_order='yes'
      AND order_date BETWEEN '".db_esc($from_date)."' AND '".db_esc($to_date)."'
      $msIdFilterForCards
      $whereExtraForCards
");
$cardOrderIds = []; $cardOrderCpMap = [];
while ($cor = mysqli_fetch_assoc($resCardOrders)) {
    $cardOrderIds[] = $cor['order_id'];
    $cardOrderCpMap[$cor['order_id']] = (int)($cor['assigned_cp_id'] ?? 0);
}

$pendingAmtCp = 0.0; $pendingAmtCompany = 0.0;
$inProgressAmtCompany = 0.0;
$completedAmtCp = 0.0; $completedAmtCompany = 0.0;

if (!empty($cardOrderIds)) {
    $cardOidList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $cardOrderIds)) . "'";

    $cardInvoicedOrderIds = [];
    $resCardInv = mysqli_query($db_conn, "
        SELECT ui.source_ms_order_id, ui.total, ui.fulfilled_by_cp_id,
               (SELECT COUNT(*) FROM receipt r WHERE r.receiptid = ui.inv_id) has_receipt
        FROM user_invoice ui WHERE ui.source_ms_order_id IN ($cardOidList)
    ");
    while ($cir = mysqli_fetch_assoc($resCardInv)) {
        $cardInvoicedOrderIds[$cir['source_ms_order_id']] = true;
        $isCp  = !empty($cir['fulfilled_by_cp_id']);
        $status = msProrderStatus(true, $isCp, (int)$cir['has_receipt'] > 0);
        $amt = (float)$cir['total'];
        if ($status === 'completed') {
            if ($isCp) { $completedAmtCp += $amt; } else { $completedAmtCompany += $amt; }
        } else {
            $inProgressAmtCompany += $amt;
        }
    }

    $pendingOrderIds = array_diff($cardOrderIds, array_keys($cardInvoicedOrderIds));
    if (!empty($pendingOrderIds)) {
        $pendingOidList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $pendingOrderIds)) . "'";
        $resCardPend = mysqli_query($db_conn, "
            SELECT mo.order_id, SUM(mo.qty * p.outlet_price * (1 - mo.discount_percentage/100)) val
            FROM ms_orders mo LEFT JOIN products p ON p.id = mo.pr_id
            WHERE mo.order_id IN ($pendingOidList)
            GROUP BY mo.order_id
        ");
        while ($pr2 = mysqli_fetch_assoc($resCardPend)) {
            $val = (float)($pr2['val'] ?? 0);
            if (($cardOrderCpMap[$pr2['order_id']] ?? 0) > 0) { $pendingAmtCp += $val; } else { $pendingAmtCompany += $val; }
        }
    }
}
						?>

                                    <h1>
									<table class="headertble">
									<tr>
									<td>Marketing Staff > <?php echo $showAllOrders ? 'Total Get Orders (all, incl. TP-assigned)' : 'Product Orders'; ?><?php if ($isTeamMode): ?> &mdash; <?php echo htmlspecialchars($teamRootName ?? 'Team'); ?>'s team (<?php echo count($se_msids); ?>)<?php endif; ?></td>
									<td>
									<a href="ms_orders_pdf?frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&se_msid=<?=htmlspecialchars((string)($se_msid), ENT_QUOTES, 'UTF-8', false);?>&&se_msids=<?=htmlspecialchars(implode(',', $se_msids));?>" title="Export" target="_blank"><img src="32-pdf.png"></a>
									</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						
						
						<form method="post" enctype="multipart/form-data" action="<?=htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES);?>">

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
<div id="searchleftcont">
<label class="form-label">Status</label>
<select name="status" class="form-control">
    <option value="all" <?=($filterStatus==='all')?'selected':''?>>All</option>
    <option value="pending" <?=($filterStatus==='pending')?'selected':''?>>Pending</option>
    <option value="in_progress" <?=($filterStatus==='in_progress')?'selected':''?>>In Progress</option>
    <option value="completed" <?=($filterStatus==='completed')?'selected':''?>>Completed</option>
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
<option value="<?=htmlspecialchars((string)($se_msid), ENT_QUOTES, 'UTF-8', false);?>" hidden=""><?=htmlspecialchars((string)(strtoupper($result_msDetails12['ms_name'])), ENT_QUOTES, 'ISO-8859-1', false);?>, <?=htmlspecialchars((string)($result_msDetails12['ms_mobile']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
<?php }?>
<?php $select_msDetailsOPT="select * from marketing_staff order by ms_name asc";
$fetch_msDetailsOPT=mysqli_query($db_conn,$select_msDetailsOPT);
while($result_msDetailsOPT=mysqli_fetch_array($fetch_msDetailsOPT))
{
?>
<option value="<?=htmlspecialchars((string)($result_msDetailsOPT['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)(strtoupper($result_msDetailsOPT['ms_name'])), ENT_QUOTES, 'ISO-8859-1', false);?>, <?=htmlspecialchars((string)($result_msDetailsOPT['ms_mobile']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
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
							<?php endif; ?>
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

							<!-- Pending / In Progress / Completed amount cards — unconditional
							     (not nested in the $se_msids-only block above), so these show in
							     the default "All DMs (Company)" view too, not only when a
							     specific DM is picked. 2026-11-17, user request. -->
							<div class="row mb-3">
							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card" style="--kpi-accent:#f59e0b;">
							            <i class="material-icons-outlined mskpi-ico">hourglass_empty</i>
							            <div class="mskpi-t">Pending (no invoice yet)</div>
							            <div class="mskpi-v">&#8377;<?php echo inr_format($pendingAmtCp + $pendingAmtCompany, 2); ?></div>
							            <div class="mskpi-sub">CP: &#8377;<?php echo inr_format($pendingAmtCp, 2); ?> &middot; Company: &#8377;<?php echo inr_format($pendingAmtCompany, 2); ?> <span style="color:#9ca3af;">(estimated)</span></div>
							        </div>
							    </div>

							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card" style="--kpi-accent:#0ea5e9;">
							            <i class="material-icons-outlined mskpi-ico">sync</i>
							            <div class="mskpi-t">In Progress (stock pending)</div>
							            <div class="mskpi-v">&#8377;<?php echo inr_format($inProgressAmtCompany, 2); ?></div>
							            <div class="mskpi-sub">CP: &#8377;0.00 &middot; Company: &#8377;<?php echo inr_format($inProgressAmtCompany, 2); ?></div>
							        </div>
							    </div>

							    <div class="col-md-3 col-sm-6 mb-3">
							        <div class="mskpi-card" style="--kpi-accent:#16a34a;">
							            <i class="material-icons-outlined mskpi-ico">verified</i>
							            <div class="mskpi-t">Completed</div>
							            <div class="mskpi-v">&#8377;<?php echo inr_format($completedAmtCp + $completedAmtCompany, 2); ?></div>
							            <div class="mskpi-sub">CP: &#8377;<?php echo inr_format($completedAmtCp, 2); ?> &middot; Company: &#8377;<?php echo inr_format($completedAmtCompany, 2); ?></div>
							        </div>
							    </div>
							</div>

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
                                         <form method="post" action="release-shop-invoice-stock-action.php" id="releaseStockForm" onsubmit="return msOrdersConfirmRelease();">
                                         <input type="hidden" name="return_to" value="ms_prorders.php">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th onclick="event.stopPropagation(); event.stopImmediatePropagation();"><input type="checkbox" id="msReleaseSelectAll" title="Select all" onclick="event.stopPropagation(); event.stopImmediatePropagation();"></th>
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
													<th>Fulfilled By</th>
													<th>Status</th>
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
// Same 3-state rule as msProrderStatus() above (used by the summary cards
// and the row badge) — kept as a hand-written SQL mirror here since a
// WHERE clause can't call a PHP function; if that rule ever changes, this
// block must be updated to match by hand. no invoice yet = Pending;
// invoice exists but Company-fulfilled and not yet receipted = In Progress;
// CP-fulfilled (deducts immediately) or already receipted = Completed.
// 2026-11-17, user request — filter dropdown needed a 3rd "In Progress"
// bucket matching what the badge already shows, instead of lumping both
// "In Progress" and "Completed" rows under one "Completed" filter.
if ($filterStatus === 'pending') {
    $whereExtra .= " and NOT EXISTS (SELECT 1 FROM user_invoice ui_stat WHERE ui_stat.source_ms_order_id = ms_orders.order_id)";
} elseif ($filterStatus === 'in_progress') {
    $whereExtra .= " and EXISTS (
        SELECT 1 FROM user_invoice ui_stat WHERE ui_stat.source_ms_order_id = ms_orders.order_id
        AND ui_stat.fulfilled_by_cp_id IS NULL
        AND NOT EXISTS (SELECT 1 FROM receipt r_stat WHERE r_stat.receiptid = ui_stat.inv_id)
    )";
} elseif ($filterStatus === 'completed') {
    $whereExtra .= " and EXISTS (
        SELECT 1 FROM user_invoice ui_stat WHERE ui_stat.source_ms_order_id = ms_orders.order_id
        AND (ui_stat.fulfilled_by_cp_id IS NOT NULL
             OR EXISTS (SELECT 1 FROM receipt r_stat WHERE r_stat.receiptid = ui_stat.inv_id))
    )";
}

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
// Also carries fulfilled_by_cp_id so the table can show whether it actually
// went to Company stock or a CP's own stock (2026-11-17, user request — the
// Fulfilled By column below).
$invoicedMap = [];
$invoiceFulfilledCpMap = [];
$invoiceIdMap = [];        // order_id -> inv_id
$receiptedMap = [];        // order_id -> true, once its invoice's stock has actually been released
if (!empty($orderIdsInRange)) {
    $oidList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $orderIdsInRange)) . "'";
    $resInvoiced = mysqli_query($db_conn, "SELECT inv_id, source_ms_order_id, fulfilled_by_cp_id FROM user_invoice WHERE source_ms_order_id IN ($oidList)");
    $invIdsToCheck = [];
    while ($ir = mysqli_fetch_assoc($resInvoiced)) {
        $invoicedMap[$ir['source_ms_order_id']] = true;
        $invoiceIdMap[$ir['source_ms_order_id']] = $ir['inv_id'];
        $invIdsToCheck[] = $ir['inv_id'];
        if (!empty($ir['fulfilled_by_cp_id'])) { $invoiceFulfilledCpMap[$ir['source_ms_order_id']] = (int)$ir['fulfilled_by_cp_id']; }
    }
    // Company-fulfilled DM order invoices no longer deduct/receipt stock at
    // Submit (2026-11-17, user request) — a receipt only gets written once
    // "Release Shop Invoice Stock" actually transfers the stock (see
    // release_shop_invoices_from_existing_stock() in AutoTransferDemand.php).
    // So "has an invoice" alone no longer means "fully done" the way it used
    // to -- this table's Status column and the release checkboxes below both
    // need the real, separate receipt signal.
    if (!empty($invIdsToCheck)) {
        $invIdList = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $invIdsToCheck)) . "'";
        $resReceipted = mysqli_query($db_conn, "SELECT DISTINCT inv_id FROM receipt WHERE receiptid IN ($invIdList)");
        $receiptedInvIds = [];
        while ($rr = mysqli_fetch_assoc($resReceipted)) { $receiptedInvIds[$rr['inv_id']] = true; }
        foreach ($invoiceIdMap as $oid => $iid) {
            if (isset($receiptedInvIds[$iid])) { $receiptedMap[$oid] = true; }
        }
    }
}

// CP names for the Fulfilled By column — covers both an order's actual
// invoiced fulfiller (fulfilled_by_cp_id above) and, for not-yet-invoiced
// orders, the DM's original Assign-To-CP pick (ms_orders.assigned_cp_id).
$fulfilledByCpNameMap = [];
$cpIdsNeeded = [];
foreach ($invoiceFulfilledCpMap as $cpId) { $cpIdsNeeded[$cpId] = true; }
foreach ($orderIdsInRange as $oid) {
    $acp = $orderMeta[$oid]['assigned_cp_id'] ?? null;
    if (!empty($acp)) { $cpIdsNeeded[(int)$acp] = true; }
}
if (!empty($cpIdsNeeded)) {
    $cpIdList = implode(',', array_map('intval', array_keys($cpIdsNeeded)));
    $resCpNames = mysqli_query($db_conn, "SELECT id, name FROM channel_partners WHERE id IN ($cpIdList)");
    while ($cnr = mysqli_fetch_assoc($resCpNames)) { $fulfilledByCpNameMap[(int)$cnr['id']] = $cnr['name']; }
}

// Has a CP or Company edited this order's qty/price before invoicing?
// (shared/MsOrderEdits.php, 2026-11-13, user request) -- shown as a
// "Changes" link on vacant-area (no TP) orders only.
require_once(__DIR__ . '/../shared/MsOrderEdits.php');
msOrderEditsEnsureSchema($db_conn);
$editedMap = [];
if (!empty($orderIdsInRange)) {
    $oidList2 = "'" . implode("','", array_map(fn($v) => mysqli_real_escape_string($db_conn, $v), $orderIdsInRange)) . "'";
    $resEdited = mysqli_query($db_conn, "SELECT DISTINCT order_id FROM ms_order_edits WHERE order_id IN ($oidList2)");
    while ($er = mysqli_fetch_assoc($resEdited)) { $editedMap[$er['order_id']] = true; }
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
					<td>
						<?php
						// Only Company-fulfilled DM order invoices go through the
						// release gate — a CP-fulfilled one deducts the CP's own
						// stock immediately at creation and never needs this
						// (2026-11-17, user request: checkbox-select which specific
						// orders' stock gets released, instead of the Auto Transfer
						// page's blanket "release everything that can" button).
						$releaseEligible = isset($invoicedMap[$orderid]) && empty($invoiceFulfilledCpMap[$orderid]) && !isset($receiptedMap[$orderid]);
						if ($releaseEligible):
						?>
						<input type="checkbox" name="inv_ids[]" value="<?=htmlspecialchars($invoiceIdMap[$orderid], ENT_QUOTES)?>" class="ms-release-checkbox">
						<?php else: ?>
						<span class="text-muted">&mdash;</span>
						<?php endif; ?>
					</td>
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
						<?php
						$invoicedCpId = $invoiceFulfilledCpMap[$orderid] ?? null;
						$assignedCpId = $result_product_list['assigned_cp_id'] ?? null;
						if (isset($invoicedMap[$orderid])):
							// Order already invoiced — show what it ACTUALLY went to.
							if (!empty($invoicedCpId)):
						?>
						<span class="badge badge-style-bordered badge-info">CP: <?=htmlspecialchars($fulfilledByCpNameMap[$invoicedCpId] ?? ('#' . $invoicedCpId));?></span>
						<?php else: ?>
						<span class="badge badge-style-bordered badge-secondary">Company</span>
						<?php
							endif;
						elseif (!empty($assignedCpId)):
						?>
						<span class="badge badge-style-bordered badge-warning">CP: <?=htmlspecialchars($fulfilledByCpNameMap[(int)$assignedCpId] ?? ('#' . $assignedCpId));?> (pending)</span>
						<?php else: ?>
						<span class="text-muted">&mdash;</span>
						<?php endif; ?>
					</td>
					<td>
						<?php
						$rowStatus = msProrderStatus(
							isset($invoicedMap[$orderid]),
							!empty($invoiceFulfilledCpMap[$orderid]),
							isset($receiptedMap[$orderid])
						);
						?>
						<?php if ($rowStatus === 'completed'): ?>
						<span class="badge badge-style-bordered badge-success">Completed</span>
						<?php elseif ($rowStatus === 'in_progress'): ?>
						<span class="badge badge-style-bordered badge-info">In Progress</span>
						<?php else: ?>
						<span class="badge badge-style-bordered badge-warning">Pending</span>
						<?php endif; ?>
					</td>
					<td style="white-space:nowrap;">
						<?php if (isset($receiptedMap[$orderid]) || !empty($invoiceFulfilledCpMap[$orderid])): ?>
						<?php // CP-fulfilled invoices deduct the CP's own stock immediately at
						// creation and never get a receipt row (that's only written once a
						// Company-fulfilled draft's stock is actually released) — gating this
						// button on $receiptedMap alone sent every CP-fulfilled "View Invoice"
						// click into ms-order-invoice-add.php's create-flow instead of the
						// actual invoice. 2026-11-20, user-reported. ?>
						<a href="shop-user-invoice-print?invoiceid=<?=base64_encode($invoiceIdMap[$orderid])?>" class="btn btn-sm btn-outline-secondary" target="_blank">View Invoice</a>
						<?php elseif (isset($invoicedMap[$orderid])): ?>
						<a href="ms-order-invoice-add.php?order_id=<?=urlencode($orderid)?>" class="btn btn-sm btn-outline-secondary">View Invoice</a>
						<?php else: ?>
						<a href="ms-order-invoice-add.php?order_id=<?=urlencode($orderid)?>" class="btn btn-sm btn-primary"><i class="material-icons" style="font-size:14px;vertical-align:middle;">receipt_long</i> Invoice</a>
						<?php if (empty($result_product_list['tp_id'])): ?>
						<button type="button" class="btn btn-sm btn-outline-secondary ms-order-edit-btn" data-order-id="<?=htmlspecialchars($orderid, ENT_QUOTES)?>" title="Edit qty/price before invoicing">
							<i class="material-icons" style="font-size:14px;vertical-align:middle;">edit</i>
						</button>
						<?php endif; ?>
						<?php endif; ?>
						<?php if (isset($editedMap[$orderid])): ?>
						<button type="button" class="btn btn-sm btn-outline-warning ms-order-changes-btn" data-order-id="<?=htmlspecialchars($orderid, ENT_QUOTES)?>" title="View edit history">
							<i class="material-icons" style="font-size:14px;vertical-align:middle;">history</i>
						</button>
						<?php endif; ?>
					</td>
                                                </tr>

										<?php }?>

										 </tbody>
                                        </table>
                                        <div style="margin-top:10px;">
                                            <button type="submit" class="btn btn-sm" style="background:#9a3412;color:#fff;">
                                                <i class="material-icons" style="font-size:16px;vertical-align:middle;">local_shipping</i> Release Stock for Selected Orders
                                            </button>
                                            <span class="text-muted" style="font-size:12px;margin-left:8px;">Only checked orders' stock is released — pick which ones to convert, instead of releasing every eligible order at once.</span>
                                        </div>
                                        </form>
                                        <script>
                                        (function () {
                                            var selectAll = document.getElementById('msReleaseSelectAll');
                                            if (!selectAll) { return; }
                                            var boxes = document.querySelectorAll('#releaseStockForm input[name="inv_ids[]"]');
                                            selectAll.addEventListener('change', function () {
                                                boxes.forEach(function (b) { b.checked = selectAll.checked; });
                                            });
                                            boxes.forEach(function (b) {
                                                b.addEventListener('change', function () {
                                                    selectAll.checked = Array.prototype.every.call(boxes, function (x) { return x.checked; });
                                                });
                                            });
                                        })();
                                        function msOrdersConfirmRelease() {
                                            var checked = document.querySelectorAll('#releaseStockForm input[name="inv_ids[]"]:checked');
                                            if (checked.length === 0) {
                                                alert('Select at least one order to release stock for.');
                                                return false;
                                            }
                                            return confirm('Release stock for ' + checked.length + ' selected order(s)? Any line still short on stock is left pending.');
                                        }
                                        </script>
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

    <!-- Edit / Changes modal (shared) for vacant-area (CP/Company) orders -->
    <div class="modal fade" id="msOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="msOrderModalTitle">Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="msOrderEditSection" style="display:none;">
                        <table class="table table-bordered" id="msOrderEditTable">
                            <thead><tr><th>Product</th><th>Qty</th><th>Price</th></tr></thead>
                            <tbody></tbody>
                        </table>
                        <label class="form-label">Reason for edit (required)</label>
                        <textarea id="msOrderEditReason" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="msOrderChangesSection" style="display:none;">
                        <div id="msOrderChangesList"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="msOrderSaveBtn" style="display:none;">Save</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function ($) {
        var $modal = new bootstrap.Modal(document.getElementById('msOrderModal'));
        var activeOrderId = null;

        function escHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }

        function openEditModal(orderId) {
            activeOrderId = orderId;
            $.getJSON('get-ms-order-lines.php', { order_id: orderId }, function (resp) {
                if (!resp || !resp.success) { alert('Could not load this order.'); return; }
                if (resp.invoiced) { alert('This order has already been invoiced.'); return; }
                $('#msOrderModalTitle').text('Edit Order');
                $('#msOrderEditSection').show();
                $('#msOrderChangesSection').hide();
                $('#msOrderSaveBtn').show();
                $('#msOrderEditReason').val('');
                var $tb = $('#msOrderEditTable tbody').empty();
                resp.lines.forEach(function (ln) {
                    $tb.append(
                        '<tr data-pr-id="' + ln.pr_id + '">' +
                        '<td>' + escHtml(ln.name) + '</td>' +
                        '<td><input type="number" min="0" class="form-control ms-edit-qty" value="' + ln.qty + '"></td>' +
                        '<td><input type="number" min="0" step="0.01" class="form-control ms-edit-price" value="' + ln.price + '"></td>' +
                        '</tr>'
                    );
                });
                $modal.show();
            });
        }

        function openChangesModal(orderId) {
            activeOrderId = orderId;
            $.getJSON('get-ms-order-lines.php', { order_id: orderId }, function (resp) {
                if (!resp || !resp.success) { alert('Could not load this order.'); return; }
                $('#msOrderModalTitle').text('Change History');
                $('#msOrderEditSection').hide();
                $('#msOrderChangesSection').show();
                $('#msOrderSaveBtn').hide();
                var $list = $('#msOrderChangesList').empty();
                if (!resp.log.length) {
                    $list.append('<div class="text-muted">No edits yet.</div>');
                } else {
                    resp.log.forEach(function (e) {
                        var priceTxt = (e.old_price !== null || e.new_price !== null)
                            ? (', price ' + (e.old_price !== null ? e.old_price.toFixed(2) : '-') + ' → ' + (e.new_price !== null ? e.new_price.toFixed(2) : '-'))
                            : '';
                        $list.append(
                            '<div style="padding:8px 0;border-bottom:1px solid #f0f0f0;font-size:13px;">' +
                            '<b>' + escHtml(e.product) + '</b>: ' + e.old_qty + ' → ' + e.new_qty + priceTxt + '<br>' +
                            'by ' + escHtml(e.by_name || e.by_type) + ' (' + escHtml(e.by_type) + ') on ' + escHtml(e.at) + '<br>' +
                            '<i>"' + escHtml(e.reason) + '"</i>' +
                            '</div>'
                        );
                    });
                }
                $modal.show();
            });
        }

        $(document).on('click', '.ms-order-edit-btn', function () { openEditModal($(this).data('order-id')); });
        $(document).on('click', '.ms-order-changes-btn', function () { openChangesModal($(this).data('order-id')); });

        $('#msOrderSaveBtn').on('click', function () {
            var reason = $.trim($('#msOrderEditReason').val());
            if (!reason) { alert('Please enter a reason.'); return; }
            var qty = {}, price = {};
            $('#msOrderEditTable tbody tr').each(function () {
                var prId = $(this).data('pr-id');
                qty[prId] = $(this).find('.ms-edit-qty').val();
                price[prId] = $(this).find('.ms-edit-price').val();
            });
            $('#msOrderSaveBtn').prop('disabled', true);
            $.post('ms-order-edit-action.php', { order_id: activeOrderId, qty: qty, price: price, reason: reason }, function (resp) {
                if (resp && resp.success) {
                    location.reload();
                } else {
                    alert((resp && resp.error) || 'Failed to save.');
                    $('#msOrderSaveBtn').prop('disabled', false);
                }
            }, 'json').fail(function () {
                alert('Failed to save. Please try again.');
                $('#msOrderSaveBtn').prop('disabled', false);
            });
        });
    })(jQuery);
    </script>
</body>

</html>