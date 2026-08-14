<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
session_start();
error_reporting(0);

// Already logged in to this account → go straight to dashboard
if (!empty($_SESSION['LOGIN_USER']) && ($_SESSION['LOGIN_USER_TYPE'] ?? '') === 'channel_partner') {
    header('Location: dashboard.php');
    exit;
}

// Login now happens through the central login page.
header('Location: ../login/index.php');
exit;
