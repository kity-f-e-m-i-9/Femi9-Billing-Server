<?php 
date_default_timezone_set("Asia/Kolkata");
/*--------user type------*/
//company
//super_stockiest
//stockiest
//super_distributor
//distributor	

/*------onboard column name------*/
//onboard_userTYPE
//onboard_userID

//-----------------------------------------------------------------------------
//-----------------------------------------------------------------------------

require_once __DIR__ . '/../shared/user-config.php';

// Define current user type
define('CURRENT_USER_TYPE', 'marketing');

// Get configuration
$userConfig = getUserConfig(CURRENT_USER_TYPE);

if (!$userConfig) {
    die('Invalid user type configuration');
}

// Set global variables for backward compatibility
$userTable = $userConfig['table'];
$userDisplayName = $userConfig['display_name'];
$business_name = "Femi9 - Happy day Everyday";

$select_LoGuserDtails="select * from marketing_staff where ms_mobile='".$_SESSION['LOGIN_USER']."'";
$fetch_LoGuserDtails=mysqli_query($db_conn,$select_LoGuserDtails);
$result_LoGuserDtails=mysqli_fetch_array($fetch_LoGuserDtails);

$Login_user_TYPEvl="marketing";
$markeingSTFID=$result_LoGuserDtails['id'];

//-------------------------------------------------------------------------------
//-------------------------------------------------------------------------------
?>