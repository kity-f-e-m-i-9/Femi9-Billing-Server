<?php
// Admin action: generate a new temporary password for a user and show it ONCE.
// Passwords are stored as one-way hashes, so existing ones can never be viewed;
// this replaces the old "view password" columns.
include("checksession.php");
require_once("include/PermissionCheck.php");
require_once __DIR__ . '/../shared/PasswordService.php';
require_once __DIR__ . '/../shared/db-helpers.php';

// table => [permission (null = staff only, 'ADMIN' = owner only), name column]
$targets = [
    'super_stockiest'  => ['ss_edit', 'name'],
    'stockiest'        => ['st_edit', 'name'],
    'distributor'      => ['dt_edit', 'name'],
    'super_distributor'=> ['sdt_edit', 'name'],
    'shop'             => ['shop_edit', 'name'],
    'marketing_staff'  => ['ms_edit', 'ms_name'],
    'sales_bdm_staff'  => ['salesbdm_manage_edit', 'bdm_name'],
    'c_and_f'          => [null, 'name'],
    'outlet'           => [null, 'name'],
    'admin_log'        => ['ADMIN', 'username'],
];

$table = (string)($_POST['table'] ?? '');
$id    = (string)($_POST['id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($targets[$table]) || $id === '') {
    http_response_code(400);
    exit('Invalid request');
}

[$perm, $nameCol] = $targets[$table];
if ($perm === 'ADMIN')      { requireAdminOnly(); }
elseif ($perm === null)     { requireStaffOnly(); }
else                        { requirePermission($perm); }

$row = mysqli_fetch_assoc(db_q($db_conn, "select `$nameCol` as n from `$table` where id=? limit 1", [$id]));
if (!$row) { http_response_code(404); exit('User not found'); }

function femi_temp_password(int $len = 10): string
{
    $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '@#$%&*'];
    $all = implode('', $sets);
    $out = [];
    foreach ($sets as $s) { $out[] = $s[random_int(0, strlen($s) - 1)]; }
    while (count($out) < $len) { $out[] = $all[random_int(0, strlen($all) - 1)]; }
    for ($i = count($out) - 1; $i > 0; $i--) { $j = random_int(0, $i); [$out[$i], $out[$j]] = [$out[$j], $out[$i]]; }
    return implode('', $out);
}

$temp = femi_temp_password();
if (!femi_password_store($db_conn, $table, 'id', $id, $temp)) {
    http_response_code(500);
    exit('Could not update the password.');
}
@error_log('password reset by ' . ($_SESSION['LOGIN_USER'] ?? '?') . " for $table#$id");
header('Cache-Control: no-store');
$name = htmlspecialchars((string)$row['n'], ENT_QUOTES, 'ISO-8859-1', false);
$pw   = htmlspecialchars($temp, ENT_QUOTES, 'UTF-8');
$back = htmlspecialchars((string)($_SERVER['HTTP_REFERER'] ?? 'dashboard'), ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Password reset</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f4f6fb}
.box{background:#fff;padding:28px 32px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:440px;width:92%}
code{display:block;font-size:22px;letter-spacing:1px;background:#f1f3f9;padding:12px 14px;border-radius:8px;margin:14px 0;word-break:break-all}
button,a.btn{padding:9px 16px;border:0;border-radius:8px;background:#4f46e5;color:#fff;text-decoration:none;cursor:pointer;font-size:14px}
p.small{color:#666;font-size:13px}</style></head><body><div class="box">
<h2 style="margin-top:0">Password reset</h2>
<p>New temporary password for <strong><?= $name ?></strong>:</p>
<code id="pw"><?= $pw ?></code>
<button type="button" onclick="navigator.clipboard.writeText(document.getElementById('pw').textContent);this.textContent='Copied'">Copy</button>
<a class="btn" href="<?= $back ?>" style="background:#6b7280">Back</a>
<p class="small">This is shown only once and cannot be viewed again. Share it with the user securely and ask them to change it after logging in.</p>
</div></body></html>
