<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireStaffOnly();
include("config.php");

	$reqid=$_REQUEST['reqid'];
	$reqid_deocde=base64_decode($reqid);
	
	$updateapproved="update stock_request set verified='1' where reqid=?";
	db_q($db_conn,$updateapproved,[$reqid_deocde]);
		
	echo "<script>window.location='stock_request_pending_accounts?updatedSuccess';</script>";
?>