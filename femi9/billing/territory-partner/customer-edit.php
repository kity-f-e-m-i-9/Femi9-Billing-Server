<?php
include("checksession.php");
include("config.php");
$prid = (int)base64_decode($_REQUEST['prid'] ?? '');
$res  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM customers WHERE id='$prid'"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update Customer : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <?php include("validate-scripts.php"); ?>
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
                            <td>Update Customer</td>
                            <td><a href="customer-manage.php" title="Manage Customers">&#9776;</a></td>
                        </tr></table></h1>
                    </div></div></div>
                    <div class="row"><div class="col-md-12"><div class="card"><div class="card-body">

<form action="customer-action.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="update_id" value="<?php echo $res['id']; ?>">
<div class="example-container"><div class="example-content">

<label class="form-label">Customer Name*</label>
<input type="text" value="<?php echo htmlspecialchars($res['name']); ?>" onkeypress="restrictSpecialChars(event)" required name="name" class="form-control">
<br/>

<style>.form-group{display:flex;align-items:center;gap:5px;}.form-group .country-code{flex:0 0 20%;}.form-group .mobile-number{flex:1;}</style>
<div class="form-group">
    <div class="country-code">
        <label class="form-label">Country Code*</label>
        <select name="country_code" required class="form-control">
        <option value="<?php echo htmlspecialchars($res['country_code']); ?>" hidden><?php echo htmlspecialchars($res['country_code']); ?></option>
        <?php $fc = mysqli_query($db_conn, "SELECT * FROM country ORDER BY c_name ASC"); while ($rc = mysqli_fetch_array($fc)) { ?>
        <option value="<?php echo $rc['c_code']; ?>"><?php echo $rc['c_name']; ?> (<?php echo $rc['c_code']; ?>)</option>
        <?php } ?>
        </select>
    </div>
    <div class="mobile-number">
        <label class="form-label">Mobile Number*</label>
        <input type="text" required name="mobile" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" value="<?php echo htmlspecialchars($res['mobile']); ?>" class="form-control" maxlength="10">
    </div>
</div>
<br/>

<label class="form-label">Email ID</label>
<input type="email" name="email" onkeypress="restrictemail(event)" value="<?php echo htmlspecialchars($res['email']); ?>" class="form-control" placeholder="optional">
<br/>

<label class="form-label">GSTIN</label>
<input type="text" name="gstin" onkeypress="restrictGSTIN(event)" class="form-control" value="<?php echo htmlspecialchars($res['gstin']); ?>" placeholder="optional">
<br/>

<label class="form-label">Address</label>
<textarea name="address" onkeypress="restrictSpecialChars(event)" class="form-control" placeholder="optional"><?php echo htmlspecialchars($res['address']); ?></textarea>
<br/>

<input type="hidden" name="marketing_date" value="<?php echo $res['marketing_date']; ?>">

<button type="submit" name="update-customer" class="btn btn-primary">Update</button>

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
