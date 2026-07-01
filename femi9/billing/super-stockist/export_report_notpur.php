<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="ONBOARDED OLS BUT NOT PURCHASED";

if($_REQUEST['modelval']=="usr2")
{$DISPLAY_LABLE="Stockist"; $tblenma="stockiest"; $usertype_stock="stockiest";}

else if($_REQUEST['modelval']=="usr3")
{$DISPLAY_LABLE="Distributor"; $tblenma="distributor"; $usertype_stock="distributor";}

else
{$DISPLAY_LABLE="Outlet"; $tblenma="outlet"; $usertype_stock="outlet";}

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE.".csv";

// Initialize CSV content
$csv_content = '';



//-----------------------------------------------------------usr2-------------------------------------
if($_REQUEST['modelval']=="usr2"){	
	
	
// Header row for CSV
$csv_content .= "User ID, Name, District, Taluk\n";

// Fetching data and formatting into CSV rows

$selectRcd_VLSS1="select * from temp_not_purchased where purchse_count='0' and usertype='stockiest' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$userid=$resultRcd_VLSS1['userid'];
											//
										$selectusers="select * from ".$tblenma." where temp_id='$userid'";
										$fetchusers=mysqli_query($db_conn,$selectusers);
										$resultusers=mysqli_fetch_array($fetchusers);
											
											$state_id_VLSS1=$resultusers['state_id'];
											$district_id_VLSS1=$resultusers['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$taluk_id_VLSS2=$resultusers['taluk_id'];
$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS1' and dist_id='$district_id_VLSS1' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];

    // Prepare CSV row
    $csv_content .= '"' . $resultusers["useridtext"] . '",' .
                    '"' . $resultusers["name"]. '",' .
					'"' . $district_name_VLSS1. '",' .
                    '"' . $taluk_name_VLSS2 . "\"\n";
					
					
					
}
										
}





//-----------------------------------------------------------usr3-------------------------------------
if($_REQUEST['modelval']=="usr3"){	
	
	
// Header row for CSV
$csv_content .= "User ID, Name, District, Taluk\n";

// Fetching data and formatting into CSV rows

$selectRcd_VLSS1="select * from temp_not_purchased where purchse_count='0' and usertype='distributor' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$userid=$resultRcd_VLSS1['userid'];
											//
										$selectusers="select * from ".$tblenma." where temp_id='$userid'";
										$fetchusers=mysqli_query($db_conn,$selectusers);
										$resultusers=mysqli_fetch_array($fetchusers);
											
											$state_id_VLSS1=$resultusers['state_id'];
											$district_id_VLSS1=$resultusers['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$taluk_id_VLSS2=$resultusers['taluk_id'];
$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS1' and dist_id='$district_id_VLSS1' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];

    // Prepare CSV row
    $csv_content .= '"' . $resultusers["useridtext"] . '",' .
                    '"' . $resultusers["name"]. '",' .
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