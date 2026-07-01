<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from products where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='manage-products?deletedDone';</script>";
?>