<?php
// Consumes a portal_login_bridge token minted by track/CheckLogin.php
// after a successful track login. track/ has no cross-portal bridge or
// dual-account logic otherwise (see track/checksession.php's own
// "track ku salesbdm link aagave aagathu" note) — this bridge exists only
// to hand the resolved login off to track's own session cookie
// (femi9_track_sess — see .htaccess), same reason as salesbdm's
// switch-login-central.php above.
$_hadExistingSession = isset($_COOKIE[session_name() ?: 'PHPSESSID']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/session-bridge.php';

$token = $_GET['token'] ?? '';
$payload = $token ? consumeBridgeToken($db_conn, $token) : null;

if (!$payload || ($payload['type'] ?? '') !== 'track') {
    header('Location: index.php?sessionexpiry');
    exit;
}

if ($_hadExistingSession) {
    session_regenerate_id(true);
}
$_SESSION['LOGIN_USER']      = $payload['mobile'];
$_SESSION['LOGIN_USER_ID']   = $payload['id'];
$_SESSION['LOGIN_USER_NAME'] = $payload['name'];
$_SESSION['LOGIN_USER_TYPE'] = $payload['type'];
$_SESSION['last_activity']   = time();

header('Location: dashboard.php');
exit;
