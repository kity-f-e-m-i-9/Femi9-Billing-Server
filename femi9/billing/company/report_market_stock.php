<?php
//Super stockist Stock
$select_marketstock_VLSS="select sum(closing_qty) from stock where user_type='super_stockiest'";
$fetch_marketstock_VLSS=mysqli_query($db_conn,$select_marketstock_VLSS);
$result_marketstock_VLSS=mysqli_fetch_array($fetch_marketstock_VLSS);

$Totalstock_VLSS=$result_marketstock_VLSS[0];
if($Totalstock_VLSS!=NULL){
	$Totalstock_VLSS_Show=$Totalstock_VLSS;}
else{$Totalstock_VLSS_Show="0.00";}


//stockist Stock
$select_marketstock_VLSTK="select sum(closing_qty) from stock where user_type='stockiest'";
$fetch_marketstock_VLSTK=mysqli_query($db_conn,$select_marketstock_VLSTK);
$result_marketstock_VLSTK=mysqli_fetch_array($fetch_marketstock_VLSTK);

$Totalstock_VLSTK=$result_marketstock_VLSTK[0];
if($Totalstock_VLSTK!=NULL){
	$Totalstock_VLSTK_Show=$Totalstock_VLSTK;}
else{$Totalstock_VLSTK_Show="0.00";}


//distributor Stock
$select_marketstock_VLDIST="select sum(closing_qty) from stock where user_type='distributor'";
$fetch_marketstock_VLDIST=mysqli_query($db_conn,$select_marketstock_VLDIST);
$result_marketstock_VLDIST=mysqli_fetch_array($fetch_marketstock_VLDIST);

$Totalstock_VLDIST=$result_marketstock_VLDIST[0];
if($Totalstock_VLDIST!=NULL){
	$Totalstock_VLDIST_Show=$Totalstock_VLDIST;}
else{$Totalstock_VLDIST_Show="0.00";}


//Outlet Stock
$select_marketstock_VLOUT="select sum(closing_qty) from stock where user_type='outlet'";
$fetch_marketstock_VLOUT=mysqli_query($db_conn,$select_marketstock_VLOUT);
$result_marketstock_VLOUT=mysqli_fetch_array($fetch_marketstock_VLOUT);

$Totalstock_VLOUT=$result_marketstock_VLOUT[0];
if($Totalstock_VLOUT!=NULL){
	$Totalstock_VLOUT_Show=$Totalstock_VLOUT;}
else{$Totalstock_VLOUT_Show="0.00";}


//Super Distributor Stock
$select_marketstock_VLSD="select sum(closing_qty) from stock where user_type='super_distributor'";
$fetch_marketstock_VLSD=mysqli_query($db_conn,$select_marketstock_VLSD);
$result_marketstock_VLSD=mysqli_fetch_array($fetch_marketstock_VLSD);

$Totalstock_VLSD=$result_marketstock_VLSD[0];
if($Totalstock_VLSD!=NULL){
	$Totalstock_VLSD_Show=$Totalstock_VLSD;}
else{$Totalstock_VLSD_Show="0.00";}
?>