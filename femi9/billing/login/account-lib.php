<?php
/**
 * Shared helpers for the central login: password verification (same 3-way
 * check every per-category CheckLogin.php already uses), matching accounts
 * by mobile number across all central-login-eligible tables, and setting
 * the session keys each panel's dashboard/config.php already expects.
 */

require_once __DIR__ . '/../shared/EncryptionService.php';
require_once __DIR__ . '/../shared/user-config.php';

/**
 * Verify a submitted password against a stored value that may be bcrypt/argon2,
 * AES-encrypted (EncryptionService), or legacy plaintext. On a legacy-plaintext
 * match, upgrades the stored value to AES encryption (same auto-upgrade every
 * existing CheckLogin.php already performs).
 */
function verifyStoredPassword(mysqli $db_conn, string $password, string $stored, string $table, string $idField, $idValue): bool {
    $encryption = new EncryptionService();
    $isValid = false;
    $needsUpgrade = false;

    if (password_get_info($stored)['algo']) {
        $isValid = password_verify($password, $stored);
        $needsUpgrade = $isValid;
    } else {
        try {
            $decrypted = $encryption->decrypt($stored);
            $isValid = ($password === $decrypted);
        } catch (Exception $e) {
            $isValid = ($password === $stored);
            $needsUpgrade = $isValid;
        }
    }

    if ($isValid && $needsUpgrade) {
        try {
            $enc = $encryption->encrypt($password);
            $upd = mysqli_prepare($db_conn, "UPDATE `$table` SET password = ? WHERE `$idField` = ?");
            if ($upd) {
                mysqli_stmt_bind_param($upd, "ss", $enc, $idValue);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
        } catch (Exception $e) {
            // non-fatal — login still succeeds even if the upgrade write fails
        }
    }

    return $isValid;
}

/**
 * Look up a single account's row for a given central-login type + mobile number.
 * Returns ['id'=>..., 'name'=>..., 'mobile'=>..., 'password'=>..., 'active'=>bool] or null.
 */
function findAccountByMobile(mysqli $db_conn, string $type, string $mobile): ?array {
    $cfg = getUserConfig($type);
    if (!$cfg || empty($cfg['mobile_field'])) return null;

    $table   = $cfg['table'];
    $idField = $cfg['id_field'];
    $mobileField = $cfg['mobile_field'];
    $nameField   = $cfg['name_field'] ?? $mobileField;
    $statusField = $cfg['status_field'] ?? null;

    $cols = "`$idField` AS id, `$nameField` AS name, `$mobileField` AS mobile, `password` AS password";
    if ($statusField) {
        $cols .= ", `$statusField` AS status_val";
    }

    $stmt = mysqli_prepare($db_conn, "SELECT $cols FROM `$table` WHERE `$mobileField` = ? LIMIT 1");
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "s", $mobile);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$row) return null;

    $active = true;
    if ($statusField) {
        $active = ((string)$row['status_val'] === (string)$cfg['status_active_value']);
    }

    return [
        'id'       => $row['id'],
        'name'     => $row['name'],
        'mobile'   => $row['mobile'],
        'password' => $row['password'],
        'active'   => $active,
        'table'    => $table,
        'id_field' => $idField,
    ];
}

/**
 * Across every central-login-eligible type, find accounts whose mobile+password
 * both match. Returns a list of match summaries safe to keep in the session.
 */
function findMatchingAccounts(mysqli $db_conn, string $mobile, string $password): array {
    $matches = [];

    foreach (getCentralLoginTypes() as $type) {
        $account = findAccountByMobile($db_conn, $type, $mobile);
        if (!$account || !$account['active']) continue;

        $valid = verifyStoredPassword($db_conn, $password, $account['password'], $account['table'], $account['id_field'], $account['id']);
        if (!$valid) continue;

        $cfg = getUserConfig($type);
        $matches[] = [
            'type'         => $type,
            'id'           => $account['id'],
            'name'         => $account['name'],
            'mobile'       => $account['mobile'],
            'display_name' => $cfg['display_name'],
            'folder'       => $cfg['folder'],
        ];
    }

    return $matches;
}

/**
 * Set the session keys every panel's checksession.php/config.php/dashboard.php
 * already expects, matching each category's own CheckLogin.php contract exactly.
 */
function activateAccountSession(array $account): void {
    $_SESSION['LOGIN_USER']      = $account['mobile'];
    $_SESSION['LOGIN_USER_ID']   = $account['id'];
    $_SESSION['LOGIN_USER_NAME'] = $account['name'];
    $_SESSION['LOGIN_USER_TYPE'] = $account['type'];
    $_SESSION['last_activity']   = time();
}
?>
