<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireStaffOnly();

$distributorid=$_REQUEST['distributorid'];
$distributorid=base64_decode($distributorid);

$del_product="delete from super_distributor where temp_id=?";
db_q($db_conn,$del_product,[$distributorid]);

$del_product23="delete from super_distributor_referral where sd_id=?";
db_q($db_conn,$del_product23,[$distributorid]);

$_SESSION['SuccessMessage']="One Pending Super Distributor Deleted Successfully!";
echo "<script>window.location='pending_super_distributor?deletedDone';</script>";
?>