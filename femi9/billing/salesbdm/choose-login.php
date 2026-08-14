<?php
// POST-only handler for the "Which login?" chooser rendered inline on
// index.php. Reached only after a mobile+password pair has already been
// verified against BOTH sales_bdm_staff and admin_log in CheckLogin.php —
// this file never re-checks credentials, it just finishes whichever login
// the user picks. The pending_switch_* session keys are the proof that
// verification already happened.
session_start();
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/include/LoginHelpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['pending_switch_bdm_id']) || empty($_SESSION['pending_switch_admin_id'])) {
    header('Location: index.php');
    exit;
}

$choice = $_POST['choice'] ?? '';

if ($choice === 'salesbdm') {
    $stmt = mysqli_prepare($db_conn, "SELECT id, bdm_name, bdm_mobile, account_status FROM sales_bdm_staff WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['pending_switch_bdm_id']);
    mysqli_stmt_execute($stmt);
    $user = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    unset($_SESSION['pending_switch_bdm_id'], $_SESSION['pending_switch_bdm_mobile'], $_SESSION['pending_switch_bdm_name'], $_SESSION['pending_switch_admin_id'], $_SESSION['pending_switch_admin_username']);

    if ($user && $user['account_status'] === 'active') {
        finalizeSalesBdmSession($db_conn, $user);
        session_regenerate_id(true);
        header('Location: dashboard.php');
        exit;
    }
    header('Location: index.php?sessionexpiry');
    exit;
}

if ($choice === 'company') {
    $adminId = (int)$_SESSION['pending_switch_admin_id'];
    unset($_SESSION['pending_switch_bdm_id'], $_SESSION['pending_switch_bdm_mobile'], $_SESSION['pending_switch_bdm_name'], $_SESSION['pending_switch_admin_id'], $_SESSION['pending_switch_admin_username']);

    $token = createCompanySwitchToken($db_conn, $adminId);
    header('Location: ../company/switch-login.php?token=' . urlencode($token));
    exit;
}

header('Location: index.php');
exit;
