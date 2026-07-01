<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$advBalance = 0;
$title = "Add Shop (Retailers)";
$manage_url = "shop-manage.php";

// Stockist's Shop-action.php handles onboarding; we pass the TP session variables
// The onboard_userID and onboard_userTYPE in Shop-action.php will be set by hidden fields
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
                                <?php if (isset($_REQUEST['distalready'])): ?><div class="alert alert-danger">Shop Details Already Exists.</div><?php endif; ?>
                                <h1><table class="headertble"><tr>
                                    <td><?php echo $title; ?></td>
                                    <td><a href="<?php echo $manage_url; ?>" title="Manage Shop">&#9776;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <?php if (isset($_REQUEST['alreadyexists'])): ?><div class="alert alert-danger">Shop already exists!</div><?php endif; ?>
<form action="shop-action.php" method="post" enctype="multipart/form-data" id="uploadForm">

<?php
function GeraHashShopTP($qtd) {
    $Caracteres = '123456789';
    $len = strlen($Caracteres) - 1;
    $Hash = NULL;
    for ($x = 1; $x <= $qtd; $x++) { $Hash .= substr($Caracteres, rand(0, $len), 1); }
    return $Hash;
}
$randum_number = GeraHashShopTP(5);
$temp_date = date("dmy");
$temp_time = date("gis");
$tempID = "" . $randum_number . "FSHP" . $temp_date . "" . $temp_time . "";
?>
<input type="hidden" name="temp_id" value="<?php echo $tempID; ?>">
<input type="hidden" name="distributor_id" value="">
<input type="hidden" name="onboard_userID" value="<?php echo $Login_user_IDvl; ?>">
<input type="hidden" name="onboard_userTYPE" value="<?php echo $Login_user_TYPEvl; ?>">

<div class="example-container">
<div class="example-content">

<label class="form-label">Category*</label>
<select name="shop_cat" class="form-control" required>
    <option value="" hidden>Select</option>
    <?php
    $selectShopCat = "select * from shop_category order by id asc";
    $fetchShopCat = mysqli_query($db_conn, $selectShopCat);
    while ($resultShopCat = mysqli_fetch_array($fetchShopCat)) {
    ?>
    <option value="<?php echo $resultShopCat['id']; ?>"><?php echo $resultShopCat['catlable']; ?></option>
    <?php } ?>
</select>
<br/>

<label class="form-label">Name*</label>
<input type="text" required name="name" class="form-control">
<br/>

<input type="hidden" name="user_icon" value="">

<label class="form-label">State Name*</label>
<select name="state_id" class="form-control" required>
    <option value="">Select State</option>
    <?php
    $sel_states = "SELECT * FROM state ORDER BY st_name ASC";
    $fetch_states = mysqli_query($db_conn, $sel_states);
    while ($res_state = mysqli_fetch_array($fetch_states)) {
    ?>
    <option value="<?php echo $res_state['id']; ?>"><?php echo $res_state['st_name']; ?></option>
    <?php } ?>
</select>
<br/>

<label class="form-label">District Name*</label>
<input type="text" name="district_id" class="form-control" required placeholder="District">
<br/>

<label class="form-label">Taluk Name*</label>
<input type="text" name="taluk_id" class="form-control" placeholder="Taluk (optional)">
<br/>

<label class="form-label">Pincode*</label>
<input type="text" name="pincode_id" class="form-control" required maxlength="15">
<br/>

<div style="display:flex;align-items:center;gap:5px;">
    <div style="flex:0 0 20%;">
        <label class="form-label">Country Code*</label>
        <select name="country_code" required class="form-control">
            <?php
            $selectCountry = "select * from country order by id asc";
            $fetchCountry = mysqli_query($db_conn, $selectCountry);
            while ($resultCountry = mysqli_fetch_array($fetchCountry)) {
            ?>
            <option value="<?php echo $resultCountry['c_code']; ?>"><?php echo $resultCountry['c_name']; ?> (<?php echo $resultCountry['c_code']; ?>)</option>
            <?php } ?>
        </select>
    </div>
    <div style="flex:1;">
        <label class="form-label">Mobile Number*</label>
        <input type="text" required name="mobile_number" pattern="[1-9]{1}[0-9]{9}" class="form-control" maxlength="10">
    </div>
</div>
<br/>

<label class="form-label">Landline Number</label>
<input type="text" name="landline" class="form-control">
<br/>

<label class="form-label">Email ID</label>
<input type="email" name="email" class="form-control">
<br/>

<label class="form-label">Address*</label>
<textarea name="address" class="form-control" required></textarea>
<br/>

<label class="form-label">GST Number</label>
<input type="text" name="gstin" class="form-control">
<br/>

<button type="submit" name="add-superstockiest" onclick="return confirm('Please make a confirm');" class="btn btn-primary">
    <i class="material-icons">add</i>Add
</button>

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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
