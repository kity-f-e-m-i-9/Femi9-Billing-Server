<?php
// Landing point for the central login handoff (login/authenticate.php,
// login/select-account.php, login/switch-account.php) — consumes a
// single-use portal_login_bridge token and starts this portal's own
// session (femi9_channel_partner_sess — see .htaccess) from it. Needed
// because this portal has its own session cookie name, separate from
// central login/'s PHPSESSID, so login/ can't write $_SESSION here
// directly.
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
$payload = $token ? consumeBridgeToken($db_conn, $token) : null;

if (!$payload || ($payload['type'] ?? '') !== 'channel_partner') {
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

header('Location: dashboard.php');
exit;
