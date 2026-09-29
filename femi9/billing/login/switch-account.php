<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
ini_set('session.gc_maxlifetime', 18000); // match 5-hour app timeout; php.ini default (24min) was killing idle sessions
session_start();
error_reporting(0);

require_once 'include/db-connect.php';
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/account-lib.php';

// Must already be authenticated with a known list of linked accounts.
// LOGIN_USER itself is no longer checked here: since each portal now has
// its own session cookie (see per-portal .htaccess), login/'s own PHPSESSID
// session never gets LOGIN_USER written into it any more — only
// LINKED_ACCOUNTS, set by authenticate.php/select-account.php right before
// handing off to a portal's own switch-login.php via bridge token.
if (empty($_SESSION['LINKED_ACCOUNTS'])) {
    header('Location: index.php');
    exit;
}

$requestedType = $_GET['type'] ?? '';

$target = null;
foreach ($_SESSION['LINKED_ACCOUNTS'] as $acct) {
    if ($acct['type'] === $requestedType) {
        $target = $acct;
        break;
    }
}

if (!$target) {
    $_SESSION['errorMessage'] = 'That account is not linked to this login.';
    header('Location: index.php');
    exit;
}

// Re-check the target account is still active right now — don't trust the
// snapshot captured at original login time.
$fresh = findAccountByMobile($db_conn, $target['type'], $target['mobile']);
if (!$fresh || !$fresh['active']) {
    $_SESSION['errorMessage'] = 'That account is no longer active.';
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../shared/session-bridge.php';

$cfg = getUserConfig($target['type']);
$bridgeToken = mintBridgeToken($db_conn, [
    'type'   => $target['type'],
    'id'     => $fresh['id'],
    'name'   => $fresh['name'],
    'mobile' => $fresh['mobile'],
], $_SESSION['LINKED_ACCOUNTS']);
header('Location: ../' . $cfg['folder'] . '/switch-login.php?token=' . urlencode($bridgeToken));
exit;
?>
