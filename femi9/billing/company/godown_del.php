<?php include("checksession.php");
require_once("include/GodownAccess.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

if (!is_godown_allowed($db_conn, (int)$prid)) {
	echo "<script>window.location='godown?unauthorized';</script>";
	exit;
}

$del_product="delete from company_godown where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='godown?deletedDone';</script>";
?>