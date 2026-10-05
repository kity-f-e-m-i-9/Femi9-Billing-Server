<?php
// Landing point for BOTH:
// 1. company/login-as-ms.php's admin "Login as Marketing Staff" bridge
//    (company_ms_login_bridge table) — new, same pattern as
//    territory-partner/switch-login.php's company_tp_login_bridge branch.
// 2. The central login handoff (login/authenticate.php,
//    login/select-account.php, login/switch-account.php) — consumes a
//    single-use portal_login_bridge token and starts this portal's own
//    session (femi9_marketing_sess — see .htaccess) from it. Needed because
//    this portal has its own session cookie name, separate from central
//    login/'s PHPSESSID, so login/ can't write $_SESSION here directly.
//
// Whether this portal's own cookie already existed BEFORE this request's
// session_start() determines whether session_regenerate_id() below is
// needed — calling it unconditionally sends a second Set-Cookie for the
// same name in the same response, and which one the browser keeps isn't
// guaranteed. Same fix already applied in company/switch-login.php and
// territory-partner/switch-login.php.
$_hadExistingSession = isset($_COOKIE[session_name() ?: 'PHPSESSID']);
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

$db_conn->query("CREATE TABLE IF NOT EXISTS company_ms_login_bridge (
    token VARCHAR(64) PRIMARY KEY,
    ms_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $db_conn->prepare("SELECT ms_id FROM company_ms_login_bridge WHERE token = ? AND expires_at > NOW() LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$bridgeRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Single-use — delete on read regardless of outcome, so the same link can
// never be replayed even if it was still within its window.
$del = $db_conn->prepare("DELETE FROM company_ms_login_bridge WHERE token = ?");
$del->bind_param('s', $token);
$del->execute();
$del->close();

if ($bridgeRow) {
    $stmt = $db_conn->prepare("SELECT id, ms_name, ms_mobile, account_status FROM marketing_staff WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param('i', $bridgeRow['ms_id']);
    $stmt->execute();
    $ms = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ms || (isset($ms['account_status']) && $ms['account_status'] != 'active')) {
        header('Location: ../login/index.php?sessionexpiry');
        exit;
    }

    if ($_hadExistingSession) {
        session_regenerate_id(true);
    }
    $_SESSION['LOGIN_USER']      = $ms['ms_mobile'];
    $_SESSION['LOGIN_USER_ID']   = $ms['id'];
    $_SESSION['LOGIN_USER_NAME'] = $ms['ms_name'];
    $_SESSION['LOGIN_USER_TYPE'] = 'marketing';
    $_SESSION['last_activity']   = time();
    // Marks this session as company-initiated so the header can offer a way
    // back — the company session itself was never touched (different cookie
    // name, see company/login-as-ms.php), this just points the header link.
    $_SESSION['LOGGED_IN_VIA_COMPANY'] = true;
    unset($_SESSION['errorMessage'], $_SESSION['successMessage']);

    header('Location: dashboard.php');
    exit;
}

// Not a company-bridge token — try the shared central-login bridge instead.
$payload = consumeBridgeToken($db_conn, $token);
if (!$payload || ($payload['type'] ?? '') !== 'marketing') {
    header('Location: ../login/index.php?sessionexpiry');
    exit;
}

if ($_hadExistingSession) {
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
