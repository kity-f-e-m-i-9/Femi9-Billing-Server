<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$get_id = mysqli_real_escape_string($db_conn, base64_decode($_REQUEST['prid'] ?? ''));
$shop   = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM shop WHERE id='$get_id' LIMIT 1"));
if (!$shop) { header("Location: shop-manage.php"); exit; }

$shop_cat_id = $shop['shop_cat'];
$cat = mysqli_fetch_array(mysqli_query($db_conn, "SELECT catlable FROM shop_category WHERE id='$shop_cat_id' LIMIT 1"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Shop : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
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
                                    <td>Edit Shop</td>
                                    <td><a href="shop-manage.php" title="Manage Shop">&#9776;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <?php include("validate-scripts.php"); ?>
<form action="shop-edit-action.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="update_id" value="<?php echo $shop['id']; ?>">
<input type="hidden" name="old_icon"  value="<?php echo $shop['user_icon']; ?>">
<div class="example-container"><div class="example-content">

<label class="form-label">Category*</label>
<select name="shop_cat" class="form-control">
    <option value="<?php echo $shop_cat_id; ?>" hidden><?php echo $cat['catlable']; ?></option>
    <?php $r = mysqli_query($db_conn, "SELECT * FROM shop_category ORDER BY id ASC");
    while ($row = mysqli_fetch_array($r)) { ?>
    <option value="<?php echo $row['id']; ?>"><?php echo $row['catlable']; ?></option>
    <?php } ?>
</select><br/>

<label class="form-label">Name</label>
<input type="text" required name="name" value="<?php echo htmlspecialchars($shop['name']); ?>" class="form-control" onkeypress="restrictSpecialChars(event)"><br/>

<div style="display:flex;align-items:center;gap:5px;">
    <div style="flex:0 0 20%;">
        <label class="form-label">Country Code*</label>
        <select name="country_code" required class="form-control">
            <option value="<?php echo $shop['country_code']; ?>" hidden><?php echo $shop['country_code']; ?></option>
            <?php $rc = mysqli_query($db_conn, "SELECT * FROM country ORDER BY c_name ASC");
            while ($rowc = mysqli_fetch_array($rc)) { ?>
            <option value="<?php echo $rowc['c_code']; ?>"><?php echo $rowc['c_name']; ?> (<?php echo $rowc['c_code']; ?>)</option>
            <?php } ?>
        </select>
    </div>
    <div style="flex:1;">
        <label class="form-label">Mobile Number*</label>
        <input type="text" required name="mobile_number" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" value="<?php echo htmlspecialchars($shop['mobile_number']); ?>" class="form-control" maxlength="10">
    </div>
</div><br/>

<label class="form-label">Landline Number</label>
<input type="text" onkeypress="restrictlandline(event)" value="<?php echo htmlspecialchars($shop['landline']); ?>" name="landline" class="form-control"><br/>

<label class="form-label">Email ID</label>
<input type="email" value="<?php echo htmlspecialchars($shop['email']); ?>" name="email" class="form-control"><br/>

<label class="form-label">Address*</label>
<textarea name="address" class="form-control" required><?php echo htmlspecialchars($shop['address']); ?></textarea><br/>

<label class="form-label">GST Number</label>
<input type="text" name="gstin" class="form-control" value="<?php echo htmlspecialchars($shop['gstin']); ?>"><br/>

<button type="submit" name="update-shop" class="btn btn-primary">
    <i class="material-icons">update</i>Update
</button>
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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
