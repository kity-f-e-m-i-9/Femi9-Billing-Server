<?php include("checksession.php");
include("config.php");
error_reporting(0);

$delid=$_REQUEST['delid'];
$delid=base64_decode($delid);

$del_product="delete from wallet_withdraw where id='$delid'";
mysqli_query($db_conn,$del_product);

$_SESSION['successMessage']="One Withdraw Request Deleted Success";
echo "<script>window.location='wallet-history?deletedDone';</script>";
?>