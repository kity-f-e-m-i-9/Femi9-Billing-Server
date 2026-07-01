<?php


$select_oboard_distri="select temp_id from distributor where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fetch_oboard_distri=mysqli_query($db_conn,$select_oboard_distri);
while($result_oboard_distri=mysqli_fetch_array($fetch_oboard_distri))
{
	
	$distid_visitoutlet=$result_oboard_distri['temp_id'];

//---------------------------------------------------------------------------
//---------------------------------------------------------------------------
//Distributor : Today
$select_market_DTOUTLT_TODAY="select count(distinct to_user_id,date) as numvisit from user_invoice where to_user_type='shop' and date between '$today_date' and '$today_date' and from_user_type='distributor' and from_user_id='$distid_visitoutlet'";
$fetch_market_DTOUTLT_TODAY=mysqli_query($db_conn,$select_market_DTOUTLT_TODAY);
$result_market_DTOUTLT_TODAY=mysqli_fetch_array($fetch_market_DTOUTLT_TODAY);
$Total_DTOUTLT_TODAY23=$result_market_DTOUTLT_TODAY['numvisit'];
$Total_DTOUTLT_TODAY+=$Total_DTOUTLT_TODAY23;

//Distributor : Yesterday
$select_market_DTOUTLT_YSTRDY="select count(distinct to_user_id,date) as numvisit2 from user_invoice where to_user_type='shop' and date between '$Yesterday_date' and '$Yesterday_date' and from_user_type='distributor' and from_user_id='$distid_visitoutlet'";
$fetch_market_DTOUTLT_YSTRDY=mysqli_query($db_conn,$select_market_DTOUTLT_YSTRDY);
$result_market_DTOUTLT_YSTRDY=mysqli_fetch_array($fetch_market_DTOUTLT_YSTRDY);
$Total_DTOUTLT_YSTRDY23=$result_market_DTOUTLT_YSTRDY['numvisit2'];
$Total_DTOUTLT_YSTRDY+=$Total_DTOUTLT_YSTRDY23;


//Distributor : This Month
$select_market_DTOUTLT_THISMONTH="select count(distinct to_user_id,date) as numvisit3 from user_invoice where to_user_type='shop' and date between '$start_date' and '$endDate' and from_user_type='distributor' and from_user_id='$distid_visitoutlet'";
$fetch_market_DTOUTLT_THISMONTH=mysqli_query($db_conn,$select_market_DTOUTLT_THISMONTH);
$result_market_DTOUTLT_THISMONTH=mysqli_fetch_array($fetch_market_DTOUTLT_THISMONTH);
$Total_DTOUTLT_THISMONTH23=$result_market_DTOUTLT_THISMONTH['numvisit3'];
$Total_DTOUTLT_THISMONTH+=$Total_DTOUTLT_THISMONTH23;


//Distributor : Till Date
$select_market_DTOUTLT_TLLDTE="select count(distinct to_user_id,date) as numvisit4 from user_invoice where to_user_type='shop' and date between '$lastmonth_date_start' and '$lastmonth_date_end' and from_user_type='distributor' and from_user_id='$distid_visitoutlet'";
$fetch_market_DTOUTLT_TLLDTE=mysqli_query($db_conn,$select_market_DTOUTLT_TLLDTE);
$result_market_DTOUTLT_TLLDTE=mysqli_fetch_array($fetch_market_DTOUTLT_TLLDTE);
$Total_DTOUTLT_TLLDTE23=$result_market_DTOUTLT_TLLDTE['numvisit4'];
$Total_DTOUTLT_TLLDTE+=$Total_DTOUTLT_TLLDTE23;

}

?>