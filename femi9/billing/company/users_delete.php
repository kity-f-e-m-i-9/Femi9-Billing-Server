<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$username=base64_decode($_REQUEST['delusername']);

$del_product="delete from admin_log where id='$prid'";
mysqli_query($db_conn,$del_product);

$del_product1222="delete from admin_log_ot where username='$username'";
mysqli_query($db_conn,$del_product1222);

echo "<script>window.location='users_manage?deletedDone';</script>";
?>