<?php
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/../shared/user-config.php';

define('CURRENT_USER_TYPE', 'track');

$userConfig = getUserConfig(CURRENT_USER_TYPE);
if (!$userConfig) {
    die('Invalid user type configuration');
}

$userTable = $userConfig['table'];
$userDisplayName = $userConfig['display_name'];
$business_name = "Femi9 - Happy day Everyday";

if (empty($result_LoGuserDtails)) {
    $select_LoGuserDtails = $db_conn->prepare("SELECT * FROM track_users WHERE mobile = ? LIMIT 1");
    $select_LoGuserDtails->bind_param('s', $_SESSION['LOGIN_USER']);
    $select_LoGuserDtails->execute();
    $result_LoGuserDtails = $select_LoGuserDtails->get_result()->fetch_assoc();
    $select_LoGuserDtails->close();
}

$Login_user_TYPEvl = "track";
$trackUserID = $result_LoGuserDtails['id'];
?>
