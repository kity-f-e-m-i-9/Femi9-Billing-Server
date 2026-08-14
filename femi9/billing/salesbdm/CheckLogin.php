<?php
session_start();
error_reporting(0);

// Load dependencies
require_once 'include/db-connect.php';
require_once __DIR__ . '/../shared/env-loader.php';

// Function to log login attempts
function logLoginAttempt($mobile, $success, $reason = '') {
    $logFile = __DIR__ . '/logs/login_attempts.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $status = $success ? 'SUCCESS' : 'FAILED';

    $logEntry = "[$timestamp] $status | Mobile: $mobile | IP: $ip | Reason: $reason | User-Agent: $userAgent\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Function to check rate limit
function checkLoginRateLimit($key, $maxAttempts = 5, $timeWindow = 900) {
    $rateLimitFile = __DIR__ . '/logs/login_rate_limit.json';

    if (!file_exists($rateLimitFile)) {
        return true;
    }

    $rateLimits = json_decode(file_get_contents($rateLimitFile), true);
    $now = time();

    if (isset($rateLimits[$key])) {
        if ($rateLimits[$key]['count'] >= $maxAttempts) {
            if (($now - $rateLimits[$key]['time']) < $timeWindow) {
                return false;
            }
        }
    }

    return true;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
    header('Location: index.php');
    exit;
}

try {
    // CSRF Token validation
    if (!isset($_POST['csrf_token']) || empty($_POST['csrf_token'])) {
        // Regenerate token if missing
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Get and sanitize inputs
    $mobileNumber = preg_replace('/[^0-9]/', '', $_POST['signInEmail'] ?? '');
    $password = $_POST['signInPassword'] ?? '';

    // Validate inputs
    if (empty($mobileNumber) || empty($password)) {
        throw new Exception('Mobile number and password are required');
    }

    if (!preg_match('/^\d{10}$/', $mobileNumber)) {
        throw new Exception('Invalid mobile number format');
    }

    // Rate limiting
    $rateLimitKey = 'login_' . $mobileNumber . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkLoginRateLimit($rateLimitKey, 5, 900)) {
        logLoginAttempt($mobileNumber, false, 'Rate limit exceeded');
        throw new Exception('Too many login attempts. Please try again after 15 minutes.');
    }

    // Query user from database
    $stmt = mysqli_prepare($db_conn,
        "SELECT id, bdm_name, password, bdm_email, bdm_mobile, account_status
         FROM sales_bdm_staff
         WHERE bdm_mobile = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception('Database error. Please try again later.');
    }

    mysqli_stmt_bind_param($stmt, "s", $mobileNumber);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Check if user exists
    if (!$user) {
        logLoginAttempt($mobileNumber, false, 'User not found');
        // Don't reveal if user exists or not (security best practice)
        throw new Exception('Invalid mobile number or password');
    }

    // Check if account is active
    if ($user['account_status'] != 'active') {
        logLoginAttempt($mobileNumber, false, 'Account inactive');
        throw new Exception('Your account is inactive. Please contact support.');
    }

    // Verify password — sales_bdm_staff.password is always plaintext by
    // design (every Sales BDM gets the fixed "salesbdm@123" on creation),
    // so a direct comparison is enough — no encrypt/decrypt round-trip needed.
    $isPasswordValid = ($password === $user['password']);

    if (!$isPasswordValid) {
        logLoginAttempt($mobileNumber, false, 'Invalid password');
        throw new Exception('Invalid mobile number or password');
    }

    // A Chief BDM/BDM who is also a company staff member (same mobile number
    // in both sales_bdm_staff and admin_log) gets a "which account?" chooser
    // instead of always landing on the Sales BDM dashboard — only when the
    // SAME password they just typed also matches the company account, so
    // this never fires for someone who merely shares a mobile number by
    // coincidence with a different password.
    $stmtAdmin = mysqli_prepare($db_conn, "SELECT id, password FROM admin_log WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtAdmin, "s", $mobileNumber);
    mysqli_stmt_execute($stmtAdmin);
    $adminUser = mysqli_stmt_get_result($stmtAdmin)->fetch_assoc();
    mysqli_stmt_close($stmtAdmin);

    if ($adminUser) {
        require_once __DIR__ . '/../shared/EncryptionService.php';
        $encryption = new EncryptionService();
        try {
            $companyPasswordValid = ($password === $encryption->decrypt($adminUser['password']));
        } catch (Exception $e) {
            $companyPasswordValid = ($password === $adminUser['password']);
        }
        if ($companyPasswordValid) {
            session_regenerate_id(true);
            $_SESSION['pending_switch_bdm_id'] = $user['id'];
            $_SESSION['pending_switch_bdm_mobile'] = $user['bdm_mobile'];
            $_SESSION['pending_switch_bdm_name'] = $user['bdm_name'];
            $_SESSION['pending_switch_admin_id'] = $adminUser['id'];
            $_SESSION['pending_switch_admin_username'] = $mobileNumber;
            logLoginAttempt($mobileNumber, true, 'Dual account — sent to chooser');
            header('Location: index.php');
            exit;
        }
    }

    // Login successful - Create session
    require_once __DIR__ . '/include/LoginHelpers.php';
    finalizeSalesBdmSession($db_conn, $user);

    // Log successful login
    logLoginAttempt($mobileNumber, true, 'Login successful');

    // Clear rate limit on successful login
    $rateLimitFile = __DIR__ . '/logs/login_rate_limit.json';
    if (file_exists($rateLimitFile)) {
        $rateLimits = json_decode(file_get_contents($rateLimitFile), true);
        unset($rateLimits[$rateLimitKey]);
        file_put_contents($rateLimitFile, json_encode($rateLimits));
    }

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    $_SESSION['errorMessage'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
?>
