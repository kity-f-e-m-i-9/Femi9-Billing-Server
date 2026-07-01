<?php include("checksession.php");

$distributorid=$_REQUEST['distributorid'];
$distributorid=base64_decode($distributorid);

$del_product="delete from super_distributor where temp_id='$distributorid'";
mysqli_query($db_conn,$del_product);

$del_product23="delete from super_distributor_referral where sd_id='$distributorid'";
mysqli_query($db_conn,$del_product23);

$_SESSION['SuccessMessage']="One Pending Super Distributor Deleted Successfully!";
echo "<script>window.location='pending_super_distributor?deletedDone';</script>";
?>