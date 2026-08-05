<?php
if (!is_dir(session_save_path()) || !is_writable(session_save_path())) {
    session_save_path(sys_get_temp_dir());
}
session_start();
error_reporting(0);

require_once 'include/db-connect.php';
require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/account-lib.php';

// Must already be authenticated with a known list of linked accounts.
if (empty($_SESSION['LOGIN_USER']) || empty($_SESSION['LINKED_ACCOUNTS'])) {
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

$cfg = getUserConfig($target['type']);
activateAccountSession([
    'type'   => $target['type'],
    'id'     => $fresh['id'],
    'name'   => $fresh['name'],
    'mobile' => $fresh['mobile'],
]);
session_regenerate_id(true);
header('Location: ../' . $cfg['folder'] . '/dashboard.php');
exit;
?>
