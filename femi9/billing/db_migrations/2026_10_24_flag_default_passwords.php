<?php
/**
 * One-time: find Sales BDM / Marketing Staff accounts still on the shared
 * default password and force them to change it at next login.
 *   php 2026_10_24_flag_default_passwords.php           # dry run
 *   php 2026_10_24_flag_default_passwords.php --apply
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/PasswordService.php';
$apply = in_array('--apply', $argv, true);
$db = mysqli_connect($_ENV['DB_HOST'] ?? 'localhost', $_ENV['DB_USERNAME'] ?? '', $_ENV['DB_PASSWORD'] ?? '',
                     $_ENV['DB_NAME'] ?? 'billing0femi9_billingapp', (int)($_ENV['DB_PORT'] ?? 3306));
if (!$db) { fwrite(STDERR, "DB connection failed\n"); exit(1); }
$jobs = [
    ['sales_bdm_staff', 'bdm_mobile', 'salesbdm',  'salesbdm@123'],
    ['marketing_staff', 'ms_mobile',  'marketing', '12345678'],
];
echo $apply ? "APPLYING\n" : "DRY RUN - add --apply to flag\n";
foreach ($jobs as [$table, $mobCol, $utype, $default]) {
    $res = mysqli_query($db, "SELECT `$mobCol` AS m, password FROM `$table`");
    $n = $total = 0;
    while ($r = mysqli_fetch_assoc($res)) {
        $total++;
        if (femi_password_check($default, (string)$r['password'])['valid']) {
            $n++;
            if ($apply) { femi_flag_must_change($db, $utype, (string)$r['m']); }
        }
    }
    echo str_pad($table, 18) . "accounts=$total still-on-default=$n" . ($apply ? " flagged" : '') . "\n";
}
