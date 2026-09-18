<?php include("checksession.php");
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);
include("config.php");
require_once("include/GodownAccess.php");

$displaytitle = "Internal Stock Transfer Credit Note";
$noteId = (int) base64_decode($_REQUEST['returnid'] ?? '');

$select_note = "select * from internal_transfer_return where id='" . $noteId . "'";
$fetch_note = mysqli_query($db_conn, $select_note);
$note = mysqli_fetch_array($fetch_note);

$select_product = "select * from products where id='" . (int)($note['product_id'] ?? 0) . "'";
$fetch_product = mysqli_query($db_conn, $select_product);
$product = mysqli_fetch_array($fetch_product);

$select_gd_from = "select * from company_godown where id='" . (int)($note['send_from'] ?? 0) . "' AND " . godown_finance_filter_sql($db_conn);
$fetch_gd_from = mysqli_query($db_conn, $select_gd_from);
$gd_from = mysqli_fetch_array($fetch_gd_from);

$select_gd_to = "select * from company_godown where id='" . (int)($note['send_to'] ?? 0) . "' AND " . godown_finance_filter_sql($db_conn);
$fetch_gd_to = mysqli_query($db_conn, $select_gd_to);
$gd_to = mysqli_fetch_array($fetch_gd_to);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo $displaytitle;?> : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">

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
                        <br/>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">

		<a href="internal_transfer_return_manage" id="linkbackvl">&#8630;&nbsp;Go Back</a>

								<h1>
									<table class="headertble">
									<tr>
									<td><?=$displaytitle;?>
									<br/>
					<div style="font-size:15px;margin-top:10px;">Transfer Invoice:-</div>
					<div style="font-size:22px;font-weight:600;color:blue;"><?=$note['tempid'] ?? '';?></div>
					<div style="font-size:12px;margin-top:5px;">Returned on <?=isset($note['created_at']) ? date("d/M/Y", strtotime($note['created_at'])) : '';?> by <?=$note['created_by'] ?? '';?></div>
									</td>
									<td>&nbsp;</td>
									</tr>
									</table>
									</h1>

<!--------------------------------------------------------------------------------------------->

										<div class="card-footer">
                                        <div class="row invoice-summary">

<div class="row">
<div class="table-responsive">
<table class="table">
<thead>
<tr>
<th scope="col">Send From</th>
<th scope="col">Send To</th>
</tr>
</thead>
<tbody>
<tr>
<td><?=$gd_from['gname'] ?? '';?></td>
<td><?=$gd_to['gname'] ?? '';?></td>
</tr>
</tbody>
</table>
</div>
</div>

<div class="row">
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">#</th>
                                                            <th scope="col">Product Description</th>
                                                            <th scope="col">Qty</th>
															<th scope="col">Rate</th>
                                                            <th scope="col">Taxable Value</th>
															<th scope="col">GST</th>
															<th scope="col">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
<th scope="row">1</th>
<td><?=$product['productName'] ?? '';?></td>
<td><?php echo inr_format($note['qty'] ?? 0, 1);?></td>
<td>&#8377;<?php echo inr_format($note['price'] ?? 0, 2);?></td>
<td align="right"><?php echo inr_format($note['taxable_value'] ?? 0, 2);?></td>
<td><?=inr_format($note['gst_amount'] ?? 0, 2);?>&nbsp;(<?=$note['gst'] ?? 0;?>%, <?=$note['gst_type'] ?? '';?>)</td>
<td align="right"><?php echo inr_format($note['total'] ?? 0, 2);?></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

										<div class="card-footer">
                                        <div class="row invoice-summary">
                                            <div class="col-lg-9"></div>
                                            <div class="col-lg-3">
                                                <div class="invoice-info">
            <p class="bold">Total <span>&#8377;<?php echo inr_format($note['total'] ?? 0, 2);?></span></p>
                                                </div>
											<div style="margin-top:15px;">
<a href="internal_transfer_return_delete?returnid=<?=$_REQUEST['returnid'];?>" class="btn btn-primary badge badge-style-bordered badge-danger" onclick="return confirm('Delete this credit note? The returned stock movement will be reversed.');">Delete Credit Note</a>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
