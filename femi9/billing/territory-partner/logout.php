<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
session_start();
error_reporting(0);

unset($_SESSION['LOGIN_USER'], $_SESSION['LOGIN_USER_ID'], $_SESSION['LOGIN_USER_NAME'],
      $_SESSION['LOGIN_USER_TYPE'], $_SESSION['last_activity']);

if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'reset') {
    $_SESSION['successMessage'] = 'Password reset successfully.';
} else {
    $_SESSION['successMessage'] = 'Logged out successfully.';
}

echo "<script>window.location='index.php?outsuc';</script>";
?>
