<?php include("checksession.php");
include("config.php");

	$reqid=$_REQUEST['reqid'];
	$reqid_deocde=base64_decode($reqid);
	
	$updateapproved="update stock_request set verified='1' where reqid='$reqid_deocde'";
	mysqli_query($db_conn,$updateapproved);
		
	echo "<script>window.location='stock_request_pending_accounts?updatedSuccess';</script>";
?>