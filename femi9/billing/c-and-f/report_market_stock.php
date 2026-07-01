<?php
//Super stockist Stock
$select_distri_onboardusers_VLSUST="select * from super_stockiest where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_distri_onbarodusers_VLSUST=mysqli_query($db_conn,$select_distri_onboardusers_VLSUST);
while($result_distri_onbarodusers_VLSUST=mysqli_fetch_array($fetch_distri_onbarodusers_VLSUST))
{
	$stcokistID_VLSUST=$result_distri_onbarodusers_VLSUST['temp_id'];
	
$select_marketstock_VLSUST="select sum(closing_qty) from stock where user_type='super_stockiest' and user_id='$stcokistID_VLSUST'";
$fetch_marketstock_VLSUST=mysqli_query($db_conn,$select_marketstock_VLSUST);
$result_marketstock_VLSUST=mysqli_fetch_array($fetch_marketstock_VLSUST);

$Totalstock_VLSUST=$result_marketstock_VLSUST[0];
if($Totalstock_VLSUST!=NULL){
	$Totalstock_VLSUST_Show23=$Totalstock_VLSUST;}
else{$Totalstock_VLSUST_Show23="0.00";}

$Totalstock_VLSUST_Show+=$Totalstock_VLSUST_Show23;

}


//stockist Stock
$select_stockist_onboardusers="select * from stockiest where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_stockist_onbarodusers=mysqli_query($db_conn,$select_stockist_onboardusers);
while($result_stockist_onbarodusers=mysqli_fetch_array($fetch_stockist_onbarodusers))
{
	$stcokistID=$result_stockist_onbarodusers['temp_id'];

$select_marketstock_VLSTK="select sum(closing_qty) from stock where user_type='stockiest' and user_id='$stcokistID'";
$fetch_marketstock_VLSTK=mysqli_query($db_conn,$select_marketstock_VLSTK);
$result_marketstock_VLSTK=mysqli_fetch_array($fetch_marketstock_VLSTK);

$Totalstock_VLSTK=$result_marketstock_VLSTK[0];
if($Totalstock_VLSTK!=NULL){
	$Totalstock_VLSTK_Show23=$Totalstock_VLSTK;}
else{$Totalstock_VLSTK_Show23="0.00";}

$Totalstock_VLSTK_Show+=$Totalstock_VLSTK_Show23;

}


//distributor Stock
$select_distri_onboardusers_VLDIST="select * from distributor where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_distri_onbarodusers_VLDIST=mysqli_query($db_conn,$select_distri_onboardusers_VLDIST);
while($result_distri_onbarodusers_VLDIST=mysqli_fetch_array($fetch_distri_onbarodusers_VLDIST))
{
	$stcokistID_VLDIST=$result_distri_onbarodusers_VLDIST['temp_id'];
	
$select_marketstock_VLDIST="select sum(closing_qty) from stock where user_type='distributor' and user_id='$stcokistID_VLDIST'";
$fetch_marketstock_VLDIST=mysqli_query($db_conn,$select_marketstock_VLDIST);
$result_marketstock_VLDIST=mysqli_fetch_array($fetch_marketstock_VLDIST);

$Totalstock_VLDIST=$result_marketstock_VLDIST[0];
if($Totalstock_VLDIST!=NULL){
	$Totalstock_VLDIST_Show23=$Totalstock_VLDIST;}
else{$Totalstock_VLDIST_Show23="0.00";}

$Totalstock_VLDIST_Show+=$Totalstock_VLDIST_Show23;

}
?>