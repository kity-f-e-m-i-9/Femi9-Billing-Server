<?php

//-------------------------------------Super Stockist------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today
$select_count_ss_today="select count(*) as numss from super_stockiest where valid_from='$today_date'";
$fetch_count_ss_today=mysqli_query($db_conn,$select_count_ss_today);
$result_count_ss_today=mysqli_fetch_array($fetch_count_ss_today);
if($result_count_ss_today['numss']!=NULL){$countss_today=$result_count_ss_today['numss'];}
else{$countss_today="0";}

//This Month
$select_count_ss_month="select count(*) as numssmonth from super_stockiest where valid_from between '$start_date' and '$endDate'";
$fetch_count_ss_month=mysqli_query($db_conn,$select_count_ss_month);
$result_count_ss_month=mysqli_fetch_array($fetch_count_ss_month);
if($result_count_ss_month['numssmonth']!=NULL){$countss_month=$result_count_ss_month['numssmonth'];}
else{$countss_month="0";}

//Overall ss
$select_count_ss_Overall="select count(*) as numssmonthOVER from super_stockiest";
$fetch_count_ss_Overall=mysqli_query($db_conn,$select_count_ss_Overall);
$result_count_ss_Overall=mysqli_fetch_array($fetch_count_ss_Overall);
if($result_count_ss_Overall['numssmonthOVER']!=NULL){$countss_Overall=$result_count_ss_Overall['numssmonthOVER'];}
else{$countss_Overall="0";}



//-------------------------------------Stockist------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today
$select_count_stock_today="select count(*) as numss from stockiest where valid_from='$today_date'";
$fetch_count_stock_today=mysqli_query($db_conn,$select_count_stock_today);
$result_count_stock_today=mysqli_fetch_array($fetch_count_stock_today);
if($result_count_stock_today['numss']!=NULL){$count_stock_today=$result_count_stock_today['numss'];}
else{$count_stock_today="0";}

//This Month
$select_count_stock_month="select count(*) as numssmonth from stockiest where valid_from between '$start_date' and '$endDate'";
$fetch_count_stock_month=mysqli_query($db_conn,$select_count_stock_month);
$result_count_stock_month=mysqli_fetch_array($fetch_count_stock_month);
if($result_count_stock_month['numssmonth']!=NULL){$count_stock_month=$result_count_stock_month['numssmonth'];}
else{$count_stock_month="0";}

//Overall st
$select_count_st_Overall="select count(*) as numstmonthOVER from stockiest";
$fetch_count_st_Overall=mysqli_query($db_conn,$select_count_st_Overall);
$result_count_st_Overall=mysqli_fetch_array($fetch_count_st_Overall);
if($result_count_st_Overall['numstmonthOVER']!=NULL){$countst_Overall=$result_count_st_Overall['numstmonthOVER'];}
else{$countst_Overall="0";}



//-------------------------------------distributor------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today
$select_count_distributor_today="select count(*) as numss from distributor where valid_from='$today_date'";
$fetch_count_distributor_today=mysqli_query($db_conn,$select_count_distributor_today);
$result_count_distributor_today=mysqli_fetch_array($fetch_count_distributor_today);
if($result_count_distributor_today['numss']!=NULL){$count_distributor_today=$result_count_distributor_today['numss'];}
else{$count_distributor_today="0";}

//This Month
$select_count_distributor_month="select count(*) as numssmonth from distributor where valid_from between '$start_date' and '$endDate'";
$fetch_count_distributor_month=mysqli_query($db_conn,$select_count_distributor_month);
$result_count_distributor_month=mysqli_fetch_array($fetch_count_distributor_month);
if($result_count_distributor_month['numssmonth']!=NULL){$count_distributor_month=$result_count_distributor_month['numssmonth'];}
else{$count_distributor_month="0";}

//Overall dt
$select_count_dt_Overall="select count(*) as numdtmonthOVER from distributor";
$fetch_count_dt_Overall=mysqli_query($db_conn,$select_count_dt_Overall);
$result_count_dt_Overall=mysqli_fetch_array($fetch_count_dt_Overall);
if($result_count_dt_Overall['numdtmonthOVER']!=NULL){$countdt_Overall=$result_count_dt_Overall['numdtmonthOVER'];}
else{$countdt_Overall="0";}

