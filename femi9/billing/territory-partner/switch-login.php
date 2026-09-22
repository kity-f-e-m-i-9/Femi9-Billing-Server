<?php
// Landing point for the company "Login as Territory Partner" button
// (company/login-as-tp.php). Consumes a single-use token and starts a real
// Territory Partner session here — same session shape territory-partner/
// checksession.php expects, mirroring salesbdm/switch-login.php's pattern.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/db-connect.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: ../login/index.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_tp_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    tp_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $db_conn->prepare("SELECT tp_id FROM company_tp_login_bridge WHERE token = ? AND expires_at > NOW() LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Single-use — delete on read regardless of outcome, so the same link can
// never be replayed even if it was still within its 2-minute window.
$del = $db_conn->prepare("DELETE FROM company_tp_login_bridge WHERE token = ?");
$del->bind_param('s', $token);
$del->execute();
$del->close();

if (!$row) {
    header('Location: ../login/index.php?sessionexpiry');
    exit;
}

$stmt = $db_conn->prepare("SELECT id, name, mobile, is_active FROM territory_partners WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param('i', $row['tp_id']);
$stmt->execute();
$tp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tp || !$tp['is_active']) {
    header('Location: ../login/index.php?sessionexpiry');
    exit;
}

session_regenerate_id(true);
$_SESSION['LOGIN_USER']      = $tp['mobile'];
$_SESSION['LOGIN_USER_ID']   = $tp['id'];
$_SESSION['LOGIN_USER_NAME'] = $tp['name'];
$_SESSION['LOGIN_USER_TYPE'] = 'territory_partner';
$_SESSION['last_activity']   = time();
// Marks this session as company-initiated so the header can offer a way
// back — the company session itself was never touched (different cookie
// name, see company/login-as-tp.php), this just points the header link.
$_SESSION['LOGGED_IN_VIA_COMPANY'] = true;

header('Location: dashboard.php');
exit;
