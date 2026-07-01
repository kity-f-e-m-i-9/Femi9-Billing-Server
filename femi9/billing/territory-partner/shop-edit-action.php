<?php
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
$state_id    = (int)($_POST["state_id"]    ?? 0);
$district_id = (int)($_POST["district_id"] ?? 0);
$taluk_id    = (int)($_POST["taluk_id"]    ?? 0);
$firka_id    = (int)($_POST["firka_id"]    ?? 0);

mysqli_query($db_conn,
    "UPDATE shop SET
        name='$name', email='$email', mobile_number='$mobile',
        address='$address', shop_cat='$shop_cat', gstin='$gstin',
        country_code='$country_code', landline='$landline',
        state_id='$state_id', district_id='$district_id',
        taluk_id='$taluk_id', firka_id='$firka_id'
     WHERE id='$update_id'"
);

echo "<script>window.location='shop-manage.php?updatedSuccess';</script>";
