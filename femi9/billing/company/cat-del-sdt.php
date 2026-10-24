<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from super_distributor_category where id=?";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='cat-view-sdt?deletedDone';</script>";
?>