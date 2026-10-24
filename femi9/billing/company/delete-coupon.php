<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from coupons where id=?";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='manage-coupon?deletedDone';</script>";
?>