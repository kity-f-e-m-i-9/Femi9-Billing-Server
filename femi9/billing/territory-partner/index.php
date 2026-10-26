<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
// See checksession.php for why this is set explicitly rather than left to
// .htaccess/.user.ini's php_value session.name.
session_name('femi9_territory_partner_sess');
session_start();
error_reporting(0);

// Already logged in to this account → go straight to dashboard
if (!empty($_SESSION['LOGIN_USER']) && ($_SESSION['LOGIN_USER_TYPE'] ?? '') === 'territory_partner') {
    header('Location: dashboard.php');
    exit;
}

// Login now happens through the central login page.
header('Location: ../login/index.php');
exit;
