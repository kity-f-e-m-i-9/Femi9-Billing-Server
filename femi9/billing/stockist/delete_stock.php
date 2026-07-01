<?php include("checksession.php");

$prid=$_REQUEST['rowid'];
$prid=base64_decode($prid);

$del_product="delete from stock where id='$prid'";
mysqli_query($db_conn,$del_product);

$_SESSION['successMessage']="One product stock deleted";
echo "<script>window.location='overall-stock.php?deletedDone';</script>";
?>