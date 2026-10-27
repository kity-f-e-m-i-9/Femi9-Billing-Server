<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('location');

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from pincode where id=?";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='manage-pincode?deletedDone';</script>";
?>