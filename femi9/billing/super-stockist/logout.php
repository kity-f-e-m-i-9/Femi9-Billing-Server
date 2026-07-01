<?php 
session_start();
error_reporting(0);
//session_unset();
//session_destroy();
unset($_SESSION['LOGIN_USER']);

if($_REQUEST['action']=="reset")
{
$_SESSION['successMessage']="Password Reset successfully";
}else{
$_SESSION['successMessage']="Logout successfully";
}

echo "<script>window.location='index.php?outsuc';</script>";
?>