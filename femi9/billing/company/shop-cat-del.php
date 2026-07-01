<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from shop_category where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='shop-cat-manage?deletedDone';</script>";
?>