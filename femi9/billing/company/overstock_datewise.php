<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
require_once("include/DatewiseStockReport.php");
error_reporting(0);

// AJAX fragment mode — called from overall-stock.php's Datewise tab. Skips
// the page shell (head/sidebar/header) and renders just the results block,
// with each date as a collapsible <details> section instead of a stacked
// full table, so a wide date range doesn't dump everything open at once.
$is_ajax = isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == '1';

$get_from_date=$_REQUEST['frdate'];
//$get_from_date=date ("Y-m-d", strtotime("-1 day", strtotime($get_from_date1)));
$get_to_date=$_REQUEST['todate'];

// Multiple company profiles can be selected together (finance logins with
// several entities) — every query below sums across all of them via
// "IN ($get_company_ids)" instead of a single "= $get_company".
$get_company_ids = [];
if($_REQUEST['godownid']!=NULL)
{
$raw_godownids = is_array($_REQUEST['godownid']) ? $_REQUEST['godownid'] : [$_REQUEST['godownid']];
foreach ($raw_godownids as $gid) {
    $gid = (int)$gid;
    if ($gid < 1) continue;
    if (!is_godown_allowed($db_conn, $gid)) {
        header("Location: overall-stock?unauthorized"); exit;
    }
    $get_company_ids[] = $gid;
}
}
$get_company_ids_sql = implode(',', $get_company_ids ?: [0]);
//company details (all selected)
$selected_godown_names = [];
$godownNamesById = [];   // id => gname, for datewiseGodownBuckets() labels
if (!empty($get_company_ids)) {
    $select_Godown="select id, gname from company_godown where id IN ($get_company_ids_sql) order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown)) {
							       $selected_godown_names[] = $result_Godown['gname'];
							       $godownNamesById[(int)$result_Godown['id']] = $result_Godown['gname'];
							   }
}

// Warehouse selection — checkboxes from overall-stock.php's filter form.
// "unassigned" means warehouse_id IS NULL; a numeric id is a real warehouse.
// No selection at all = no warehouse filter (every row counted, matching
// this page's pre-warehouse behavior).
$warehouseNames = [];
$whRes = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($whRes && ($whRow = $whRes->fetch_assoc())) {
    $warehouseNames[(int)$whRow['id']] = $whRow['code'] . ($whRow['name'] ? ' - ' . $whRow['name'] : '');
}
$selectedWarehouseIds = [];   // real warehouse ids selected
$includeUnassigned    = false;
$filterByWarehouse    = false;
if (!empty($_REQUEST['warehouseid'])) {
    $rawWarehouseIds = is_array($_REQUEST['warehouseid']) ? $_REQUEST['warehouseid'] : [$_REQUEST['warehouseid']];
    foreach ($rawWarehouseIds as $wid) {
        if ($wid === 'unassigned') { $includeUnassigned = true; continue; }
        $wid = (int)$wid;
        if ($wid > 0 && isset($warehouseNames[$wid])) $selectedWarehouseIds[] = $wid;
    }
    $filterByWarehouse = true;
}
$selected_warehouse_names = [];
if ($includeUnassigned) $selected_warehouse_names[] = 'Unassigned';
foreach ($selectedWarehouseIds as $wid) $selected_warehouse_names[] = $warehouseNames[$wid];
// warehouseLedgerCondition() and computeStockMovement() live in
// include/DatewiseStockReport.php, shared with overstock_datewise_pdf.php.
$warehouseCond = warehouseLedgerCondition($filterByWarehouse, $selectedWarehouseIds, $includeUnassigned);

// When 2+ warehouse buckets are selected (a real warehouse and/or
// Unassigned), the report splits into one sub-table per warehouse instead
// of one merged total — $warehouseBuckets is empty when 0 or 1 bucket is
// selected, which is the signal the render loop below uses to fall back to
// today's single-table behavior.
$selectedBucketCount = count($selectedWarehouseIds) + ($includeUnassigned ? 1 : 0);
$warehouseBuckets = $selectedBucketCount >= 2
    ? datewiseWarehouseBuckets($selectedWarehouseIds, $includeUnassigned, $warehouseNames)
    : [];

// Same idea, per Company Profile: 2+ godowns selected splits the report
// into one block per godown (nesting the warehouse breakdown inside it, if
// that's also active) instead of one merged total across them.
$godownBuckets = count($get_company_ids) >= 2
    ? datewiseGodownBuckets($get_company_ids, $godownNamesById)
    : [];

