<?php

//-------------------------------------Stockist------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today
$select_count_stock_today="select count(*) as numss from stockiest where valid_from='$today_date' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_count_stock_today=mysqli_query($db_conn,$select_count_stock_today);
$result_count_stock_today=mysqli_fetch_array($fetch_count_stock_today);
if($result_count_stock_today['numss']!=NULL){$count_stock_today=$result_count_stock_today['numss'];}
else{$count_stock_today="0";}

//This Month
$select_count_stock_month="select count(*) as numssmonth from stockiest where valid_from between '$start_date' and '$endDate' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_count_stock_month=mysqli_query($db_conn,$select_count_stock_month);
$result_count_stock_month=mysqli_fetch_array($fetch_count_stock_month);
if($result_count_stock_month['numssmonth']!=NULL){$count_stock_month=$result_count_stock_month['numssmonth'];}
else{$count_stock_month="0";}

//Overall st
$select_count_st_Overall="select count(*) as numstmonthOVER from stockiest where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_count_st_Overall=mysqli_query($db_conn,$select_count_st_Overall);
$result_count_st_Overall=mysqli_fetch_array($fetch_count_st_Overall);
if($result_count_st_Overall['numstmonthOVER']!=NULL){$countst_Overall=$result_count_st_Overall['numstmonthOVER'];}
else{$countst_Overall="0";}



//-------------------------------------distributor------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today
$select_count_distributor_today="select count(*) as numss from distributor where valid_from='$today_date' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_count_distributor_today=mysqli_query($db_conn,$select_count_distributor_today);
$result_count_distributor_today=mysqli_fetch_array($fetch_count_distributor_today);
if($result_count_distributor_today['numss']!=NULL){$count_distributor_today=$result_count_distributor_today['numss'];}
else{$count_distributor_today="0";}

//This Month
$select_count_distributor_month="select count(*) as numssmonth from distributor where valid_from between '$start_date' and '$endDate' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_count_distributor_month=mysqli_query($db_conn,$select_count_distributor_month);
$result_count_distributor_month=mysqli_fetch_array($fetch_count_distributor_month);
if($result_count_distributor_month['numssmonth']!=NULL){$count_distributor_month=$result_count_distributor_month['numssmonth'];}
else{$count_distributor_month="0";}

//Overall dt
$select_count_dt_Overall="select count(*) as numdtmonthOVER from distributor where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_count_dt_Overall=mysqli_query($db_conn,$select_count_dt_Overall);
$result_count_dt_Overall=mysqli_fetch_array($fetch_count_dt_Overall);
if($result_count_dt_Overall['numdtmonthOVER']!=NULL){$countdt_Overall=$result_count_dt_Overall['numdtmonthOVER'];}
else{$countdt_Overall="0";}

//-------------------------------------Shop------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today
$select_count_shop_today="select count(*) as numss from shop where valid_from='$today_date' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_count_shop_today=mysqli_query($db_conn,$select_count_shop_today);
$result_count_shop_today=mysqli_fetch_array($fetch_count_shop_today);
if($result_count_shop_today['numss']!=NULL){$count_shop_today=$result_count_shop_today['numss'];}
else{$count_shop_today="0";}

//This Month
$select_count_shop_month="select count(*) as numssmonth from shop where valid_from between '$start_date' and '$endDate' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_count_shop_month=mysqli_query($db_conn,$select_count_shop_month);
$result_count_shop_month=mysqli_fetch_array($fetch_count_shop_month);
if($result_count_shop_month['numssmonth']!=NULL){$count_shop_month=$result_count_shop_month['numssmonth'];}
else{$count_shop_month="0";}

//Overall shop
$select_count_shp_Overall="select count(*) as numshpmonthOVER from shop where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_count_shp_Overall=mysqli_query($db_conn,$select_count_shp_Overall);
$result_count_shp_Overall=mysqli_fetch_array($fetch_count_shp_Overall);
if($result_count_shp_Overall['numshpmonthOVER']!=NULL){$countshp_Overall=$result_count_shp_Overall['numshpmonthOVER'];}
else{$countshp_Overall="0";}
?>