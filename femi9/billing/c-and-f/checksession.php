<?php session_start(); 
include("include/db-connect.php");

if(empty($_SESSION['LOGIN_USER'])) 
{
	$_SESSION['errorMessage'] = 'Session Expired.';
	echo "<script>window.location='index.php?sessionexpiry';</script>";
}
?>
	