<?php include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
error_reporting(0);

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
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
    <title>Internal Stock Transfer Credit Notes : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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

								<?php
// Check for success message in session
if (isset($_SESSION['sucMessage'])) {
$sucMessage = $_SESSION['sucMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $sucMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['sucMessage']); } ?>

<?php
// Check for error message in session (e.g. delete refused because the
// returned stock was already partly consumed — see internal_transfer_return_delete.php)
if (isset($_SESSION['errorMessage'])) {
$errorMessage = $_SESSION['errorMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'error',
                          title: 'Cannot Delete',
                          text: '<?php echo addslashes($errorMessage); ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['errorMessage']); } ?>

                                    <h1>
									<table class="headertble">
									<tr>
									<td>Internal Stock Transfer Credit Notes</td>
									</tr>
									</table>
									</h1>
									<br/>
                                </div>
                            </div>
                        </div>

<?php
//----Continuos Serial Number In Next Page.......................
$num_rec_per_page=30;
if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=1; };
 $start_from = ($page-1) * $num_rec_per_page;
$i= $start_from;
//---------------------------------------------------------------
?>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Date</th>
													<th>Product</th>
													<th>Qty Returned</th>
													<th>Send From</th>
													<th>Send To</th>
													<th>Total(Rs.)</th>
													<th>Returned By</th>
													<th>View</th>
													<th>Delete</th>
                                                </tr>
                                            </thead>

											<tbody>
										<?php
										$select_returns = "select * from internal_transfer_return order by id desc";
										$fetch_returns = mysqli_query($db_conn, $select_returns);
										while ($note = mysqli_fetch_array($fetch_returns)) {

											$select_product = "select * from products where id='" . (int)$note['product_id'] . "'";
											$fetch_product = mysqli_query($db_conn, $select_product);
											$product = mysqli_fetch_array($fetch_product);

											$select_gd_from = "select * from company_godown where id='" . (int)$note['send_from'] . "' AND " . godown_finance_filter_sql($db_conn);
											$fetch_gd_from = mysqli_query($db_conn, $select_gd_from);
											$gd_from = mysqli_fetch_array($fetch_gd_from);

											$select_gd_to = "select * from company_godown where id='" . (int)$note['send_to'] . "' AND " . godown_finance_filter_sql($db_conn);
											$fetch_gd_to = mysqli_query($db_conn, $select_gd_to);
											$gd_to = mysqli_fetch_array($fetch_gd_to);

											$noteIdEnc = base64_encode($note['id']);
										?>
                                            <tr>
                                                <td><?php echo ++$i; ?></td>
												<td><?php echo date("d/M/Y", strtotime($note['created_at'])); ?></td>
												<td><?php echo $product['productName']; ?></td>
												<td><?php echo inr_format($note['qty'], 1); ?></td>
												<td><?php echo $gd_from['gname']; ?></td>
												<td><?php echo $gd_to['gname']; ?></td>
												<td><?php echo inr_format($note['total'], 2); ?></td>
												<td><?php echo $note['created_by']; ?></td>
												<td>
<a href="internal_transfer_return_details?returnid=<?=$noteIdEnc;?>" target="_blank"><img src="../../assets/images/details-32.png"/></a>
												</td>
												<td>
<a href="internal_transfer_return_delete?returnid=<?=$noteIdEnc;?>" onclick="return confirm('Delete this credit note? The returned stock movement will be reversed.');"><span class="badge bg-danger">Delete</span></a>
												</td>
                                            </tr>

										<?php }?>

										 </tbody>
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
