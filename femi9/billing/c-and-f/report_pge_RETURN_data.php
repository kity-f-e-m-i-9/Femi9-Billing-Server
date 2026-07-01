<?php
//-----------------------------------------------------------------------------
//super stockiest : Today
$select_market_RTNSUST_TODAY="select sum(qty) from user_return_stock_items where from_usertype='super_stockiest' and date between '$today_date' and '$today_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNSUST_TODAY=mysqli_query($db_conn,$select_market_RTNSUST_TODAY);
$result_market_RTNSUST_TODAY=mysqli_fetch_array($fetch_market_RTNSUST_TODAY);
if($result_market_RTNSUST_TODAY[0]!=NULL){
$Total_RTNSUST_TODAY=$result_market_RTNSUST_TODAY[0];
}else{$Total_RTNSUST_TODAY="0";}


//super stockiest : Yesterday
$select_market_RTNSUST_YSTRDY="select sum(qty) from user_return_stock_items where from_usertype='super_stockiest' and date between '$Yesterday_date' and '$Yesterday_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNSUST_YSTRDY=mysqli_query($db_conn,$select_market_RTNSUST_YSTRDY);
$result_market_RTNSUST_YSTRDY=mysqli_fetch_array($fetch_market_RTNSUST_YSTRDY);
if($result_market_RTNSUST_YSTRDY[0]!=NULL){
$Total_RTNSUST_YSTRDY=$result_market_RTNSUST_YSTRDY[0];
}else{$Total_RTNSUST_YSTRDY="0";}


//super stockiest : This Month
$select_market_RTNSUST_THISMONTH="select sum(qty) from user_return_stock_items where from_usertype='super_stockiest' and date between '$start_date' and '$endDate' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNSUST_THISMONTH=mysqli_query($db_conn,$select_market_RTNSUST_THISMONTH);
$result_market_RTNSUST_THISMONTH=mysqli_fetch_array($fetch_market_RTNSUST_THISMONTH);
if($result_market_RTNSUST_THISMONTH[0]!=NULL){
$Total_RTNSUST_THISMONTH=$result_market_RTNSUST_THISMONTH[0];
}else{$Total_RTNSUST_THISMONTH="0";}



//super stockiest : Till Date
$select_market_RTNSUST_TLLDTE="select sum(qty) from user_return_stock_items where from_usertype='super_stockiest' and date between '$lastmonth_date_start' and '$lastmonth_date_end' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNSUST_TLLDTE=mysqli_query($db_conn,$select_market_RTNSUST_TLLDTE);
$result_market_RTNSUST_TLLDTE=mysqli_fetch_array($fetch_market_RTNSUST_TLLDTE);
if($result_market_RTNSUST_TLLDTE[0]!=NULL){
$Total_RTNSUST_TLLDTE=$result_market_RTNSUST_TLLDTE[0];
}else{$Total_RTNSUST_TLLDTE="0";}


//-----------------------------------------------------------------------------
//stockiest : Today
$select_market_RTNST_TODAY="select sum(qty) from user_return_stock_items where from_usertype='stockiest' and date between '$today_date' and '$today_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNST_TODAY=mysqli_query($db_conn,$select_market_RTNST_TODAY);
$result_market_RTNST_TODAY=mysqli_fetch_array($fetch_market_RTNST_TODAY);
if($result_market_RTNST_TODAY[0]!=NULL){
$Total_RTNST_TODAY=$result_market_RTNST_TODAY[0];
}else{$Total_RTNST_TODAY="0";}


//stockiest : Yesterday
$select_market_RTNST_YSTRDY="select sum(qty) from user_return_stock_items where from_usertype='stockiest' and date between '$Yesterday_date' and '$Yesterday_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNST_YSTRDY=mysqli_query($db_conn,$select_market_RTNST_YSTRDY);
$result_market_RTNST_YSTRDY=mysqli_fetch_array($fetch_market_RTNST_YSTRDY);
if($result_market_RTNST_YSTRDY[0]!=NULL){
$Total_RTNST_YSTRDY=$result_market_RTNST_YSTRDY[0];
}else{$Total_RTNST_YSTRDY="0";}