// Manufacturer purchases (Neksomo "Purchase from Manufacturer") always credit
// into Neksomo's own godown, tracked separately from the legacy input_stock
// table — only relevant when viewing that godown specifically, or all godowns.
$neksomoGodownId = (int) (mysqli_fetch_row(mysqli_query($db_conn,
    "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
))[0] ?? 0);
// $_REQUEST['godownid'] arrives as an array from the real filter form (multi-
// select checkboxes) — (int) on an array casts to 1 in PHP, not the selected
// id, which silently broke this check (and therefore dropped manufacturer
// purchase credits from the closing-stock total) whenever Neksomo's godown
// was selected via the real form rather than a single scalar value.
$showManufPurchases = empty($get_company_ids) || in_array($neksomoGodownId, $get_company_ids, true);

$pdfHref = "overstock_datewise_pdf?frdate=$get_from_date&&todate=$get_to_date&&"
    . implode('&&', array_map(fn($gid) => 'godownid[]=' . $gid, $get_company_ids))
    . ($filterByWarehouse ? '&&' . implode('&&', array_map(fn($w) => 'warehouseid[]=' . urlencode($w), array_merge($selectedWarehouseIds, $includeUnassigned ? ['unassigned'] : []))) : '');

if (!$is_ajax) {
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Datewise Overall Stocks : <?php echo $business_name;?></title>

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

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

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
                        <div class="row">
                            <div class="col">
                                <div class="page-description">

                                    <h1>
									<table class="headertble">
									<tr>
									<td>Datewise Overall stock</td>
									<td><a href="overall-stock">&#8592; Go Back</a></td>
									<td><a href="<?=htmlspecialchars($pdfHref, ENT_QUOTES, 'UTF-8');?>" title="Export" target="_blank"><img src="32-pdf.png"></a></td>
									</tr>
									</table>
									</h1>
									<h5><?=date("d-m-Y",strtotime($get_from_date));?> (to) <?=date("d-m-Y",strtotime($get_to_date));?>
									<?php if(!empty($selected_godown_names)){?>
									<br/>Company Profile : <b><?=htmlspecialchars(implode(', ', $selected_godown_names));?></b>
									<?php }?>
									<?php if(!empty($selected_warehouse_names)){?>
									<br/>Warehouse : <b><?=htmlspecialchars(implode(', ', $selected_warehouse_names));?></b>
									<?php }?>
									</h5>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									<div style="background:#fff;overflow:scroll;width:100%;">

									<?php } // !$is_ajax ?>
									<?php if ($is_ajax): ?>
									<div class="datewise-ajax-summary">
										<div class="datewise-ajax-summary-row">
											<span><strong><?=date("d M Y",strtotime($get_from_date));?></strong> &rarr; <strong><?=date("d M Y",strtotime($get_to_date));?></strong></span>
											<a href="<?=htmlspecialchars($pdfHref, ENT_QUOTES, 'UTF-8');?>" class="btn btn-sm btn-outline-secondary" title="Export PDF" target="_blank"><i class="material-icons" style="font-size:16px;vertical-align:-3px;">picture_as_pdf</i> Export PDF</a>
										</div>
										<?php if(!empty($selected_godown_names)){?>
										<div class="datewise-ajax-summary-meta">Company Profile: <b><?=htmlspecialchars(implode(', ', $selected_godown_names));?></b></div>
										<?php }?>
										<?php if(!empty($selected_warehouse_names)){?>
										<div class="datewise-ajax-summary-meta">Warehouse: <b><?=htmlspecialchars(implode(', ', $selected_warehouse_names));?></b></div>
										<?php }?>
									</div>
									<?php endif; ?>
									<?php
// computeStockMovement() lives in include/DatewiseStockReport.php — reads
// stock_ledger, the one table StockService guarantees is warehouse-scoped
// and complete for every write since it was introduced (2026-06-22
// onward). Replaces the old 10-table reconstruction (input_stock,
// ot_sales, invoice_items, tp_invoice_items, internal_transfer,
// pl_godown_transfer_items, ...), most of which have no warehouse_id
// column at all and so could never honor a warehouse filter.

$filterByGodown = ($_REQUEST['godownid'] != NULL);

// All products, loaded once (was re-queried inside the day loop before).
$allProducts = [];
$fetch_productDetils = mysqli_query($db_conn, "select * from products where (temp_id not like 'NKS-%' or temp_id is null) order by id asc");
while ($p = mysqli_fetch_assoc($fetch_productDetils)) { $allProducts[] = $p; }

