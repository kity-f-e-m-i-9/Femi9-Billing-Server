<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('products_delete');

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from products where id=? and (temp_id not like 'NKS-%' or temp_id is null)";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='manage-products?deletedDone';</script>";
?>