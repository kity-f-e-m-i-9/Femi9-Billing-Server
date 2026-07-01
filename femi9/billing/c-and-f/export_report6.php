<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Market Stock Report";

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

if($_REQUEST['modelval']=="usr2"){
	
	// Header row for CSV
$csv_content .= "User ID, Name, District, Taluk, Available Stock\n";
	
	$selectRcd_VLSS2="select * from ".$tblenma." where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl' order by id asc";
										$fetchRcd_VLSS2=mysqli_query($db_conn,$selectRcd_VLSS2);
										while($resultRcd_VLSS2=mysqli_fetch_array($fetchRcd_VLSS2))
										{
											
											$state_id_VLSS2=$resultRcd_VLSS2['state_id'];
											$district_id_VLSS2=$resultRcd_VLSS2['district_id'];
											$taluk_id_VLSS2=$resultRcd_VLSS2['taluk_id'];

										$selectrecords_VLSS2="select * from district where state_id='$state_id_VLSS2' and id='$district_id_VLSS2'";
										$fetchrecords_VLSS2=mysqli_query($db_conn,$selectrecords_VLSS2);
										$resultrecords_VLSS2=mysqli_fetch_array($fetchrecords_VLSS2);
										$district_name_VLSS2=$resultrecords_VLSS2['dist_name'];
										
$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS2' and dist_id='$district_id_VLSS2' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
										
$select_marketstock_VLDIST_VLSS2="select sum(closing_qty) from stock where user_type='$usertype_stock' and user_id='".$resultRcd_VLSS2['temp_id']."'";
$fetch_marketstock_VLDIST_VLSS2=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS2);
$result_marketstock_VLDIST_VLSS2=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS2);

if($result_marketstock_VLDIST_VLSS2[0]!=NULL){
$Total_available_stock_VLSS2=$result_marketstock_VLDIST_VLSS2[0];
}else{$Total_available_stock_VLSS2="0";}
$Total_available_stock123_VLSS2+=$Total_available_stock_VLSS2;

if($Total_available_stock_VLSS2>0){

$csv_content .='"' . $resultRcd_VLSS2["useridtext"]. '",' .
							'"' . $resultRcd_VLSS2["name"]. '",' .
							'"' . $district_name_VLSS2. '",' .
							'"' . $taluk_name_VLSS2. '",' .
							'"' . $Total_available_stock_VLSS2 . "\"\n";

										}
										}
	
	
}




if($_REQUEST['modelval']=="usr3"){
	
	// Header row for CSV
$csv_content .= "User ID, Name, District, Taluk, Available Stock\n";


$selectRcd_VLSS3="select * from ".$tblenma." where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl' order by id asc";
										$fetchRcd_VLSS3=mysqli_query($db_conn,$selectRcd_VLSS3);
										while($resultRcd_VLSS3=mysqli_fetch_array($fetchRcd_VLSS3))
										{
											
											$state_id_VLSS3=$resultRcd_VLSS3['state_id'];
											$district_id_VLSS3=$resultRcd_VLSS3['district_id'];
											$taluk_id_VLSS3=$resultRcd_VLSS3['taluk_id'];

										$selectrecords_VLSS3="select * from district where state_id='$state_id_VLSS3' and id='$district_id_VLSS3'";
										$fetchrecords_VLSS3=mysqli_query($db_conn,$selectrecords_VLSS3);
										$resultrecords_VLSS3=mysqli_fetch_array($fetchrecords_VLSS3);
										$district_name_VLSS3=$resultrecords_VLSS3['dist_name'];
										
										$selectrecords_VLSS3_taluk="select * from taluk where state_id='$state_id_VLSS3' and dist_id='$district_id_VLSS3' and id='$taluk_id_VLSS3'";
$fetchrecords_VLSS3_taluk=mysqli_query($db_conn,$selectrecords_VLSS3_taluk);
$resultrecords_VLSS3_taluk=mysqli_fetch_array($fetchrecords_VLSS3_taluk);
$taluk_name_VLSS3=$resultrecords_VLSS3_taluk['taluk'];
										
$select_marketstock_VLDIST_VLSS3="select sum(closing_qty) from stock where user_type='$usertype_stock' and user_id='".$resultRcd_VLSS3['temp_id']."'";
$fetch_marketstock_VLDIST_VLSS3=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS3);
$result_marketstock_VLDIST_VLSS3=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS3);

if($result_marketstock_VLDIST_VLSS3[0]!=NULL){
$Total_available_stock_VLSS3=$result_marketstock_VLDIST_VLSS3[0];
}else{$Total_available_stock_VLSS3="0";}
$Total_available_stock123_VLSS3+=$Total_available_stock_VLSS3;

if($Total_available_stock_VLSS3>0)
{
$csv_content .='"' . $resultRcd_VLSS3["useridtext"]. '",' .
							'"' . $resultRcd_VLSS3["name"]. '",' .
							'"' . $district_name_VLSS3. '",' .
							'"' . $taluk_name_VLSS3. '",' .
							'"' . $Total_available_stock_VLSS3 . "\"\n";

										}
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