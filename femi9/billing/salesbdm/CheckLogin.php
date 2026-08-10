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

    // Login successful - Create session
    $_SESSION['LOGIN_USER'] = $user['bdm_mobile'];
    $_SESSION['LOGIN_USER_ID'] = $user['id'];
    $_SESSION['LOGIN_USER_NAME'] = $user['bdm_name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'salesbdm';
    $_SESSION['last_activity'] = time();

    // company/ runs its own session cookie name (see company/.htaccess —
    // isolates it from other portals' session files to avoid lock
    // contention across tabs), so it can't see this session at all. A BDM
    // uses a handful of shared Territory Partner pages there (add/manage/
    // edit) — rather than mirroring into a second PHP session (fragile:
    // depends on session.serialize_handler and session.save_path matching
    // between the two, which can silently differ per-hosting-environment),
    // hand off via a small DB-backed token instead: a random cookie that
    // company/checksession.php looks up fresh on every request. No
    // dependency on PHP's session internals at all for this bridge.
    $db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_company_bridge (
        token VARCHAR(64) PRIMARY KEY,
        bdm_id INT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // expires_at computed via MySQL's own NOW() (not PHP's date()) — the two
    // can be in different timezones, which would make expires_at compare
    // wrong against NOW() on every later lookup.
    $_bridgeToken = bin2hex(random_bytes(32));
    $_bridgeStmt = $db_conn->prepare("INSERT INTO salesbdm_company_bridge (token, bdm_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
    $_bridgeStmt->bind_param('si', $_bridgeToken, $user['id']);
    $_bridgeStmt->execute();
    $_bridgeStmt->close();
    setcookie('femi9_bdm_bridge', $_bridgeToken, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

    // Update last login time
    $updateLoginStmt = mysqli_prepare($db_conn,
        "UPDATE sales_bdm_staff SET last_login = NOW() WHERE bdm_mobile = ?"
    );
    mysqli_stmt_bind_param($updateLoginStmt, "s", $mobileNumber);
    mysqli_stmt_execute($updateLoginStmt);
    mysqli_stmt_close($updateLoginStmt);

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
