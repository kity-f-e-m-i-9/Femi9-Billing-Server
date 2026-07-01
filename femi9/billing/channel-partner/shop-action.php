<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: shop-add.php"); exit;
}

$shop_cat    = $_POST["shop_cat"]       ?? '';
$temp_id     = $_POST["temp_id"]        ?? '';
$name        = str_replace("'", "&#39;", $_POST["name"]        ?? '');
$state_id    = $_POST["state_id"]       ?? '';
$district_id = str_replace("'", "&#39;", $_POST["district_id"] ?? '');
$taluk_id    = str_replace("'", "&#39;", $_POST["taluk_id"]    ?? '');
$pincode_id  = str_replace("'", "&#39;", $_POST["pincode_id"]  ?? '');
$country_code= $_POST["country_code"]   ?? '';
$mobile      = str_replace("'", "&#39;", $_POST["mobile_number"]?? '');
$landline    = str_replace("'", "&#39;", $_POST["landline"]    ?? '');
$email       = str_replace("'", "&#39;", $_POST["email"]       ?? '');
$address     = str_replace("'", "&#39;", $_POST["address"]     ?? '');
$gstin       = str_replace("'", "&#39;", $_POST["gstin"]       ?? '');
$user_icon   = "Nil";

// Duplicate check
$chk = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) AS n FROM shop WHERE temp_id='$temp_id'"));
if ((int)$chk['n'] > 0) {
    echo "<script>window.location='shop-add.php?distalready';</script>"; exit;
}

$maxid = mysqli_fetch_array(mysqli_query($db_conn, "SELECT MAX(userid) AS n FROM shop"));
$userid = (int)$maxid['n'] + 1;
$useridtext = "FEMI9-R-" . str_pad($userid, 3, '0', STR_PAD_LEFT);

$valid_from = date("Y-m-d");
$valid_to   = date("Y-m-d", strtotime("+1 months"));

mysqli_query($db_conn, "INSERT INTO shop
    (state_id,temp_id,user_icon,name,district_id,email,mobile_number,username,password,
     plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,
     account_status,merchantOrderId,merchantTransactionId,merchantUserId,
     taluk_id,distributor_id,pincode_id,gstin,onboard_userTYPE,onboard_userID,
     address,userid,useridtext,shop_cat,country_code,landline)
    VALUES
    ('$state_id','$temp_id','$user_icon','$name','$district_id','$email','$mobile',
     'Nil','Nil','0','1','$valid_from','$valid_to','Nil','Nil','Nil','Nil','Nil','Nil','Nil',
     '$taluk_id','','$pincode_id','$gstin',
     '$Login_user_TYPEvl','$Login_user_IDvl',
     '$address','$userid','$useridtext','$shop_cat','$country_code','$landline')");

echo "<script>window.location='shop-manage.php?addedsuccess';</script>";
