<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: shop-manage.php"); exit;
}

$update_id    = (int)$_POST["update_id"];
$shop_cat     = $_POST["shop_cat"]      ?? '';
$name         = str_replace("'", "&#39;", $_POST["name"]         ?? '');
$mobile       = str_replace("'", "&#39;", $_POST["mobile_number"]?? '');
$landline     = str_replace("'", "&#39;", $_POST["landline"]     ?? '');
$email        = str_replace("'", "&#39;", $_POST["email"]        ?? '');
$address      = str_replace("'", "&#39;", $_POST["address"]      ?? '');
$gstin        = str_replace("'", "&#39;", $_POST["gstin"]        ?? '');
$country_code = $_POST["country_code"]  ?? '';

mysqli_query($db_conn,
    "UPDATE shop SET
        name='".db_esc($name)."', email='".db_esc($email)."', mobile_number='".db_esc($mobile)."',
        address='".db_esc($address)."', shop_cat='".db_esc($shop_cat)."', gstin='".db_esc($gstin)."',
        country_code='".db_esc($country_code)."', landline='".db_esc($landline)."'
     WHERE id='".db_esc($update_id)."'"
);

echo "<script>window.location='shop-manage.php?updatedSuccess';</script>";
