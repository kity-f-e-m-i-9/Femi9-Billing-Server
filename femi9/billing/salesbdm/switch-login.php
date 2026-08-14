<?php
// Landing point for the company "Switch to Sales BDM" link — consumes a
// single-use token from company/switch-to-salesbdm.php and starts a real
// Sales BDM session (via the same finalizeSalesBdmSession() used by the
// normal CheckLogin.php / choose-login.php flows).
session_start();
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/include/LoginHelpers.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: index.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_login_switch_bridge (
    token VARCHAR(64) PRIMARY KEY,
    bdm_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $db_conn->prepare("SELECT bdm_id FROM company_login_switch_bridge WHERE token = ? AND expires_at > NOW() LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Single-use — delete on read regardless of outcome.
$del = $db_conn->prepare("DELETE FROM company_login_switch_bridge WHERE token = ?");
$del->bind_param('s', $token);
$del->execute();
$del->close();

if (!$row) {
    header('Location: index.php?sessionexpiry');
    exit;
}

$stmt = mysqli_prepare($db_conn, "SELECT id, bdm_name, bdm_mobile, account_status FROM sales_bdm_staff WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $row['bdm_id']);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$user || $user['account_status'] !== 'active') {
    header('Location: index.php?sessionexpiry');
    exit;
}

finalizeSalesBdmSession($db_conn, $user);
session_regenerate_id(true);
header('Location: dashboard.php');
exit;
