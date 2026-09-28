<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
require_once("include/DatewiseStockReport.php");
error_reporting(0);

$get_from_date=$_REQUEST['frdate'];
//$get_from_date=date ("Y-m-d", strtotime("-1 day", strtotime($get_from_date1)));
$get_to_date=$_REQUEST['todate'];

// Mirrors overstock_datewise.php — multiple company profiles combined via
// "IN (...)", and every from_user_id/user_id/to_userid match is scoped by
// its matching *_type='company' too (from_user_id etc. are plain ints/
// varchars re-used across every channel's own table, e.g.
// territory_partners.id=1 collides with company_godown.id=1).
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

// Warehouse selection — same convention as overall-stock.php /
// overstock_datewise.php. "unassigned" means warehouse_id IS NULL.
$warehouseNames = [];
$whRes = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($whRes && ($whRow = $whRes->fetch_assoc())) {
    $warehouseNames[(int)$whRow['id']] = $whRow['code'] . ($whRow['name'] ? ' - ' . $whRow['name'] : '');
}
$selectedWarehouseIds = [];
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
// include/DatewiseStockReport.php, shared with overstock_datewise.php.
$warehouseCond = warehouseLedgerCondition($filterByWarehouse, $selectedWarehouseIds, $includeUnassigned);

// Same breakdown signal as overstock_datewise.php — 2+ warehouse buckets
// selected means one sub-table per warehouse instead of one merged total.
$selectedBucketCount = count($selectedWarehouseIds) + ($includeUnassigned ? 1 : 0);
$warehouseBuckets = $selectedBucketCount >= 2
    ? datewiseWarehouseBuckets($selectedWarehouseIds, $includeUnassigned, $warehouseNames)
    : [];

$neksomoGodownId = (int) (mysqli_fetch_row(mysqli_query($db_conn,
    "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
))[0] ?? 0);
$showManufPurchases = empty($get_company_ids) || in_array($neksomoGodownId, $get_company_ids, true);
$filterByGodown = ($_REQUEST['godownid'] != NULL);

// computeStockMovement() lives in include/DatewiseStockReport.php, shared
// with overstock_datewise.php — see that file for the full rationale
// (warehouse_id is only reliably present on stock_ledger, not on the ~10
// legacy transaction tables this page used to query directly).

$allProducts = [];
$fetch_productDetils = mysqli_query($db_conn, "select * from products where (temp_id not like 'NKS-%' or temp_id is null) order by id asc");
while ($p = mysqli_fetch_assoc($fetch_productDetils)) { $allProducts[] = $p; }

// Same today-anchored closing-qty reconstruction as overstock_datewise.php,
// via the shared datewiseSeedRunningClosing() helper. Merged-view path (0/1
// warehouse selected) seeds one map; breakdown path (2+ warehouses) seeds
// one map per warehouse bucket, each independently anchored.
if (empty($warehouseBuckets)) {
    $runningClosing = datewiseSeedRunningClosing($db_conn, $allProducts, $get_from_date, $filterByGodown, $get_company_ids_sql, $showManufPurchases, $warehouseCond);
} else {
    $runningClosingByBucket = [];
    foreach ($warehouseBuckets as $bi => $bucket) {
        $runningClosingByBucket[$bi] = datewiseSeedRunningClosing($db_conn, $allProducts, $get_from_date, $filterByGodown, $get_company_ids_sql, $showManufPurchases, $bucket['cond']);
    }
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

  
</head>

<body>




									<table align="center">
									<tr>
									<td><h1>Datewise Overall stock</h1>
									<h5><?=date("d-m-Y",strtotime($get_from_date));?> (to) <?=date("d-m-Y",strtotime($get_to_date));?>
									<?php if(!empty($selected_godown_names)){?>
									<br/>Company Profile : <b><?=htmlspecialchars(implode(', ', $selected_godown_names));?></b>
									<?php }?>
									<?php if(!empty($selected_warehouse_names)){?>
									<br/>Warehouse : <b><?=htmlspecialchars(implode(', ', $selected_warehouse_names));?></b>
									<?php }?>
									</h5>
									</td>
									</tr>
									</table>
									<hr/>
									
								
								
								<?php 
$startTime = strtotime($get_from_date);
$endTime = strtotime($get_to_date);

// Loop between timestamps, 24 hours at a time
// Renders one <table> of product movement rows for $warehouseCondForBucket,
// carrying forward $runningClosingRef (byref map, keyed by product id) day
// by day. Mirrors overstock_datewise.php's renderMovementTable — kept as a
// separate closure here since this file's table has fewer columns (no
// pieces-per-pack breakdown, no Neksomo columns; this is a plain print view).
$renderPdfMovementTable = function($warehouseCondForBucket, array &$runningClosingRef) use ($db_conn, $allProducts, &$thisDate, $get_company_ids_sql, $filterByGodown, $showManufPurchases) {
    ob_start();
    ?>
    <table class="table">
        <thead>
           <tr>
			<th>Product Name</th>
			<th style="text-align:right;">Opening Stock Qty</th>
			<th style="text-align:right;">Input Stock Qty</th>
			<th style="text-align:right;">Sales Qty</th>
			<th style="text-align:right;">Return Qty</th>
			<th style="text-align:right;">Internal Transfer Qty</th>
			<th style="text-align:right;">Movement to CP</th>
			<th style="text-align:right;">Closing Stock</th>
			</tr>
        </thead>
		<tbody>
<?php foreach ($allProducts as $Result_productDetils):
	$report_prid = (int)$Result_productDetils['id'];
	// Opening Stock Qty — the balance before any of this day's transactions,
	// i.e. the running-closing value carried over from the previous day.
	$openingStock = $runningClosingRef[$report_prid] ?? 0;
	$m = computeStockMovement($db_conn, $report_prid, $thisDate, $thisDate, $get_company_ids_sql, $filterByGodown, $showManufPurchases, $warehouseCondForBucket);
	$runningClosingRef[$report_prid] = $openingStock + $m['net_change'];
	$closingStock = $runningClosingRef[$report_prid];
?>
                       <tr>
                        <td><?php echo $Result_productDetils["productName"];?></td>
						<td align="right"><?php echo $filterByGodown ? $openingStock : '—'; ?></td>
						<td align="right"><?php echo $m['input_qty'];?></td>
						<td align="right"><?php echo $m['total_sales'];?></td>
						<td align="right"><?php echo $m['total_sales_return'];?></td>
						<td align="right"><?php echo $m['internal_transfer'];?></td>
						<td align="right"><?php echo $m['movement_to_cp'];?></td>
						<td align="right"><b><?php echo $filterByGodown ? $closingStock : '—'; ?></b></td>
                        </tr>
						<?php endforeach; ?>
									    </tbody>
                                        </table>
    <?php
    return ob_get_clean();
};

for ( $i = $startTime; $i <= $endTime; $i = $i + 86400 ) {

 $thisDate = date( 'Y-m-d', $i ); // 2010-05-01, 2010-05-02, etc

 ?>
 <h1 align="center"><?=date("d-m-Y",strtotime($thisDate));?></h1>
<?php
 if (empty($warehouseBuckets)) {
    echo $renderPdfMovementTable($warehouseCond, $runningClosing);
 } else {
    foreach ($warehouseBuckets as $bi => $bucket) {
        echo '<h5 style="margin:10px 0 4px;">' . htmlspecialchars($bucket['label'], ENT_QUOTES, 'UTF-8') . '</h5>';
        echo $renderPdfMovementTable($bucket['cond'], $runningClosingByBucket[$bi]);
    }
 }
?>
										<?php }?>
										
										
										<script>window.print();</script>
</body>

</html>