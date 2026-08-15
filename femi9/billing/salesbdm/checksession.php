<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
        session_save_path(sys_get_temp_dir());
    }
    session_start();
}

// Include database connection
require_once __DIR__ . '/include/db-connect.php';

// Company "view a BDM's dashboard" bridge — read-only, allowlisted pages
// only. Never touches $_SESSION (so it can't leak into a persistent
// salesbdm login for any other page) — it just resolves $result_LoGuserDtails
// for THIS request, and config.php (included right after) picks it up
// instead of re-querying by $_SESSION['LOGIN_USER']. Includes dashboard.php's
// own AJAX endpoints (Filled/Unassigned Firkas modals) — without them here,
// those fetches hit the normal login check below and get redirected instead
// of returning JSON, which surfaces as "Could not load" in the modal. Also
// includes the "Our Team" submenu (Tree View / Our Team Report), since
// femi_menu.php links to them from every salesbdm page including this
// bridge view — without them here, clicking either link from a bridged
// dashboard redirects to a logged-out page instead of showing the report.
$_companyBridgeAllowedScripts = [
    'dashboard.php', 'get-filled-firka-tps.php', 'get-unassigned-firkas.php',
    'my-team.php', 'my-team-report.php',
];
if (in_array(basename($_SERVER['SCRIPT_NAME']), $_companyBridgeAllowedScripts, true) && !empty($_COOKIE['femi9_company_bdm_view'])) {
    $db_conn->query("CREATE TABLE IF NOT EXISTS company_bdm_view_bridge (
        token VARCHAR(64) PRIMARY KEY,
        bdm_id INT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $stmt_cbv = $db_conn->prepare("
        SELECT s.* FROM company_bdm_view_bridge b
        JOIN sales_bdm_staff s ON s.id = b.bdm_id
        WHERE b.token = ? AND b.expires_at > NOW() LIMIT 1
    ");
    $stmt_cbv->bind_param('s', $_COOKIE['femi9_company_bdm_view']);
    $stmt_cbv->execute();
    $bridgeStaffRow = $stmt_cbv->get_result()->fetch_assoc();
    $stmt_cbv->close();
    if ($bridgeStaffRow && ($bridgeStaffRow['account_status'] ?? '') === 'active') {
        $result_LoGuserDtails = $bridgeStaffRow;
        $log_username = $bridgeStaffRow['bdm_mobile'];
        $Login_user_IDvl = (int)$bridgeStaffRow['id'];
        $Login_user_TYPEvl = 'salesbdm';
        $_companyBridgeView = true;
        return;
    }
}

// Check if user is logged in - Use isset() instead of empty()
if (!isset($_SESSION['LOGIN_USER']) || $_SESSION['LOGIN_USER'] === '' || ($_SESSION['LOGIN_USER_TYPE'] ?? '') !== 'salesbdm') {
    $_SESSION['errorMessage'] = 'Session Expired. Please login again.';
    header('Location: index.php?sessionexpiry');
    exit;
}

// Session timeout check (30 minutes of inactivity)
$timeout_duration = 1800; // 30 minutes

if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];

    if ($elapsed_time > $timeout_duration) {
        // Session expired due to inactivity
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['errorMessage'] = 'Session expired due to inactivity. Please login again.';
        header('Location: index.php?sessionexpiry');
        exit;
    }
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Get user details from database
$log_username = $_SESSION['LOGIN_USER'];
$Login_user_IDvl = $_SESSION['LOGIN_USER_ID'] ?? '';
$Login_user_TYPEvl = $_SESSION['LOGIN_USER_TYPE'] ?? 'salesbdm';

// Fetch user details
$select_LoGuserDtails = "SELECT * FROM sales_bdm_staff WHERE bdm_mobile = ? LIMIT 1";
$stmt_LoGuserDtails = mysqli_prepare($db_conn, $select_LoGuserDtails);
mysqli_stmt_bind_param($stmt_LoGuserDtails, "s", $log_username);
mysqli_stmt_execute($stmt_LoGuserDtails);
$fetch_LoGuserDtails = mysqli_stmt_get_result($stmt_LoGuserDtails);
$result_LoGuserDtails = mysqli_fetch_assoc($fetch_LoGuserDtails);
mysqli_stmt_close($stmt_LoGuserDtails);

// Check if user exists
if (!$result_LoGuserDtails) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'User account not found. Please contact support.';
    header('Location: index.php');
    exit;
}

// Check if account is still active
if (isset($result_LoGuserDtails['account_status']) && $result_LoGuserDtails['account_status'] != 'active') {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'Your account has been deactivated. Please contact support.';
    header('Location: index.php');
    exit;
}

// Set additional session variables if not set
if (!isset($_SESSION['LOGIN_USER_ID'])) {
    $_SESSION['LOGIN_USER_ID'] = $result_LoGuserDtails['id'];
}
if (!isset($_SESSION['LOGIN_USER_NAME'])) {
    $_SESSION['LOGIN_USER_NAME'] = $result_LoGuserDtails['bdm_name'];
}

// All checks passed - user is authenticated
?>
