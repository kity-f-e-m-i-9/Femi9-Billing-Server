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

// Sales BDM access to a narrow allowlist of Territory Partner pages —
// verified via a DB-backed bridge token (see salesbdm/CheckLogin.php), not
// a mirrored PHP session, since PHP session portability across the
// salesbdm/ vs company/ session stores (different session.name per
// company/.htaccess) isn't guaranteed across hosting environments. Looked
// up fresh on every request — no dependency on $_SESSION at all for this
// path, so it runs before the normal company-staff login check below,
// which would otherwise reject a BDM outright (they aren't in admin_log).
$_bdmAllowedScripts = [
    'add-territory-partner.php', 'manage-territory-partner.php', 'edit-territory-partner.php',
    'territory-partner-action.php', 'delete-territory-partner.php', 'toggle-partner-status.php',
    'get-tp-flat-nodes.php', 'search-referral-user.php',
];
if (!empty($_COOKIE['femi9_bdm_bridge']) && in_array(basename($_SERVER['SCRIPT_NAME']), $_bdmAllowedScripts, true)) {
    $db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_company_bridge (
        token VARCHAR(64) PRIMARY KEY,
        bdm_id INT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // sales_bdm_staff.zone may not exist yet on every environment (self-migrated
    // lazily by company/salesbdm-team-report.php) — guard here too so this bridge
    // query never 500s on a host that hasn't hit that page yet.
    $_chkZoneCS = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'zone'");
    if ($_chkZoneCS && $_chkZoneCS->num_rows === 0) {
        $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN zone VARCHAR(100) NULL DEFAULT NULL AFTER monthly_target_amount");
    }
    $stmt_bridge = $db_conn->prepare("
        SELECT b.bdm_id, s.bdm_name, s.bdm_mobile, s.zone, s.account_status
        FROM salesbdm_company_bridge b
        JOIN sales_bdm_staff s ON s.id = b.bdm_id
        WHERE b.token = ? AND b.expires_at > NOW()
        LIMIT 1
    ");
    $stmt_bridge->bind_param('s', $_COOKIE['femi9_bdm_bridge']);
    $stmt_bridge->execute();
    $bridgeRow = $stmt_bridge->get_result()->fetch_assoc();
    $stmt_bridge->close();

    if (!$bridgeRow || ($bridgeRow['account_status'] ?? '') !== 'active') {
        setcookie('femi9_bdm_bridge', '', ['expires' => time() - 3600, 'path' => '/']);
        header('Location: ../salesbdm/index.php?sessionexpiry');
        exit;
    }

    // Sliding 30-minute expiry, extended on every valid request.
    $stmt_touch = $db_conn->prepare("UPDATE salesbdm_company_bridge SET expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE token = ?");
    $stmt_touch->bind_param('s', $_COOKIE['femi9_bdm_bridge']);
    $stmt_touch->execute();
    $stmt_touch->close();

    $Login_user_IDvl = (int)$bridgeRow['bdm_id'];
    $Login_user_TYPEvl = 'salesbdm';
    $salesBdmID = (int)$bridgeRow['bdm_id'];
    $_SESSION['LOGIN_USER_NAME'] = $bridgeRow['bdm_name'];
    $_SESSION['LOGIN_USER'] = $bridgeRow['bdm_mobile'];
    // Mirrors what salesbdm/checksession.php's own `SELECT *` produces, so
    // salesbdm/app-header.php (included on these shared pages too) can read
    // bdm_name/zone the same way regardless of which folder served the page.
    $result_LoGuserDtails = $bridgeRow;
    return;
}

// Check if user is logged in
if (!isset($_SESSION['LOGIN_USER']) || $_SESSION['LOGIN_USER'] === '') {
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
$Login_user_TYPEvl = $_SESSION['LOGIN_USER_TYPE'] ?? 'company';

// Fetch user details
$select_LoGuserDtails = "SELECT id, username, usertype FROM admin_log WHERE username = ? LIMIT 1";
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

// Set additional session variables if not set (using correct column names)
if (!isset($_SESSION['LOGIN_USER_ID']) || empty($_SESSION['LOGIN_USER_ID'])) {
    $_SESSION['LOGIN_USER_ID'] = $result_LoGuserDtails['id']; // Changed from temp_id to id
}

// Set username as display name if name column doesn't exist
if (!isset($_SESSION['LOGIN_USER_NAME'])) {
    $_SESSION['LOGIN_USER_NAME'] = $result_LoGuserDtails['username']; // Using username instead of name
}

// All checks passed - user is authenticated
?>