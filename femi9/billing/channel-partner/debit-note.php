<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;
$displaytitle = "Debit Note";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $displaytitle; ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
<?php
if (isset($_SESSION['successMessage'])) {
    $successMessage = $_SESSION['successMessage'];
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>Swal.fire({ icon:'success', title:'Success', text:'<?php echo $successMessage; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['successMessage']); } ?>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1><table class="headertble"><tr><td><?php echo $displaytitle; ?></td></tr></table></h1>
                            </div>
                        </div>
                    </div>
<?php
$num_rec_per_page = 30;
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$start_from = ($page - 1) * $num_rec_per_page;
$i = $start_from;
?>
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <style>#overflowon{width:100%;overflow-x:scroll !important;height:100%;overflow-y:hidden;}</style>
                                    <div id="overflowon">
                                        <table id="datatable1" class="display" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Invoice Number</th>
                                                    <th>Date</th>
                                                    <th>Total Amount</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
<?php
$select_debit = "SELECT * FROM user_return_stock WHERE from_usertype='$Login_user_TYPEvl' AND from_userid='$Login_user_IDvl' AND status='accept' ORDER BY id DESC";
$fetch_debit = mysqli_query($db_conn, $select_debit);
while ($result_debit = mysqli_fetch_array($fetch_debit)) {
    $invid = $result_debit['invnumber'];
    $select_inv = "SELECT * FROM user_invoice WHERE inv_id='$invid'";
    $result_inv = mysqli_fetch_array(mysqli_query($db_conn, $select_inv));
?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_inv["inv_number"] ?? '---'; ?></td>
                                                    <td><?php echo date("d/M/Y", strtotime($result_debit["date"])); ?></td>
                                                    <td><?php echo number_format($result_debit["total"], 2, '.', ''); ?></td>
                                                    <td><a href="dnote_details.php?returnid=<?php echo base64_encode($result_debit["returnid"]); ?>"><img src="../../assets/images/details-32.png"/></a></td>
                                                </tr>
<?php } ?>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
</body>
</html>
