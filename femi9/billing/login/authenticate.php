<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
session_start();
error_reporting(0);

require_once 'include/db-connect.php';
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/account-lib.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function logLoginAttempt(string $mobile, bool $success, string $reason = ''): void {
    $logFile = __DIR__ . '/logs/login_attempts.log';
    $entry = sprintf(
        "[%s] %s | Mobile: %s | IP: %s | Reason: %s | UA: %s\n",
        date('Y-m-d H:i:s'),
        $success ? 'SUCCESS' : 'FAILED',
        $mobile,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $reason,
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    );
    file_put_contents($logFile, $entry, FILE_APPEND);
}

function checkRateLimit(string $key, int $maxAttempts = 5, int $window = 900): bool {
    $file = __DIR__ . '/logs/login_rate_limit.json';
    if (!file_exists($file)) return true;

    $data = json_decode(file_get_contents($file), true) ?? [];
    if (isset($data[$key])) {
        if ($data[$key]['count'] >= $maxAttempts && (time() - $data[$key]['time']) < $window) {
            return false;
        }
    }
    return true;
}

function recordFailedAttempt(string $key): void {
    $file = __DIR__ . '/logs/login_rate_limit.json';
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    if (!isset($data[$key]) || (time() - $data[$key]['time']) >= 900) {
        $data[$key] = ['count' => 1, 'time' => time()];
    } else {
        $data[$key]['count']++;
    }
    file_put_contents($file, json_encode($data));
}

function clearRateLimit(string $key): void {
    $file = __DIR__ . '/logs/login_rate_limit.json';
    if (!file_exists($file)) return;
    $data = json_decode(file_get_contents($file), true) ?? [];
    unset($data[$key]);
    file_put_contents($file, json_encode($data));
}

// ── Guard ─────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
    header('Location: index.php');
    exit;
}

try {
    // CSRF
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        throw new Exception('Invalid request. Please try again.');
    }

    $mobile   = preg_replace('/[^0-9]/', '', $_POST['signInEmail'] ?? '');
    $password = $_POST['signInPassword'] ?? '';

    if (empty($mobile) || empty($password)) {
        throw new Exception('Mobile number and password are required.');
    }
    if (!preg_match('/^\d{10}$/', $mobile)) {
        throw new Exception('Invalid mobile number format.');
    }

    // Rate limiting
    $rlKey = 'central_login_' . $mobile . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkRateLimit($rlKey)) {
        logLoginAttempt($mobile, false, 'Rate limit exceeded');
        throw new Exception('Too many login attempts. Please try again after 15 minutes.');
    }

    $matches = findMatchingAccounts($db_conn, $mobile, $password);

    if (empty($matches)) {
        logLoginAttempt($mobile, false, 'No matching account/password');
        recordFailedAttempt($rlKey);
        throw new Exception('Invalid mobile number or password.');
    }

    logLoginAttempt($mobile, true, 'Login successful (' . count($matches) . ' account(s))');
    clearRateLimit($rlKey);

    if (count($matches) === 1) {
        activateAccountSession($matches[0]);
        unset($_SESSION['PENDING_LOGIN']);
        $_SESSION['LINKED_ACCOUNTS'] = $matches;
        session_regenerate_id(true);
        header('Location: ../' . $matches[0]['folder'] . '/dashboard.php');
        exit;
    }

    // Multiple accounts share this mobile+password — let the user pick one.
    $_SESSION['LINKED_ACCOUNTS'] = $matches;
    $_SESSION['PENDING_LOGIN']   = true;
    session_regenerate_id(true);
    header('Location: select-account.php');
    exit;

} catch (Exception $e) {
    $_SESSION['errorMessage'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
?>
