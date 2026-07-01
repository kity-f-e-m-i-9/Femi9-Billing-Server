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

//-----------------------------------------------------------------------------
//-----------------------------------------------------------------------------

$select_LoGuserDtails="select * from c_and_f where username='".$_SESSION['LOGIN_USER']."'";
$fetch_LoGuserDtails=mysqli_query($db_conn,$select_LoGuserDtails);
$result_LoGuserDtails=mysqli_fetch_array($fetch_LoGuserDtails);

$Login_user_TYPEvl="candf"; // user type
$Login_user_IDvl=$result_LoGuserDtails['temp_id']; // user id

$DummyStockistID="DMYSTKST/CandF/002f";
$DummyDistributorID="DMYDSTBTRS/CandF/002f";

$onboard_userTYPE="candf";
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
?>