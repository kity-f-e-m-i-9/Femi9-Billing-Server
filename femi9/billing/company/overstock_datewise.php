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
									<td><a href="overstock_datewise_pdf?frdate=<?=$get_from_date;?>&&todate=<?=$get_to_date;?>&&<?php echo implode('&&', array_map(fn($gid) => 'godownid[]=' . $gid, $get_company_ids)); ?>" title="Export" target="_blank"><img src="32-pdf.png"></a></td>
									</tr>
									</table>
									</h1>
									<h5><?=date("d-m-Y",strtotime($get_from_date));?> (to) <?=date("d-m-Y",strtotime($get_to_date));?>
									<?php if(!empty($selected_godown_names)){?>
									<br/>Company Profile : <b><?=htmlspecialchars(implode(', ', $selected_godown_names));?></b>
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
// Net stock movement for one product over an inclusive date range, honoring
// the same godown-selection scope as the rest of this page. Used both for
// each single report day AND for the one-off "catch-up" sum from a
// product's opening_date through the day before the report's from_date —
// same formula either way, just a wider range for the catch-up call.
function computeStockMovement($db_conn, $prid, $fromDate, $toDate, $companyIdsSql, $filterByGodown, $showManufPurchases) {
    $prid = (int)$prid;
    $fromDate = mysqli_real_escape_string($db_conn, $fromDate);
    $toDate   = mysqli_real_escape_string($db_conn, $toDate);
    $sum = function($sql) use ($db_conn) {
        return (int)(mysqli_fetch_row(mysqli_query($db_conn, $sql))[0] ?? 0);
    };

    $input_qty = $filterByGodown
        ? $sum("select sum(input_qty) from input_stock where input_date between '$fromDate' and '$toDate' and product_id='$prid' and godownid IN ($companyIdsSql)")
        : $sum("select sum(input_qty) from input_stock where input_date between '$fromDate' and '$toDate' and product_id='$prid'");

    $ot_sales = $filterByGodown
        ? $sum("select sum(qty) from ot_sales where date between '$fromDate' and '$toDate' and prid='$prid' and godownid IN ($companyIdsSql)")
        : $sum("select sum(qty) from ot_sales where date between '$fromDate' and '$toDate' and prid='$prid'");

    $ot_return = $filterByGodown
        ? $sum("select sum(qty) from ot_sales_return where return_date between '$fromDate' and '$toDate' and prid='$prid' and godownid IN ($companyIdsSql)")
        : $sum("select sum(qty) from ot_sales_return where return_date between '$fromDate' and '$toDate' and prid='$prid'");

    // Every channel's sales (company, TP, SS, stockiest, distributor, ...)
    // count toward "Overall" stock movement. When a specific company profile
    // IS selected, from_user_id/user_id must be scoped to type='company' too
    // — those id columns are reused across every channel's own table, so
    // e.g. territory_partners.id=1 would otherwise collide with
    // company_godown.id=1 and get miscounted as that company's sale.
    $sls1 = $filterByGodown
        ? $sum("select sum(qty) from user_invoice_items where date between '$fromDate' and '$toDate' and pr_id='$prid' and from_user_id IN ($companyIdsSql) and from_user_type='company'")
        : $sum("select sum(qty) from user_invoice_items where date between '$fromDate' and '$toDate' and pr_id='$prid'");

    $sls2 = $filterByGodown
        ? $sum("select sum(qty) from invoice_items where date between '$fromDate' and '$toDate' and pr_id='$prid' and user_id IN ($companyIdsSql) and user_type='company'")
        : $sum("select sum(qty) from invoice_items where date between '$fromDate' and '$toDate' and pr_id='$prid'");

    // Company-to-Territory-Partner invoices land in their own dedicated
    // tp_invoices/tp_invoice_items tables, not user_invoice_items/invoice_items.
    $sls3 = $filterByGodown
        ? $sum("select sum(tpi.quantity) from tp_invoice_items tpi inner join tp_invoices ti on ti.id=tpi.tp_invoice_id where ti.invoice_date between '$fromDate' and '$toDate' and tpi.product_id='$prid' and ti.source_godown_id IN ($companyIdsSql)")
        : $sum("select sum(tpi.quantity) from tp_invoice_items tpi inner join tp_invoices ti on ti.id=tpi.tp_invoice_id where ti.invoice_date between '$fromDate' and '$toDate' and tpi.product_id='$prid'");

    $sls_return = $filterByGodown
        ? $sum("select sum(qty) from user_return_stock_items where date between '$fromDate' and '$toDate' and prid='$prid' and to_userid IN ($companyIdsSql) and to_usertype='company'")
        : $sum("select sum(qty) from user_return_stock_items where date between '$fromDate' and '$toDate' and prid='$prid'");

    $dfd = $filterByGodown
        ? $sum("select sum(qty) from demofreedamage where date between '$fromDate' and '$toDate' and product_id='$prid' and userid IN ($companyIdsSql)")
        : $sum("select sum(qty) from demofreedamage where date between '$fromDate' and '$toDate' and product_id='$prid'");

    $intrn = $filterByGodown
        ? $sum("select sum(qty) from internal_transfer where date between '$fromDate' and '$toDate' and product_id='$prid' and send_from IN ($companyIdsSql)")
        : $sum("select sum(qty) from internal_transfer where date between '$fromDate' and '$toDate' and product_id='$prid'");

    // internal_transfer's counterpart credit — a godown-to-godown transfer
    // received BY this godown (send_to) — was never queried before, so any
    // inbound transfer was invisible to the closing-stock reconstruction.
    $intrn_in = $filterByGodown
        ? $sum("select sum(qty) from internal_transfer where date between '$fromDate' and '$toDate' and product_id='$prid' and send_to IN ($companyIdsSql)")
        : 0;

    // Partner-Location <-> Godown transfers (pl-godown-transfer-action.php,
    // ref_id 'PLT-xxxxx' in stock_ledger) are a third, separate transfer
    // mechanism from internal_transfer/tp_invoices and were not queried by
    // any of the components above at all — both directions were invisible.
    $plt_out = $filterByGodown
        ? $sum("select sum(i.quantity) from pl_godown_transfer_items i inner join pl_godown_transfers t on t.id=i.transfer_id where t.transfer_date between '$fromDate' and '$toDate' and i.product_id='$prid' and t.transfer_type='godown_to_location' and t.godown_id IN ($companyIdsSql)")
        : $sum("select sum(i.quantity) from pl_godown_transfer_items i inner join pl_godown_transfers t on t.id=i.transfer_id where t.transfer_date between '$fromDate' and '$toDate' and i.product_id='$prid' and t.transfer_type='godown_to_location'");

    $plt_in = $filterByGodown
        ? $sum("select sum(i.quantity) from pl_godown_transfer_items i inner join pl_godown_transfers t on t.id=i.transfer_id where t.transfer_date between '$fromDate' and '$toDate' and i.product_id='$prid' and t.transfer_type='location_to_godown' and t.godown_id IN ($companyIdsSql)")
        : $sum("select sum(i.quantity) from pl_godown_transfer_items i inner join pl_godown_transfers t on t.id=i.transfer_id where t.transfer_date between '$fromDate' and '$toDate' and i.product_id='$prid' and t.transfer_type='location_to_godown'");

    // Neksomo "Purchase from Manufacturer" (StockService/stock_ledger based),
    // tracked separately from the legacy input_stock table above.
    $manuf = 0;
    if ($showManufPurchases) {
        $manuf = $sum("select sum(npi.quantity_packs) from neksomo_purchase_items npi inner join neksomo_manufacturer_purchases mp on mp.id=npi.purchase_id where mp.purchase_date between '$fromDate' and '$toDate' and npi.product_id='$prid'");
    }

    $input_qty           = $input_qty + $intrn_in + $plt_in;
    $total_sales        = $ot_sales + $sls1 + $sls2 + $sls3;
    $total_sales_return = $ot_return + $sls_return;
    $total_sent          = $dfd + $intrn + $plt_out;

    return [
        'input_qty'          => $input_qty,
        'total_sales'        => $total_sales,
        'total_sales_return' => $total_sales_return,
        'total_sent'         => $total_sent,
        'manuf_qty'          => $manuf,
        // Credits (input, returns, manufacturer purchase) add to stock;
        // sales and sent (demo/free/damage + internal transfer) remove from it.
        'net_change'         => $input_qty + $total_sales_return + $manuf - $total_sales - $total_sent,
    ];
}

$filterByGodown = ($_REQUEST['godownid'] != NULL);

// All products, loaded once (was re-queried inside the day loop before).
$allProducts = [];
$fetch_productDetils = mysqli_query($db_conn, "select * from products order by id asc");
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
$select_today_closing = $filterByGodown
    ? "SELECT product_id, SUM(closing_qty) sum_closing FROM stock WHERE user_type='company' AND user_id IN ($get_company_ids_sql) GROUP BY product_id"
    : "SELECT product_id, SUM(closing_qty) sum_closing FROM stock WHERE user_type='company' GROUP BY product_id";
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
        $sinceFrom = computeStockMovement($db_conn, $prid, $get_from_date, $today, $get_company_ids_sql, $filterByGodown, $showManufPurchases);
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
											<th style="text-align:right;">Sent Qty</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Sent Qty (Pieces)</th><?php endif; ?>
<?php if ($showManufPurchases): ?><th style="text-align:right;">Manufacturer Purchase Qty</th><?php endif; ?>
<?php if ($showManufPurchases && is_neksomo_login($db_conn)): ?><th style="text-align:right;">Manufacturer Purchase Qty (Pieces)</th><?php endif; ?>
											<th style="text-align:right;">Closing Stock</th>
<?php if (is_neksomo_login($db_conn)): ?><th style="text-align:right;">Closing Stock (Pieces)</th><?php endif; ?>
											</tr>
                                            </thead>

											<tbody>
<?php foreach ($allProducts as $Result_productDetils):
	$report_prid = (int)$Result_productDetils['id'];
	$m = computeStockMovement($db_conn, $report_prid, $thisDate, $thisDate, $get_company_ids_sql, $filterByGodown, $showManufPurchases);
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
						<td align="right"><?php echo $m['total_sent'];?></td>
						<?php if (is_neksomo_login($db_conn)): ?><td align="right"><?php echo $m['total_sent']*$PiecesPerPack;?></td><?php endif; ?>
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