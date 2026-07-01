<?php 
session_start();
//session_unset();
//session_destroy();
unset($_SESSION['LOGIN_USER']);

$_SESSION['successMessage']="Logout successfully";
echo "<script>window.location='index?outsuc';</script>";
?>