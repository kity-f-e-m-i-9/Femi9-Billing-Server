<?php
//Receipt table(received) + invoice(total) = cash 

//---------------------------------------------------------------------------
//---------------------------------------------------------------------------
//Super stockist Cash : Today
$select_market_SSCASH_VLSS_TODAY="select sum(received) from receipt where from_user_type='super_stockiest' and date between '$today_date' and '$today_date'";
$fetch_market_SSCASH_VLSS_TODAY=mysqli_query($db_conn,$select_market_SSCASH_VLSS_TODAY);
$result_market_SSCASH_VLSS_TODAY=mysqli_fetch_array($fetch_market_SSCASH_VLSS_TODAY);

$Total_SSCASH_VLSS_TODAY=$result_market_SSCASH_VLSS_TODAY[0];

if($Total_SSCASH_VLSS_TODAY!=NULL)
{$Total_SSCASH_VLSS_Show_TODAY=number_format($Total_SSCASH_VLSS_TODAY,2,'.','');}
else
{$Total_SSCASH_VLSS_Show_TODAY="0.00";}

/*

//Super stockist Cash : Yesterday
$select_market_SSCASH_VLSS_YSTRDY="select sum(received) from receipt where from_user_type='super_stockiest' and date between '$Yesterday_date' and '$Yesterday_date'";
$fetch_market_SSCASH_VLSS_YSTRDY=mysqli_query($db_conn,$select_market_SSCASH_VLSS_YSTRDY);
$result_market_SSCASH_VLSS_YSTRDY=mysqli_fetch_array($fetch_market_SSCASH_VLSS_YSTRDY);

$Total_SSCASH_VLSS_YSTRDY=$result_market_SSCASH_VLSS_YSTRDY[0];

if($Total_SSCASH_VLSS_YSTRDY!=NULL)
{$Total_SSCASH_VLSS_Show_YSTRDY=number_format($Total_SSCASH_VLSS_YSTRDY,2,'.','');}
else
{$Total_SSCASH_VLSS_Show_YSTRDY="0.00";}


//Super stockist Cash : This Month
$select_market_SSCASH_VLSS_THISMONTH="select sum(received) from receipt where from_user_type='super_stockiest' and date between '$start_date' and '$endDate'";
$fetch_market_SSCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_SSCASH_VLSS_THISMONTH);
$result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH);

$Total_SSCASH_VLSS_THISMONTH=$result_market_SSCASH_VLSS_THISMONTH[0];

if($Total_SSCASH_VLSS_THISMONTH!=NULL)
{$Total_SSCASH_VLSS_Show_THISMONTH=number_format($Total_SSCASH_VLSS_THISMONTH,2,'.','');}
else
{$Total_SSCASH_VLSS_Show_THISMONTH="0.00";}


//Super stockist Cash : Till Date
$select_market_SSCASH_VLSS_TLLDTE="select sum(received) from receipt where from_user_type='super_stockiest' and date between '$lastmonth_date_start' and '$lastmonth_date_end'";
$fetch_market_SSCASH_VLSS_TLLDTE=mysqli_query($db_conn,$select_market_SSCASH_VLSS_TLLDTE);
$result_market_SSCASH_VLSS_TLLDTE=mysqli_fetch_array($fetch_market_SSCASH_VLSS_TLLDTE);

$Total_SSCASH_VLSS_TLLDTE=$result_market_SSCASH_VLSS_TLLDTE[0];

if($Total_SSCASH_VLSS_TLLDTE!=NULL)
{$Total_SSCASH_VLSS_Show_TLLDTE=number_format($Total_SSCASH_VLSS_TLLDTE,2,'.','');}
else
{$Total_SSCASH_VLSS_Show_TLLDTE="0.00";}

*/



