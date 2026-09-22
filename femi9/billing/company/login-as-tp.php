<?php
// "Login as Territory Partner" button on Manage Territory Partner — lets a
// company user open a real, fully-scoped TP session for support/debugging,
// without knowing or resetting the TP's password. Hands off via a
// single-use, short-lived DB-backed token to territory-partner/switch-login.php,
// mirroring the existing company<->salesbdm switch-to-salesbdm.php /
// salesbdm/switch-login.php bridge pattern.
//
// territory-partner/ has no custom session.name (unlike company/'s
// femi9_company_sess — see company/.htaccess), so it runs on the plain
// PHPSESSID cookie: starting a TP session here does NOT touch or clear the
// admin's own company session at all — both stay logged in independently in
// the same browser, same as the salesbdm bridge.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');

$tpid = (int) base64_decode($_GET['tpid'] ?? '');
if ($tpid <= 0) {
    header('Location: manage-territory-partner.php');
    exit;
}

$stmt = $db_conn->prepare("SELECT id, is_active FROM territory_partners WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param('i', $tpid);
$stmt->execute();
$tp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tp || !$tp['is_active']) {
    header('Location: manage-territory-partner.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_tp_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    tp_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$token = bin2hex(random_bytes(32));
$ins = $db_conn->prepare("INSERT INTO company_tp_login_bridge (token, tp_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
$ins->bind_param('si', $token, $tp['id']);
$ins->execute();
$ins->close();

header('Location: ../territory-partner/switch-login.php?token=' . urlencode($token));
exit;
