<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include("include/db-connect.php");

// Check if user is logged in
if (empty($_SESSION['LOGIN_USER'])) {
    $_SESSION['errorMessage'] = 'Session Expired. Please login again.';
    header('Location: ../login/index.php');
    exit;
}

// The session may belong to a different account type (e.g. after switching
// accounts, or a session shared across panels) — reject if it doesn't match.
if (isset($_SESSION['LOGIN_USER_TYPE']) && $_SESSION['LOGIN_USER_TYPE'] !== 'candf') {
    session_unset();
    session_destroy();
    header('Location: ../login/index.php');
    exit;
}

// Session timeout check (30 minutes of inactivity)
$timeout_duration = 1800; // 30 minutes

if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];

    if ($elapsed_time > $timeout_duration) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['errorMessage'] = 'Session expired due to inactivity. Please login again.';
        header('Location: ../login/index.php');
        exit;
    }
}

$_SESSION['last_activity'] = time();

// Get user details from database
$log_username = $_SESSION['LOGIN_USER'];

$select_LoGuserDtails = "SELECT * FROM c_and_f WHERE username = ? LIMIT 1";
$stmt_LoGuserDtails = mysqli_prepare($db_conn, $select_LoGuserDtails);
mysqli_stmt_bind_param($stmt_LoGuserDtails, "s", $log_username);
mysqli_stmt_execute($stmt_LoGuserDtails);
$fetch_LoGuserDtails = mysqli_stmt_get_result($stmt_LoGuserDtails);
$result_LoGuserDtails = mysqli_fetch_assoc($fetch_LoGuserDtails);
mysqli_stmt_close($stmt_LoGuserDtails);

if (!$result_LoGuserDtails) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'User account not found. Please contact support.';
    header('Location: ../login/index.php');
    exit;
}

if (isset($result_LoGuserDtails['account_status']) && $result_LoGuserDtails['account_status'] != 'active') {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['errorMessage'] = 'Your account has been deactivated. Please contact support.';
    header('Location: ../login/index.php');
    exit;
}

if (!isset($_SESSION['LOGIN_USER_ID'])) {
    $_SESSION['LOGIN_USER_ID'] = $result_LoGuserDtails['temp_id'];
}
if (!isset($_SESSION['LOGIN_USER_NAME'])) {
    $_SESSION['LOGIN_USER_NAME'] = $result_LoGuserDtails['name'];
}

// All checks passed - user is authenticated
?>
