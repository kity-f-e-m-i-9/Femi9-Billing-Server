<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$advBalance = 0;
$displaytitle = "Manage Return (Credit Note)";
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
    $successMessage = addslashes($_SESSION['successMessage']);
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>Swal.fire({ icon:'success', title:'Success', text:'<?php echo $successMessage; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['successMessage']); }
if (isset($_SESSION['errorMessage'])) {
    $errorMessage = addslashes($_SESSION['errorMessage']);
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>Swal.fire({ icon:'error', title:'Error', text:'<?php echo $errorMessage; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['errorMessage']); } ?>
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
                                                    <th>Usertype</th>
                                                    <th>Name</th>
                                                    <th>Return Date</th>
                                                    <th>Return Amount</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
<?php
$select_returns = "SELECT * FROM user_return_stock WHERE to_usertype='$Login_user_TYPEvl' AND to_userid='$Login_user_IDvl' ORDER BY id DESC";
$fetch_returns = mysqli_query($db_conn, $select_returns);
while ($result_ret = mysqli_fetch_array($fetch_returns)) {
    $getinvuser = $result_ret['from_usertype'];

    $lablenamedisplay = ucwords(str_replace('_', ' ', $getinvuser));
    $tablename = '';

    if ($getinvuser == "super_stockiest") { $lablenamedisplay = "Super Stockist"; $tablename = "super_stockiest"; }
    elseif ($getinvuser == "stockiest") { $lablenamedisplay = "Stockist"; $tablename = "stockiest"; }
    elseif ($getinvuser == "super_distributor") { $lablenamedisplay = "Super Distributor"; $tablename = "super_distributor"; }
    elseif ($getinvuser == "distributor") { $lablenamedisplay = "Distributor"; $tablename = "distributor"; }
    elseif ($getinvuser == "shop") { $lablenamedisplay = "Shop"; $tablename = "shop"; }
    elseif ($getinvuser == "outlet") { $lablenamedisplay = "Outlet"; $tablename = "outlet"; }

    if ($getinvuser == "customer") {
        $CuSTID = $result_ret['from_userid'];
        if ($CuSTID != 0) {
            $sel_cust = "SELECT * FROM customers WHERE id='$CuSTID'";
            $res_cust = mysqli_fetch_array(mysqli_query($db_conn, $sel_cust));
            $Cust_Name = $res_cust['name'];
            $Cust_Mobile = $res_cust['mobile'];
        } else {
            $Cust_Name = "Walking Customer";
            $Cust_Mobile = "---";
        }
    } elseif ($tablename) {
        $CuSTID = $result_ret['from_userid'];
        $sel_cust = "SELECT * FROM " . $tablename . " WHERE temp_id='$CuSTID'";
        $res_cust = mysqli_fetch_array(mysqli_query($db_conn, $sel_cust));
        $Cust_Name = $res_cust['name'];
        $Cust_Mobile = $res_cust['mobile_number'];
    } else {
        $Cust_Name = '---';
        $Cust_Mobile = '---';
    }

    $invid = $result_ret['invnumber'];
    $sel_inv = ($getinvuser == "customer") ? "SELECT * FROM invoice WHERE inv_id='$invid'" : "SELECT * FROM user_invoice WHERE inv_id='$invid'";
    $res_inv = mysqli_fetch_array(mysqli_query($db_conn, $sel_inv));
?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $res_inv["inv_number"] ?? '---'; ?></td>
                                                    <td><?php echo $lablenamedisplay; ?></td>
                                                    <td><?php echo $Cust_Name; ?><br/>M: <?php echo $Cust_Mobile; ?></td>
                                                    <td><?php echo date("d/M/Y", strtotime($result_ret["date"])); ?></td>
                                                    <td><?php echo number_format($result_ret["total"], 2, '.', '');
                                                        if ($result_ret["status"] == "pending") { echo "<br/><span class='badge badge-style-bordered badge-danger'>Incomplete</span>"; }
                                                    ?></td>
                                                    <td><a href="cnote_details?returnid=<?php echo base64_encode($result_ret["returnid"]); ?>"><img src="../../assets/images/details-32.png"/></a></td>
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
