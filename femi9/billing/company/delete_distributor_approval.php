<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireStaffOnly();

$distributorid=$_REQUEST['distributorid'];
$distributorid=base64_decode($distributorid);

$del_product="delete from distributor where id=?";
db_q($db_conn,$del_product,[$distributorid]);

$_SESSION['SuccessMessage']="One Pending Distributor Deleted Successfully!";
echo "<script>window.location='pending-distributor?deletedDone';</script>";
?>