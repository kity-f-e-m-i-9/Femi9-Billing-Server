<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

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
if (!empty($get_company_ids)) {
    $select_Godown="select gname from company_godown where id IN ($get_company_ids_sql) order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown)) { $selected_godown_names[] = $result_Godown['gname']; }
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
// SQL fragment: "AND warehouse_id IN (...)" / "AND warehouse_id IS NULL" /
// combined via OR — applied to stock_ledger queries only when a warehouse
// filter was actually submitted.
function warehouseLedgerCondition(bool $filterByWarehouse, array $selectedWarehouseIds, bool $includeUnassigned): string {
    if (!$filterByWarehouse) return '';
    $parts = [];
    if (!empty($selectedWarehouseIds)) $parts[] = 'warehouse_id IN (' . implode(',', $selectedWarehouseIds) . ')';
    if ($includeUnassigned) $parts[] = 'warehouse_id IS NULL';
    if (empty($parts)) return ' AND 1=0'; // filter submitted but nothing selected — show nothing rather than silently ignore it
    return ' AND (' . implode(' OR ', $parts) . ')';
}
$warehouseCond = warehouseLedgerCondition($filterByWarehouse, $selectedWarehouseIds, $includeUnassigned);

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
									<td><a href="overstock_datewise_pdf?frdate=<?=$get_from_date;?>&&todate=<?=$get_to_date;?>&&<?php echo implode('&&', array_map(fn($gid) => 'godownid[]=' . $gid, $get_company_ids)); ?><?php echo $filterByWarehouse ? '&&' . implode('&&', array_map(fn($w) => 'warehouseid[]=' . urlencode($w), array_merge($selectedWarehouseIds, $includeUnassigned ? ['unassigned'] : []))) : ''; ?>" title="Export" target="_blank"><img src="32-pdf.png"></a></td>
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
									
									<?php
// Net stock movement for one product over an inclusive date range, read
// entirely from stock_ledger — the one table StockService guarantees is
// warehouse-scoped and complete for every write since it was introduced
// (2026-06-22 onward). Replaces the old 10-table reconstruction (input_stock,
// ot_sales, invoice_items, tp_invoice_items, internal_transfer,
// pl_godown_transfer_items, ...), most of which have no warehouse_id column
// at all and so could never honor a warehouse filter. $warehouseCond is the
// pre-built "AND (warehouse_id IN (...) OR warehouse_id IS NULL)" fragment
// (empty string when no warehouse filter was submitted).
function computeStockMovement($db_conn, $prid, $fromDate, $toDate, $companyIdsSql, $filterByGodown, $showManufPurchases, $warehouseCond) {
    $prid = (int)$prid;
    $fromDate = mysqli_real_escape_string($db_conn, $fromDate);
    $toDate   = mysqli_real_escape_string($db_conn, $toDate);
    $sum = function($sql) use ($db_conn) {
        return (int)(mysqli_fetch_row(mysqli_query($db_conn, $sql))[0] ?? 0);
    };
    $godownCond = $filterByGodown ? " AND user_id IN ($companyIdsSql)" : '';
    $dateCond   = " AND created_at >= '$fromDate 00:00:00' AND created_at <= '$toDate 23:59:59'";
    $base       = "product_id=$prid AND user_type='company'$godownCond$warehouseCond$dateCond";
    $sumAction  = function($action) use ($sum, $base) {
        return $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='$action'");
    };

    // Input Stock Qty — every credit into this godown: input_stock
    // submissions, Neksomo conversions, transfer-in from another godown/
    // warehouse, and accepted returns. Net of reverse_credit (a credit later
    // undone) and transfer_in_reverse (a transfer_in later undone).
    $credit       = $sumAction('credit');
    $reverseCr    = $sumAction('reverse_credit');
    $transferIn   = $sumAction('transfer_in');
    $transferInRv = $sumAction('transfer_in_reverse');
    $returnAccept = $sumAction('return_accept');
    $input_qty    = $credit - $reverseCr + $transferIn - $transferInRv + $returnAccept;

    // Sales Qty — every deduction from this godown that represents goods
    // leaving via a sale: ordinary deduct (customer/TP/user invoices),
    // ot_deduct (OT sales), and demofree's transfer_out (demo/free/damage,
    // folded into Sales per this page's existing convention). Net of
    // reverse_deduct and ot_reverse.
    $deduct      = $sumAction('deduct');
    $reverseDed  = $sumAction('reverse_deduct');
    $otDeduct    = $sumAction('ot_deduct');
    $otReverse   = $sumAction('ot_reverse');
    $dfd         = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out' AND ref_type='demofree'")
                 - (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out_reverse' AND ref_type='demofree'");
    $total_sales = $deduct - $reverseDed + $otDeduct - $otReverse + $dfd;

    // Return Qty — reverse_deduct entries are already netted into Sales
    // above (a return of a prior sale reduces net sales), but this page
    // also shows the gross return amount as its own column, same as before.
    $total_sales_return = $reverseDed + $otReverse;

    // Internal Transfer Qty — outbound godown-to-godown/warehouse-to-
    // warehouse transfer_out, excluding demofree's transfer_out (already
    // counted in Sales above). Net of transfer_out_reverse.
    $transferOut   = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out' AND ref_type != 'demofree'");
    $transferOutRv = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out_reverse' AND ref_type != 'demofree'");
    $internal_transfer = $transferOut - $transferOutRv;

    // Movement to CP — Partner-Location <-> Godown transfers
    // (pl-godown-transfer-action.php) write their own ref_type/ref_id
    // ('PLT-xxxxx') through StockService like everything else, so they're
    // already included in transfer_out above; no separate query needed.
    $movement_to_cp = 0;

    // Neksomo "Purchase from Manufacturer" — StockService-based like
    // everything else here (ref_type='adjustment', note references the
    // manufacturer purchase), already included in $credit above when this
    // godown is Neksomo's. Kept as its own reported column via a dedicated
    // ref_id pattern match, same rows already counted in $input_qty (not
    // double-added to net_change).
    $manuf = 0;
    if ($showManufPurchases) {
        $manuf = $sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='credit' AND ref_id LIKE 'manuf_purchase_%'");
    }

    return [
        'input_qty'          => $input_qty,
        'total_sales'        => $total_sales,
        'total_sales_return' => $total_sales_return,
        'internal_transfer'  => $internal_transfer,
        'movement_to_cp'      => $movement_to_cp,
        'manuf_qty'          => $manuf,
        // Credits add to stock; sales (incl. demo/free/damage) and internal
        // transfer remove from it. Return Qty is informational only — its
        // effect is already netted into total_sales via reverse_deduct.
        'net_change'         => $input_qty - $total_sales - $internal_transfer,
    ];
}

$filterByGodown = ($_REQUEST['godownid'] != NULL);

// All products, loaded once (was re-queried inside the day loop before).
$allProducts = [];
$fetch_productDetils = mysqli_query($db_conn, "select * from products where (temp_id not like 'NKS-%' or temp_id is null) order by id asc");
while ($p = mysqli_fetch_assoc($fetch_productDetils)) { $allProducts[] = $p; }

// Today's real closing_qty per product (summed across whichever godowns are
// in scope) — the live `stock` table is always accurate, so this is used as
// a trusted anchor instead of building forward from opening_qty. Older
// company-godown stock includes bulk-imported/migrated quantities that were
// never logged into input_stock or stock_ledger with a date, so reconstructing
// history forward from opening_date can be wildly wrong wherever that gap
// exists. Anchoring to today and working backward only needs the *recent*
// movement window (report's from_date through today) to be complete, which
// normal day-to-day tracked activity reliably is.
$todayClosingByProduct = [];
$select_today_closing = "SELECT product_id, SUM(closing_qty) sum_closing FROM stock WHERE user_type='company'"
    . ($filterByGodown ? " AND user_id IN ($get_company_ids_sql)" : '')
    . $warehouseCond
    . " GROUP BY product_id";
$fetch_today_closing = mysqli_query($db_conn, $select_today_closing);
while ($row = mysqli_fetch_assoc($fetch_today_closing)) {
    $todayClosingByProduct[(int)$row['product_id']] = (int)$row['sum_closing'];
}

// Running closing balance per product, seeded with: today's real closing_qty
// minus the net movement from this report's from_date through today
// (inclusive) — i.e. the balance as it stood right before from_date, derived
// only from the recent/reliable tracking window. Carried forward day by day
// inside the loop below.
$runningClosing = [];
$today = date('Y-m-d');
foreach ($allProducts as $p) {
    $prid = (int)$p['id'];
    $todayClosing = $todayClosingByProduct[$prid] ?? 0;
    if ($get_from_date <= $today) {
        $sinceFrom = computeStockMovement($db_conn, $prid, $get_from_date, $today, $get_company_ids_sql, $filterByGodown, $showManufPurchases, $warehouseCond);
        $runningClosing[$prid] = $todayClosing - $sinceFrom['net_change'];
    } else {
        // Report starts in the future — nothing to unwind, start from today's balance.
        $runningClosing[$prid] = $todayClosing;
    }
}

$startTime = strtotime($get_from_date);
$endTime = strtotime($get_to_date);

// Loop between timestamps, 24 hours at a time
for ( $i = $startTime; $i <= $endTime; $i = $i + 86400 ) {

 $thisDate = date( 'Y-m-d', $i ); // 2010-05-01, 2010-05-02, etc

 ?>
 <h1 align="center"><?=date("d-m-Y",strtotime($thisDate));?></h1>
                                        <table class="table">
                                            <thead>
                                               <tr>
											<th>Product Name</th>
											<th style="text-align:right;">Input Stock Qty</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Input Stock Qty (Pieces)</th><?php endif; ?>
											<th style="text-align:right;">Sales Qty</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Sales Qty (Pieces)</th><?php endif; ?>
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
<?php foreach ($allProducts as $Result_productDetils):
	$report_prid = (int)$Result_productDetils['id'];
	$m = computeStockMovement($db_conn, $report_prid, $thisDate, $thisDate, $get_company_ids_sql, $filterByGodown, $showManufPurchases, $warehouseCond);
	$runningClosing[$report_prid] = ($runningClosing[$report_prid] ?? 0) + $m['net_change'];
	$closingStock = $runningClosing[$report_prid];
	$PiecesPerPack=max((int)($Result_productDetils['pieces_per_pack'] ?? 1), 1);
						?>
                        <tr>
                        <td><?php echo $Result_productDetils["productName"];?></td>
						<td align="right"><?php echo $m['input_qty'];?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['input_qty']*$PiecesPerPack;?></td><?php endif; ?>
						<td align="right"><?php echo $m['total_sales'];?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['total_sales']*$PiecesPerPack;?></td><?php endif; ?>
						<td align="right"><?php echo $m['total_sales_return'];?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['total_sales_return']*$PiecesPerPack;?></td><?php endif; ?>
						<td align="right"><?php echo $m['internal_transfer'];?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['internal_transfer']*$PiecesPerPack;?></td><?php endif; ?>
						<td align="right"><?php echo $m['movement_to_cp'];?></td>
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
						<?php endforeach; ?>

									    </tbody>
                                        </table>

										<?php }?>

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