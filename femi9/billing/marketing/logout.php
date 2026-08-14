<?php 
session_start();
error_reporting(0);
//session_unset();
//session_destroy();
unset($_SESSION['LOGIN_USER'], $_SESSION['LOGIN_USER_ID'], $_SESSION['LOGIN_USER_NAME'],
      $_SESSION['LOGIN_USER_TYPE'], $_SESSION['last_activity'], $_SESSION['LINKED_ACCOUNTS']);

if($_REQUEST['action']=="reset")
{
$_SESSION['successMessage']="Password Reset successfully";
}else{
$_SESSION['successMessage']="Logout successfully";
}

echo "<script>window.location='index.php?outsuc';</script>";
?>