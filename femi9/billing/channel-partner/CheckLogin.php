<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
session_start();
error_reporting(0);

require_once 'include/db-connect.php';
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/EncryptionService.php';

function logLoginAttempt(string $mobile, bool $success, string $reason = ''): void {
    $logFile = __DIR__ . '/logs/login_attempts.log';
    $entry   = sprintf(
        "[%s] %s | Mobile: %s | IP: %s | Reason: %s\n",
        date('Y-m-d H:i:s'), $success ? 'SUCCESS' : 'FAILED',
        $mobile, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $reason
    );
    file_put_contents($logFile, $entry, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
    header('Location: index.php'); exit;
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

    // Fetch CP user
    $stmt = mysqli_prepare($db_conn,
        "SELECT id, cp_id, name, mobile, password, is_active FROM channel_partners WHERE mobile = ? LIMIT 1"
    );
    if (!$stmt) throw new Exception('Database error. Please try again later.');

    mysqli_stmt_bind_param($stmt, "s", $mobile);
    mysqli_stmt_execute($stmt);
    $user = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$user) {
        logLoginAttempt($mobile, false, 'User not found');
        throw new Exception('Invalid mobile number or password.');
    }

    if (!$user['is_active']) {
        logLoginAttempt($mobile, false, 'Account inactive');
        throw new Exception('Your account is inactive. Please contact support.');
    }

    // Verify password — supports bcrypt, AES-256-CBC, plain text (legacy)
    $encryption      = new EncryptionService();
    $isPasswordValid = false;
    $needsUpgrade    = false;
    $stored          = $user['password'];

    if (password_get_info($stored)['algo']) {
        $isPasswordValid = password_verify($password, $stored);
        $needsUpgrade    = $isPasswordValid;
    } else {
        try {
            $decrypted       = $encryption->decrypt($stored);
            $isPasswordValid = ($password === $decrypted);
        } catch (Exception $e) {
            $isPasswordValid = ($password === $stored);
            $needsUpgrade    = $isPasswordValid;
        }
    }

    if ($isPasswordValid && $needsUpgrade) {
        try {
            $enc = $encryption->encrypt($password);
            $upd = mysqli_prepare($db_conn, "UPDATE channel_partners SET password = ? WHERE mobile = ?");
            mysqli_stmt_bind_param($upd, "ss", $enc, $mobile);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        } catch (Exception $e) { /* non-fatal */ }
    }

    if (!$isPasswordValid) {
        logLoginAttempt($mobile, false, 'Invalid password');
        throw new Exception('Invalid mobile number or password.');
    }

    // Success
    $_SESSION['LOGIN_USER']      = $user['mobile'];
    $_SESSION['LOGIN_USER_ID']   = $user['id'];
    $_SESSION['LOGIN_USER_NAME'] = $user['name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'channel_partner';
    $_SESSION['last_activity']   = time();

    logLoginAttempt($mobile, true, 'Login successful');
    session_regenerate_id(true);

    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    $_SESSION['errorMessage'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
?>
