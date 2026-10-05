<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('salesbdm_manage_delete');

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from sales_bdm_staff where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='salesbdm_manage?deletedDone';</script>";
?>
