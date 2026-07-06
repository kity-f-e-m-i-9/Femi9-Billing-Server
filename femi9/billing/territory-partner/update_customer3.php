<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$invid      = $_REQUEST['InvoiceID'] ?? '';
$getinvuser = $_REQUEST['invuser']   ?? 'customer';
$backlink   = "customer-manage-invoice.php";
$title      = "Update Customer";
$lablenamedisplay = "Customer Name";

$inv     = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM invoice WHERE inv_id='$invid' LIMIT 1"));
$CuSTID  = $inv['customer_id'] ?? '';
$resCust = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM customers WHERE id='$CuSTID' LIMIT 1"));
$Cust_Name  = $resCust['name']   ?? '';
$Cust_Mbile = $resCust['mobile'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?> : <?php echo $business_name; ?></title>
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
    <div class="app-sidebar"><?php include("logo.php"); ?><?php include("femi_menu.php"); ?></div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row"><div class="col"><div class="page-description">
                        <h1><table class="headertble"><tr>
                            <td><?php echo $title; ?></td>
                            <td><a href="<?php echo $backlink; ?>" title="Go Back">&#9776;</a></td>
                        </tr></table></h1>
                    </div></div></div>

<?php if (isset($_REQUEST['updatedsuccess'])): ?><div class="alert alert-success">Customer updated successfully.</div><?php endif; ?>
<?php if (isset($_REQUEST['alreadyexists'])): ?><div class="alert alert-danger">Same customer already selected.</div><?php endif; ?>

                    <div class="row"><div class="col-md-12"><div class="card"><div class="card-body">
                        <h5>Invoice Details</h5>
                        <table id="receipttble" style="width:100%;margin-bottom:20px;">
                            <thead><tr>
                                <th>Invoice Number</th>
                                <th><?php echo $lablenamedisplay; ?></th>
                                <th>Invoice Date</th>
                                <th>Invoice Amount</th>
                            </tr></thead>
                            <tbody><tr>
                                <td><?php echo htmlspecialchars($inv['inv_number'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($Cust_Name); ?><br/>M: <?php echo htmlspecialchars($Cust_Mbile); ?></td>
                                <td><?php echo date("d/M/Y", strtotime($inv['date'] ?? '')); ?></td>
                                <td><?php echo inr_format((float)($inv['total'] ?? 0), 2); ?></td>
                            </tr></tbody>
                        </table>

                        <form action="update_customer_action3.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('Confirm update?');">
                        <input type="hidden" name="invid" value="<?php echo $invid; ?>">
                        <input type="hidden" name="invuser" value="<?php echo $getinvuser; ?>">
                        <input type="hidden" name="old_customer_id" value="<?php echo $CuSTID; ?>">
                        <div class="example-container"><div class="example-content">

                        <label class="form-label"><?php echo $lablenamedisplay; ?>*</label>
                        <select required name="new_customer_id" class="form-control">
                        <option value="" hidden>Select Customer</option>
                        <?php
                        $res = mysqli_query($db_conn, "SELECT * FROM customers WHERE user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl' ORDER BY name ASC");
                        while ($rc = mysqli_fetch_array($res)) {
                            $sel = ($rc['id'] == $CuSTID) ? '' : '';
                            echo "<option value='{$rc['id']}'>" . htmlspecialchars(strtoupper($rc['name'])) . ", " . htmlspecialchars($rc['mobile']) . "</option>";
                        }
                        ?>
                        </select>
                        <br/>
                        <button type="submit" name="updateCustomer" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
                        </div></div>
                        </form>
                    </div></div></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
