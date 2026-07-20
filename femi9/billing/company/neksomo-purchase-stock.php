<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/NeksomoStockHelper.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

// Neksomo's own godown id, looked up by name rather than hardcoded — same
// pattern as neksomo-manufacturer-purchase-action.php.
$select_Godowndetails = "SELECT * FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1";
$fetch_Godowndetails  = mysqli_query($db_conn, $select_Godowndetails);
$result_Godown        = mysqli_fetch_array($fetch_Godowndetails);

// Optional date range filter — applies to both sides of the closing-stock
// calculation (Purchased Qty and LLP/Healthcare Sales Qty), so they're always
// comparing the same window instead of an all-time purchase total against an
// all-time sales total that spans years further back.
$get_from_date = mysqli_real_escape_string($db_conn, trim($_REQUEST['fromdate'] ?? ''));
$get_to_date   = mysqli_real_escape_string($db_conn, trim($_REQUEST['todate'] ?? ''));
$has_date_filter = ($get_from_date !== '' && $get_to_date !== '');

// Purchased Qty per product, from the actual purchase transactions.
$purchased_where_date = $has_date_filter
    ? "AND mp.purchase_date BETWEEN '$get_from_date' AND '$get_to_date'"
    : "";
$select_purchased = "SELECT npi.product_id, SUM(npi.quantity_pieces) AS purchased_qty
                      FROM neksomo_purchase_items npi
                      JOIN neksomo_manufacturer_purchases mp ON mp.id = npi.purchase_id
                      WHERE 1=1 $purchased_where_date
                      GROUP BY npi.product_id";
$Fetch_purchased = mysqli_query($db_conn, $select_purchased);
$purchasedByProduct = [];
while ($row = mysqli_fetch_assoc($Fetch_purchased)) {
    $purchasedByProduct[(int)$row['product_id']] = (int)$row['purchased_qty'];
}

// LLP + Healthcare sales, converted to pieces via neksomo_product_mapping,
// same date range as Purchased Qty above.
$soldPiecesByProduct = get_neksomo_pieces_sold_via_llp_healthcare($db_conn, $get_from_date, $get_to_date);

$Result_sumclosing12 = [array_sum($purchasedByProduct) - array_sum($soldPiecesByProduct)];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Purchase Stock : <?php echo $business_name; ?></title>

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
									<td>Purchase Stock : <?=inr_format($Result_sumclosing12[0] ?? 0, 0);?> (Qty)</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>

						<form method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
							<div class="overviewcontainar">
							<div id="searchleftcont">
								<label class="form-label">From Date</label>
								<input type="date" name="fromdate" value="<?= htmlspecialchars($get_from_date); ?>" class="form-control">
							</div>
							<div id="searchleftcont">
								<label class="form-label">To Date</label>
								<input type="date" name="todate" value="<?= htmlspecialchars($get_to_date); ?>" class="form-control">
							</div>
							<div id="searchbuttoncont">
								<button type="submit" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
								<?php if ($has_date_filter): ?>
								<a href="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary ms-2">Clear</a>
								<?php endif; ?>
							</div>
							</div>
							<div style="clear:both;"></div>
							<br/>
						</form>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									<p class="text-muted" style="font-size:13px;">
										Closing Stock = Purchased Qty &minus; (LLP + Healthcare Sales Qty, converted to pieces via the product mapping)<?php if ($has_date_filter): ?>, restricted to <?= date("d/M/Y", strtotime($get_from_date)); ?> &ndash; <?= date("d/M/Y", strtotime($get_to_date)); ?><?php else: ?>, all-time<?php endif; ?>.
										A product with no mapped company pack-product(s) shows 0 sold, regardless of what actually moved through LLP/Healthcare.
									</p>
									<div style="background:#fff;overflow:scroll;width:100%;">

									<h1><?=$result_Godown['gname'] ?? 'NEKSOMO HYGIENE INDUSTRIES';?></h1>

                                        <table class="table">
                                            <thead>
                                               <tr>
												<th>Product Name</th>
												<th>HSN</th>
												<th style="text-align:right;">Purchased Qty</th>
												<th style="text-align:right;">LLP + Healthcare Sales Qty (pcs)</th>
												<th style="text-align:right;">Closing Stock (pcs)</th>
												</tr>
                                            </thead>

											<tbody>
			<?php
$total_closing = 0;
$total_purchased = 0;
$total_sold = 0;

$select_products = "SELECT id, productName, hsn
                     FROM products
                     WHERE temp_id LIKE 'NKS-%' AND deleted_at IS NULL
                     ORDER BY productName ASC";
										$Fetch_products = mysqli_query($db_conn, $select_products);
										$row_count = mysqli_num_rows($Fetch_products);
										while ($Result_product = mysqli_fetch_array($Fetch_products)) {
											$pid = (int)$Result_product['id'];
											$PurchasedQty = $purchasedByProduct[$pid] ?? 0;
											$SoldPieces   = $soldPiecesByProduct[$pid] ?? 0;
											$ClosingStock = $PurchasedQty - $SoldPieces;
											$total_purchased += $PurchasedQty;
											$total_sold      += $SoldPieces;
											$total_closing    += $ClosingStock;
										?>
                                                <tr>
                                                    <td><?php echo $Result_product["productName"];?></td>
													<td><?php echo $Result_product["hsn"];?></td>

						<!-------PURCHASE QTY------------->
						<td align="right"><?php echo inr_format($PurchasedQty, 0);?></td>

						<!-------LLP + HEALTHCARE SALES (PIECES)------------->
						<td align="right"><?php echo inr_format($SoldPieces, 0);?></td>

						<td align="right"><b><?php echo inr_format($ClosingStock, 0);?></b></td>

                                                </tr>
										<?php }
										if ($row_count === 0) { ?>
										<tr><td colspan="5" style="text-align:center;color:#898781;">No products added yet.</td></tr>
										<?php } ?>

										 </tbody>

										 <tfoot>
										 <tr>
										<td colspan="2" style="text-align:right;">Total</td>
										<td align="right"><b><?=inr_format($total_purchased, 0);?></b></td>
										<td align="right"><b><?=inr_format($total_sold, 0);?></b></td>
										<td align="right"><b><?=inr_format($total_closing, 0);?></b></td>
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
