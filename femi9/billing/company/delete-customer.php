<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('cus_delete');

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from customers where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='manage-customer?deletedDone';</script>";
?>