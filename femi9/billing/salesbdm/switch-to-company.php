<?php
// "Switch to Company Login" link from the salesbdm header dropdown — only
// shown when this BDM's mobile number also has an active company login
// (see app-header.php). Reuses the same single-use bridge-token mechanism
// already built for the login-time chooser's "Company" button.
include("checksession.php");
require_once __DIR__ . '/include/LoginHelpers.php';

$stmt = $db_conn->prepare("SELECT id FROM admin_log WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $_SESSION['LOGIN_USER']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: dashboard.php');
    exit;
}

$token = createCompanySwitchToken($db_conn, (int)$row['id']);
header('Location: ../company/switch-login.php?token=' . urlencode($token));
exit;
