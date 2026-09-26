<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
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
function warehouseLedgerCondition(bool $filterByWarehouse, array $selectedWarehouseIds, bool $includeUnassigned): string {
    if (!$filterByWarehouse) return '';
    $parts = [];
    if (!empty($selectedWarehouseIds)) $parts[] = 'warehouse_id IN (' . implode(',', $selectedWarehouseIds) . ')';
    if ($includeUnassigned) $parts[] = 'warehouse_id IS NULL';
    if (empty($parts)) return ' AND 1=0';
    return ' AND (' . implode(' OR ', $parts) . ')';
}
$warehouseCond = warehouseLedgerCondition($filterByWarehouse, $selectedWarehouseIds, $includeUnassigned);

$neksomoGodownId = (int) (mysqli_fetch_row(mysqli_query($db_conn,
    "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
))[0] ?? 0);
$showManufPurchases = empty($get_company_ids) || in_array($neksomoGodownId, $get_company_ids, true);
$filterByGodown = ($_REQUEST['godownid'] != NULL);

// Same stock_ledger-based reconstruction as overstock_datewise.php — see
// that file for the full rationale (warehouse_id is only reliably present
// on stock_ledger, not on the ~10 legacy transaction tables this page used
// to query directly).
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

    $credit       = $sumAction('credit');
    $reverseCr    = $sumAction('reverse_credit');
    $transferIn   = $sumAction('transfer_in');
    $transferInRv = $sumAction('transfer_in_reverse');
    $returnAccept = $sumAction('return_accept');
    $input_qty    = $credit - $reverseCr + $transferIn - $transferInRv + $returnAccept;

    $deduct      = $sumAction('deduct');
    $reverseDed  = $sumAction('reverse_deduct');
    $otDeduct    = $sumAction('ot_deduct');
    $otReverse   = $sumAction('ot_reverse');
    $dfd         = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out' AND ref_type='demofree'")
                 - (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out_reverse' AND ref_type='demofree'");
    $total_sales = $deduct - $reverseDed + $otDeduct - $otReverse + $dfd;

    $total_sales_return = $reverseDed + $otReverse;

    $transferOut   = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out' AND ref_type != 'demofree'");
    $transferOutRv = (int)$sum("SELECT COALESCE(SUM(qty),0) FROM stock_ledger WHERE $base AND action='transfer_out_reverse' AND ref_type != 'demofree'");
    $internal_transfer = $transferOut - $transferOutRv;

    $movement_to_cp = 0; // already included in transfer_out above (PLT writes through StockService too)

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
        'net_change'         => $input_qty - $total_sales - $internal_transfer,
    ];
}

$allProducts = [];
$fetch_productDetils = mysqli_query($db_conn, "select * from products where (temp_id not like 'NKS-%' or temp_id is null) order by id asc");
while ($p = mysqli_fetch_assoc($fetch_productDetils)) { $allProducts[] = $p; }

// Same today-anchored closing-qty reconstruction as overstock_datewise.php.
$todayClosingByProduct = [];
$select_today_closing = "SELECT product_id, SUM(closing_qty) sum_closing FROM stock WHERE user_type='company'"
    . ($filterByGodown ? " AND user_id IN ($get_company_ids_sql)" : '')
    . $warehouseCond
    . " GROUP BY product_id";
$fetch_today_closing = mysqli_query($db_conn, $select_today_closing);
while ($row = mysqli_fetch_assoc($fetch_today_closing)) {
    $todayClosingByProduct[(int)$row['product_id']] = (int)$row['sum_closing'];
}

$runningClosing = [];
$today = date('Y-m-d');
foreach ($allProducts as $p) {
    $prid = (int)$p['id'];
    $todayClosing = $todayClosingByProduct[$prid] ?? 0;
    if ($get_from_date <= $today) {
        $sinceFrom = computeStockMovement($db_conn, $prid, $get_from_date, $today, $get_company_ids_sql, $filterByGodown, $showManufPurchases, $warehouseCond);
        $runningClosing[$prid] = $todayClosing - $sinceFrom['net_change'];
    } else {
        $runningClosing[$prid] = $todayClosing;
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
for ( $i = $startTime; $i <= $endTime; $i = $i + 86400 ) {
 
 $thisDate = date( 'Y-m-d', $i ); // 2010-05-01, 2010-05-02, etc

 ?>
 <h1 align="center"><?=date("d-m-Y",strtotime($thisDate));?></h1>
                                        <table class="table">
                                            <thead>
                                               <tr>
											<th>Product Name</th>
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
	$m = computeStockMovement($db_conn, $report_prid, $thisDate, $thisDate, $get_company_ids_sql, $filterByGodown, $showManufPurchases, $warehouseCond);
	$runningClosing[$report_prid] = ($runningClosing[$report_prid] ?? 0) + $m['net_change'];
	$closingStock = $runningClosing[$report_prid];
?>
                       <tr>
                        <td><?php echo $Result_productDetils["productName"];?></td>
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
										
										<?php }?>
										
										
										<script>window.print();</script>
</body>

</html>