// Build the flat list of "leaves" to render — each leaf is one independent
// (Company Profile, Warehouse) scope with its own movement queries and its
// own running-closing balance (merged totals can't be split after the fact,
// same reasoning as the single-axis breakdowns below). Company Profile is
// the outer grouping and Warehouse the inner one, matching the Current
// Stock tab's godown-card -> warehouse-bucket nesting:
//   - 1 godown (or none), 1 warehouse (or none)         -> single leaf (today's merged behavior)
//   - 2+ godowns, <2 warehouses selected                 -> one leaf per godown
//   - <2 godowns, 2+ warehouses selected                 -> one leaf per warehouse (existing behavior)
//   - 2+ godowns AND 2+ warehouses                        -> one leaf per (godown, warehouse) pair, grouped by godown
$leaves = [];
if (empty($godownBuckets)) {
    if (empty($warehouseBuckets)) {
        $leaves[] = ['key' => 'merged', 'godownLabel' => null, 'warehouseLabel' => null, 'idsSql' => $get_company_ids_sql, 'whCond' => $warehouseCond, 'whParam' => ''];
    } else {
        foreach ($warehouseBuckets as $wi => $wb) {
            $leaves[] = ['key' => "wh$wi", 'godownLabel' => null, 'warehouseLabel' => $wb['label'], 'idsSql' => $get_company_ids_sql, 'whCond' => $wb['cond'], 'whParam' => $wb['whParam']];
        }
    }
} else {
    foreach ($godownBuckets as $gi => $gb) {
        if (empty($warehouseBuckets)) {
            $leaves[] = ['key' => "gd$gi", 'godownLabel' => $gb['label'], 'warehouseLabel' => null, 'idsSql' => $gb['ids_sql'], 'whCond' => $warehouseCond, 'whParam' => ''];
        } else {
            foreach ($warehouseBuckets as $wi => $wb) {
                $leaves[] = ['key' => "gd{$gi}_wh{$wi}", 'godownLabel' => $gb['label'], 'warehouseLabel' => $wb['label'], 'idsSql' => $gb['ids_sql'], 'whCond' => $wb['cond'], 'whParam' => $wb['whParam']];
            }
        }
    }
}

// Today's real closing_qty per product is the trusted anchor for seeding
// each leaf's running closing balance (see datewiseSeedRunningClosing() for
// the full rationale — bulk-imported/migrated stock predates stock_ledger,
// so building forward from opening_date can be wildly wrong; anchoring to
// today and working backward only needs the recent tracking window to be
// complete). One map per leaf, keyed by product id, carried forward day by
// day inside the loop below.
$runningClosingByLeaf = [];
foreach ($leaves as $leaf) {
    $runningClosingByLeaf[$leaf['key']] = datewiseSeedRunningClosing($db_conn, $allProducts, $get_from_date, $filterByGodown, $leaf['idsSql'], $showManufPurchases, $leaf['whCond']);
}

$startTime = strtotime($get_from_date);
$endTime = strtotime($get_to_date);
$totalDays = (int)floor(($endTime - $startTime) / 86400) + 1;

// Pass 1: walk every day once, computing each leaf's movement table +
// totals for that day, and each day's combined quick-totals. Stored keyed
// by day index / leaf key rather than emitted immediately, so Pass 2 below
// can regroup the output with Company Profile as the outer structure (one
// card per profile) instead of Date as the outer structure — the running-
// closing balances still only need to be carried forward once, in this
// same single walk from oldest day to newest.
$dayDates = [];
$dayTotalsByDay = [];      // dayIndex => totals array
$leafResultsByDay = [];    // dayIndex => [leafKey => ['html'=>..., 'totals'=>...]]
$dayIndex = 0;