//-------------------------------------Shop------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today
$select_count_shop_today="select count(*) as numss from shop where valid_from='$today_date'";
$fetch_count_shop_today=mysqli_query($db_conn,$select_count_shop_today);
$result_count_shop_today=mysqli_fetch_array($fetch_count_shop_today);
if($result_count_shop_today['numss']!=NULL){$count_shop_today=$result_count_shop_today['numss'];}
else{$count_shop_today="0";}

//This Month
$select_count_shop_month="select count(*) as numssmonth from shop where valid_from between '$start_date' and '$endDate'";
$fetch_count_shop_month=mysqli_query($db_conn,$select_count_shop_month);
$result_count_shop_month=mysqli_fetch_array($fetch_count_shop_month);
if($result_count_shop_month['numssmonth']!=NULL){$count_shop_month=$result_count_shop_month['numssmonth'];}
else{$count_shop_month="0";}

//Overall shop
$select_count_shp_Overall="select count(*) as numshpmonthOVER from shop";
$fetch_count_shp_Overall=mysqli_query($db_conn,$select_count_shp_Overall);
$result_count_shp_Overall=mysqli_fetch_array($fetch_count_shp_Overall);
if($result_count_shp_Overall['numshpmonthOVER']!=NULL){$countshp_Overall=$result_count_shp_Overall['numshpmonthOVER'];}
else{$countshp_Overall="0";}

//-------------------------------------Outlet------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today
$select_count_outlet_today="select count(*) as numss from outlet where valid_from='$today_date'";
$fetch_count_outlet_today=mysqli_query($db_conn,$select_count_outlet_today);
$result_count_outlet_today=mysqli_fetch_array($fetch_count_outlet_today);
if($result_count_outlet_today['numss']!=NULL){$count_outlet_today=$result_count_outlet_today['numss'];}
else{$count_outlet_today="0";}

//This Month
$select_count_outlet_month="select count(*) as numssmonth from outlet where valid_from between '$start_date' and '$endDate'";
$fetch_count_outlet_month=mysqli_query($db_conn,$select_count_outlet_month);
$result_count_outlet_month=mysqli_fetch_array($fetch_count_outlet_month);
if($result_count_outlet_month['numssmonth']!=NULL){$count_outlet_month=$result_count_outlet_month['numssmonth'];}
else{$count_outlet_month="0";}

//Overall outlet
$select_count_otl_Overall="select count(*) as numotlmonthOVER from outlet";
$fetch_count_otl_Overall=mysqli_query($db_conn,$select_count_otl_Overall);
$result_count_otl_Overall=mysqli_fetch_array($fetch_count_otl_Overall);
if($result_count_otl_Overall['numotlmonthOVER']!=NULL){$countotl_Overall=$result_count_otl_Overall['numotlmonthOVER'];}
else{$countotl_Overall="0";}



//-------------------------------------Super Distributor------------------------------
//---------------------------------------------------------------------------------
//---------------------------------------------------------------------------------
//Today
$select_count_SD_today="select count(*) as numss from super_distributor where valid_from='$today_date'";
$fetch_count_SD_today=mysqli_query($db_conn,$select_count_SD_today);
$result_count_SD_today=mysqli_fetch_array($fetch_count_SD_today);
if($result_count_SD_today['numss']!=NULL){$count_SD_today=$result_count_SD_today['numss'];}
else{$count_SD_today="0";}

//This Month
$select_count_SD_month="select count(*) as numssmonth from super_distributor where valid_from between '$start_date' and '$endDate'";
$fetch_count_SD_month=mysqli_query($db_conn,$select_count_SD_month);
$result_count_SD_month=mysqli_fetch_array($fetch_count_SD_month);
if($result_count_SD_month['numssmonth']!=NULL){$count_SD_month=$result_count_SD_month['numssmonth'];}
else{$count_SD_month="0";}

//Overall dt
$select_count_SD_Overall="select count(*) as numdtmonthOVER from super_distributor";
$fetch_count_SD_Overall=mysqli_query($db_conn,$select_count_SD_Overall);
$result_count_SD_Overall=mysqli_fetch_array($fetch_count_SD_Overall);
if($result_count_SD_Overall['numdtmonthOVER']!=NULL)
{
$count_SD_Overall=$result_count_SD_Overall['numdtmonthOVER'];
}
else
{
$count_SD_Overall="0";
}
?>