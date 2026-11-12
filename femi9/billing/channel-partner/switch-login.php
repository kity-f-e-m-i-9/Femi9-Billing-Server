<?php
// Landing point for BOTH:
// 1. company/login-as-cp.php's admin "Login" bridge (company_cp_login_bridge
//    table) — new, added here, mirrors territory-partner/switch-login.php's
//    equivalent company-bridge branch.
// 2. The central login handoff (login/authenticate.php,
//    login/select-account.php, login/switch-account.php) via the shared
//    portal_login_bridge table — pre-existing, unchanged.
//
// Whether this portal's own cookie already existed BEFORE this request's
// session_start() determines whether session_regenerate_id() below is
// needed — calling it unconditionally sends a second Set-Cookie for the
// same name in the same response, and which one the browser keeps isn't
// guaranteed. Same fix already applied in company/switch-login.php and
// territory-partner/switch-login.php.
//
// Explicit session_name() call (not just relying on .htaccess's php_value
// session.name) for the same reason territory-partner/switch-login.php has
// one: confirmed in that module's history that relying on .htaccess alone
// wasn't reliable on every environment, so setting it in code is the
// hardening fix already applied there.
session_name('femi9_channel_partner_sess');
$_hadExistingCpSession = isset($_COOKIE[session_name()]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/session-bridge.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: ../login/index.php');
    exit;
}

$db_conn->query("CREATE TABLE IF NOT EXISTS company_cp_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    cp_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $db_conn->prepare("SELECT cp_id FROM company_cp_login_bridge WHERE token = ? AND expires_at > NOW() LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Single-use — delete on read regardless of outcome, so the same link can
// never be replayed even if it was still within its window.
$del = $db_conn->prepare("DELETE FROM company_cp_login_bridge WHERE token = ?");
$del->bind_param('s', $token);
$del->execute();
$del->close();

if ($row) {
    $stmt = $db_conn->prepare("SELECT id, name, mobile, is_active FROM channel_partners WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $row['cp_id']);
    $stmt->execute();
    $cp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cp || !$cp['is_active']) {
        header('Location: ../login/index.php?sessionexpiry');
        exit;
    }

    if ($_hadExistingCpSession) {
        session_regenerate_id(true);
    }
    $_SESSION['LOGIN_USER']      = $cp['mobile'];
    $_SESSION['LOGIN_USER_ID']   = $cp['id'];
    $_SESSION['LOGIN_USER_NAME'] = $cp['name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'channel_partner';
    $_SESSION['last_activity']   = time();
    // Marks this session as company-initiated so the header can offer a way
    // back — the company session itself was never touched (different
    // cookie name), this just points the header link.
    $_SESSION['LOGGED_IN_VIA_COMPANY'] = true;
    // A stale flash message from an earlier event in this same browser
    // session must never survive into a fresh, successful login.
    unset($_SESSION['errorMessage'], $_SESSION['successMessage']);

    header('Location: dashboard.php');
    exit;
}

// Not a company-bridge token — try the shared central-login bridge instead
// (pre-existing path, unchanged).
$payload = consumeBridgeToken($db_conn, $token);
if (!$payload || ($payload['type'] ?? '') !== 'channel_partner') {
    header('Location: ../login/index.php?sessionexpiry');
    exit;
}

if ($_hadExistingCpSession) {
    session_regenerate_id(true);
}
$_SESSION['LOGIN_USER']      = $payload['mobile'];
$_SESSION['LOGIN_USER_ID']   = $payload['id'];
$_SESSION['LOGIN_USER_NAME'] = $payload['name'];
$_SESSION['LOGIN_USER_TYPE'] = $payload['type'];
$_SESSION['LINKED_ACCOUNTS'] = $payload['linked_accounts'] ?? [];
$_SESSION['last_activity']   = time();
unset($_SESSION['errorMessage'], $_SESSION['successMessage']);

header('Location: dashboard.php');
exit;