// Renders one <table> of product movement rows for one leaf's
// ($idsSqlForLeaf, $warehouseCondForBucket) scope on $thisDate, carrying
// forward $runningClosingRef (byref map, keyed by product id) day by day.
// Returns [html, totals]. Shared by every leaf regardless of whether it's
// the single merged view or one cell of the godown x warehouse breakdown —
// same table markup either way, just scoped to a different (company ids,
// warehouse condition) pair and its own running-closing map. Defined once
// outside the day loop (Pass 1 below) and called once per (day, leaf).
$renderMovementTable = function($thisDate, $idsSqlForLeaf, $warehouseCondForBucket, $whParamForBucket, array &$runningClosingRef) use ($db_conn, $allProducts, $filterByGodown, $showManufPurchases) {
    $totals = ['opening_qty'=>0,'input_qty'=>0,'total_sales'=>0,'total_sales_return'=>0,'dfd_qty'=>0,'internal_transfer'=>0,'movement_to_cp'=>0,'manuf_qty'=>0,'closing'=>0];
    $rowsHtml = '';
    foreach ($allProducts as $Result_productDetils) {
        $report_prid = (int)$Result_productDetils['id'];
        // Opening Stock Qty — the balance as it stood before any of this
        // day's transactions: simply the running-closing value carried over
        // from the previous day, captured before this day's net_change is
        // folded in below.
        $openingStock = $runningClosingRef[$report_prid] ?? 0;
        $m = computeStockMovement($db_conn, $report_prid, $thisDate, $thisDate, $idsSqlForLeaf, $filterByGodown, $showManufPurchases, $warehouseCondForBucket);
        $runningClosingRef[$report_prid] = $openingStock + $m['net_change'];
        $closingStock = $runningClosingRef[$report_prid];
        $PiecesPerPack=max((int)($Result_productDetils['pieces_per_pack'] ?? 1), 1);
        if ($filterByGodown) $totals['opening_qty'] += $openingStock;
        $totals['input_qty'] += $m['input_qty'];
        $totals['total_sales'] += $m['total_sales'];
        $totals['total_sales_return'] += $m['total_sales_return'];
        $totals['dfd_qty'] += $m['dfd_qty'];
        $totals['internal_transfer'] += $m['internal_transfer'];
        $totals['movement_to_cp'] += $m['movement_to_cp'];
        $totals['manuf_qty'] += $m['manuf_qty'];
        if ($filterByGodown) $totals['closing'] += $closingStock;
        ob_start();
                            ?>
                        <tr>
                        <td><?php echo $Result_productDetils["productName"];?></td>
						<?php // Opening stock, like Closing Stock, is only meaningful once scoped
						// to specific company godown(s) — see the Closing Stock column's note
						// below for the full rationale. ?>
						<td align="right"<?php if ($filterByGodown && $openingStock < 0): ?> title="Net figure is <?=$openingStock;?> (an earlier reversal outweighed input at the time); shown as 0"<?php endif; ?>><?php echo $filterByGodown ? max(0, $openingStock) : '—'; ?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $filterByGodown ? max(0, $openingStock)*$PiecesPerPack : '—'; ?></td><?php endif; ?>
						<?php // A same-day reversal can outweigh a same-day fresh transfer-in,
						// making the net figure negative — mathematically correct (Closing
						// below already accounts for it) but reads oddly as "input". Shown
						// as 0 with a tooltip instead of a bare negative number. ?>
						<td align="right"<?php if ($m['input_qty'] < 0): ?> title="Net figure is <?=$m['input_qty'];?> (a same-day reversal outweighed new input); shown as 0"<?php endif; ?>><?php echo max(0, $m['input_qty']);?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo max(0, $m['input_qty'])*$PiecesPerPack;?></td><?php endif; ?>
						<td align="right"><?php echo $m['total_sales'];?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['total_sales']*$PiecesPerPack;?></td><?php endif; ?>
						<td align="right"><?php echo $m['dfd_qty'];?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['dfd_qty']*$PiecesPerPack;?></td><?php endif; ?>
						<td align="right"><?php echo $m['total_sales_return'];?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['total_sales_return']*$PiecesPerPack;?></td><?php endif; ?>
						<td align="right"><?php if ($m['internal_transfer'] > 0): ?><a href="javascript:void(0)" class="intrn-transfer-link" data-product-id="<?=$report_prid;?>" data-godown-id="<?=htmlspecialchars($idsSqlForLeaf, ENT_QUOTES, 'UTF-8');?>" data-warehouse="<?=htmlspecialchars((string)$whParamForBucket, ENT_QUOTES, 'UTF-8');?>" data-from-date="<?=$thisDate;?>" data-to-date="<?=$thisDate;?>"><?php echo $m['internal_transfer'];?></a><?php else: ?><?php echo $m['internal_transfer'];?><?php endif; ?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['internal_transfer']*$PiecesPerPack;?></td><?php endif; ?>
						<td align="right"><?php if ($m['movement_to_cp'] > 0): ?><a href="javascript:void(0)" class="cp-movement-link" data-product-id="<?=$report_prid;?>" data-godown-id="<?=htmlspecialchars($idsSqlForLeaf, ENT_QUOTES, 'UTF-8');?>" data-warehouse="<?=htmlspecialchars((string)$whParamForBucket, ENT_QUOTES, 'UTF-8');?>" data-from-date="<?=$thisDate;?>" data-to-date="<?=$thisDate;?>"><?php echo $m['movement_to_cp'];?></a><?php else: ?><?php echo $m['movement_to_cp'];?><?php endif; ?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['movement_to_cp']*$PiecesPerPack;?></td><?php endif; ?>
						<?php if ($showManufPurchases): ?><td align="right"><?php echo $m['manuf_qty'];?></td><?php endif; ?>
						<?php if ($showManufPurchases && is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['manuf_qty']*$PiecesPerPack;?></td><?php endif; ?>
						<?php // Closing stock is only meaningful once scoped to specific company
						// godown(s) — the "all channels" movement figures above (used when no
						// godown is selected) aggregate the whole downstream network's sales,
						// not just this company's own warehouse, so netting them here would be
						// physically meaningless. The Company Profile field is required on the
						// filter form, so this branch is a defensive fallback, not the normal path. ?>
						<td align="right"><b><?php echo $filterByGodown ? $closingStock : '—'; ?></b></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><b><?php echo $filterByGodown ? $closingStock*$PiecesPerPack : '—'; ?></b></td><?php endif; ?>
                        </tr>
						<?php
        $rowsHtml .= ob_get_clean();
    }
    ob_start();
    ?>
    <table class="table">
        <thead>
           <tr>
			<th>Product Name</th>
			<th style="text-align:right;">Opening Stock Qty</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Opening Stock Qty (Pieces)</th><?php endif; ?>
			<th style="text-align:right;">Input Stock Qty</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Input Stock Qty (Pieces)</th><?php endif; ?>
			<th style="text-align:right;">Sales Qty</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Sales Qty (Pieces)</th><?php endif; ?>
			<th style="text-align:right;">Demo/Free/Damage Qty</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Demo/Free/Damage Qty (Pieces)</th><?php endif; ?>
			<th style="text-align:right;">Return Qty</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Return Qty (Pieces)</th><?php endif; ?>
			<th style="text-align:right;">Internal Transfer Qty</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Internal Transfer Qty (Pieces)</th><?php endif; ?>
			<th style="text-align:right;">Movement to CP</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Movement to CP (Pieces)</th><?php endif; ?>
<?php if ($showManufPurchases): ?><th style="text-align:right;">Manufacturer Purchase Qty</th><?php endif; ?>
<?php if ($showManufPurchases && is_neksomo_login($db_conn)): ?><th style="text-align:right;">Manufacturer Purchase Qty (Pieces)</th><?php endif; ?>
			<th style="text-align:right;">Closing Stock</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Closing Stock (Pieces)</th><?php endif; ?>
			</tr>
        </thead>
		<tbody>
		<?php echo $rowsHtml; ?>
		</tbody>
    </table>
    <?php
    return ['html' => ob_get_clean(), 'totals' => $totals];
};

// Pass 1: walk every day once (oldest to newest, so each leaf's running-
// closing balance carries forward correctly), computing every leaf's
// movement table + totals for that day. Nothing is emitted yet — just
// collected into $leafResultsByDay / $dayTotalsByDay, keyed by day index,
// so Pass 2 below can regroup the output with Company Profile as the outer
// structure (one card per profile) instead of walking days once per card.
for ( $i = $startTime; $i <= $endTime; $i = $i + 86400 ) {
    $dayIndex++;
    $thisDate = date( 'Y-m-d', $i ); // 2010-05-01, 2010-05-02, etc
    $dayDates[$dayIndex] = $thisDate;

    $dayTotals = ['opening_qty'=>0,'input_qty'=>0,'total_sales'=>0,'total_sales_return'=>0,'dfd_qty'=>0,'internal_transfer'=>0,'movement_to_cp'=>0,'manuf_qty'=>0,'closing'=>0];
    $leafResults = [];
    foreach ($leaves as $leaf) {
        $result = $renderMovementTable($thisDate, $leaf['idsSql'], $leaf['whCond'], $leaf['whParam'], $runningClosingByLeaf[$leaf['key']]);
        foreach ($dayTotals as $k => $v) { $dayTotals[$k] += $result['totals'][$k]; }
        $leafResults[$leaf['key']] = $result;
    }
    $dayTotalsByDay[$dayIndex] = $dayTotals;
    $leafResultsByDay[$dayIndex] = $leafResults;
}

// Pass 2: assemble the output. Company Profile is the outermost structure —
// one card per selected profile (or a single unwrapped section when 0/1
// profile is selected, matching today's simpler view). Inside each card,
// dates are still the collapsible <details> sections; Warehouse breakdown
// (if active) nests inside each date, same as before.
$godownCardKeys = !empty($godownBuckets)
    ? array_map(fn($gb) => $gb['label'], $godownBuckets)
    : [null]; // single unwrapped section

foreach ($godownCardKeys as $cardGodownLabel) {
    // Leaves belonging to this card — every leaf if there's no godown
    // breakdown, else just the ones whose godownLabel matches this card.
    $cardLeaves = array_values(array_filter($leaves, fn($leaf) => $leaf['godownLabel'] === $cardGodownLabel));
    if (empty($cardLeaves)) continue;

    if ($cardGodownLabel !== null) {
        echo '<div class="datewise-godown-card"><div class="datewise-godown-card-header">' . htmlspecialchars($cardGodownLabel, ENT_QUOTES, 'UTF-8') . '</div><div class="datewise-godown-card-body">';
    }

    for ($dayIndex = 1; $dayIndex <= $totalDays; $dayIndex++) {
        $thisDate = $dayDates[$dayIndex];
        $isLastDay = ($dayIndex === $totalDays);
        $leafResults = $leafResultsByDay[$dayIndex];

        // Combined quick-totals for just this card's leaves (not the whole
        // day across every card) — sum only the leaves that belong here.
        $dayTotals = ['opening_qty'=>0,'input_qty'=>0,'total_sales'=>0,'total_sales_return'=>0,'dfd_qty'=>0,'internal_transfer'=>0,'movement_to_cp'=>0,'manuf_qty'=>0,'closing'=>0];
        foreach ($cardLeaves as $leaf) {
            foreach ($dayTotals as $k => $v) { $dayTotals[$k] += $leafResults[$leaf['key']]['totals'][$k]; }
        }

        if (count($cardLeaves) === 1) {
            // Single leaf on this card — no warehouse wrapper needed.
            $dayBodyHtml = $leafResults[$cardLeaves[0]['key']]['html'];
        } else {
            // 2+ warehouse leaves within this card — wrap each in a
            // warehouse-style block.
            $dayBodyHtml = '';
            foreach ($cardLeaves as $leaf) {
                $html = $leafResults[$leaf['key']]['html'];
                if ($leaf['warehouseLabel'] !== null) {
                    $html = '<div class="datewise-warehouse-block">'
                        . '<div class="datewise-warehouse-label">' . htmlspecialchars($leaf['warehouseLabel'], ENT_QUOTES, 'UTF-8') . '</div>'
                        . $html . '</div>';
                }
                $dayBodyHtml .= $html;
            }
        }
?>
<details class="datewise-day" <?php echo $isLastDay ? 'open' : ''; ?>>
	<summary class="datewise-day-summary">
		<span class="datewise-day-date"><?=date("d M Y (D)",strtotime($thisDate));?></span>
		<span class="datewise-day-quicktotals">
			<?php if ($filterByGodown): ?>Opening <b><?=$dayTotals['opening_qty'];?></b> &middot; <?php endif; ?>
			In <b><?=$dayTotals['input_qty'];?></b> &middot;
			Sales <b><?=$dayTotals['total_sales'];?></b> &middot;
			DFD <b><?=$dayTotals['dfd_qty'];?></b> &middot;
			Transfer <b><?=$dayTotals['internal_transfer'];?></b> &middot;
			CP <b><?=$dayTotals['movement_to_cp'];?></b>
			<?php if ($filterByGodown): ?> &middot; Closing <b><?=$dayTotals['closing'];?></b><?php endif; ?>
		</span>
	</summary>
	<div style="overflow-x:auto;">
	<?php echo $dayBodyHtml; ?>
	</div>
</details>
<?php
    } // end date loop

    if ($cardGodownLabel !== null) {
        echo '</div></div><!-- /.datewise-godown-card -->';
    }
} // end godown card loop

if (!$is_ajax) {
?>
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
</body>

</html>
<?php } // !$is_ajax ?>