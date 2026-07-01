<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// ── CSRF token bootstrap ──────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token_opstock'])) {
    $_SESSION['csrf_token_opstock'] = bin2hex(random_bytes(32));
}

// ── Handle opening stock submission ──────────────────────────────────────────
if (isset($_REQUEST['update-opstock'])) {

    // CSRF validation
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (
        empty($_SESSION['csrf_token_opstock']) ||
        !hash_equals($_SESSION['csrf_token_opstock'], $submittedToken)
    ) {
        $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
        header("Location: op-stock.php?csrferror");
        exit;
    }
    // Rotate after use
    $_SESSION['csrf_token_opstock'] = bin2hex(random_bytes(32));

    $user_type          = (string) $Login_user_TYPEvl;
    $user_id            = (string) $Login_user_IDvl;
    $opening_stock_date = date("Y-m-d");
    $createdBy          = $_SESSION['LOGIN_USER'] ?? 'system';

    $pr_ids  = $_POST['pr_id']  ?? [];
    $op_qtys = $_POST['op_qty'] ?? [];

    if (!is_array($pr_ids) || count($pr_ids) === 0) {
        header("Location: op-stock.php?invalid");
        exit;
    }

    $stmtChk = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM stock
          WHERE product_id = ? AND user_type = ? AND user_id = ?"
    );
    $stmtIns = $db_conn->prepare(
        "INSERT INTO stock
             (product_id, opening_qty, opening_date, input_qty, sales_qty,
              sent_qty, closing_qty, user_type, user_id, returnqty)
         VALUES (?, ?, ?, 0, 0, 0, ?, ?, ?, 0)"
    );
    $stmtLed = $db_conn->prepare(
        "INSERT INTO stock_ledger
             (product_id, user_type, user_id, action, qty,
              qty_before, qty_after, ref_type, ref_id, note, created_by)
         VALUES (?, ?, ?, 'opening_stock', ?, 0, ?, 'opening_stock', ?, 'opening stock set', ?)"
    );

    $inserted = 0;
    foreach ($pr_ids as $i => $rawPid) {
        $pid = (int) $rawPid;
        $qty = (int) ($op_qtys[$i] ?? 0);
        if ($pid <= 0 || $qty < 0) continue;

        // Skip if stock row already exists for this product
        $stmtChk->bind_param('iss', $pid, $user_type, $user_id);
        $stmtChk->execute();
        if ((int)$stmtChk->get_result()->fetch_assoc()['n'] > 0) continue;

        // Insert stock row
        $stmtIns->bind_param('iiiss', $pid, $qty, $qty, $user_type, $user_id);
        $stmtIns->execute();

        // Audit trail: opening stock ledger entry
        $refId = (string)$pid;
        $stmtLed->bind_param('issiiiss', $pid, $user_type, $user_id, $qty, $qty, $refId, $createdBy);
        $stmtLed->execute();

        $inserted++;
    }

    $stmtChk->close();
    $stmtIns->close();
    $stmtLed->close();

    if ($inserted > 0) {
        echo "<script>window.location='op-stock.php?StockUpdatedSuccess';</script>";
    } else {
        echo "<script>window.location='op-stock.php?stockalreadyupdated';</script>";
    }
    exit;
}