//stockiest : This Month
$select_market_RTNST_THISMONTH="select sum(qty) from user_return_stock_items where from_usertype='stockiest' and date between '$start_date' and '$endDate' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNST_THISMONTH=mysqli_query($db_conn,$select_market_RTNST_THISMONTH);
$result_market_RTNST_THISMONTH=mysqli_fetch_array($fetch_market_RTNST_THISMONTH);
if($result_market_RTNST_THISMONTH[0]!=NULL){
$Total_RTNST_THISMONTH=$result_market_RTNST_THISMONTH[0];
}else{$Total_RTNST_THISMONTH="0";}



//stockiest : Till Date
$select_market_RTNST_TLLDTE="select sum(qty) from user_return_stock_items where from_usertype='stockiest' and date between '$lastmonth_date_start' and '$lastmonth_date_end' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNST_TLLDTE=mysqli_query($db_conn,$select_market_RTNST_TLLDTE);
$result_market_RTNST_TLLDTE=mysqli_fetch_array($fetch_market_RTNST_TLLDTE);
if($result_market_RTNST_TLLDTE[0]!=NULL){
$Total_RTNST_TLLDTE=$result_market_RTNST_TLLDTE[0];
}else{$Total_RTNST_TLLDTE="0";}



//-----------------------------------------------------------------------------
//distributor : Today
$select_market_RTNDT_TODAY="select sum(qty) from user_return_stock_items where from_usertype='distributor' and date between '$today_date' and '$today_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNDT_TODAY=mysqli_query($db_conn,$select_market_RTNDT_TODAY);
$result_market_RTNDT_TODAY=mysqli_fetch_array($fetch_market_RTNDT_TODAY);
if($result_market_RTNDT_TODAY[0]!=NULL){
$Total_RTNDT_TODAY=$result_market_RTNDT_TODAY[0];
}else{$Total_RTNDT_TODAY="0";}



//distributor : Yesterday
$select_market_RTNDT_YSTRDY="select sum(qty) from user_return_stock_items where from_usertype='distributor' and date between '$Yesterday_date' and '$Yesterday_date' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNDT_YSTRDY=mysqli_query($db_conn,$select_market_RTNDT_YSTRDY);
$result_market_RTNDT_YSTRDY=mysqli_fetch_array($fetch_market_RTNDT_YSTRDY);
if($result_market_RTNDT_YSTRDY[0]!=NULL){
$Total_RTNDT_YSTRDY=$result_market_RTNDT_YSTRDY[0];
}else{$Total_RTNDT_YSTRDY="0";}


//distributor : This Month
$select_market_RTNDT_THISMONTH="select sum(qty) from user_return_stock_items where from_usertype='distributor' and date between '$start_date' and '$endDate' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNDT_THISMONTH=mysqli_query($db_conn,$select_market_RTNDT_THISMONTH);
$result_market_RTNDT_THISMONTH=mysqli_fetch_array($fetch_market_RTNDT_THISMONTH);
if($result_market_RTNDT_THISMONTH[0]!=NULL){
$Total_RTNDT_THISMONTH=$result_market_RTNDT_THISMONTH[0];
}else{$Total_RTNDT_THISMONTH="0";}


//distributor : Till Date
$select_market_RTNDT_TLLDTE="select sum(qty) from user_return_stock_items where from_usertype='distributor' and date between '$lastmonth_date_start' and '$lastmonth_date_end' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_RTNDT_TLLDTE=mysqli_query($db_conn,$select_market_RTNDT_TLLDTE);
$result_market_RTNDT_TLLDTE=mysqli_fetch_array($fetch_market_RTNDT_TLLDTE);
if($result_market_RTNDT_TLLDTE[0]!=NULL){
$Total_RTNDT_TLLDTE=$result_market_RTNDT_TLLDTE[0];
}else{$Total_RTNDT_TLLDTE="0";}


?>