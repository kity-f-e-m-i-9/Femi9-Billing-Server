<?php require_once __DIR__ . '/../shared/db-helpers.php';
//---------------------------------------------------------------------------
//---------------------------------------------------------------------------
//Distributor : Today
$select_market_DTOUTLT_TODAY="select count(distinct to_user_id,date) as numvisit from user_invoice where to_user_type='shop' and date between '".db_esc($today_date)."' and '".db_esc($today_date)."' and from_user_type='distributor'";
$fetch_market_DTOUTLT_TODAY=mysqli_query($db_conn,$select_market_DTOUTLT_TODAY);
$result_market_DTOUTLT_TODAY=mysqli_fetch_array($fetch_market_DTOUTLT_TODAY);
$Total_DTOUTLT_TODAY=$result_market_DTOUTLT_TODAY['numvisit'];


/*

//Distributor : Yesterday
$select_market_DTOUTLT_YSTRDY="select count(distinct to_user_id,date) as numvisit2 from user_invoice where to_user_type='shop' and date between '".db_esc($Yesterday_date)."' and '".db_esc($Yesterday_date)."' and from_user_type='distributor'";
$fetch_market_DTOUTLT_YSTRDY=mysqli_query($db_conn,$select_market_DTOUTLT_YSTRDY);
$result_market_DTOUTLT_YSTRDY=mysqli_fetch_array($fetch_market_DTOUTLT_YSTRDY);
$Total_DTOUTLT_YSTRDY=$result_market_DTOUTLT_YSTRDY['numvisit2'];


//Distributor : This Month
$select_market_DTOUTLT_THISMONTH="select count(distinct to_user_id,date) as numvisit3 from user_invoice where to_user_type='shop' and date between '".db_esc($start_date)."' and '".db_esc($endDate)."' and from_user_type='distributor'";
$fetch_market_DTOUTLT_THISMONTH=mysqli_query($db_conn,$select_market_DTOUTLT_THISMONTH);
$result_market_DTOUTLT_THISMONTH=mysqli_fetch_array($fetch_market_DTOUTLT_THISMONTH);
$Total_DTOUTLT_THISMONTH=$result_market_DTOUTLT_THISMONTH['numvisit3'];


//Distributor : Till Date
$select_market_DTOUTLT_TLLDTE="select count(distinct to_user_id,date) as numvisit4 from user_invoice where to_user_type='shop' and date between '".db_esc($lastmonth_date_start)."' and '".db_esc($lastmonth_date_end)."' and from_user_type='distributor'";
$fetch_market_DTOUTLT_TLLDTE=mysqli_query($db_conn,$select_market_DTOUTLT_TLLDTE);
$result_market_DTOUTLT_TLLDTE=mysqli_fetch_array($fetch_market_DTOUTLT_TLLDTE);
$Total_DTOUTLT_TLLDTE=$result_market_DTOUTLT_TLLDTE['numvisit4'];

*/

?>