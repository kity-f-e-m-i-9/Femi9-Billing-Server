<?php
// "Login as Marketing Staff" link on Manage Team / Shop View — lets a
// company user open a real, fully-scoped marketing staff session for
// support/debugging, without knowing or resetting the staff's password.
// Hands off via a single-use, short-lived DB-backed token to
// marketing/switch-login.php, mirroring company/login-as-tp.php's bridge
// to territory-partner/switch-login.php.
//
// marketing/ has its own session.name (femi9_marketing_sess — see
// marketing/.htaccess), separate from company/'s femi9_company_sess, so
// starting a marketing session here does NOT touch or clear the admin's
// own company session — both stay logged in independently in the same
// browser.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');

$msid = (int) base64_decode($_GET['msid'] ?? '');
if ($msid <= 0) {
    header('Location: ms-team-shop-view.php');
    exit;
}

$_chkDel = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'deleted_at'");
if ($_chkDel && $_chkDel->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
}

$stmt = $db_conn->prepare("SELECT id FROM marketing_staff WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param('i', $msid);
$stmt->execute();
$ms = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ms) {
    header('Location: ms-team-shop-view.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_ms_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    ms_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$token = bin2hex(random_bytes(32));
$ins = $db_conn->prepare("INSERT INTO company_ms_login_bridge (token, ms_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
$ins->bind_param('si', $token, $ms['id']);
$ins->execute();
$ins->close();

header('Location: ../marketing/switch-login.php?token=' . urlencode($token));
exit;
