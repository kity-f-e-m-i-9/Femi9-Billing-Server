<?php
/**
 * One-time migration: re-store every legacy password (plain text or reversible
 * AES) as a one-way bcrypt hash, so no password can be read back from the DB.
 *
 * Nothing breaks if this is never run - logins upgrade each account on its
 * next successful login - but until then plain-text / AES values stay in the
 * database. Run once, from the command line, after taking a DB backup:
 *
 *     php 2026_10_24_hash_all_passwords.php            # dry run: counts only
 *     php 2026_10_24_hash_all_passwords.php --apply    # perform the update
 *
 * Needs shared/.env with the DB credentials and the SAME ENCRYPTION_KEY the
 * app uses (to decrypt the AES rows).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/EncryptionService.php';
require_once __DIR__ . '/../shared/PasswordService.php';

$apply = in_array('--apply', $argv, true);
$db = mysqli_connect($_ENV['DB_HOST'] ?? 'localhost', $_ENV['DB_USERNAME'] ?? '', $_ENV['DB_PASSWORD'] ?? '',
                     $_ENV['DB_NAME'] ?? 'billing0femi9_billingapp', (int)($_ENV['DB_PORT'] ?? 3306));
if (!$db) { fwrite(STDERR, "DB connection failed: " . mysqli_connect_error() . "\n"); exit(1); }
mysqli_set_charset($db, 'utf8mb4');

// table => primary-key column
$tables = [
    'admin_log' => 'id', 'super_stockiest' => 'id', 'stockiest' => 'id', 'distributor' => 'id',
    'super_distributor' => 'id', 'c_and_f' => 'id', 'shop' => 'id', 'outlet' => 'id',
    'marketing_staff' => 'id', 'sales_bdm_staff' => 'id', 'channel_partners' => 'id',
    'territory_partners' => 'id', 'warehouse_users' => 'id',
];

echo $apply ? "APPLYING\n" : "DRY RUN (no changes) - add --apply to perform\n";
foreach ($tables as $table => $pk) {
    $chk = @mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE 'password'");
    if (!$chk || mysqli_num_rows($chk) === 0) { echo str_pad($table, 20) . "skipped (no such table/column)\n"; continue; }
    if ($apply) { femi_password_ensure_column($db, $table); }

    $res = mysqli_query($db, "SELECT `$pk` AS pk, `password` AS pw FROM `$table`");
    $total = $already = $legacy = $unreadable = $done = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $total++;
        $stored = (string)$row['pw'];
        if ($stored === '') { continue; }
        if (password_get_info($stored)['algo']) { $already++; continue; }

        $plain = null;
        try { $plain = (new EncryptionService())->decrypt($stored); } catch (Throwable $e) { $plain = $stored; }
        if ($plain === null || $plain === '') { $unreadable++; continue; }
        $legacy++;
        if ($apply && femi_password_store($db, $table, $pk, $row['pk'], $plain)) { $done++; }
    }
    echo str_pad($table, 20) . "rows=$total already-hashed=$already legacy=$legacy" . ($apply ? " converted=$done" : '') . ($unreadable ? " UNREADABLE=$unreadable" : '') . "\n";
}
echo "Done.\n";
