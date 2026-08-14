<?php
// Landing point for the Sales BDM "choose account → Company" switch. Runs
// under company/'s own .htaccess, so session_start() here already picks up
// the femi9_company_sess cookie name — no manual session_name() needed.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/db-connect.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: index.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_login_switch_bridge (
    token VARCHAR(64) PRIMARY KEY,
    admin_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $db_conn->prepare("SELECT admin_id FROM salesbdm_login_switch_bridge WHERE token = ? AND expires_at > NOW() LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Single-use — delete on read regardless of outcome, so the same link can
// never be replayed even if it was still within its 2-minute window.
$del = $db_conn->prepare("DELETE FROM salesbdm_login_switch_bridge WHERE token = ?");
$del->bind_param('s', $token);
$del->execute();
$del->close();

if (!$row) {
    header('Location: index.php?sessionexpiry');
    exit;
}

$stmtUser = $db_conn->prepare("SELECT id, username FROM admin_log WHERE id = ? LIMIT 1");
$stmtUser->bind_param('i', $row['admin_id']);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

if (!$user) {
    header('Location: index.php?sessionexpiry');
    exit;
}

$_SESSION['LOGIN_USER'] = $user['username'];
$_SESSION['LOGIN_USER_ID'] = $user['id'];
$_SESSION['LOGIN_USER_TYPE'] = 'company';
$_SESSION['last_activity'] = time();

$updateStmt = $db_conn->prepare("UPDATE admin_log SET last_login = NOW() WHERE id = ?");
$updateStmt->bind_param('i', $user['id']);
$updateStmt->execute();
$updateStmt->close();

session_regenerate_id(true);
header('Location: dashboard.php');
exit;
