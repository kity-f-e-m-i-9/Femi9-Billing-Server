<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$invid      = $_REQUEST['InvoiceID'] ?? '';
$getinvuser = $_REQUEST['invuser']   ?? 'shop';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update Shop : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
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
                                <h1><table class="headertble"><tr>
                                    <td>Update Shop</td>
                                    <td><a href="shop-manage-invoice.php" title="Go Back">&#9776;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>
<?php if (isset($_REQUEST['updatedsuccess'])): ?><div class="alert alert-success">Shop updated.</div><?php endif; ?>
<?php if (isset($_REQUEST['alreadyexists'])): ?><div class="alert alert-danger">This shop is already on the invoice.</div><?php endif; ?>
<?php
$inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='$invid' LIMIT 1"));
$shopRow = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM shop WHERE temp_id='{$inv['to_user_id']}' LIMIT 1"));
?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <table style="width:100%;" class="table">
                                        <thead><tr><th>Invoice Number</th><th>Shop Name</th><th>Invoice Date</th><th>Invoice Amount</th></tr></thead>
                                        <tbody>
                                        <tr>
                                            <td><?php echo $inv['inv_number']; ?></td>
                                            <td><?php echo $shopRow['name']; ?><br/>M:&nbsp;<?php echo $shopRow['mobile_number']; ?></td>
                                            <td><?php echo date("d/M/Y", strtotime($inv['date'])); ?></td>
                                            <td><?php echo number_format($inv['total'], 2, '.', ''); ?></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <form action="update_customer_action2.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('Confirm?');">
                                        <input type="hidden" name="invid"           value="<?php echo $invid; ?>">
                                        <input type="hidden" name="invuser"         value="<?php echo $getinvuser; ?>">
                                        <input type="hidden" name="old_customer_id" value="<?php echo $inv['to_user_id']; ?>">
                                        <div class="example-container"><div class="example-content">
                                        <label class="form-label">Shop Name*</label>
                                        <select required name="new_customer_id" class="js-states form-control">
                                            <option value="" hidden>Select</option>
                                            <?php
                                            $shops = mysqli_query($db_conn, "SELECT * FROM shop WHERE onboard_userTYPE='$Login_user_TYPEvl' AND onboard_userID='$Login_user_IDvl' ORDER BY name ASC");
                                            while ($s = mysqli_fetch_array($shops)) { ?>
                                            <option value="<?php echo $s['temp_id']; ?>"><?php echo strtoupper($s['name']); ?>, <?php echo $s['mobile_number']; ?></option>
                                            <?php } ?>
                                        </select><br/>
                                        <button type="submit" name="updateCustomer" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
                                        </div></div>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/select2.js"></script>
</body>
</html>
