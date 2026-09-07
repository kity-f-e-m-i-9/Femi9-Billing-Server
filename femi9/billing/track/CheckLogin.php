<?php
session_start();
error_reporting(0);

require_once 'include/db-connect.php';
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/user-config.php';
ensureTrackUsersTable($db_conn);

function logLoginAttempt($mobile, $success, $reason = '') {
    $logFile = __DIR__ . '/logs/login_attempts.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $status = $success ? 'SUCCESS' : 'FAILED';
    file_put_contents($logFile, "[$timestamp] $status | Mobile: $mobile | IP: $ip | Reason: $reason | User-Agent: $userAgent\n", FILE_APPEND);
}

function checkLoginRateLimit($key, $maxAttempts = 5, $timeWindow = 900) {
    $rateLimitFile = __DIR__ . '/logs/login_rate_limit.json';
    if (!file_exists($rateLimitFile)) return true;
    $rateLimits = json_decode(file_get_contents($rateLimitFile), true);
    $now = time();
    if (isset($rateLimits[$key]) && $rateLimits[$key]['count'] >= $maxAttempts && ($now - $rateLimits[$key]['time']) < $timeWindow) {
        return false;
    }
    return true;
}

function recordFailedAttempt($key) {
    $rateLimitFile = __DIR__ . '/logs/login_rate_limit.json';
    $rateLimits = file_exists($rateLimitFile) ? json_decode(file_get_contents($rateLimitFile), true) : [];
    $now = time();
    if (isset($rateLimits[$key]) && ($now - $rateLimits[$key]['time']) < 900) {
        $rateLimits[$key]['count']++;
    } else {
        $rateLimits[$key] = ['count' => 1, 'time' => $now];
    }
    file_put_contents($rateLimitFile, json_encode($rateLimits));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
    header('Location: index.php');
    exit;
}

try {
    if (!isset($_POST['csrf_token']) || empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? null)) {
        throw new Exception('Your session expired — please try again.');
    }

    $mobileNumber = preg_replace('/[^0-9]/', '', $_POST['signInEmail'] ?? '');
    $password = $_POST['signInPassword'] ?? '';

    if (empty($mobileNumber) || empty($password)) {
        throw new Exception('Mobile number and password are required');
    }
    if (!preg_match('/^\d{10}$/', $mobileNumber)) {
        throw new Exception('Invalid mobile number format');
    }

    $rateLimitKey = 'login_' . $mobileNumber . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkLoginRateLimit($rateLimitKey, 5, 900)) {
        logLoginAttempt($mobileNumber, false, 'Rate limit exceeded');
        throw new Exception('Too many login attempts. Please try again after 15 minutes.');
    }

    $stmt = $db_conn->prepare("SELECT id, name, password, email, mobile, account_status FROM track_users WHERE mobile = ? LIMIT 1");
    $stmt->bind_param("s", $mobileNumber);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        recordFailedAttempt($rateLimitKey);
        logLoginAttempt($mobileNumber, false, 'User not found');
        throw new Exception('Invalid mobile number or password');
    }

    if ($user['account_status'] !== 'active') {
        logLoginAttempt($mobileNumber, false, 'Account inactive');
        throw new Exception('Your account is inactive. Please contact support.');
    }

    if (!password_verify($password, $user['password'])) {
        recordFailedAttempt($rateLimitKey);
        logLoginAttempt($mobileNumber, false, 'Invalid password');
        throw new Exception('Invalid mobile number or password');
    }

    $_SESSION['LOGIN_USER'] = $user['mobile'];
    $_SESSION['LOGIN_USER_ID'] = $user['id'];
    $_SESSION['LOGIN_USER_NAME'] = $user['name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'track';
    $_SESSION['last_activity'] = time();

    $upd = $db_conn->prepare("UPDATE track_users SET last_login = NOW() WHERE id = ?");
    $upd->bind_param('i', $user['id']);
    $upd->execute();
    $upd->close();

    logLoginAttempt($mobileNumber, true, 'Login successful');

    $rateLimitFile = __DIR__ . '/logs/login_rate_limit.json';
    if (file_exists($rateLimitFile)) {
        $rateLimits = json_decode(file_get_contents($rateLimitFile), true);
        unset($rateLimits[$rateLimitKey]);
        file_put_contents($rateLimitFile, json_encode($rateLimits));
    }

    session_regenerate_id(true);
    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    $_SESSION['errorMessage'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
?>
