<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('stock_request');

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from wallet_withdraw where id=?";
db_q($db_conn,$del_product,[$prid]);

$_SESSION['successMessage']='One request deleted success!';
echo "<script>window.location='wallet_request?deletedDone';</script>";
?>