<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

require_once __DIR__ . '/../shared/user-config.php';

define('CURRENT_USER_TYPE', 'territory_partner');

$userConfig = getUserConfig(CURRENT_USER_TYPE);
if (!$userConfig) {
    die('Invalid user type configuration');
}

$userTable       = $userConfig['table'];
$userDisplayName = $userConfig['display_name'];
$business_name   = "Femi9 - Happy day Everyday";

// Shorthand variables available to all pages after checksession + config
$Login_user_TYPEvl = 'territory_partner';
$Login_user_IDvl   = $result_LoGuserDtails['id'];
$Login_user_tp_id  = $result_LoGuserDtails['tp_id'];
$Login_user_name   = $result_LoGuserDtails['name'];
$Login_user_mobile = $result_LoGuserDtails['mobile'];
?>
