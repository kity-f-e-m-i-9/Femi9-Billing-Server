<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

$delid=$_REQUEST['delid'];
$delid=base64_decode($delid);

$del_product="delete from wallet_withdraw where id=?";
db_q($db_conn,$del_product,[$delid]);

$_SESSION['successMessage']="One Withdraw Request Deleted Success";
echo "<script>window.location='wallet-history?deletedDone';</script>";
?>