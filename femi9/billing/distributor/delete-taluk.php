<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from taluk where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='manage-taluk.php?deletedDone';</script>";
?>