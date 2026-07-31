<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/NeksomoStockHelper.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

// Reached only from neksomo-purchase-stock.php's date-filter form — both
// dates are required there (mirrors overall-stock.php -> overstock_datewise.php).
$get_from_date = mysqli_real_escape_string($db_conn, trim($_REQUEST['fromdate'] ?? ''));
$get_to_date   = mysqli_real_escape_string($db_conn, trim($_REQUEST['todate'] ?? ''));
if ($get_from_date === '' || $get_to_date === '') {
    header("Location: neksomo-purchase-stock");
    exit;
}

// Neksomo's own godown id, looked up by name rather than hardcoded — same
// pattern as neksomo-manufacturer-purchase-action.php.
$select_Godowndetails = "SELECT * FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1";
$fetch_Godowndetails  = mysqli_query($db_conn, $select_Godowndetails);
$result_Godown        = mysqli_fetch_array($fetch_Godowndetails);

// Purchased Qty and LLP+Healthcare Sales Qty *within the selected range* —
// shown as their own columns, purely informational about period activity.
// Pack-based products (unit_type='pack') are maintained in packs throughout —
// see neksomo-purchase-stock.php for the same convention.
$select_purchased = "SELECT npi.product_id, SUM(npi.quantity_pieces) AS purchased_pieces, SUM(npi.quantity_packs) AS purchased_packs
                      FROM neksomo_purchase_items npi
                      JOIN neksomo_manufacturer_purchases mp ON mp.id = npi.purchase_id
                      WHERE mp.purchase_date BETWEEN '$get_from_date' AND '$get_to_date'
                      GROUP BY npi.product_id";
$Fetch_purchased = mysqli_query($db_conn, $select_purchased);
$purchasedPiecesByProduct = [];
$purchasedPacksByProduct = [];
while ($row = mysqli_fetch_assoc($Fetch_purchased)) {
    $purchasedPiecesByProduct[(int)$row['product_id']] = (int)$row['purchased_pieces'];
    $purchasedPacksByProduct[(int)$row['product_id']]  = (int)$row['purchased_packs'];
}
$soldPiecesByProduct = get_neksomo_pieces_sold_via_llp_healthcare($db_conn, $get_from_date, $get_to_date);
$soldPacksByProduct  = get_neksomo_packs_sold_via_llp_healthcare($db_conn, $get_from_date, $get_to_date);

// Closing Stock is the *real* running balance as it stood at the end of
// $get_to_date — all purchases ever made up to that date, minus all
// LLP/Healthcare pieces/packs ever sold up to that date — not the net movement
// within the selected range (which would wrongly assume zero opening stock
// at $get_from_date). No lower bound is passed, so both calls below run
// "as of $get_to_date" over full history.
$select_purchased_todate = "SELECT npi.product_id, SUM(npi.quantity_pieces) AS purchased_pieces, SUM(npi.quantity_packs) AS purchased_packs
                             FROM neksomo_purchase_items npi
                             JOIN neksomo_manufacturer_purchases mp ON mp.id = npi.purchase_id
                             WHERE mp.purchase_date <= '$get_to_date'
                             GROUP BY npi.product_id";
$Fetch_purchased_todate = mysqli_query($db_conn, $select_purchased_todate);
$purchasedPiecesByProductToDate = [];
$purchasedPacksByProductToDate = [];
while ($row = mysqli_fetch_assoc($Fetch_purchased_todate)) {
    $purchasedPiecesByProductToDate[(int)$row['product_id']] = (int)$row['purchased_pieces'];
    $purchasedPacksByProductToDate[(int)$row['product_id']]  = (int)$row['purchased_packs'];
}
$soldPiecesByProductToDate = get_neksomo_pieces_sold_via_llp_healthcare($db_conn, '', $get_to_date);
$soldPacksByProductToDate  = get_neksomo_packs_sold_via_llp_healthcare($db_conn, '', $get_to_date);

$closingPiecesByProduct = [];
foreach (array_unique(array_merge(array_keys($purchasedPiecesByProductToDate), array_keys($soldPiecesByProductToDate))) as $pid) {
    $closingPiecesByProduct[$pid] = ($purchasedPiecesByProductToDate[$pid] ?? 0) - ($soldPiecesByProductToDate[$pid] ?? 0);
}
$closingPacksByProduct = [];
foreach (array_unique(array_merge(array_keys($purchasedPacksByProductToDate), array_keys($soldPacksByProductToDate))) as $pid) {
    $closingPacksByProduct[$pid] = ($purchasedPacksByProductToDate[$pid] ?? 0) - ($soldPacksByProductToDate[$pid] ?? 0);
}

// Build per-product rows up front (rather than inside the HTML loop below) so
// the header total and the table/footer totals are derived from the exact
// same numbers — each product's own unit_type decides whether its purchased/
// sold/closing figures come from the pieces or the packs maps, so summing the
// raw pieces/packs arrays directly (which both contain entries for every
// product, not just ones of that unit) would double count across units.
$select_products = "SELECT id, productName, hsn, unit_type
                     FROM products
                     WHERE temp_id LIKE 'NKS-%' AND deleted_at IS NULL
                     ORDER BY productName ASC";
