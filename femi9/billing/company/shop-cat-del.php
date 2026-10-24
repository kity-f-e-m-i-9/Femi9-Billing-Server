<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('shop_delete');

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from shop_category where id=?";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='shop-cat-manage?deletedDone';</script>";
?>