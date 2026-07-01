<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
        session_save_path(sys_get_temp_dir());
    }
    session_start();
}

require_once __DIR__ . '/include/db-connect.php';

if (!isset($_SESSION['LOGIN_USER']) || $_SESSION['LOGIN_USER'] === '') {
    $_SESSION['errorMessage'] = 'Session Expired. Please login again.';
    header('Location: index.php?sessionexpiry');
    exit;
}

// 30-minute inactivity timeout
$timeout_duration = 1800;
if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['errorMessage'] = 'Session expired due to inactivity. Please login again.';
        header('Location: index.php?sessionexpiry');
        exit;
    }
}
$_SESSION['last_activity'] = time();

// Validate login type is channel_partner
if (isset($_SESSION['LOGIN_USER_TYPE']) && $_SESSION['LOGIN_USER_TYPE'] !== 'channel_partner') {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'Invalid session. Please login again.';
    header('Location: index.php');
    exit;
}

$log_username = $_SESSION['LOGIN_USER'];

// Fetch fresh CP data on every request
$stmt_cp = mysqli_prepare($db_conn,
    "SELECT id, cp_id, name, mobile, email, is_active
     FROM channel_partners WHERE mobile = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt_cp, "s", $log_username);
mysqli_stmt_execute($stmt_cp);
$result_LoGuserDtails = mysqli_stmt_get_result($stmt_cp)->fetch_assoc();
mysqli_stmt_close($stmt_cp);

if (!$result_LoGuserDtails) {
    session_unset(); session_destroy(); session_start();
    $_SESSION['errorMessage'] = 'User account not found. Please contact support.';
    header('Location: index.php');
    exit;
}

if (!$result_LoGuserDtails['is_active']) {
    session_unset(); session_destroy(); session_start();
    $_SESSION['errorMessage'] = 'Your account has been deactivated. Please contact support.';
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['LOGIN_USER_ID'])) {
    $_SESSION['LOGIN_USER_ID'] = $result_LoGuserDtails['id'];
}
if (!isset($_SESSION['LOGIN_USER_NAME'])) {
    $_SESSION['LOGIN_USER_NAME'] = $result_LoGuserDtails['name'];
}
?>
