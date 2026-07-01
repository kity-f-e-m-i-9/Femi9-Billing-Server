<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$del_product="delete from company_godown where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='godown?deletedDone';</script>";
?>