$Fetch_products = mysqli_query($db_conn, $select_products);
$product_rows = [];
$total_closing_pieces = 0;
$total_purchased_pieces = 0;
$total_sold_pieces = 0;
$total_closing_packs = 0;
$total_purchased_packs = 0;
$total_sold_packs = 0;
while ($Result_product = mysqli_fetch_assoc($Fetch_products)) {
    $pid = (int)$Result_product['id'];
    $isPack = ($Result_product['unit_type'] === 'pack');
    $PurchasedQty = $isPack ? ($purchasedPacksByProduct[$pid] ?? 0) : ($purchasedPiecesByProduct[$pid] ?? 0);
    $SoldQty      = $isPack ? ($soldPacksByProduct[$pid] ?? 0)     : ($soldPiecesByProduct[$pid] ?? 0);
    $ClosingStock = $isPack ? ($closingPacksByProduct[$pid] ?? 0)  : ($closingPiecesByProduct[$pid] ?? 0);
    if ($isPack) {
        $total_purchased_packs += $PurchasedQty;
        $total_sold_packs      += $SoldQty;
        $total_closing_packs   += $ClosingStock;
    } else {
        $total_purchased_pieces += $PurchasedQty;
        $total_sold_pieces      += $SoldQty;
        $total_closing_pieces   += $ClosingStock;
    }
    $product_rows[] = [
        'productName'  => $Result_product['productName'],
        'hsn'          => $Result_product['hsn'],
        'isPack'       => $isPack,
        'unitLabel'    => $isPack ? 'packs' : 'pcs',
        'PurchasedQty' => $PurchasedQty,
        'SoldQty'      => $SoldQty,
        'ClosingStock' => $ClosingStock,
    ];
}

$Result_closing_pieces = $total_closing_pieces;
$Result_closing_packs  = $total_closing_packs;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Datewise Purchase Stock : <?php echo $business_name; ?></title>

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
									<td>Datewise Purchase Stock : <?=inr_format($Result_closing_pieces, 0);?> pcs, <?=inr_format($Result_closing_packs, 0);?> packs (Qty)</td>
									<td><a href="neksomo-purchase-stock">&#8592; Go Back</a></td>
									</tr>
									</table>
									</h1>
									<h5><?=date("d-m-Y",strtotime($get_from_date));?> (to) <?=date("d-m-Y",strtotime($get_to_date));?></h5>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									<p class="text-muted" style="font-size:13px;">
										Purchased Qty and LLP + Healthcare Sales Qty below are scoped to <?= date("d/M/Y", strtotime($get_from_date)); ?> &ndash; <?= date("d/M/Y", strtotime($get_to_date)); ?> (period activity only). Sales Qty is net of returns in that same period — a returned piece/pack goes back into available stock, it isn't gone twice.
										Closing Stock is the real running balance as it stood at the end of <?= date("d/M/Y", strtotime($get_to_date)); ?> — total ever purchased minus total LLP/Healthcare ever sold net of returns, up to that date — not derived from the two period columns.
										Pieces-based products are converted to pieces via the product mapping; pack-based products are maintained in packs (mapped 1:1, no piece conversion) — each row shows its own unit.
										A product with no mapped company pack-product(s) shows 0 sold, regardless of what actually moved through LLP/Healthcare.
									</p>
									<div style="background:#fff;overflow:scroll;width:100%;">

									<h1><?=$result_Godown['gname'] ?? 'NEKSOMO HYGIENE INDUSTRIES';?></h1>

                                        <table class="table">
                                            <thead>
                                               <tr>
												<th>Product Name</th>
												<th>HSN</th>
												<th style="text-align:right;">Purchased Qty (period)</th>
												<th style="text-align:right;">LLP + Healthcare Sales Qty (net of returns, period)</th>
												<th style="text-align:right;">Closing Stock (as of <?= date("d/M/Y", strtotime($get_to_date)); ?>)</th>
												</tr>
                                            </thead>

											<tbody>
			<?php foreach ($product_rows as $r): ?>
                                                <tr>
                                                    <td><?php echo $r['productName'];?></td>
													<td><?php echo $r['hsn'];?></td>

						<!-------PURCHASE QTY------------->
						<td align="right"><?php echo inr_format($r['PurchasedQty'], 0) . ' ' . $r['unitLabel'];?></td>

						<!-------LLP + HEALTHCARE SALES QTY------------->
						<td align="right"><?php echo inr_format($r['SoldQty'], 0) . ' ' . $r['unitLabel'];?></td>

						<td align="right"><b><?php echo inr_format($r['ClosingStock'], 0) . ' ' . $r['unitLabel'];?></b></td>

                                                </tr>
										<?php endforeach;
										if (empty($product_rows)) { ?>
										<tr><td colspan="5" style="text-align:center;color:#898781;">No products added yet.</td></tr>
										<?php } ?>

										 </tbody>

										 <tfoot>
										 <tr>
										<td colspan="2" style="text-align:right;">Total (Pieces)</td>
										<td align="right"><b><?=inr_format($total_purchased_pieces, 0);?> pcs</b></td>
										<td align="right"><b><?=inr_format($total_sold_pieces, 0);?> pcs</b></td>
										<td align="right"><b><?=inr_format($total_closing_pieces, 0);?> pcs</b></td>
										</tr>
										 <tr>
										<td colspan="2" style="text-align:right;">Total (Packs)</td>
										<td align="right"><b><?=inr_format($total_purchased_packs, 0);?> packs</b></td>
										<td align="right"><b><?=inr_format($total_sold_packs, 0);?> packs</b></td>
										<td align="right"><b><?=inr_format($total_closing_packs, 0);?> packs</b></td>
										</tr>
										 </tfoot>

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
</body>

</html>
