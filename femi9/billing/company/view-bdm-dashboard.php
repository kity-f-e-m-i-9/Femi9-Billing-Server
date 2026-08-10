<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
error_reporting(0);

$bdmId = (int)($_GET['bdm_id'] ?? 0);
if (!$bdmId) { header('Location: salesbdm-team-report'); exit; }

$stmt = $db_conn->prepare("SELECT id FROM sales_bdm_staff WHERE id = ? AND account_status = 'active' LIMIT 1");
$stmt->bind_param('i', $bdmId);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$exists) { header('Location: salesbdm-team-report'); exit; }

$db_conn->query("CREATE TABLE IF NOT EXISTS company_bdm_view_bridge (
    token VARCHAR(64) PRIMARY KEY,
    bdm_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$token = bin2hex(random_bytes(32));
$ins = $db_conn->prepare("INSERT INTO company_bdm_view_bridge (token, bdm_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
$ins->bind_param('si', $token, $bdmId);
$ins->execute();
$ins->close();

setcookie('femi9_company_bdm_view', $token, [
    'expires'  => time() + 900,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
header('Location: ../salesbdm/dashboard.php');
exit;
