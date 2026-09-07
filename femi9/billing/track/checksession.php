<?php
// Standalone login portal — no cross-portal bridge/dual-account logic, by
// explicit request 2026-09-04 ("track ku salesbdm link aagave aagathu" —
// track must never be linked to salesbdm or any other portal).
if (session_status() === PHP_SESSION_NONE) {
    if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
        session_save_path(sys_get_temp_dir());
    }
    session_start();
}

require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/user-config.php';
ensureTrackUsersTable($db_conn);

if (!isset($_SESSION['LOGIN_USER']) || $_SESSION['LOGIN_USER'] === '' || ($_SESSION['LOGIN_USER_TYPE'] ?? '') !== 'track') {
    $_SESSION['errorMessage'] = 'Session Expired. Please login again.';
    header('Location: index.php?sessionexpiry');
    exit;
}

$timeout_duration = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'Session expired due to inactivity. Please login again.';
    header('Location: index.php?sessionexpiry');
    exit;
}
$_SESSION['last_activity'] = time();

$log_username = $_SESSION['LOGIN_USER'];
$Login_user_IDvl = $_SESSION['LOGIN_USER_ID'] ?? '';
$Login_user_TYPEvl = $_SESSION['LOGIN_USER_TYPE'] ?? 'track';

$stmt_LoGuserDtails = $db_conn->prepare("SELECT * FROM track_users WHERE mobile = ? LIMIT 1");
$stmt_LoGuserDtails->bind_param('s', $log_username);
$stmt_LoGuserDtails->execute();
$result_LoGuserDtails = $stmt_LoGuserDtails->get_result()->fetch_assoc();
$stmt_LoGuserDtails->close();

if (!$result_LoGuserDtails) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'User account not found. Please contact support.';
    header('Location: index.php');
    exit;
}

if (($result_LoGuserDtails['account_status'] ?? '') !== 'active') {
    session_unset();
    session_destroy();
    session_start();
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
