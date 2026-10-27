<?php
/**
 * One place for password storage and checking.
 *
 * New/changed passwords are stored as one-way hashes (password_hash, bcrypt).
 * Existing rows may still hold the two legacy formats and are accepted until
 * their owner next logs in, at which point they are silently re-stored as a
 * hash (no forced reset):
 *   - AES-256-CBC via EncryptionService (reversible), or
 *   - plain text.
 *
 *   femi_password_hash($plain)                          -> string to store
 *   femi_password_check($plain, $stored)                -> ['valid'=>bool,'rehash'=>bool]
 *   femi_password_verify($db,$plain,$stored,$table,$idField,$idValue) -> bool (+ upgrades row)
 *   femi_password_store($db,$table,$idField,$idValue,$plain)          -> bool (hash + UPDATE)
 */
if (!class_exists('EncryptionService')) {
    $__enc = __DIR__ . '/EncryptionService.php';
    if (is_file($__enc)) { require_once $__enc; }
}

if (!function_exists('femi_password_hash')) {
    function femi_password_hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    /**
     * Compares $plain with a stored value of any supported format.
     * Never throws; 'rehash' is true when the stored form should be replaced
     * with a fresh hash (legacy format or outdated hash cost).
     */
    function femi_password_check(string $plain, string $stored): array
    {
        if ($stored === '') {
            return ['valid' => false, 'rehash' => false];
        }
        if (password_get_info($stored)['algo']) {
            $ok = password_verify($plain, $stored);
            return ['valid' => $ok, 'rehash' => $ok && password_needs_rehash($stored, PASSWORD_DEFAULT)];
        }
        if (class_exists('EncryptionService')) {
            try {
                $dec = (new EncryptionService())->decrypt($stored);
                $ok = hash_equals((string)$dec, $plain);
                return ['valid' => $ok, 'rehash' => $ok];
            } catch (Throwable $e) {
                // not an AES value (or key not configured) - treat as legacy plain text
            }
        }
        $ok = hash_equals($stored, $plain);
        return ['valid' => $ok, 'rehash' => $ok];
    }

    /** Widens a too-short password column once per request (bcrypt needs 60+). */
    function femi_password_ensure_column(mysqli $db, string $table): bool
    {
        static $checked = [];
        if (isset($checked[$table])) { return $checked[$table]; }
        $ok = true;
        $t = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $res = @mysqli_query($db, "SHOW COLUMNS FROM `$t` LIKE 'password'");
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            if (preg_match('/^(?:var)?char\((\d+)\)/i', (string)$row['Type'], $m) && (int)$m[1] < 100) {
                $ok = (bool)@mysqli_query($db, "ALTER TABLE `$t` MODIFY `password` VARCHAR(255) NOT NULL");
            }
        }
        return $checked[$table] = $ok;
    }

    function femi_password_store(mysqli $db, string $table, string $idField, $idValue, string $plain): bool
    {
        $t = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $f = preg_replace('/[^A-Za-z0-9_]/', '', $idField);
        if (!femi_password_ensure_column($db, $t)) { return false; }
        $hash = femi_password_hash($plain);
        $stmt = mysqli_prepare($db, "UPDATE `$t` SET `password` = ? WHERE `$f` = ?");
        if (!$stmt) { return false; }
        $idStr = (string)$idValue;
        mysqli_stmt_bind_param($stmt, 'ss', $hash, $idStr);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    function femi_password_verify(mysqli $db, string $plain, string $stored, string $table, string $idField, $idValue): bool
    {
        $r = femi_password_check($plain, $stored);
        if ($r['valid'] && $r['rehash']) {
            try { femi_password_store($db, $table, $idField, $idValue, $plain); } catch (Throwable $e) { /* login still succeeds */ }
        }
        if (!$r['valid']) {
            // A pending "forgot password" temporary password also works - and only then replaces the real one.
            try { return femi_reset_try($db, $plain, $table, $idField, $idValue); } catch (Throwable $e) { return false; }
        }
        return $r['valid'];
    }
}

/*
 * "Forgot password" without letting a stranger lock someone out.
 *
 * Requesting a reset no longer overwrites the account's password. It stores a
 * hashed temporary password in password_reset_pending (valid 30 minutes) and
 * sends it to the owner. The real password is replaced only if the owner then
 * logs in with the temporary one (femi_reset_try). Until then, and after it
 * expires, the existing password keeps working.
 */
