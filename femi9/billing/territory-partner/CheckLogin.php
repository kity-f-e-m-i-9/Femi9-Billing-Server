<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
session_start();
error_reporting(0);

require_once 'include/db-connect.php';
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/EncryptionService.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function logLoginAttempt(string $mobile, bool $success, string $reason = ''): void {
    $logFile  = __DIR__ . '/logs/login_attempts.log';
    $entry    = sprintf(
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
    $rlKey = 'tp_login_' . $mobile . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkRateLimit($rlKey)) {
        logLoginAttempt($mobile, false, 'Rate limit exceeded');
        throw new Exception('Too many login attempts. Please try again after 15 minutes.');
    }

    // Fetch user — note: territory_partners uses `mobile`, `is_active`, `id` (not mobile_number / account_status / temp_id)
    $stmt = mysqli_prepare($db_conn,
        "SELECT id, tp_id, name, mobile, email, password, is_active
         FROM territory_partners WHERE mobile = ? LIMIT 1"
    );
    if (!$stmt) throw new Exception('Database error. Please try again later.');

    mysqli_stmt_bind_param($stmt, "s", $mobile);
    mysqli_stmt_execute($stmt);
    $user = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$user) {
        logLoginAttempt($mobile, false, 'User not found');
        recordFailedAttempt($rlKey);
        throw new Exception('Invalid mobile number or password.');
    }

    if (!$user['is_active']) {
        logLoginAttempt($mobile, false, 'Account inactive');
        throw new Exception('Your account is inactive. Please contact support.');
    }

    // Verify password — supports three formats: AES-256-CBC, bcrypt, plain text (legacy)
    $encryption      = new EncryptionService();
    $isPasswordValid = false;
    $needsUpgrade    = false;

    $stored = $user['password'];

    if (password_get_info($stored)['algo']) {
        // bcrypt / argon2 hash
        $isPasswordValid = password_verify($password, $stored);
        $needsUpgrade    = $isPasswordValid; // migrate to AES on success
    } else {
        try {
            $decrypted       = $encryption->decrypt($stored);
            $isPasswordValid = ($password === $decrypted);
        } catch (Exception $e) {
            // Plain-text legacy
            $isPasswordValid = ($password === $stored);
            $needsUpgrade    = $isPasswordValid;
        }
    }

    if ($isPasswordValid && $needsUpgrade) {
        // Auto-upgrade to AES encryption
        try {
            $enc = $encryption->encrypt($password);
            $upd = mysqli_prepare($db_conn, "UPDATE territory_partners SET password = ? WHERE mobile = ?");
            mysqli_stmt_bind_param($upd, "ss", $enc, $mobile);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        } catch (Exception $e) { /* non-fatal */ }
    }

    if (!$isPasswordValid) {
        logLoginAttempt($mobile, false, 'Invalid password');
        recordFailedAttempt($rlKey);
        throw new Exception('Invalid mobile number or password.');
    }

    // ── Success ───────────────────────────────────────────────────────────────
    $_SESSION['LOGIN_USER']      = $user['mobile'];
    $_SESSION['LOGIN_USER_ID']   = $user['id'];
    $_SESSION['LOGIN_USER_NAME'] = $user['name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'territory_partner';
    $_SESSION['last_activity']   = time();

    // Update last_login (column added via ALTER TABLE — safe to attempt)
    $upd2 = mysqli_prepare($db_conn, "UPDATE territory_partners SET last_login = NOW() WHERE mobile = ?");
    if ($upd2) {
        mysqli_stmt_bind_param($upd2, "s", $mobile);
        mysqli_stmt_execute($upd2);
        mysqli_stmt_close($upd2);
    }

    logLoginAttempt($mobile, true, 'Login successful');
    clearRateLimit($rlKey);
    session_regenerate_id(true);

    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    $_SESSION['errorMessage'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
?>
