<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

// Deliberately bypasses godown_finance_filter_sql (which would restrict a
// neksomo login to only its own godown) — this page exists specifically to
// give Neksomo visibility into LLP/Healthcare stock, by name, not the full
// godown list. The Neksomo section itself is filtered to non-NKS products —
// i.e. stock credited via the generic "Add Input Stock" flow, as distinct
// from the piece-native NKS- products covered by the Purchase Stock page.
$sections = [
    ['gname' => 'FEMI NAYAN LLP',              'exclude_nks' => false],
    ['gname' => 'FEMI HEALTH CARE',             'exclude_nks' => false],
    ['gname' => 'NEKSOMO HYGIENE INDUSTRIES',   'exclude_nks' => true],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Company Stock : <?php echo $business_name; ?></title>

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
									<td>Company Stock</td>
									</tr>
									</table>
									</h1>
									<p class="text-muted" style="font-size:13px;">
										All sections shown in both pack and piece units (pieces = pack qty &times; pieces per pack).
										Neksomo's section is stock added through the generic "Add Input Stock" flow (the pack-based
										products it holds, not the piece-native purchases already covered on Purchase Stock).
									</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									<div style="background:#fff;overflow:scroll;width:100%;">

									<?php foreach ($sections as $section):
										$result_Godown = mysqli_fetch_array(mysqli_query(
											$db_conn,
											"SELECT * FROM company_godown WHERE gname = '" . mysqli_real_escape_string($db_conn, $section['gname']) . "' LIMIT 1"
										));
										if (!$result_Godown) continue;
										$user_id_Loginvl = $result_Godown['id'];
										$show_pieces = true;
									?>

									<h1><?=$result_Godown['gname'];?></h1>

                                        <table class="table">
                                            <thead>
                                               <tr>
												<th>Product Name</th>
												<th>Opening Stock Qty</th>
												<th>Opening Stock Date</th>
												<th style="text-align:right;">Input Stock Qty</th>
												<th style="text-align:right;">Sales Qty</th>
												<th style="text-align:right;">Sent Qty</th>
												<th style="text-align:right;">Closing Qty</th>
												<?php if ($show_pieces): ?>
												<th style="text-align:right;">Closing Qty (Pieces)</th>
												<?php endif; ?>
												</tr>
                                            </thead>

											<tbody>
			<?php
$total_closing = 0;
$total_closing_pieces = 0;

$nks_condition = $section['exclude_nks'] ? "AND p.temp_id NOT LIKE 'NKS-%'" : "";
$select_OPStock = "SELECT s.*, p.productName, p.pieces_per_pack
                    FROM stock s
                    JOIN products p ON p.id = s.product_id
                    WHERE s.user_type = 'company' AND s.user_id = '$user_id_Loginvl'
                      $nks_condition
                    ORDER BY p.productName ASC";
										$Fetch_OPStock = mysqli_query($db_conn, $select_OPStock);
										$row_count = mysqli_num_rows($Fetch_OPStock);
										while ($Result_OPStock = mysqli_fetch_array($Fetch_OPStock)) {
											$ClosingStock = $Result_OPStock['closing_qty'];
											$total_closing += $ClosingStock;
											if ($show_pieces) {
												$PiecesPerPack = max((int)($Result_OPStock['pieces_per_pack'] ?? 1), 1);
												$ExtraPieces   = (int)($Result_OPStock['extra_pieces'] ?? 0);
												$ClosingStockPieces = ($ClosingStock * $PiecesPerPack) + $ExtraPieces;
												$total_closing_pieces += $ClosingStockPieces;
											}
										?>
                                                <tr>
                                                    <td><?php echo $Result_OPStock["productName"];?></td>
													<td><?php echo inr_format($Result_OPStock['opening_qty'], 0);?></td>
													<td><?php echo date("d/M/Y",strtotime($Result_OPStock['opening_date']));?></td>

						<!-------PURCHASE QTY------------->
						<td align="right"><?php echo inr_format($Result_OPStock['input_qty'], 0);?></td>

						<!-------SALES QTY------------->
						<td align="right"><?php echo inr_format($Result_OPStock['sales_qty'], 0);?></td>

						<!-------INTERNAL TRANSFER + DEMO/FREE/DAMAGE------------->
						<td align="right"><?php echo inr_format($Result_OPStock['sent_qty'], 0);?></td>

						<td align="right"><b><?php echo inr_format($ClosingStock, 0);?></b></td>

						<?php if ($show_pieces): ?>
						<td align="right"><b><?php echo inr_format($ClosingStockPieces, 0);?></b></td>
						<?php endif; ?>

                                                </tr>
										<?php }
										if ($row_count === 0) { ?>
										<tr><td colspan="<?= $show_pieces ? 8 : 7 ?>" style="text-align:center;color:#898781;">No stock recorded.</td></tr>
										<?php } ?>

										 </tbody>

										 <tfoot>
										 <tr>
										<td colspan="6" style="text-align:right;">Total Stock Qty</td>
										<td align="right"><b><?=inr_format($total_closing, 0);?></b></td>
										<?php if ($show_pieces): ?>
										<td align="right"><b><?=inr_format($total_closing_pieces, 0);?></b></td>
										<?php endif; ?>
										</tr>
										 </tfoot>

                                        </table>
									<br/>
									<?php endforeach; ?>

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
