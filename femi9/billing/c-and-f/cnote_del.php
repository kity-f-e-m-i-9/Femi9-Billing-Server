<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);

	$delRecord="delete from user_return_stock where returnid=?";
	db_q($db_conn,$delRecord,[$returnid_decode]);
	
	$_SESSION['successMessage']="Incomplete Return (Credit Note) Deleted Success !";
	echo "<script>window.location='cnote_manage.php?DeleteSuccess';</script>";

?>