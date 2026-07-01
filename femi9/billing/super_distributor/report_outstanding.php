<?php
//stockist outstanding
$select_market_OUTST_VLSTK="select sum(receivable) from receipt where to_user_type='stockiest' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_market_OUTST_VLSTK=mysqli_query($db_conn,$select_market_OUTST_VLSTK);
$result_market_OUTST_VLSTK=mysqli_fetch_array($fetch_market_OUTST_VLSTK);

$Total_OUTST_VLSTK=$result_market_OUTST_VLSTK[0];
if($Total_OUTST_VLSTK!=NULL){
	$Total_OUTST_VLSTK_Show=number_format($Total_OUTST_VLSTK,2,'.','');}
else{$Total_OUTST_VLSTK_Show="0.00";}


//distributor outstanding
$select_market_OUTST_VLDIST="select sum(receivable) from receipt where to_user_type='distributor' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_market_OUTST_VLDIST=mysqli_query($db_conn,$select_market_OUTST_VLDIST);
$result_market_OUTST_VLDIST=mysqli_fetch_array($fetch_market_OUTST_VLDIST);

$Total_OUTST_VLDIST=$result_market_OUTST_VLDIST[0];
if($Total_OUTST_VLDIST!=NULL){
	$Total_OUTST_VLDIST_Show=number_format($Total_OUTST_VLDIST,2,'.','');}
else{$Total_OUTST_VLDIST_Show="0.00";}


//shop outstanding
$select_market_OUTST_VLSHOP="select sum(receivable) from receipt where to_user_type='shop' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
$fetch_market_OUTST_VLSHOP=mysqli_query($db_conn,$select_market_OUTST_VLSHOP);
$result_market_OUTST_VLSHOP=mysqli_fetch_array($fetch_market_OUTST_VLSHOP);

$Total_OUTST_VLSHOP=$result_market_OUTST_VLSHOP[0];
if($Total_OUTST_VLSHOP!=NULL){
	$Total_OUTST_VLSHOP_Show=number_format($Total_OUTST_VLSHOP,2,'.','');}
else{$Total_OUTST_VLSHOP_Show="0.00";}

?>