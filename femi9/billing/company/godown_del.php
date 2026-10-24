<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
require_once("include/GodownAccess.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

if (!is_godown_allowed($db_conn, (int)$prid)) {
	echo "<script>window.location='godown?unauthorized';</script>";
	exit;
}

$del_product="delete from company_godown where id=?";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='godown?deletedDone';</script>";
?>