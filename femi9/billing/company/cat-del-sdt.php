<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from super_distributor_category where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='cat-view-sdt?deletedDone';</script>";
?>