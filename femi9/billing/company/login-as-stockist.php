<?php
// "Login as Stockist" button on Manage Stockist — lets a company user open a
// real, fully-scoped Stockist session for support/debugging, without
// knowing or resetting the stockist's password. Mirrors
// company/login-as-tp.php's / login-as-cp.php's bridge pattern exactly, via
// a single-use, short-lived DB-backed token to stockist/switch-login.php.
//
// stockist/ has no custom session.name set in code (relies on .htaccess for
// femi9_stockist_sess — see stockist/switch-login.php's own comment), but
// that's still a different cookie name than company/'s femi9_company_sess,
// so starting a stockist session here does NOT touch or clear the admin's
// own company session — both stay logged in independently in the same
// browser, same as the TP/CP bridges.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('st_edit');

$stid = (int) base64_decode($_GET['stid'] ?? '');
if ($stid <= 0) {
    header('Location: stockist-manage.php');
    exit;
}

$stmt = $db_conn->prepare("SELECT id, temp_id, name, mobile_number, account_status FROM stockiest WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $stid);
$stmt->execute();
$st = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$st || $st['account_status'] !== 'active') {
    header('Location: stockist-manage.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_stockist_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    st_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$token = bin2hex(random_bytes(32));
$ins = $db_conn->prepare("INSERT INTO company_stockist_login_bridge (token, st_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
$ins->bind_param('si', $token, $st['id']);
$ins->execute();
$ins->close();

header('Location: ../stockist/switch-login.php?token=' . urlencode($token));
exit;
