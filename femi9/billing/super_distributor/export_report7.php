<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Outstanding";

if($_REQUEST['modelval']=="usr4")
{$DISPLAY_LABLE="Shop"; $tblenma="shop"; $usertype_stock="shop";}

else
{$DISPLAY_LABLE="Outlet"; $tblenma="outlet"; $usertype_stock="outlet";}

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE.".csv";

// Initialize CSV content
$csv_content = '';


//-----------------------------------------------------------usr4-------------------------------------
if($_REQUEST['modelval']=="usr4"){
	
	// Header row for CSV
$csv_content .= "User ID, Name, District, Taluk, Outstanding\n";



$selectRcd_VLSS4="select * from ".$tblenma." where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl' order by id asc";
										$fetchRcd_VLSS4=mysqli_query($db_conn,$selectRcd_VLSS4);
										while($resultRcd_VLSS4=mysqli_fetch_array($fetchRcd_VLSS4))
										{
											
											$state_id_VLSS4=$resultRcd_VLSS4['state_id'];
											$district_id_VLSS4=$resultRcd_VLSS4['district_id'];

										$selectrecords_VLSS4="select * from district where state_id='$state_id_VLSS4' and id='$district_id_VLSS4'";
										$fetchrecords_VLSS4=mysqli_query($db_conn,$selectrecords_VLSS4);
										$resultrecords_VLSS4=mysqli_fetch_array($fetchrecords_VLSS4);
										$district_name_VLSS4=$resultrecords_VLSS4['dist_name'];
										
										$taluk_id_VLSS4=$resultRcd_VLSS4['taluk_id'];
										
$selectrecords_VLSS4_taluk="select * from taluk where state_id='$state_id_VLSS4' and dist_id='$district_id_VLSS4' and id='$taluk_id_VLSS4'";
$fetchrecords_VLSS4_taluk=mysqli_query($db_conn,$selectrecords_VLSS4_taluk);
$resultrecords_VLSS4_taluk=mysqli_fetch_array($fetchrecords_VLSS4_taluk);
$taluk_name_VLSS4=$resultrecords_VLSS4_taluk['taluk'];
										
$select_marketstock_VLDIST_VLSS4="select sum(receivable) from receipt where to_user_type='$usertype_stock' and to_user_id='".$resultRcd_VLSS4['temp_id']."'";
$fetch_marketstock_VLDIST_VLSS4=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS4);
$result_marketstock_VLDIST_VLSS4=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS4);

if($result_marketstock_VLDIST_VLSS4[0]!=NULL){
$Total_available_stock_VLSS4=number_format($result_marketstock_VLDIST_VLSS4[0],2,'.','');
}else{$Total_available_stock_VLSS4="0";}
$Total_available_stock123_VLSS4+=$Total_available_stock_VLSS4;

if($Total_available_stock_VLSS4>0)
{
	
	
							$csv_content .='"' . $resultRcd_VLSS4["useridtext"]. '",' .
							'"' . $resultRcd_VLSS4["name"]. '",' .
							'"' . $district_name_VLSS4. '",' .
							'"' . $taluk_name_VLSS4. '",' .
							'"' . $Total_available_stock_VLSS4 . "\"\n";	
										
										}}
	
}



										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>