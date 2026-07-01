<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Demo/Free/Damage : <?php echo $business_name; ?></title>
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
<?php
if (isset($_SESSION['sucMessage'])) {
    $sucMessage = $_SESSION['sucMessage'];
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>Swal.fire({ icon:'success', title:'Success', text:'<?php echo $sucMessage; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['sucMessage']); } ?>
                                <h1><table class="headertble"><tr>
                                    <td>Manage Demo/Free/Damage</td>
                                    <td><a href="demofree-new.php" title="Add Demo/Free/Damage">&#10011;</a></td>
                                </tr></table></h1>
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
                                    <div style="overflow-x:scroll;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Category</th>
                                                    <th>Date</th>
                                                    <th>Remarks</th>
                                                    <?php
                                                    $select_prdetails_header = "select * from `products` order by `id` asc";
                                                    $fetch_prdetails_header = mysqli_query($db_conn, $select_prdetails_header);
                                                    while ($result_prdetails_header = mysqli_fetch_array($fetch_prdetails_header)) {
                                                    ?>
                                                    <th><?php echo $result_prdetails_header['productName']; ?></th>
                                                    <?php } ?>
                                                    <th>Details</th>
                                                    <th>Edit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
<?php
$select_tempids = "SELECT DISTINCT `tempid` FROM `demofreedamage` WHERE usertype='$Login_user_TYPEvl' AND userid='$Login_user_IDvl'";
$fetch_tempids = mysqli_query($db_conn, $select_tempids);
while ($rowTempid = mysqli_fetch_array($fetch_tempids)) {
    $tempid = $rowTempid["tempid"];

    $select_rec = "SELECT * FROM demofreedamage WHERE tempid='$tempid'";
    $fetch_rec = mysqli_query($db_conn, $select_rec);
    $ResultRecords = mysqli_fetch_array($fetch_rec);
?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $ResultRecords["category"]; ?></td>
                                                    <td><?php echo date("d/M/Y", strtotime($ResultRecords["date"])); ?></td>
                                                    <td><?php echo $ResultRecords["remarks"]; ?></td>
<?php
    $select_prdetails_header = "select * from `products` order by `id` asc";
    $fetch_prdetails_header = mysqli_query($db_conn, $select_prdetails_header);
    while ($result_prdetails_header = mysqli_fetch_array($fetch_prdetails_header)) {
        $prid_header = $result_prdetails_header['id'];
        $select_SUM_QTY = "SELECT qty FROM demofreedamage WHERE tempid='$tempid' AND product_id='$prid_header'";
        $fetch_SUM_QTY = mysqli_query($db_conn, $select_SUM_QTY);
        $result_SUM_QTY = mysqli_fetch_array($fetch_SUM_QTY);
        $slsqty = ($result_SUM_QTY['qty'] != NULL) ? $result_SUM_QTY['qty'] : "0";
?>
                                                    <td><?php echo $slsqty; ?></td>
<?php } ?>
                                                    <td><a href="demofree_details.php?tempid=<?php echo urlencode($tempid); ?>"><img src="../../assets/images/details-32.png"/></a></td>
                                                    <td><a href="demofree_edit.php?tempid=<?php echo urlencode($tempid); ?>"><img src="../../assets/images/edit-32.png"/></a></td>
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
