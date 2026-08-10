<?php
session_start();
error_reporting(0);
require_once __DIR__ . '/include/db-connect.php';
unset($_SESSION['LOGIN_USER']);

// Also revoke the company/ bridge token (see CheckLogin.php) so a logged-out
// BDM can't still reach the shared Territory Partner pages there.
if (!empty($_COOKIE['femi9_bdm_bridge'])) {
    $_stmt = $db_conn->prepare("DELETE FROM salesbdm_company_bridge WHERE token = ?");
    $_stmt->bind_param('s', $_COOKIE['femi9_bdm_bridge']);
    $_stmt->execute();
    $_stmt->close();
    setcookie('femi9_bdm_bridge', '', ['expires' => time() - 3600, 'path' => '/']);
}

$_SESSION['successMessage']="Logout successfully";

echo "<script>window.location='index.php?outsuc';</script>";
?>