//---------------------------------------------------------------------------
//---------------------------------------------------------------------------
//stockist Cash : Today
$select_market_STCASH_VLSS_TODAY="select sum(received) from receipt where from_user_type='stockiest' and date between '$today_date' and '$today_date'";
$fetch_market_STCASH_VLSS_TODAY=mysqli_query($db_conn,$select_market_STCASH_VLSS_TODAY);
$result_market_STCASH_VLSS_TODAY=mysqli_fetch_array($fetch_market_STCASH_VLSS_TODAY);


$Total_STCASH_VLSS_TODAY=$result_market_STCASH_VLSS_TODAY[0];

if($Total_STCASH_VLSS_TODAY!=NULL)
{$Total_STCASH_VLSS_Show_TODAY=number_format($Total_STCASH_VLSS_TODAY,2,'.','');}
else
{$Total_STCASH_VLSS_Show_TODAY="0.00";}


/*

//stockist Cash : Yesterday
$select_market_STCASH_VLSS_YSTRDY="select sum(received) from receipt where from_user_type='stockiest' and date between '$Yesterday_date' and '$Yesterday_date'";
$fetch_market_STCASH_VLSS_YSTRDY=mysqli_query($db_conn,$select_market_STCASH_VLSS_YSTRDY);
$result_market_STCASH_VLSS_YSTRDY=mysqli_fetch_array($fetch_market_STCASH_VLSS_YSTRDY);


$Total_STCASH_VLSS_YSTRDY=$result_market_STCASH_VLSS_YSTRDY[0];

if($Total_STCASH_VLSS_YSTRDY!=NULL)
{$Total_STCASH_VLSS_Show_YSTRDY=number_format($Total_STCASH_VLSS_YSTRDY,2,'.','');}
else
{$Total_STCASH_VLSS_Show_YSTRDY="0.00";}


//stockist Cash : This Month
$select_market_STCASH_VLSS_THISMONTH="select sum(received) from receipt where from_user_type='stockiest' and date between '$start_date' and '$endDate'";
$fetch_market_STCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_STCASH_VLSS_THISMONTH);
$result_market_STCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_STCASH_VLSS_THISMONTH);


$Total_STCASH_VLSS_THISMONTH=$result_market_STCASH_VLSS_THISMONTH[0];

if($Total_STCASH_VLSS_THISMONTH!=NULL)
{$Total_STCASH_VLSS_Show_THISMONTH=number_format($Total_STCASH_VLSS_THISMONTH,2,'.','');}
else
{$Total_STCASH_VLSS_Show_THISMONTH="0.00";}


//stockist Cash : Till Date
$select_market_STCASH_VLSS_TLLDTE="select sum(received) from receipt where from_user_type='stockiest' and date between '$lastmonth_date_start' and '$lastmonth_date_end'";
$fetch_market_STCASH_VLSS_TLLDTE=mysqli_query($db_conn,$select_market_STCASH_VLSS_TLLDTE);
$result_market_STCASH_VLSS_TLLDTE=mysqli_fetch_array($fetch_market_STCASH_VLSS_TLLDTE);


$Total_STCASH_VLSS_TLLDTE=$result_market_STCASH_VLSS_TLLDTE[0];

if($Total_STCASH_VLSS_TLLDTE!=NULL)
{$Total_STCASH_VLSS_Show_TLLDTE=number_format($Total_STCASH_VLSS_TLLDTE,2,'.','');}
else
{$Total_STCASH_VLSS_Show_TLLDTE="0.00";}

*/


//---------------------------------------------------------------------------
//---------------------------------------------------------------------------
//Distributor Cash : Today
$select_market_DTCASH_VLSS_TODAY="select sum(received) from receipt where from_user_type='distributor' and date between '$today_date' and '$today_date'";
$fetch_market_DTCASH_VLSS_TODAY=mysqli_query($db_conn,$select_market_DTCASH_VLSS_TODAY);
$result_market_DTCASH_VLSS_TODAY=mysqli_fetch_array($fetch_market_DTCASH_VLSS_TODAY);

$Total_DTCASH_VLSS_TODAY=$result_market_DTCASH_VLSS_TODAY[0];

