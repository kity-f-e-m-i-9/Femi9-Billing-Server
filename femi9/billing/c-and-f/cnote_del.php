<?php include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);

	$delRecord="delete from user_return_stock where returnid='$returnid_decode'";
	mysqli_query($db_conn,$delRecord);
	
	$_SESSION['successMessage']="Incomplete Return (Credit Note) Deleted Success !";
	echo "<script>window.location='cnote_manage.php?DeleteSuccess';</script>";

?>