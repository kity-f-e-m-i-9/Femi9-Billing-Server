<?php include("checksession.php");

$distributorid=$_REQUEST['distributorid'];
$distributorid=base64_decode($distributorid);

$del_product="delete from distributor where id='$distributorid'";
mysqli_query($db_conn,$del_product);

$_SESSION['SuccessMessage']="One Pending Distributor Deleted Successfully!";
echo "<script>window.location='pending-distributor?deletedDone';</script>";
?>