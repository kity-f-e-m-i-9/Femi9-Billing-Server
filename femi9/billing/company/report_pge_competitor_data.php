<?php
mysqli_query($db_conn,"DELETE FROM temp_competerion_stock_report where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'");

//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------
$select_comp_stock_shop="select temp_id from shop";
$fetch_com_stock_shop=mysqli_query($db_conn,$select_comp_stock_shop);
while($result_com_stock_shop=mysqli_fetch_array($fetch_com_stock_shop))
{
	$shopid=$result_com_stock_shop['temp_id'];
	
$select_comp_stock_shop23="select max(date) asshopcomp from shop_competitor_stock where shop_id='$shopid'";
$fetch_com_stock_shop23=mysqli_query($db_conn,$select_comp_stock_shop23);
while($result_com_stock_shop23=mysqli_fetch_array($fetch_com_stock_shop23))
{

if($result_com_stock_shop23['asshopcomp']!=NULL){
$max_date_shop=$result_com_stock_shop23['asshopcomp'];
}else{
    date_default_timezone_set("Asia/Kolkata");
    $max_date_shop=date("Y-m-d");
}

$select_sum_comp_stock="select sum(qty) from shop_competitor_stock where shop_id='$shopid' and date='$max_date_shop'";
$fetch_sum_comp_stock=mysqli_query($db_conn,$select_sum_comp_stock);
$result_sum_comp_stock=mysqli_fetch_array($fetch_sum_comp_stock);
$Total_cmp_stock=$result_sum_comp_stock[0];

$select_COUNT_comp_stock="select count(*) as numcntcompeterion from temp_competerion_stock_report where shop_id='$shopid' and date='$max_date_shop' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_COUNT_comp_stock=mysqli_query($db_conn,$select_COUNT_comp_stock);
$result_COUNT_comp_stock=mysqli_fetch_array($fetch_COUNT_comp_stock);
if($result_COUNT_comp_stock['numcntcompeterion']==0 && $Total_cmp_stock>0)
{
	$insertRecords234="insert into temp_competerion_stock_report 
	(shop_id,date,qty,onboard_userTYPE,onboard_userID) 
	values ('$shopid','$max_date_shop','$Total_cmp_stock','$Login_user_TYPEvl','$Login_user_IDvl')";
	mysqli_query($db_conn,$insertRecords234);
}


}
	
	
}
//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------


//---------------------------------------------------------------------------
//---------------------------------------------------------------------------
//Competerion Stock Report

$select_market_CMPTRION_TLLDTE="select sum(qty) from temp_competerion_stock_report where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_market_CMPTRION_TLLDTE=mysqli_query($db_conn,$select_market_CMPTRION_TLLDTE);
$result_market_CMPTRION_TLLDTE=mysqli_fetch_array($fetch_market_CMPTRION_TLLDTE);

$Total_CMPTRION_TLLDTE=$result_market_CMPTRION_TLLDTE[0];

if($Total_CMPTRION_TLLDTE!=NULL)
{$Total_CMPTRION_Show_TLLDTE=$Total_CMPTRION_TLLDTE;}
else
{$Total_CMPTRION_Show_TLLDTE="0";}


?>