<?php
// "Login as Super Stockist" button on Manage Super Stockist — mirrors
// company/login-as-stockist.php exactly (see its comment for the full
// picture), scoped to the super_stockiest table / super-stockist/
// switch-login.php instead.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ss_edit');

$ssid = (int) base64_decode($_GET['ssid'] ?? '');
if ($ssid <= 0) {
    header('Location: manage_ss.php');
    exit;
}

$stmt = $db_conn->prepare("SELECT id, temp_id, name, mobile_number, account_status FROM super_stockiest WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $ssid);
$stmt->execute();
$ss = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ss || $ss['account_status'] !== 'active') {
    header('Location: manage_ss.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_superstockist_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    ss_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$token = bin2hex(random_bytes(32));
$ins = $db_conn->prepare("INSERT INTO company_superstockist_login_bridge (token, ss_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
$ins->bind_param('si', $token, $ss['id']);
$ins->execute();
$ins->close();

header('Location: ../super-stockist/switch-login.php?token=' . urlencode($token));
exit;
