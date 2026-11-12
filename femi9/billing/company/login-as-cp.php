<?php
// "Login" button on Manage Channel Partner — lets a company user open a
// real, fully-scoped Channel Partner session for support/debugging, without
// knowing or resetting the CP's password. Hands off via a single-use,
// short-lived DB-backed token to channel-partner/switch-login.php, mirroring
// company/login-as-tp.php's existing TP bridge exactly.
//
// channel-partner/ already has its own session cookie name
// ("femi9_channel_partner_sess", set in channel-partner/.htaccess), so
// starting a CP session here does NOT touch or clear the admin's own
// company session at all — both stay logged in independently in the same
// browser, same as the TP bridge.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');

$cpid = (int) base64_decode($_GET['cpid'] ?? '');
if ($cpid <= 0) {
    header('Location: manage-channel-partner.php');
    exit;
}

$stmt = $db_conn->prepare("SELECT id, is_active FROM channel_partners WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $cpid);
$stmt->execute();
$cp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cp || !$cp['is_active']) {
    header('Location: manage-channel-partner.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_cp_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    cp_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$token = bin2hex(random_bytes(32));
$ins = $db_conn->prepare("INSERT INTO company_cp_login_bridge (token, cp_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
$ins->bind_param('si', $token, $cp['id']);
$ins->execute();
$ins->close();

header('Location: ../channel-partner/switch-login.php?token=' . urlencode($token));
exit;
