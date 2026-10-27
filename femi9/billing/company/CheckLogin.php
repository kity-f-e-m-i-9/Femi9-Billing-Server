<?php
// See checksession.php for why this is set explicitly rather than left to
// .htaccess/.user.ini's php_value session.name. Missing this here (while
// index.php/checksession.php had it) was itself a regression: login would
// write LOGIN_USER into the default-named session while every other page
// read back the femi9_company_sess-named one, so a correct password still
// bounced straight to "session expired" right after signing in.
session_name('femi9_company_sess');
session_start();
error_reporting(0);

// Load dependencies
require_once __DIR__ . '/../shared/env-loader.php';
require_once 'include/db-connect.php';
require_once __DIR__ . '/../shared/EncryptionService.php';

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


// Function to record a failed attempt (without this the limiter never triggers)
function recordFailedAttempt($key) {
    $rateLimitFile = __DIR__ . '/logs/login_rate_limit.json';
    $fp = @fopen($rateLimitFile, 'c+');
    if (!$fp) { return; }
    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $rateLimits = json_decode($raw ?: '[]', true);
        if (!is_array($rateLimits)) { $rateLimits = []; }
        $now = time();
        foreach ($rateLimits as $k => $v) {            // drop stale entries
            if (($now - ($v['time'] ?? 0)) > 3600) { unset($rateLimits[$k]); }
        }
        $count = ($rateLimits[$key]['count'] ?? 0) + 1;
        $rateLimits[$key] = ['count' => $count, 'time' => $now];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($rateLimits));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
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
        "SELECT id, username, password 
         FROM admin_log 
         WHERE username = ? 
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
        recordFailedAttempt($rateLimitKey);
        // Don't reveal if user exists or not (security best practice)
        throw new Exception('Invalid mobile number or password');
    }
    
    // Verify password (bcrypt hash; legacy AES/plain-text rows are accepted once
    // and re-stored as a hash on this successful login)
    require_once __DIR__ . '/../shared/PasswordService.php';
    $isPasswordValid = femi_password_verify($db_conn, $password, (string)$user['password'], 'admin_log', 'username', $mobileNumber);

    if (!$isPasswordValid) {
        logLoginAttempt($mobileNumber, false, 'Invalid password');
        recordFailedAttempt($rateLimitKey);
        throw new Exception('Invalid mobile number or password');
    }
    
    // Login successful - Create session
    $_SESSION['LOGIN_USER'] = $user['username'];
    $_SESSION['LOGIN_USER_ID'] = $user['id'];
    $_SESSION['LOGIN_USER_TYPE'] = 'company';
    $_SESSION['last_activity'] = time();
    
    // Update last login time
    $updateLoginStmt = mysqli_prepare($db_conn,
        "UPDATE admin_log SET last_login = NOW() WHERE username = ?"
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