$user_type_Loginvl = (string) $Login_user_TYPEvl;
$user_id_Loginvl   = (string) $Login_user_IDvl;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Set Opening Stock : <?php echo $business_name;?></title>

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
	<link href="../../assets/css/vlstyle.css" rel="stylesheet">

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
									<td>Set Opening Stock</td>
									</tr>
									</table>
									</h1>
								<?php if (isset($_REQUEST['StockUpdatedSuccess'])) { ?><div class="alert alert-success">Stock Updated Successfully.</div><?php } ?>
								<?php if (isset($_REQUEST['stockalreadyupdated'])) { ?><div class="alert alert-warning">Stock already set for all products.</div><?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                       
<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token_opstock']) ?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
											
											<table class="table">
											<thead>
											<tr>
											<th>Product Name</th>
											<th>Opening Stock Qty</th>
											</tr>
											</thead>
											
											
											<tbody>
										<?php
										$stmtProds = $db_conn->prepare("SELECT id, productName FROM products ORDER BY id ASC");
										$stmtProds->execute();
										$products = $stmtProds->get_result();
										$stmtProds->close();

										while ($result_product_list = $products->fetch_assoc()) {
											$stock_product_ID = (int) $result_product_list['id'];

											$stmtStk = $db_conn->prepare(
												"SELECT opening_qty FROM stock
												  WHERE user_type = ? AND user_id = ? AND product_id = ?"
											);
											$stmtStk->bind_param('ssi', $user_type_Loginvl, $user_id_Loginvl, $stock_product_ID);
											$stmtStk->execute();
											$stockRow = $stmtStk->get_result()->fetch_assoc();
											$stmtStk->close();

											if (!$stockRow) { ?>
                        <input type="hidden" name="pr_id[]" value="<?php echo $result_product_list['id']; ?>"/>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($result_product_list["productName"]); ?></td>
													<td><input type="number" name="op_qty[]" class="form-control" required="" style="border-color:#000 !important;" placeholder="Opening Stock Qty" min="0"/></td>
                                                </tr>
										<?php } else { ?>
										 <tr>
                                                    <td><?php echo htmlspecialchars($result_product_list["productName"]); ?></td>
													<td><input type="number" value="<?= (int)$stockRow['opening_qty']; ?>" disabled class="form-control"/></td>
                                                </tr>
										<?php } } ?>
										
										<tr>
										<td></td>
										<td>
										<button type="submit" onclick="return confirm('Please make a confirm!');" name="update-opstock" class="btn btn-primary"><i class="material-icons">update</i>Update</button></td>
										</tr>
										
										 </tbody>
                                        </table>
										
										
										<?php /*?>
										<table class="table">
											<thead>
											<tr>
											<th>Product Name</th>
											<th>Opening Stock Qty</th>
											</tr>
											</thead>
											
											<tbody>
			<?php $select_OPStock="select * from stock where user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
										$Fetch_OPStock=mysqli_query($db_conn,$select_OPStock);
										while($Result_OPStock=mysqli_fetch_array($Fetch_OPStock))
										{
											//Get Product Details
											$StockProductID=$Result_OPStock['product_id'];
											
						$select_productDetils="select * from products where id='$StockProductID'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
										
										$OPQTY=$Result_OPStock['opening_qty'];
										$OPQTY123+=$OPQTY;
										?>
                                                <tr>
												<td style="display:none;"><?php echo date("d/M/Y",strtotime($Result_OPStock['opening_date']));?></td>
                                                    <td>
													<a href="#" class="popup-trigger">
													<?php echo $Result_productDetils["productName"];?></a></td>
													<td align="right"><?php echo $Result_OPStock['opening_qty'];?></td>
                                                </tr>
                                           
										<?php }?>
										
										<tr>
										<td align="left"><b>Total</b></td>
										<td align="right"><b><?php echo $OPQTY123;?></b></td>
										</tr>
										
										 </tbody>
                                        </table>
										
										 
										 
										  <!-- Popup container -->
<div id="popup" class="popup">
    <h2>Stock Details</h2>
    <div id="popup-content">
        <!-- Content will be loaded dynamically -->
    </div>
    <a href="#" id="close-popup"><img src="../../assets/images/close 32.png"></a>
</div>

<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    // Show popup when button is clicked
    $('.popup-trigger').click(function(){
        var rowData = $(this).closest('tr').find('td').map(function(){
            return $(this).text();
        }).get();

        // Populate popup content with row data
        $('#popup-content').html("<p>Stock Opening Date : <b>" + rowData[0] + "</b></p><p>Product Name : <b>" + rowData[1] + "</b></p><p>Opening Stock Qty : <b>" + rowData[2] + "</b></p>");

        // Show the popup
        $('#popup').fadeIn();
    });

    // Close popup when close button is clicked
    $('#close-popup').click(function(){
        $('#popup').fadeOut();
    });
});
</script>

<?php */?>

												
                                            </div>
                                        </div>
										</form>
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