if (!function_exists('femi_reset_ensure_table')) {
    function femi_reset_ensure_table(mysqli $db): void
    {
        static $done = false;
        if ($done) { return; }
        $done = true;
        @mysqli_query($db, "CREATE TABLE IF NOT EXISTS password_reset_pending (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_table VARCHAR(40) NOT NULL,
            id_field VARCHAR(40) NOT NULL,
            user_id VARCHAR(64) NOT NULL,
            utype VARCHAR(40) NOT NULL DEFAULT '',
            mobile VARCHAR(30) NOT NULL DEFAULT '',
            temp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_user (user_table, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    function femi_reset_issue(mysqli $db, string $table, string $idField, $userId, string $utype, string $mobile, string $plainTemp, int $minutes = 30): bool
    {
        femi_reset_ensure_table($db);
        $uid = (string)$userId;
        $del = mysqli_prepare($db, "DELETE FROM password_reset_pending WHERE user_table = ? AND user_id = ?");
        mysqli_stmt_bind_param($del, 'ss', $table, $uid);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
        $hash = femi_password_hash($plainTemp);
        $exp = date('Y-m-d H:i:s', time() + $minutes * 60);
        $ins = mysqli_prepare($db, "INSERT INTO password_reset_pending (user_table, id_field, user_id, utype, mobile, temp_hash, expires_at, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
        mysqli_stmt_bind_param($ins, 'sssssss', $table, $idField, $uid, $utype, $mobile, $hash, $exp);
        $ok = mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        return $ok;
    }

    function femi_reset_try(mysqli $db, string $plain, string $table, string $idField, $userId): bool
    {
        femi_reset_ensure_table($db);
        $uid = (string)$userId;
        $sel = mysqli_prepare($db, "SELECT id, utype, mobile, temp_hash FROM password_reset_pending WHERE user_table = ? AND user_id = ? AND expires_at > NOW()");
        if (!$sel) { return false; }
        mysqli_stmt_bind_param($sel, 'ss', $table, $uid);
        mysqli_stmt_execute($sel);
        $rows = mysqli_fetch_all(mysqli_stmt_get_result($sel), MYSQLI_ASSOC);
        mysqli_stmt_close($sel);
        foreach ($rows as $r) {
            if (!password_verify($plain, $r['temp_hash'])) { continue; }
            femi_password_store($db, $table, $idField, $uid, $plain);
            $d = mysqli_prepare($db, "DELETE FROM password_reset_pending WHERE user_table = ? AND user_id = ?");
            mysqli_stmt_bind_param($d, 'ss', $table, $uid);
            mysqli_stmt_execute($d);
            mysqli_stmt_close($d);
            // Force the usual "change your password now" step for a reset login.
            try {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $f = mysqli_prepare($db, "INSERT INTO forgotpassword (usertype, mobilenumber, reset_at, ip_address, must_change_password)
                     VALUES (?, ?, NOW(), ?, 1)
                     ON DUPLICATE KEY UPDATE reset_at = NOW(), attempts = attempts + 1, must_change_password = 1, password_changed_at = NULL");
                mysqli_stmt_bind_param($f, 'sss', $r['utype'], $r['mobile'], $ip);
                mysqli_stmt_execute($f);
                mysqli_stmt_close($f);
            } catch (Throwable $e) { /* flag table optional */ }
            return true;
        }
        return false;
    }
}

if (!function_exists('femi_password_hidden_cell')) {
    /**
     * Admin list/detail cell shown instead of a password: passwords are stored
     * as one-way hashes and cannot be displayed. Offers a one-click reset
     * (company/reset-user-password.php) that shows a new temporary password once.
     */
    function femi_password_hidden_cell($table, $id): void
    {
        $t = htmlspecialchars((string)$table, ENT_QUOTES, 'ISO-8859-1', false);
        $i = htmlspecialchars((string)$id, ENT_QUOTES, 'ISO-8859-1', false);
        echo '<form method="post" action="reset-user-password.php" style="margin:0;display:inline"'
           . ' onsubmit="return confirm(\'Generate a new temporary password for this user? Their current password will stop working.\');">'
           . '<input type="hidden" name="table" value="' . $t . '"><input type="hidden" name="id" value="' . $i . '">'
           . '<span class="text-muted" title="Passwords are stored securely and cannot be viewed">&bull;&bull;&bull;&bull;&bull;&bull;</span> '
           . '<button type="submit" class="btn btn-sm btn-outline-secondary" style="padding:1px 8px;font-size:12px">Reset</button></form>';
    }
}

if (!function_exists('femi_flag_must_change')) {
    /** Makes the user's next dashboard visit force a password change (same flag forgot-password uses). */
    function femi_flag_must_change(mysqli $db, string $utype, string $mobile): void
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'system';
            $f = mysqli_prepare($db, "INSERT INTO forgotpassword (usertype, mobilenumber, reset_at, ip_address, must_change_password)
                 VALUES (?, ?, NOW(), ?, 1)
                 ON DUPLICATE KEY UPDATE must_change_password = 1, password_changed_at = NULL");
            mysqli_stmt_bind_param($f, 'sss', $utype, $mobile, $ip);
            mysqli_stmt_execute($f);
            mysqli_stmt_close($f);
        } catch (Throwable $e) { /* flag table optional */ }
    }

    function femi_clear_must_change(mysqli $db, string $utype, string $mobile): void
    {
        try {
            $f = mysqli_prepare($db, "UPDATE forgotpassword SET must_change_password = 0, password_changed_at = NOW() WHERE usertype = ? AND mobilenumber = ?");
            mysqli_stmt_bind_param($f, 'ss', $utype, $mobile);
            mysqli_stmt_execute($f);
            mysqli_stmt_close($f);
        } catch (Throwable $e) { }
    }
}
