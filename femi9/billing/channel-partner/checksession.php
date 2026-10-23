<?php
ini_set('session.gc_maxlifetime', 18000); // match 5-hour app timeout; php.ini default (24min) was killing idle sessions
if (session_status() === PHP_SESSION_NONE) {
    if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
        session_save_path(sys_get_temp_dir());
    }
    session_start();
}

require_once __DIR__ . '/include/db-connect.php';

if (!isset($_SESSION['LOGIN_USER']) || $_SESSION['LOGIN_USER'] === '') {
    $_SESSION['errorMessage'] = 'Session Expired. Please login again.';
    header('Location: ../login/index.php');
    exit;
}

// 5-hour inactivity timeout
$timeout_duration = 18000;
if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['errorMessage'] = 'Session expired due to inactivity. Please login again.';
        header('Location: ../login/index.php');
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
    header('Location: ../login/index.php');
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
    header('Location: ../login/index.php');
    exit;
}

if (!$result_LoGuserDtails['is_active']) {
    session_unset(); session_destroy(); session_start();
    $_SESSION['errorMessage'] = 'Your account has been deactivated. Please contact support.';
    header('Location: ../login/index.php');
    exit;
}

if (!isset($_SESSION['LOGIN_USER_ID'])) {
    $_SESSION['LOGIN_USER_ID'] = $result_LoGuserDtails['id'];
}
if (!isset($_SESSION['LOGIN_USER_NAME'])) {
    $_SESSION['LOGIN_USER_NAME'] = $result_LoGuserDtails['name'];
}

// Force the Channel Partner Agreement to be read & signed before any other
// page is usable -- same gate as territory-partner/checksession.php's
// identical one (see its own comment for why checking is_locked, not just
// "has signed_at", also re-blocks the portal if Company unlocks it again
// for re-signing after an edit).
//
// Only active once Company has switched on "Work All CPs" on
// manage-agreements.php (agreement_settings.cp_sign_enforced) -- before
// that, a CP can use every page as usual, same as before this gate existed.
$__currentPage = basename($_SERVER['PHP_SELF'] ?? '');
if (!in_array($__currentPage, ['agreement.php', 'agreement-action.php', 'change-password.php', 'logout.php'], true)) {
    $__enforceRes = $db_conn->query("SELECT cp_sign_enforced FROM agreement_settings WHERE id = 1");
    $__enforceRow = $__enforceRes ? $__enforceRes->fetch_assoc() : null;
    if ((int) ($__enforceRow['cp_sign_enforced'] ?? 0) === 1) {
        $__stmtAgr = $db_conn->prepare("SELECT is_locked FROM channel_partner_agreements WHERE channel_partner_id = ?");
        $__stmtAgr->bind_param('i', $result_LoGuserDtails['id']);
        $__stmtAgr->execute();
        $__agrRow = $__stmtAgr->get_result()->fetch_assoc();
        $__stmtAgr->close();
        if ((int) ($__agrRow['is_locked'] ?? 0) !== 1) {
            header('Location: agreement.php');
            exit;
        }
    }
}
?>
