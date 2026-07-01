<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from wallet_withdraw where id='$prid'";
mysqli_query($db_conn,$del_product);

$_SESSION['successMessage']='One request deleted success!';
echo "<script>window.location='wallet_request?deletedDone';</script>";
?>