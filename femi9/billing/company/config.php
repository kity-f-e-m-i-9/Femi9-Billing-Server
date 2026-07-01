<?php 
/*--------user type------*/
//company
//candf
//super_stockiest
//stockiest
//super_distributor
//distributor	
//shop
//outlet

/*----USER ID GENERATE-----*/
//FEMI9-SS (SUPER STOCKIST)
//FEMI9-S (STOCKIST)
//FEMI9-SD (Super DISTRIBUTOR)
//FEMI9-D (DISTRIBUTOR)
//FEMI9-R (SHOP)
// user id used in stockist onboard - referral section

/*------onboard column name------*/
//onboard_userTYPE
//onboard_userID

/*
intra state - tamilnadu
interstate - other state
*/
error_reporting(0); 
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

require_once __DIR__ . '/../shared/user-config.php';

// Define current user type
define('CURRENT_USER_TYPE', 'company');

// Get configuration
$userConfig = getUserConfig(CURRENT_USER_TYPE);

if (!$userConfig) {
    die('Invalid user type configuration');
}

// Set global variables for backward compatibility
$userTable = $userConfig['table'];
$userDisplayName = $userConfig['display_name'];
$business_name = "Femi9 - Happy day Everyday";


$Log_users_Dtails134="select * from admin_log where username='".$_SESSION['LOGIN_USER']."'";
$Fetch_Log_users_Dtails134=mysqli_query($db_conn,$Log_users_Dtails134);
$Result_Log_users_Dtails134=mysqli_fetch_array($Fetch_Log_users_Dtails134);

$adminDetailsCONFGI="select state from admin_log where usertype='admin'";
$fetchDetailsCONFGI=mysqli_query($db_conn,$adminDetailsCONFGI);
$resultDetailsConfig=mysqli_fetch_array($fetchDetailsCONFGI);

$Config_Admin_State=$resultDetailsConfig['state'];

//-----------------------------------------------------------------------------
//-----------------------------------------------------------------------------

$Login_user_TYPEvl="company"; // user type
$Login_user_IDvl="company"; // user id

$DummySuperStockistID="DMYSS/CMP/001";
$DummyStockistID="DMYSTKST/CMP/001";
$DummyDistributorID="DMYDSTBTRS/CMP/001";

$onboard_userTYPE="company";
$onboard_userID="company";

//-----------------------------------------------------------------------------
//-----------------------------------------------------------------------------

/*$invoice_logo="../../assets/images/flogo.png";
$invoice_logo_alt="FEMI9";
$invoice_logo_style="width: 100%; max-width: 150px;border-radius:5px;";
$forlable="Femi Health Care";

$invoice_from_line1="Femi9, Inc.";
$invoice_from_line2="101 / 1st Floor, 162/2 , PP Tower,Poondurai Main Road,
Chettipalayam,";
$invoice_from_line3="<b>GSTIN/UIN :</b> 33AGMPG9625P1ZS";
$invoice_from_line4="<b>State Name</b> : Tamil Nadu, <b>Code</b> : 33";
$invoice_from_line5="<b>Contact</b> : 9585711510";
$invoice_from_line6="<b>E-Mail</b> : femihealthcare21@gmail.com";*/

//-------------------------------------------------------------------------------
//-------------------------------------------------------------------------------

////https://mybusinesskit.in/femi9/billing/
//$CallbackURL_own="http://localhost/cowsic/femi9/billing/company/success_ss.php";
//$CallbackURL_own_stockist="http://localhost/cowsic/femi9/billing/company/success_stockist.php";
//$CallbackURL_own_distri="http://localhost/cowsic/femi9/billing/company/success_distributor.php";

//$api_url_own="https://pay.mybusinesskit.net/api/create-order";
//$user_token_own="5aca451e8b2461ac6c86ff275c5644e3";
//$api_end_point_uri="https://pay.mybusinesskit.net/api/check-order-status";
?>