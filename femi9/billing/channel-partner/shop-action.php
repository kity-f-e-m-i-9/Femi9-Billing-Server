<?php require_once __DIR__ . '/../shared/db-helpers.php';
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
$chk = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) AS n FROM shop WHERE temp_id='".db_esc($temp_id)."'"));
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
    ('".db_esc($state_id)."','".db_esc($temp_id)."','".db_esc($user_icon)."','".db_esc($name)."','".db_esc($district_id)."','".db_esc($email)."','".db_esc($mobile)."',
     'Nil','Nil','0','1','".db_esc($valid_from)."','".db_esc($valid_to)."','Nil','Nil','Nil','Nil','Nil','Nil','Nil',
     '".db_esc($taluk_id)."','','".db_esc($pincode_id)."','".db_esc($gstin)."',
     '".db_esc($Login_user_TYPEvl)."','".db_esc($Login_user_IDvl)."',
     '".db_esc($address)."','".db_esc($userid)."','".db_esc($useridtext)."','".db_esc($shop_cat)."','".db_esc($country_code)."','".db_esc($landline)."')");

echo "<script>window.location='shop-manage.php?addedsuccess';</script>";
