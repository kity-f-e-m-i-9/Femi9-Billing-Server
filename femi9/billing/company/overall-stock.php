<?php include("checksession.php"); require_once("include/GodownAccess.php");
require_once("include/PermissionCheck.php"); requirePermission('products');
require_once("include/DatewiseStockReport.php");
error_reporting(0);
// Pulled from the Neksomo menu — a purpose-built stock view is coming for that login.
if (is_neksomo_login($db_conn)) { header("Location: dashboard.php"); exit; }
$user_type_Loginvl="company";

// Warehouse code/name lookup, used both to label per-warehouse cards below
// and to build the warehouse filter checkboxes.
$warehouseNames = [];
$whRes = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($whRes && ($whRow = $whRes->fetch_assoc())) {
    $warehouseNames[(int)$whRow['id']] = $whRow['code'] . ($whRow['name'] ? ' - ' . $whRow['name'] : '');
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
    <title>Overall Stocks : <?php echo $business_name;?></title>

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

    <style>
        /* ── Overall Stocks page: tabs + sticky filter bar ── */
        .stock-tabs { display:flex; gap:4px; border-bottom:2px solid #e9ecef; margin-bottom:0; }
        .stock-tab-btn {
            border:none; background:none; padding:10px 18px; font-weight:600; font-size:14px;
            color:#6c757d; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px;
        }
        .stock-tab-btn.active { color:#3f51b5; border-bottom-color:#3f51b5; }
        .stock-tab-btn:hover:not(.active) { color:#495057; }

        .stock-filter-bar {
            position:sticky; top:0; z-index:15; background:#fff; padding:14px 16px;
            border:1px solid #e9ecef; border-top:none; border-radius:0 0 8px 8px;
            box-shadow:0 2px 6px rgba(0,0,0,0.04); margin-bottom:16px;
        }
        .stock-filter-row { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
        .stock-filter-item { min-width:180px; flex:1 1 180px; }
        .stock-filter-item label { font-size:12px; font-weight:600; color:#6c757d; text-transform:uppercase; letter-spacing:.02em; margin-bottom:4px; display:block; }
        .stock-filter-item.grow { flex:2 1 240px; }
        .stock-filter-item.datewise-only { display:none; }
        body.tab-datewise .stock-filter-item.datewise-only { display:block; }

        .ms-dropdown-selectall {
            display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600;
            padding:0 0 6px; margin-bottom:6px; border-bottom:1px solid #e9ecef; white-space:nowrap;
        }
        .ms-dropdown-selectall a { color:#3f51b5; text-decoration:none; }
        .ms-dropdown-selectall a:hover { text-decoration:underline; }
        .ms-dropdown-selectall span { color:#ced4da; }

        .stock-summary-strip {
            display:flex; flex-wrap:wrap; gap:24px; align-items:center; padding:12px 16px;
            background:#f8f9fb; border:1px solid #e9ecef; border-radius:8px; margin-bottom:16px;
        }
        .stock-summary-strip .stat { display:flex; flex-direction:column; }
        .stock-summary-strip .stat .label { font-size:11px; color:#6c757d; text-transform:uppercase; letter-spacing:.03em; }
        .stock-summary-strip .stat .value { font-size:20px; font-weight:700; color:#212529; }

        .wh-card .card-body h1 { font-size:16px; font-weight:700; margin-bottom:4px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
        .wh-card .card-summary-line { font-size:12px; color:#6c757d; font-weight:500; }
        .wh-card thead th { position:sticky; top:0; background:#f8f9fb; z-index:2; }

        #datewiseTabPane { display:none; }
        #datewiseTabPane.active { display:block; }
        #currentStockTabPane.hidden-by-tab { display:none; }

        .datewise-day { border:1px solid #e9ecef; border-radius:6px; margin-bottom:10px; background:#fff; }
        .datewise-day > summary { list-style:none; cursor:pointer; }
        .datewise-day > summary::-webkit-details-marker { display:none; }
        .datewise-day-summary {
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;
            padding:10px 14px; font-weight:600;
        }
        .datewise-day-date { font-size:14px; }
        .datewise-day-quicktotals { font-size:12px; color:#6c757d; font-weight:500; }
        .datewise-day table { margin-bottom:0; }
        .datewise-warehouse-block { margin-bottom:14px; }
        .datewise-warehouse-block:last-child { margin-bottom:0; }
        .datewise-warehouse-label {
            font-size:12px; font-weight:700; color:#3f51b5; text-transform:uppercase;
            letter-spacing:.03em; padding:8px 14px 4px;
        }
        /* One card per Company Profile in the Datewise tab — same visual
           language as the Current Stock tab's godown cards. */
        .datewise-godown-card {
            border:1px solid #e9ecef; border-radius:8px; margin-bottom:20px;
            background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.04); overflow:hidden;
        }
        .datewise-godown-card:last-child { margin-bottom:0; }
        .datewise-godown-card-header {
            font-size:15px; font-weight:700; color:#212529; padding:12px 16px;
            background:#f8f9fb; border-bottom:1px solid #e9ecef;
        }
        .datewise-godown-card-body { padding:14px 16px; }
        .datewise-ajax-summary { padding:8px 2px 14px; }
        .datewise-ajax-summary-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
        .datewise-ajax-summary-meta { font-size:12px; color:#6c757d; margin-top:4px; }
        #datewiseResults .loading-placeholder { padding:40px; text-align:center; color:#6c757d; }

        @media (max-width: 768px) {
            .stock-filter-bar { position:static; }
            .stock-filter-item { min-width:100%; }
        }
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
								<?php
								$select_sumclosing12="select sum(closing_qty) from stock where user_type='$user_type_Loginvl'
										and product_id in (select id from products where temp_id not like 'NKS-%' or temp_id is null)";
										$Fetch_sumclosing12=mysqli_query($db_conn,$select_sumclosing12);
										$Result_sumclosing12=mysqli_fetch_array($Fetch_sumclosing12);
										?>
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Overall Stocks : <?=$Result_sumclosing12[0];?> (Qty)</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>

						<!-- Tabs: Current Stock (today's cards, client-side filtered) vs
						     Datewise View (movement report, loaded via AJAX into this same
						     page — no more navigating away to overstock_datewise). -->
						<div class="stock-tabs">
							<button type="button" class="stock-tab-btn active" id="tabBtnCurrent" onclick="switchStockTab('current')">Current Stock</button>
							<button type="button" class="stock-tab-btn" id="tabBtnDatewise" onclick="switchStockTab('datewise')">Datewise View</button>
						</div>

						<!-- Shared sticky filter bar. Company Profile / Warehouse / Product
						     search apply to whichever tab is active; From/To Date only matter
						     (and only show) on the Datewise tab. -->
						<div class="stock-filter-bar">
							<div class="stock-filter-row">
								<div class="stock-filter-item datewise-only">
									<label class="form-label">From Date</label>
									<input type="date" id="frdate" class="form-control">
								</div>
								<div class="stock-filter-item datewise-only">
									<label class="form-label">To Date</label>
									<input type="date" id="todate" class="form-control">
								</div>
								<div class="stock-filter-item">
									<label class="form-label">Company Profile</label>
									<div class="ms-dropdown" style="position:relative;">
									<div class="form-control" id="godownDropdownToggle" onclick="toggleGodownDropdown(event)" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;">
									<span id="godownDropdownLabel" style="color:#6c757d;">All</span>
									<i class="material-icons" style="font-size:20px;">arrow_drop_down</i>
									</div>
									<div id="godownDropdownPanel" style="display:none;position:absolute;z-index:20;background:#fff;border:1px solid #ced4da;border-radius:4px;padding:8px 12px;margin-top:2px;min-width:100%;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
									<?php $select_Godown="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
									$fetch_Godown=mysqli_query($db_conn,$select_Godown);
									while($result_Godown=mysqli_fetch_array($fetch_Godown))
									{?>
								<label style="font-weight:normal;display:flex;align-items:center;gap:6px;padding:5px 0;white-space:nowrap;margin:0;">
								<input type="checkbox" name="godownid[]" value="<?=$result_Godown['id'];?>" class="godown-check" data-gname="<?=htmlspecialchars($result_Godown['gname'], ENT_QUOTES, 'UTF-8');?>" onchange="onGodownFilterChange()"> <?=$result_Godown['gname'];?>
								</label>
									<?php }?>
									</div>
									</div>
								</div>

								<div class="stock-filter-item grow">
									<label class="form-label">Filter by Product</label>
									<input type="text" id="productFilterInput" class="form-control" placeholder="Type a product name…">
								</div>

								<div class="stock-filter-item">
									<label class="form-label">Warehouse</label>
									<div class="ms-dropdown" style="position:relative;">
									<div class="form-control" id="warehouseDropdownToggle" onclick="toggleWarehouseDropdown(event)" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;">
									<span id="warehouseDropdownLabel" style="color:#6c757d;">All</span>
									<i class="material-icons" style="font-size:20px;">arrow_drop_down</i>
									</div>
									<div id="warehouseDropdownPanel" style="display:none;position:absolute;z-index:20;background:#fff;border:1px solid #ced4da;border-radius:4px;padding:8px 12px;margin-top:2px;min-width:100%;box-shadow:0 4px 10px rgba(0,0,0,0.1);">
									<div class="ms-dropdown-selectall">
										<a href="javascript:void(0)" onclick="setAllWarehouseChecks(true)">Select all</a>
										<span>&middot;</span>
										<a href="javascript:void(0)" onclick="setAllWarehouseChecks(false)">Deselect all</a>
									</div>
									<label style="font-weight:normal;display:flex;align-items:center;gap:6px;padding:5px 0;white-space:nowrap;margin:0;">
									<input type="checkbox" name="warehouseid[]" value="unassigned" class="warehouse-check" checked onchange="onWarehouseFilterChange(this)"> Unassigned
									</label>
									<?php foreach ($warehouseNames as $whId => $whLabel): ?>
									<label style="font-weight:normal;display:flex;align-items:center;gap:6px;padding:5px 0;white-space:nowrap;margin:0;">
									<input type="checkbox" name="warehouseid[]" value="<?=(int)$whId;?>" class="warehouse-check" checked onchange="onWarehouseFilterChange(this)"> <?=htmlspecialchars($whLabel, ENT_QUOTES, 'UTF-8');?>
									</label>
									<?php endforeach; ?>
									</div>
									</div>
								</div>

								<div class="stock-filter-item" style="flex:0 0 auto;min-width:0;">
									<button type="button" class="btn btn-primary" id="applyFiltersBtn" onclick="applyStockFilters()"><i class="material-icons" style="font-size:18px;vertical-align:-4px;">search</i> Apply</button>
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

						<div id="currentStockTabPane">
						<?php
						//get Godown Details
$select_Godowndetails="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
$fetch_Godowndetails=mysqli_query($db_conn,$select_Godowndetails);
while($result_Godown=mysqli_fetch_array($fetch_Godowndetails))
{
$user_id_Loginvl=$result_Godown['id'];

// Pull every stock row for this godown (no GROUP BY — each warehouse's row
// stays separate so it can render as its own card), keyed by warehouse
// bucket. "" (empty string key) is the Unassigned bucket (warehouse_id IS NULL).
$warehouseBuckets = [];   // bucketKey => ['label' => ..., 'rows' => [productId => row]]
$warehouseBuckets[''] = ['label' => 'Unassigned', 'rows' => []];
foreach ($warehouseNames as $whId => $whLabel) {
    $warehouseBuckets[(string)$whId] = ['label' => $whLabel, 'rows' => []];
}

// Excludes Neksomo's raw piece-native placeholder products (temp_id LIKE
// 'NKS-%') — these are internal purchase-tracking rows (see
// neksomo-manufacturer-purchase-action.php's neksomo_credit_pieces()), not
// something any company-facing stock report should surface; the mapped
// normal company product is what should show here (see NeksomoStockBridge.php).
$select_OPStock="select product_id, warehouse_id, opening_qty, opening_date, input_qty, sales_qty, closing_qty, extra_pieces
    from stock where user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'
    and product_id in (select id from products where temp_id not like 'NKS-%' or temp_id is null)";
$Fetch_OPStock=mysqli_query($db_conn,$select_OPStock);
while($Result_OPStock=mysqli_fetch_array($Fetch_OPStock))
{
    $StockProductID=$Result_OPStock['product_id'];
    $bucketKey = $Result_OPStock['warehouse_id'] !== null ? (string)$Result_OPStock['warehouse_id'] : '';
    if (!isset($warehouseBuckets[$bucketKey])) {
        // Row references a warehouse that's since been deactivated/removed —
        // still show its stock rather than silently dropping it.
        $warehouseBuckets[$bucketKey] = ['label' => "Godown #$bucketKey", 'rows' => []];
    }
    $warehouseBuckets[$bucketKey]['rows'][$StockProductID] = $Result_OPStock;
}

// Render the Unassigned bucket first (it's where every pre-warehouse row
// already lives, and where Demo/Free/Damage still only shows — its source
// table, demofreedamage, has no warehouse_id), then each real warehouse in
// code order. Internal Transfer Qty and Movement to CP show on every card
// now, each scoped to its own warehouse via stock_ledger (see $cardWarehouseCond).
foreach ($warehouseBuckets as $bucketKey => $bucket) {
    if (empty($bucket['rows']) && $bucketKey !== '') continue; // skip empty warehouse cards entirely
    $isUnassignedBucket = ($bucketKey === '');
    $cardFilterClass = $isUnassignedBucket ? 'unassigned' : 'wh-' . (int)$bucketKey;
    // This card's own single-warehouse ledger condition — used to scope
    // Internal Transfer Qty (stock_ledger-based) to exactly this warehouse,
    // same convention as the Datewise tab's per-warehouse breakdown.
    $cardWarehouseCond = $isUnassignedBucket
        ? warehouseLedgerCondition(true, [], true)
        : warehouseLedgerCondition(true, [(int)$bucketKey], false);
?>
						<div class="row wh-card" data-warehouse="<?=$cardFilterClass;?>" data-gname="<?=htmlspecialchars(strtolower($result_Godown['gname']), ENT_QUOTES, 'UTF-8');?>">
							<div class="col">
								<div class="card">
									<div class="card-body">
										<h1><span><?=$result_Godown['gname'];?> &mdash; <?=htmlspecialchars($bucket['label'], ENT_QUOTES, 'UTF-8');?></span> <span class="card-summary-line" id="cardSummary-<?=$user_id_Loginvl;?>-<?=htmlspecialchars($cardFilterClass, ENT_QUOTES, 'UTF-8');?>"></span></h1>
										<div style="background:#fff;overflow:scroll;width:100%;">
										<table class="table">
											<thead>
												<tr>
													<th>Product Name</th>
													<th>Opening Stock Qty</th>
													<th>Opening Stock Date</th>
													<th style="text-align:right;">Input Stock Qty</th>
													<th style="text-align:right;">Sales Qty</th>
													<th style="text-align:right;">Return Qty</th>
													<th style="text-align:right;">Demo/Free/Damage Qty</th>
													<th style="text-align:right;">Internal Transfer Qty</th>
													<th style="text-align:right;">Movement to CP</th>
													<th style="text-align:right;">Closing Qty</th>
													<?php if (is_neksomo_login($db_conn)): ?>
													<th style="text-align:right;">Closing Qty (Pieces)</th>
													<?php endif; ?>
												</tr>
											</thead>
											<tbody>
<?php
$total_closing_pieces=0;
$total_closing_qty_shown=0;
$total_intrn_transfer=0;
$total_sent_other=0;
$total_dfd=0;
$total_return=0;
foreach ($bucket['rows'] as $StockProductID => $Result_OPStock) {
    $select_productDetils="select * from products where id='$StockProductID'";
    $Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
    $Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
    if ($Result_productDetils["productName"]==NULL) continue;

    // Always the godown's own real stock.closing_qty — no addition from
    // Neksomo's pending pool. Tracked per godown by its own pack quantity,
    // same as every other company profile on this page.
    $ClosingStock=(int)$Result_OPStock['closing_qty'];
    $PiecesPerPack=max((int)($Result_productDetils['pieces_per_pack'] ?? 1), 1);
    $ExtraPieces=(int)($Result_OPStock['extra_pieces'] ?? 0);
    $ClosingStockPieces=($ClosingStock*$PiecesPerPack)+$ExtraPieces;
    $total_closing_pieces+=$ClosingStockPieces;
    $total_closing_qty_shown+=$ClosingStock;

    // Internal Transfer Qty and Movement to CP are both computed from
    // stock_ledger, scoped to this card's own single warehouse
    // ($cardWarehouseCond) — accurate per warehouse, unlike the old
    // internal_transfer / pl_godown_transfer_items tables which have no
    // warehouse_id at all. Demo/Free/Damage still only appears on the
    // Unassigned card: its source table (demofreedamage) genuinely has no
    // warehouse_id to scope by.
    $IntrnTransferQty = datewiseTransferOutTotal($db_conn, (int)$StockProductID, '2000-01-01', date('Y-m-d'), (string)$user_id_Loginvl, true, $cardWarehouseCond);
    $total_intrn_transfer+=$IntrnTransferQty;

    $MovementToCP = datewiseCpMovementTotal($db_conn, (int)$StockProductID, '2000-01-01', date('Y-m-d'), (string)$user_id_Loginvl, true, $cardWarehouseCond);
    $total_sent_other+=$MovementToCP;

    // Return Qty — regular invoice returns and OT-channel sale
    // returns/deletes (gross; Sales Qty above is already net of these, per
    // stock.sales_qty's own convention). Same per-warehouse stock_ledger
    // scoping as Internal Transfer / Movement to CP above.
    $ReturnQty = datewiseReturnTotal($db_conn, (int)$StockProductID, '2000-01-01', date('Y-m-d'), (string)$user_id_Loginvl, true, $cardWarehouseCond);
    $total_return+=$ReturnQty;

    $DfdQty = 0;
    if ($isUnassignedBucket) {
        // Demo/Free/Damage — shown in its own column (see $DfdQty below),
        // only attributable to the Unassigned bucket for the reason above.
        $select_dfdQty="select sum(qty) from demofreedamage where product_id='$StockProductID' and userid='$user_id_Loginvl'";
        $Fetch_dfdQty=mysqli_query($db_conn,$select_dfdQty);
        $DfdQty=(int)(mysqli_fetch_row($Fetch_dfdQty)[0] ?? 0);
        $total_dfd+=$DfdQty;
    }
    // Sales Qty — computed fresh from stock_ledger (deduct + ot_deduct),
    // NOT the stored stock.sales_qty column, which StockService.php
    // decrements on every return (see datewiseGrossSalesTotal()'s own
    // note). A return already has its own Return Qty column below; it
    // shouldn't also silently shrink Sales. Demo/Free/Damage is its own
    // column too (previously folded together, see $DfdQty above).
    $SalesQtyShown = datewiseGrossSalesTotal($db_conn, (int)$StockProductID, '2000-01-01', date('Y-m-d'), (string)$user_id_Loginvl, true, $cardWarehouseCond);
?>
												<tr class="product-row" data-product-name="<?php echo htmlspecialchars(strtolower($Result_productDetils['productName']), ENT_QUOTES, 'UTF-8'); ?>">
													<td><?php echo $Result_productDetils["productName"];?></td>
													<td><?php echo $Result_OPStock['opening_qty'];?></td>
													<td><?php echo date("d/M/Y",strtotime($Result_OPStock['opening_date']));?></td>
													<td align="right"><?php echo $Result_OPStock['input_qty'];?></td>
													<td align="right"><?php echo $SalesQtyShown;?></td>
													<td align="right"><?php echo $ReturnQty;?></td>
													<td align="right"><?php echo $isUnassignedBucket ? $DfdQty : '&mdash;';?></td>
													<td align="right"><?php if ($IntrnTransferQty > 0): ?><a href="javascript:void(0)" class="intrn-transfer-link" data-product-id="<?=(int)$StockProductID;?>" data-godown-id="<?=(int)$user_id_Loginvl;?>" data-warehouse="<?=$isUnassignedBucket ? 'unassigned' : (int)$bucketKey;?>" data-from-date="2000-01-01" data-to-date="<?=date('Y-m-d');?>"><?php echo $IntrnTransferQty;?></a><?php else: ?><?php echo $IntrnTransferQty;?><?php endif; ?></td>
													<td align="right"><?php if ($MovementToCP > 0): ?><a href="javascript:void(0)" class="cp-movement-link" data-product-id="<?=(int)$StockProductID;?>" data-godown-id="<?=(int)$user_id_Loginvl;?>" data-warehouse="<?=$isUnassignedBucket ? 'unassigned' : (int)$bucketKey;?>" data-from-date="2000-01-01" data-to-date="<?=date('Y-m-d');?>"><?php echo $MovementToCP;?></a><?php else: ?><?php echo $MovementToCP;?><?php endif; ?></td>
													<td align="right"><b><?php echo $ClosingStock;?></b></td>
													<?php if (is_neksomo_login($db_conn)): ?>
													<td align="right"><b><?php echo $ClosingStockPieces;?></b></td>
													<?php endif; ?>
												</tr>
<?php
}

?>
											</tbody>
											<tfoot>
												<tr>
													<td colspan="5" style="text-align:right;">Total</td>
													<td align="right"><b><?=$total_return;?></b></td>
													<td align="right"><b><?=$isUnassignedBucket ? $total_dfd : '&mdash;';?></b></td>
													<td align="right"><b><?=$total_intrn_transfer;?></b></td>
													<td align="right"><b><?=$total_sent_other;?></b></td>
													<td align="right"><b><?=$total_closing_qty_shown;?></b></td>
													<?php if (is_neksomo_login($db_conn)): ?>
													<td align="right"><b><?=$total_closing_pieces;?></b></td>
													<?php endif; ?>
												</tr>
											</tfoot>
										</table>
										</div>
									</div>
								</div>
							</div>
						</div>
<?php
    // Fill in the always-visible summary line now that totals for this card
    // are known (item count + closing qty), so it's readable before
    // scrolling into the table itself.
    $itemCount = count($bucket['rows']);
    echo '<script>document.getElementById(' . json_encode('cardSummary-' . $user_id_Loginvl . '-' . $cardFilterClass) . ').textContent = ' . json_encode("$itemCount item" . ($itemCount === 1 ? '' : 's') . " \xC2\xB7 Closing qty $total_closing_qty_shown") . ';</script>';
}
}
?>
						</div><!-- /#currentStockTabPane -->

						<div id="datewiseTabPane">
							<div id="datewiseResults">
								<div class="loading-placeholder">Pick a date range and Company Profile, then click Apply to load the datewise report.</div>
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
    /* ── Tabs ── */
    function switchStockTab(tab) {
        var isDatewise = (tab === 'datewise');
        document.getElementById('tabBtnCurrent').classList.toggle('active', !isDatewise);
        document.getElementById('tabBtnDatewise').classList.toggle('active', isDatewise);
        document.getElementById('currentStockTabPane').classList.toggle('hidden-by-tab', isDatewise);
        document.getElementById('datewiseTabPane').classList.toggle('active', isDatewise);
        document.body.classList.toggle('tab-datewise', isDatewise);
        if (isDatewise && !document.getElementById('datewiseTabPane').dataset.loadedOnce) {
            // Don't auto-fetch on first switch — wait for the user to pick
            // dates and hit Apply (date range is required and has no sane
            // default). Once they've applied once, later Apply clicks reuse
            // the same panel.
        }
    }

    /* ── Company Profile dropdown ── */
    function toggleGodownDropdown(e) {
        e.stopPropagation();
        var panel = document.getElementById('godownDropdownPanel');
        panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
    }

    function updateGodownDropdownLabel() {
        var checked = document.querySelectorAll('.godown-check:checked');
        var label = document.getElementById('godownDropdownLabel');
        if (checked.length === 0) {
            label.textContent = 'All';
            label.style.color = '#6c757d';
        } else if (checked.length === 1) {
            label.textContent = checked[0].closest('label').textContent.trim();
            label.style.color = '#000';
        } else {
            label.textContent = checked.length + ' companies selected';
            label.style.color = '#000';
        }
    }

    function onGodownFilterChange() {
        updateGodownDropdownLabel();
        // Company Profile also drives the Current Stock cards client-side
        // (no reload needed there); Datewise only re-fetches on Apply since
        // it needs a server round trip anyway.
        applyStockFilters();
    }

    /* ── Warehouse dropdown ── */
    function toggleWarehouseDropdown(e) {
        e.stopPropagation();
        var panel = document.getElementById('warehouseDropdownPanel');
        panel.style.display = (panel.style.display === 'block') ? 'none' : 'block';
    }

    function updateWarehouseDropdownLabel() {
        var boxes = document.querySelectorAll('.warehouse-check');
        var checked = document.querySelectorAll('.warehouse-check:checked');
        var label = document.getElementById('warehouseDropdownLabel');
        if (checked.length === boxes.length) {
            label.textContent = 'All';
            label.style.color = '#6c757d';
        } else if (checked.length === 0) {
            label.textContent = 'None';
            label.style.color = '#000';
        } else if (checked.length === 1) {
            label.textContent = checked[0].closest('label').textContent.trim();
            label.style.color = '#000';
        } else {
            label.textContent = checked.length + ' warehouses selected';
            label.style.color = '#000';
        }
    }

    function onWarehouseFilterChange() {
        updateWarehouseDropdownLabel();
        applyStockFilters();
    }
    updateWarehouseDropdownLabel();

    function setAllWarehouseChecks(checked) {
        document.querySelectorAll('.warehouse-check').forEach(function (cb) { cb.checked = checked; });
        onWarehouseFilterChange();
    }

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.ms-dropdown').forEach(function (dropdown) {
            if (!dropdown.contains(e.target)) {
                var panel = dropdown.querySelector('[id$="DropdownPanel"]');
                if (panel) panel.style.display = 'none';
            }
        });
    });

    document.getElementById('godownDropdownPanel').addEventListener('click', function (e) {
        e.stopPropagation();
    });
    document.getElementById('warehouseDropdownPanel').addEventListener('click', function (e) {
        e.stopPropagation();
    });

    /* ── Current Stock tab: Product / Company Profile / Warehouse filters,
       purely client-side over the already-rendered cards, no page reload. ── */
    function applyCurrentStockFilters() {
        var searchTerm = (document.getElementById('productFilterInput').value || '').trim().toLowerCase();
        var checkedWarehouseBoxes = Array.prototype.slice.call(document.querySelectorAll('.warehouse-check:checked')).map(function (cb) { return cb.value; });
        var allWarehouses = checkedWarehouseBoxes.length === document.querySelectorAll('.warehouse-check').length;
        var checkedGodownNames = Array.prototype.slice.call(document.querySelectorAll('.godown-check:checked')).map(function (cb) { return cb.dataset.gname.toLowerCase(); });
        var allGodowns = checkedGodownNames.length === 0;

        document.querySelectorAll('.wh-card').forEach(function (card) {
            var wh = card.getAttribute('data-warehouse'); // 'unassigned' or 'wh-<id>'
            var whId = wh === 'unassigned' ? 'unassigned' : wh.replace('wh-', '');
            var warehouseVisible = allWarehouses || checkedWarehouseBoxes.indexOf(whId) !== -1;
            var gname = card.getAttribute('data-gname') || '';
            var godownVisible = allGodowns || checkedGodownNames.indexOf(gname) !== -1;
            if (!warehouseVisible || !godownVisible) {
                card.style.display = 'none';
                return;
            }
            card.style.display = '';

            // Within a visible card, filter individual product rows by name;
            // hide the whole card if the search term matches nothing in it.
            var anyRowVisible = !searchTerm;
            card.querySelectorAll('.product-row').forEach(function (row) {
                var name = row.getAttribute('data-product-name') || '';
                var matches = !searchTerm || name.indexOf(searchTerm) !== -1;
                row.style.display = matches ? '' : 'none';
                if (matches) anyRowVisible = true;
            });
            if (searchTerm) {
                card.style.display = anyRowVisible ? '' : 'none';
            }
        });
    }

    /* ── Datewise tab: fetch the report fragment via AJAX using the shared
       filter bar's dates / Company Profile / Warehouse selections. ── */
    function loadDatewiseTab() {
        var frdate = document.getElementById('frdate').value;
        var todate = document.getElementById('todate').value;
        var resultsEl = document.getElementById('datewiseResults');
        if (!frdate || !todate) {
            resultsEl.innerHTML = '<div class="loading-placeholder">Pick both a From Date and To Date, then click Apply.</div>';
            return;
        }

        var params = new URLSearchParams();
        params.set('ajax', '1');
        params.set('frdate', frdate);
        params.set('todate', todate);
        document.querySelectorAll('.godown-check:checked').forEach(function (cb) { params.append('godownid[]', cb.value); });
        document.querySelectorAll('.warehouse-check:checked').forEach(function (cb) { params.append('warehouseid[]', cb.value); });

        resultsEl.innerHTML = '<div class="loading-placeholder">Loading…</div>';
        fetch('overstock_datewise?' + params.toString())
            .then(function (r) { return r.text(); })
            .then(function (html) {
                resultsEl.innerHTML = html;
                document.getElementById('datewiseTabPane').dataset.loadedOnce = '1';
            })
            .catch(function () {
                resultsEl.innerHTML = '<div class="loading-placeholder">Could not load the report. Please try again.</div>';
            });
    }

    /* ── Apply button: runs whichever tab is active. ── */
    function applyStockFilters() {
        var isDatewise = document.body.classList.contains('tab-datewise');
        if (isDatewise) {
            loadDatewiseTab();
        } else {
            applyCurrentStockFilters();
        }
    }

    document.getElementById('productFilterInput').addEventListener('input', applyCurrentStockFilters);

    /* ── Internal Transfer Qty click-to-view breakdown popup — shared by
       both tabs (Current Stock's server-rendered links, and Datewise's
       AJAX-injected links use the same .intrn-transfer-link /
       .cp-movement-link classes and data-* attributes, so one delegated
       listener covers both, for both Internal Transfer and Movement to CP). ── */
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.intrn-transfer-link, .cp-movement-link');
        if (!link) return;
        e.preventDefault();

        var isCp = link.classList.contains('cp-movement-link');
        var params = new URLSearchParams();
        params.set('type', isCp ? 'cp' : 'internal');
        params.set('product_id', link.dataset.productId);
        params.set('from_date', link.dataset.fromDate);
        params.set('to_date', link.dataset.toDate);
        if (link.dataset.warehouse) params.set('warehouse', link.dataset.warehouse);
        // data-godown-id may be a single id (Current Stock's one card) or a
        // comma-list (a Datewise leaf spanning multiple selected profiles).
        if (link.dataset.godownId) {
            link.dataset.godownId.split(',').forEach(function (gid) {
                if (gid) params.append('godownid[]', gid);
            });
        }

        document.getElementById('intrnTransferModalTitle').textContent = isCp ? 'Movement to CP Breakdown' : 'Internal Transfer Breakdown';
        var modalBody = document.getElementById('intrnTransferModalBody');
        modalBody.innerHTML = '<div class="loading-placeholder">Loading…</div>';
        var modal = new bootstrap.Modal(document.getElementById('intrnTransferModal'));
        modal.show();

        fetch('get-internal-transfer-breakdown?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var rows = data.rows || [];
                if (!rows.length) {
                    modalBody.innerHTML = '<div class="loading-placeholder">No detail available for this transfer.</div>';
                    return;
                }
                var html;
                if (isCp) {
                    html = '<table class="table table-sm"><thead><tr>'
                        + '<th>From Company Profile</th><th>From Warehouse</th>'
                        + '<th>Channel Partner</th>'
                        + '<th style="text-align:right;">Qty</th></tr></thead><tbody>';
                    rows.forEach(function (r) {
                        html += '<tr><td>' + escapeHtml(r.from_godown) + '</td><td>' + escapeHtml(r.from_warehouse) + '</td>'
                            + '<td>' + escapeHtml(r.cp_name) + '</td>'
                            + '<td style="text-align:right;"><b>' + r.qty + '</b></td></tr>';
                    });
                } else {
                    html = '<table class="table table-sm"><thead><tr>'
                        + '<th>From Company Profile</th><th>From Warehouse</th>'
                        + '<th>To Company Profile</th><th>To Warehouse</th>'
                        + '<th style="text-align:right;">Qty</th></tr></thead><tbody>';
                    rows.forEach(function (r) {
                        html += '<tr><td>' + escapeHtml(r.from_godown) + '</td><td>' + escapeHtml(r.from_warehouse) + '</td>'
                            + '<td>' + escapeHtml(r.to_godown) + '</td><td>' + escapeHtml(r.to_warehouse) + '</td>'
                            + '<td style="text-align:right;"><b>' + r.qty + '</b></td></tr>';
                    });
                }
                html += '</tbody></table>';
                modalBody.innerHTML = html;
            })
            .catch(function () {
                modalBody.innerHTML = '<div class="loading-placeholder">Could not load the breakdown. Please try again.</div>';
            });
    });

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }
    </script>

    <div class="modal fade" id="intrnTransferModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="intrnTransferModalTitle">Internal Transfer Breakdown</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="intrnTransferModalBody">
                    <div class="loading-placeholder">Loading…</div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
