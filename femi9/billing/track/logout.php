<?php
session_start();
unset($_SESSION['LOGIN_USER'], $_SESSION['LOGIN_USER_ID'], $_SESSION['LOGIN_USER_NAME'], $_SESSION['LOGIN_USER_TYPE']);
$_SESSION['successMessage'] = 'You have been logged out.';
echo "<script>window.location='index.php?outsuc';</script>";
?>
