<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="RETURN STOCK REPORTS";

if($_REQUEST['lable']==2)
{
	$DISPLAY_LABLE="Stockist"; 			$usertypevl="stockiest"; $tblname="stockiest";
}

else
{
	$DISPLAY_LABLE="Distributor";  		$usertypevl="distributor"; $tblname="distributor";
}


$start_date=$_REQUEST['frdate'];
$endDate=$_REQUEST['todate'];	

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE."-".$start_date."(to)".$endDate.".csv";

// Initialize CSV content
$csv_content = '';



//-----------------------------------------------------------usr2-------------------------------------
if($_REQUEST['lable']==2){
	
	
// Header row for CSV
$csv_content .= "Inv Num,Date of return,Qty of return,User ID,Name, District, Taluk\n";

// Fetching data and formatting into CSV rows

$select_market_SSCASH_VLSS_THISMONTH="select * from user_return_stock where from_usertype='$usertypevl' and date between '$start_date' and '$endDate' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_SSCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_SSCASH_VLSS_THISMONTH);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$to_user_id=$result_market_SSCASH_VLSS_THISMONTH['from_userid'];
	$returnid=$result_market_SSCASH_VLSS_THISMONTH['returnid'];
	
	//qty of returned
	$select_sumqty="select sum(qty) from user_return_stock_items where returnid='$returnid'";
	$fetch_sumqty=mysqli_query($db_conn,$select_sumqty);
	$result_sumqty=mysqli_fetch_array($fetch_sumqty);
	
	$selectRcd_VLSS1="select * from ".$tblname." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
	
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];
											$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];
										$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS1' and dist_id='$district_id_VLSS1' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];

     // Prepare CSV row
    $csv_content .= '"' . $result_market_SSCASH_VLSS_THISMONTH['invnumber'] . '",' .
                    '"' . $result_market_SSCASH_VLSS_THISMONTH['date']. '",' .
					 '"' . $result_sumqty[0]. '",' .
					  '"' . $useridtext. '",' .
					   '"' . $usernametext. '",' .
					    '"' . $district_name_VLSS1. '",' .
                    '"' . $taluk_name_VLSS2 . "\"\n";
					
					
					
}
										
}





//-----------------------------------------------------------usr3-------------------------------------
if($_REQUEST['lable']==3){
	
	
// Header row for CSV
$csv_content .= "Inv Num,Date of return,Qty of return,User ID,Name, District, Taluk\n";

// Fetching data and formatting into CSV rows

$select_market_SSCASH_VLSS_THISMONTH="select * from user_return_stock where from_usertype='$usertypevl' and date between '$start_date' and '$endDate' and to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl'";
$fetch_market_SSCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_SSCASH_VLSS_THISMONTH);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$to_user_id=$result_market_SSCASH_VLSS_THISMONTH['from_userid'];
	$returnid=$result_market_SSCASH_VLSS_THISMONTH['returnid'];
	
	//qty of returned
	$select_sumqty="select sum(qty) from user_return_stock_items where returnid='$returnid'";
	$fetch_sumqty=mysqli_query($db_conn,$select_sumqty);
	$result_sumqty=mysqli_fetch_array($fetch_sumqty);
	
	$selectRcd_VLSS1="select * from ".$tblname." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
	
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];
											$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];
										$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS1' and dist_id='$district_id_VLSS1' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];


     // Prepare CSV row
    $csv_content .= '"' . $result_market_SSCASH_VLSS_THISMONTH['invnumber'] . '",' .
                    '"' . $result_market_SSCASH_VLSS_THISMONTH['date']. '",' .
					 '"' . $result_sumqty[0]. '",' .
					  '"' . $useridtext. '",' .
					   '"' . $usernametext. '",' .
					    '"' . $district_name_VLSS1. '",' .
                    '"' . $taluk_name_VLSS2 . "\"\n";
					
					
					
}
										
}





										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>