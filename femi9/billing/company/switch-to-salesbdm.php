<?php
// "Switch to Sales BDM" link from the company header dropdown — only shown
// when this company user's mobile number also has an active sales_bdm_staff
// account (see app-header.php). Hands off via a single-use, short-lived
// DB-backed token to salesbdm/switch-login.php, which starts a real Sales
// BDM session there — mirrors the reverse (salesbdm -> company) bridge.
include("checksession.php");

$stmt = $db_conn->prepare("SELECT id FROM sales_bdm_staff WHERE bdm_mobile = ? AND account_status = 'active' LIMIT 1");
$stmt->bind_param('s', $_SESSION['LOGIN_USER']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: dashboard.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_login_switch_bridge (
    token VARCHAR(64) PRIMARY KEY,
    bdm_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$token = bin2hex(random_bytes(32));
$ins = $db_conn->prepare("INSERT INTO company_login_switch_bridge (token, bdm_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))");
$ins->bind_param('si', $token, $row['id']);
$ins->execute();
$ins->close();

header('Location: ../salesbdm/switch-login.php?token=' . urlencode($token));
exit;
