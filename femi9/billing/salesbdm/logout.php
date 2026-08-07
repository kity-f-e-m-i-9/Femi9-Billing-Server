<?php
session_start();
error_reporting(0);
unset($_SESSION['LOGIN_USER']);

$_SESSION['successMessage']="Logout successfully";

echo "<script>window.location='index.php?outsuc';</script>";
?>
