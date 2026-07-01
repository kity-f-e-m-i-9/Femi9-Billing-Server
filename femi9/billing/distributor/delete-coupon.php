<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from coupons where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='manage-coupon.php?deletedDone';</script>";
?>