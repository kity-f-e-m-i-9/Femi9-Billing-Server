<?php
// Consumes a portal_login_bridge token minted by this portal's own
// CheckLogin.php after a successful salesbdm login. Needed even though
// CheckLogin.php lives in this same folder: salesbdm/ now has its own
// session cookie (femi9_salesbdm_sess — see .htaccess), and CheckLogin.php
// runs before that cookie exists in the browser on a first-ever visit, so
// routing through one consistent mint+consume bridge (same as every other
// portal) avoids a separate direct-write code path that would silently
// diverge from how every other portal now logs in.
$_hadExistingSession = isset($_COOKIE[session_name() ?: 'PHPSESSID']);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/../shared/session-bridge.php';

$token = $_GET['token'] ?? '';
$payload = $token ? consumeBridgeToken($db_conn, $token) : null;

if (!$payload || ($payload['type'] ?? '') !== 'salesbdm') {
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
$_SESSION['LINKED_ACCOUNTS'] = $payload['linked_accounts'] ?? [];
$_SESSION['last_activity']   = time();

header('Location: dashboard.php');
exit;
