<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

$Report_LABLE="Channelwise Sales";

if($_REQUEST['lable']==1 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Today";}
else if($_REQUEST['lable']==2 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Yesterday";}
else if($_REQUEST['lable']==3 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="This Month";}
else
{$DISPLAY_LABLE="Last Month";}

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];
$catname=base64_decode($_REQUEST['cat']);

// ── Products (fetched once, reused for the header row and every data row's
// product-qty columns — the old version re-ran this same query once per
// order, on top of one qty lookup per order per product) ───────────────────
$products = [];
$prRes = mysqli_query($db_conn, "select * from `products` order by `id` asc");
while ($pr = mysqli_fetch_assoc($prRes)) { $products[] = $pr; }

// ── Orders (tempid) in range — one row per tempid, mirroring the old
// "select * from ot_sales where tempid=X" + fetch_array (first row) — made
// deterministic via MIN(id) instead of relying on implicit row order ───────
$catFilterSql = ($catname !== null && $catname !== '') ? " and cat=?" : "";
$baseWhere = "date between ? and ?" . $catFilterSql . " and godownid IN (" . godown_ids_subquery($db_conn) . ")";

$ordersSql = "
    select o.*
    from ot_sales o
    inner join (
        select tempid, MIN(id) as min_id
        from ot_sales
        where $baseWhere
        group by tempid
    ) first_row on first_row.min_id = o.id
    order by o.id asc
";
$stmt = mysqli_prepare($db_conn, $ordersSql);
if ($catname !== null && $catname !== '') {
    mysqli_stmt_bind_param($stmt, "sss", $from_date, $to_date, $catname);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
}
mysqli_stmt_execute($stmt);
$orders = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// ── Per-order total, per-order-per-product qty, and company_godown lookups —
// all fetched in one query each instead of one query per order (and, for
// qty, one query per order PER PRODUCT) ─────────────────────────────────────
$totalsByTempid    = [];
$qtyByTempidProduct = [];
$godownById         = [];

if ($orders) {
    $tempids      = array_column($orders, 'tempid');
    $placeholders = implode(',', array_fill(0, count($tempids), '?'));
    $types        = str_repeat('s', count($tempids));

    $stmt = mysqli_prepare($db_conn, "select tempid, sum(total) as total_sum from ot_sales where tempid IN ($placeholders) group by tempid");
    mysqli_stmt_bind_param($stmt, $types, ...$tempids);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) { $totalsByTempid[$row['tempid']] = $row['total_sum']; }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($db_conn, "select tempid, prid, sum(qty) as qty from ot_sales where tempid IN ($placeholders) group by tempid, prid");
    mysqli_stmt_bind_param($stmt, $types, ...$tempids);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) { $qtyByTempidProduct[$row['tempid']][$row['prid']] = $row['qty']; }
    mysqli_stmt_close($stmt);

    $godownIds = array_values(array_unique(array_column($orders, 'godownid')));
    if ($godownIds) {
        $gph    = implode(',', array_fill(0, count($godownIds), '?'));
        $gtypes = str_repeat('i', count($godownIds));
        $stmt = mysqli_prepare($db_conn, "select * from company_godown where id IN ($gph) AND " . godown_finance_filter_sql($db_conn));
        mysqli_stmt_bind_param($stmt, $gtypes, ...$godownIds);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) { $godownById[$row['id']] = $row; }
        mysqli_stmt_close($stmt);
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
    <title>OT Sales Report</title>
	<style type="text/css">
	body{font-family:arial;text-align:center;}
	table{width:100%;border-collapse:collapse;font-family:arial;}
	table th{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	table td{border:1px solid #000;padding:2px;font-size:14px;font-weight:bold;}
	</style>
</head>

<body>
    <h1>OT Sales Report</h1>
	<h3><?=date("d/m/Y",strtotime($from_date));?> (to) <?=date("d/m/Y",strtotime($to_date));?></h3>

                                         <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Company Profile</th>
													<th>Category</th>
													<th>Date</th>
													<th>Order Number</th>
													<th>Customer Name</th>
													<th>Customer Mobile</th>
													<th>Address</th>
													<th>Total Amount</th>

				<?php foreach ($products as $result_prdetails_header) { ?>
				<th><?=$result_prdetails_header['productName'];?></th>
				<?php }?>

												</tr>
                                            </thead>

											<tbody>
										<?php
										foreach ($orders as $resultrecords) {
											$ot_tempid = $resultrecords['tempid'];

											//company profile details
											$godownid = $resultrecords['godownid'];
											$result_Customers = $godownById[$godownid] ?? null;
										?>

                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?=$result_Customers["gname"] ?? '';?></td>
													<td><?=$resultrecords['cat'];?></td>
													<td><?=date("d/M/Y",strtotime($resultrecords["date"]));?></td>

													<td><?php echo $resultrecords["order_number"];?></td>

					<td><?php echo $resultrecords["customer_name"];?></td>
					<td><?php echo $resultrecords["customer_mobile"];?></td>
					<td><?php echo $resultrecords["customer_address"];?></td>

				<?php
				$TotalAmount = $totalsByTempid[$ot_tempid] ?? '0';
				if ($TotalAmount === null) { $TotalAmount = '0'; }
				$TotalAmount123 += $TotalAmount;
				?>
				<td align="right"><?php echo inr_format($TotalAmount, 2);?></td>


				<!------------------------PRODUCT WISE SALES QTY------------------------------->
				<?php foreach ($products as $result_prdetails_header) {
					$prid_header = $result_prdetails_header['id'];
					$net_sls_qty = $qtyByTempidProduct[$ot_tempid][$prid_header] ?? '0';
				?>
				<td><b><?=$net_sls_qty;?></b></td>
				<?php }?>
				<!-------------------------------------------------------------------->



                                        </tr>



										<?php }?>

										</tbody>

										 <?php /*?>
										 <tfoot>
										 <tr>
										 <td colspan="4">Grand Total</td>
				<td align="right"><b><?php echo inr_format($TotalAmount123, 2);?></b></td>
				<td align="right"><b><?=$TotalPrQty123;?></b></td>
										 </tr>
										 </tfoot>
										 <?php */?>

                                        </table>


										<script>window.print();</script>
