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

// Check if user is logged in - Use isset() instead of empty()
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
$Login_user_TYPEvl = $_SESSION['LOGIN_USER_TYPE'] ?? 'marketing';

// Fetch user details
$select_LoGuserDtails = "SELECT * FROM marketing_staff WHERE ms_mobile = ? LIMIT 1";
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
    $_SESSION['LOGIN_USER_NAME'] = $result_LoGuserDtails['ms_name'];
}

// All checks passed - user is authenticated
?>