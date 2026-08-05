<?php
session_start();
error_reporting(0);

// Already logged in to this account → go straight to dashboard
if (!empty($_SESSION['LOGIN_USER']) && ($_SESSION['LOGIN_USER_TYPE'] ?? '') === 'stockiest') {
    header('Location: dashboard.php');
    exit;
}

// Login now happens through the central login page.
header('Location: ../login/index.php');
exit;
