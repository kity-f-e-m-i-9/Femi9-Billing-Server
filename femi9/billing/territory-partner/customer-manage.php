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
    <title>Manage Customers : <?php echo $business_name; ?></title>
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
                                <?php if (isset($_REQUEST['addesuccess'])): ?><div class="alert alert-success">Customer added successfully.</div><?php endif; ?>
                                <?php if (isset($_REQUEST['updatedSuccess'])): ?><div class="alert alert-info">Changes saved.</div><?php endif; ?>
                                <?php if (isset($_REQUEST['deletedDone'])): ?><div class="alert alert-warning">Customer deleted.</div><?php endif; ?>
                                <h1><table class="headertble"><tr>
                                    <td>Manage Customers</td>
                                    <td><a href="customer-add.php" title="Add Customer">&#10011;</a></td>
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
                                                    <th>#</th>
                                                    <th>Name</th>
                                                    <th>Mobile</th>
                                                    <th>Email</th>
                                                    <th>GSTIN</th>
                                                    <th>Address</th>
                                                    <th>Date</th>
                                                    <th>Edit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
<?php
$select_customers = "SELECT * FROM customers WHERE user_id='$Login_user_IDvl' ORDER BY id ASC";
$fetch_customers = mysqli_query($db_conn, $select_customers);
while ($result_cust = mysqli_fetch_array($fetch_customers)) {
    $product_id = base64_encode($result_cust["id"]);
?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo htmlspecialchars($result_cust["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($result_cust["country_code"]); ?>&nbsp;<?php echo htmlspecialchars($result_cust["mobile"]); ?></td>
                                                    <td><?php echo htmlspecialchars($result_cust["email"]); ?></td>
                                                    <td><?php echo htmlspecialchars($result_cust["gstin"]); ?></td>
                                                    <td><?php echo htmlspecialchars($result_cust["address"]); ?></td>
                                                    <td><?php echo date("d/M/Y", strtotime($result_cust["marketing_date"])); ?></td>
                                                    <td><a href="customer-edit.php?prid=<?php echo $product_id; ?>"><img src="../../assets/images/edit-32.png"/></a>&nbsp;<a href="customer-delete.php?prid=<?php echo $product_id; ?>" onclick="return confirm('Delete this customer?');"><img src="../../assets/images/delete-32.png"/></a></td>
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
