<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$username=base64_decode($_REQUEST['delusername']);

$del_product="delete from admin_log where id=?";
db_q($db_conn,$del_product,[$prid]);

$del_product1222="delete from admin_log_ot where username=?";
db_q($db_conn,$del_product1222,[$username]);

echo "<script>window.location='users_manage?deletedDone';</script>";
?>