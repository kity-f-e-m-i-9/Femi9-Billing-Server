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
define('CURRENT_USER_TYPE', 'distributor');

// Get configuration
$userConfig = getUserConfig(CURRENT_USER_TYPE);

if (!$userConfig) {
    die('Invalid user type configuration');
}

// Set global variables for backward compatibility
$userTable = $userConfig['table'];
$userDisplayName = $userConfig['display_name'];
$business_name = "Femi9 - Happy day Everyday";

$select_LoGuserDtails="select * from distributor where username='".$_SESSION['LOGIN_USER']."'";
$fetch_LoGuserDtails=mysqli_query($db_conn,$select_LoGuserDtails);
$result_LoGuserDtails=mysqli_fetch_array($fetch_LoGuserDtails);

$Login_user_TYPEvl="distributor"; // user type
$Login_user_IDvl=$result_LoGuserDtails['temp_id']; // user id

$DummyStockistID="DMYSTKST/DSTR/003";
$DummyDistributorID="DMYDSTBTRS/DSTR/003";

$onboard_userTYPE="distributor";
$onboard_userID=$result_LoGuserDtails['temp_id'];


$Login_user_useridtext=$result_LoGuserDtails['useridtext'];

//REFERAL PERSON & CATEGORY DETAILS
$select_RFRDtailsCNG="select * from distributor_referral where distributor_id='$Login_user_IDvl'";
$fetch_RFRDtailsCNG=mysqli_query($db_conn,$select_RFRDtailsCNG);
if(mysqli_num_rows($fetch_RFRDtailsCNG)==1)
{
$result_RFRDtailsCNG=mysqli_fetch_array($fetch_RFRDtailsCNG);
$target_amount=$result_RFRDtailsCNG['target_amount'];

$select_RFRDtailsCNG_CAT="select * from distributor_category where amount='$target_amount'";
$fetch_RFRDtailsCNG_CAT=mysqli_query($db_conn,$select_RFRDtailsCNG_CAT);
$result_RFRDtailsCNG_CAT=mysqli_fetch_array($fetch_RFRDtailsCNG_CAT);
$Login_person_CAT=$result_RFRDtailsCNG_CAT['amount'];

}else{
	$target_amount="0";
	$Login_person_CAT="Nil";
}


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
?>