if($Total_DTCASH_VLSS_TODAY!=NULL)
{$Total_DTCASH_VLSS_Show_TODAY=number_format($Total_DTCASH_VLSS_TODAY,2,'.','');}
else
{$Total_DTCASH_VLSS_Show_TODAY="0.00";}


/*
//Distributor Cash : Yesterday
$select_market_DTCASH_VLSS_YSTRDY="select sum(received) from receipt where from_user_type='distributor' and date between '$Yesterday_date' and '$Yesterday_date'";
$fetch_market_DTCASH_VLSS_YSTRDY=mysqli_query($db_conn,$select_market_DTCASH_VLSS_YSTRDY);
$result_market_DTCASH_VLSS_YSTRDY=mysqli_fetch_array($fetch_market_DTCASH_VLSS_YSTRDY);

$Total_DTCASH_VLSS_YSTRDY=$result_market_DTCASH_VLSS_YSTRDY[0];

if($Total_DTCASH_VLSS_YSTRDY!=NULL)
{$Total_DTCASH_VLSS_Show_YSTRDY=number_format($Total_DTCASH_VLSS_YSTRDY,2,'.','');}
else
{$Total_DTCASH_VLSS_Show_YSTRDY="0.00";}


//Distributor Cash : This Month
$select_market_DTCASH_VLSS_THISMONTH="select sum(received) from receipt where from_user_type='distributor' and date between '$start_date' and '$endDate'";
$fetch_market_DTCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_DTCASH_VLSS_THISMONTH);
$result_market_DTCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_DTCASH_VLSS_THISMONTH);

$Total_DTCASH_VLSS_THISMONTH=$result_market_DTCASH_VLSS_THISMONTH[0];

if($Total_DTCASH_VLSS_THISMONTH!=NULL)
{$Total_DTCASH_VLSS_Show_THISMONTH=number_format($Total_DTCASH_VLSS_THISMONTH,2,'.','');}
else
{$Total_DTCASH_VLSS_Show_THISMONTH="0.00";}


//Distributor Cash : Till Date
$select_market_DTCASH_VLSS_TLLDTE="select sum(received) from receipt where from_user_type='distributor' and date between '$lastmonth_date_start' and '$lastmonth_date_end'";
$fetch_market_DTCASH_VLSS_TLLDTE=mysqli_query($db_conn,$select_market_DTCASH_VLSS_TLLDTE);
$result_market_DTCASH_VLSS_TLLDTE=mysqli_fetch_array($fetch_market_DTCASH_VLSS_TLLDTE);

$Total_DTCASH_VLSS_TLLDTE=$result_market_DTCASH_VLSS_TLLDTE[0];

if($Total_DTCASH_VLSS_TLLDTE!=NULL)
{$Total_DTCASH_VLSS_Show_TLLDTE=number_format($Total_DTCASH_VLSS_TLLDTE,2,'.','');}
else
{$Total_DTCASH_VLSS_Show_TLLDTE="0.00";}

*/



//---------------------------------------------------------------------------
//---------------------------------------------------------------------------
//Super Distributor Cash : Today
$select_market_SDTCASH_VLSS_TODAY="select sum(received) from receipt where from_user_type='super_distributor' and date between '$today_date' and '$today_date'";
$fetch_market_SDTCASH_VLSS_TODAY=mysqli_query($db_conn,$select_market_SDTCASH_VLSS_TODAY);
$result_market_SDTCASH_VLSS_TODAY=mysqli_fetch_array($fetch_market_SDTCASH_VLSS_TODAY);

$Total_SDTCASH_VLSS_TODAY=$result_market_SDTCASH_VLSS_TODAY[0];

if($Total_SDTCASH_VLSS_TODAY!=NULL)
{$Total_SDTCASH_VLSS_Show_TODAY=number_format($Total_SDTCASH_VLSS_TODAY,2,'.','');}
else
{$Total_SDTCASH_VLSS_Show_TODAY="0.00";}
?>