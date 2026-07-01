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

// Validate login type is territory_partner
if (isset($_SESSION['LOGIN_USER_TYPE']) && $_SESSION['LOGIN_USER_TYPE'] !== 'territory_partner') {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'Invalid session. Please login again.';
    header('Location: index.php');
    exit;
}

$log_username      = $_SESSION['LOGIN_USER'];
$Login_user_IDvl   = $_SESSION['LOGIN_USER_ID'] ?? '';
$Login_user_TYPEvl = 'territory_partner';

// Fetch fresh user data on every request
$stmt_tp = mysqli_prepare($db_conn,
    "SELECT id, tp_id, name, mobile, email, gstin, address, photo, is_active, must_change_password
     FROM territory_partners WHERE mobile = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt_tp, "s", $log_username);
mysqli_stmt_execute($stmt_tp);
$result_LoGuserDtails = mysqli_stmt_get_result($stmt_tp)->fetch_assoc();
mysqli_stmt_close($stmt_tp);

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

// Force password change if default password has not been reset
if (!empty($result_LoGuserDtails['must_change_password'])) {
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
    if (!in_array($current_page, ['change-password.php', 'logout.php'])) {
        header('Location: change-password.php?forced=1');
        exit;
    }
}

if (!isset($_SESSION['LOGIN_USER_ID'])) {
    $_SESSION['LOGIN_USER_ID'] = $result_LoGuserDtails['id'];
}
if (!isset($_SESSION['LOGIN_USER_NAME'])) {
    $_SESSION['LOGIN_USER_NAME'] = $result_LoGuserDtails['name'];
}
?>
