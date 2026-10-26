<?php require_once __DIR__ . '/../shared/csrf-guard.php'; femi_csrf_guard();
ini_set('session.gc_maxlifetime', 18000); // match 5-hour app timeout; php.ini default (24min) was killing idle sessions
if (session_status() === PHP_SESSION_NONE) {
    if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
        session_save_path(sys_get_temp_dir());
    }
    // Set explicitly in code, not just via .htaccess/.user.ini's php_value
    // session.name — confirmed on production (2026-10-26) that this portal
    // and company/ were both silently falling back to the same default
    // PHPSESSID cookie there, so opening a TP session from company's
    // "Login as TP" bridge was overwriting the admin's own company session
    // in place. Calling session_name() here guarantees the cookie name
    // regardless of whether the host honors those ini files.
    session_name('femi9_territory_partner_sess');
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

// Validate login type is territory_partner
if (isset($_SESSION['LOGIN_USER_TYPE']) && $_SESSION['LOGIN_USER_TYPE'] !== 'territory_partner') {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'Invalid session. Please login again.';
    header('Location: ../login/index.php');
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
    header('Location: ../login/index.php');
    exit;
}

if (!$result_LoGuserDtails['is_active']) {
    session_unset(); session_destroy(); session_start();
    $_SESSION['errorMessage'] = 'Your account has been deactivated. Please contact support.';
    header('Location: ../login/index.php');
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

// Force the Territory Partner Agreement to be read & signed before any
// other page is usable -- same gate pattern as must_change_password above.
// is_locked only ever becomes 1 via agreement-action.php's own signature
// submission (or stays 0 if Company later unlocks it for re-signing after
// an edit), so this naturally re-blocks the rest of the portal again
// whenever that happens, not just on a brand new account.
//
// Only active once Company has switched on "Work All TPs" on
// manage-agreements.php (agreement_settings.tp_sign_enforced) -- before
// that, a TP can use every page as usual, same as before this gate existed.
$__currentPage = basename($_SERVER['PHP_SELF'] ?? '');
if (!in_array($__currentPage, ['agreement.php', 'agreement-action.php', 'change-password.php', 'logout.php'], true)) {
    $__enforceRes = $db_conn->query("SELECT tp_sign_enforced FROM agreement_settings WHERE id = 1");
    $__enforceRow = $__enforceRes ? $__enforceRes->fetch_assoc() : null;
    if ((int) ($__enforceRow['tp_sign_enforced'] ?? 0) === 1) {
        $__stmtAgr = $db_conn->prepare("SELECT is_locked FROM territory_partner_agreements WHERE territory_partner_id = ?");
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

if (!isset($_SESSION['LOGIN_USER_ID'])) {
    $_SESSION['LOGIN_USER_ID'] = $result_LoGuserDtails['id'];
}
if (!isset($_SESSION['LOGIN_USER_NAME'])) {
    $_SESSION['LOGIN_USER_NAME'] = $result_LoGuserDtails['name'];
}
?>
