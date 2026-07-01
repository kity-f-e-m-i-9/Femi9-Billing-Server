<?php 
/*--------user type------*/
//company
//super_stockiest
//stockiest
//distributor	

/*------onboard column name------*/
//onboard_userTYPE
//onboard_userID

//-----------------------------------------------------------------------------
//-----------------------------------------------------------------------------

require_once __DIR__ . '/../shared/user-config.php';

// Define current user type
define('CURRENT_USER_TYPE', 'super_stockiest');

// Get configuration
$userConfig = getUserConfig(CURRENT_USER_TYPE);

if (!$userConfig) {
    die('Invalid user type configuration');
}

// Set global variables for backward compatibility
$userTable = $userConfig['table'];
$userDisplayName = $userConfig['display_name'];
$business_name = "Femi9 - Happy day Everyday";

$select_LoGuserDtails="select * from super_stockiest where username='".$_SESSION['LOGIN_USER']."'";
$fetch_LoGuserDtails=mysqli_query($db_conn,$select_LoGuserDtails);
$result_LoGuserDtails=mysqli_fetch_array($fetch_LoGuserDtails);

$Login_user_TYPEvl="super_stockiest"; // user type
$Login_user_IDvl=$result_LoGuserDtails['temp_id']; // user id

$DummyStockistID="DMYSTKST/SUPSTK/002";
$DummyDistributorID="DMYDSTBTRS/SUPSTK/002";

$onboard_userTYPE="super_stockiest";
$onboard_userID=$result_LoGuserDtails['temp_id'];;

//-----------------------------------------------------------------------------
//-----------------------------------------------------------------------------

$invoice_logo="../../assets/images/flogo.png";
$invoice_logo_alt="FEMI9";
$invoice_logo_style="width: 100%; max-width: 150px;border-radius:5px;";
$forlable="Femi Health Care";

$invoice_from_line1="Femi9, Inc.";
$invoice_from_line2="101 / 1st Floor, 162/2 , PP Tower,Poondurai Main Road,
Chettipalayam,";
$invoice_from_line3="<b>GSTIN/UIN :</b> 33AGMPG9625P1ZS";
$invoice_from_line4="<b>State Name</b> : Tamil Nadu, <b>Code</b> : 33";
$invoice_from_line5="<b>Contact</b> : 9585711510";
$invoice_from_line6="<b>E-Mail</b> : femihealthcare21@gmail.com";

//-------------------------------------------------------------------------------
//-------------------------------------------------------------------------------

////https://mybusinesskit.in/femi9/billing/
/*$CallbackURL_own="http://localhost/cowsic/femi9/billing/super-stockist/success_ss.php";
$CallbackURL_own_distri="http://localhost/cowsic/femi9/billing/super-stockist/success_distributor.php";

$api_url_own="https://pay.mybusinesskit.net/api/create-order";
$user_token_own="5aca451e8b2461ac6c86ff275c5644e3";
$api_end_point_uri="https://pay.mybusinesskit.net/api/check-order-status";*/


//Sales Reward Points Concept - Updated in 31st May 2025
/*
1. user-invoice-action.php (rwpoints_sls)
2. user-invoice-action2.php (rwpoints_sls)
3. cnote_action.php (rwpoints_sls)
*/
?>