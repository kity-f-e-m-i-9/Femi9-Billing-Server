<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="PO RAISED BUT PRODUCT NOT DELIVERED";

if($_REQUEST['modelval']=="usr1" )
{$DISPLAY_LABLE="Super Stockist - Request to Company"; $tblenma="super_stockiest"; $usertype_stock="super_stockiest";}

else if($_REQUEST['modelval']=="usr2")
{$DISPLAY_LABLE="Stockist - Request to Super Stockist"; $tblenma="stockiest"; $usertype_stock="stockiest";}

else if($_REQUEST['modelval']=="usr3")
{$DISPLAY_LABLE="Distributor - Request to Stockist"; $tblenma="distributor"; $usertype_stock="distributor";}

else
{$DISPLAY_LABLE="Outlet"; $tblenma="outlet"; $usertype_stock="outlet";}

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE.".csv";

// Initialize CSV content
$csv_content = '';

//-----------------------------------------------------------usr3-------------------------------------
if($_REQUEST['modelval']=="usr1"){	
	
	
// Header row for CSV
$csv_content .= "Date of request,Qty of request,User ID,Name, District\n";

// Fetching data and formatting into CSV rows

$selectRcd_VLSS1190="select * from stock_request where fromusertype='super_stockiest' and status='pending'";
										$fetchRcd_VLSS1190=mysqli_query($db_conn,$selectRcd_VLSS1190);
										while($resultRcd_VLSS1190=mysqli_fetch_array($fetchRcd_VLSS1190))
										{
											
											$userid=$resultRcd_VLSS1190['fromuserid'];
											//
										$selectusers134="select * from ".$tblenma." where temp_id='$userid'";
										$fetchusers134=mysqli_query($db_conn,$selectusers134);
										$resultusers134=mysqli_fetch_array($fetchusers134);
											
											$state_id_VLSS1=$resultusers134['state_id'];
											$district_id_VLSS1=$resultusers134['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$reqid=$resultRcd_VLSS1190['reqid'];
										//
										$SltTotalQty190="select sum(qty) from stock_request_items where reqid='$reqid'";
										$fetchTotalQty190=mysqli_query($db_conn,$SltTotalQty190);
										$resultTotalQty190=mysqli_fetch_array($fetchTotalQty190);

    // Prepare CSV row
    $csv_content .= '"' . $resultRcd_VLSS1190['date'] . '",' .
                    '"' . $resultTotalQty190[0]. '",' .
					 '"' . $resultusers134['useridtext']. '",' .
					  '"' . $resultusers134['name']. '",' .
                    '"' . $district_name_VLSS1 . "\"\n";
					
					
					
}
										
}





//-----------------------------------------------------------usr2-------------------------------------
if($_REQUEST['modelval']=="usr2"){
	
	
// Header row for CSV
$csv_content .= "Date of request,Qty of request,User ID,Name, District, Taluk\n";

// Fetching data and formatting into CSV rows

$selectRcd_VLSS1="select * from stock_request where fromusertype='stockiest' and status='pending'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$userid=$resultRcd_VLSS1['fromuserid'];
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
										
$reqid=$resultRcd_VLSS1['reqid'];
										//
										$SltTotalQty190="select sum(qty) from stock_request_items where reqid='$reqid'";
										$fetchTotalQty190=mysqli_query($db_conn,$SltTotalQty190);
										$resultTotalQty190=mysqli_fetch_array($fetchTotalQty190);

    // Prepare CSV row
    $csv_content .= '"' . $resultRcd_VLSS1["date"] . '",' .
                    '"' . $resultTotalQty190[0]. '",' .
					'"' . $resultusers['useridtext']. '",' .
					'"' . $resultusers['name']. '",' .
					'"' . $district_name_VLSS1. '",' .
                    '"' . $taluk_name_VLSS2 . "\"\n";
					
					
					
}
										
}





//-----------------------------------------------------------usr3-------------------------------------
if($_REQUEST['modelval']=="usr3"){
	
	
// Header row for CSV
$csv_content .= "Date of request,Qty of request,User ID,Name, District, Taluk\n";

// Fetching data and formatting into CSV rows

$selectRcd_VLSS1="select * from stock_request where fromusertype='distributor' and status='pending'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$userid=$resultRcd_VLSS1['fromuserid'];
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
										
$reqid=$resultRcd_VLSS1['reqid'];
										//
										$SltTotalQty190="select sum(qty) from stock_request_items where reqid='$reqid'";
										$fetchTotalQty190=mysqli_query($db_conn,$SltTotalQty190);
										$resultTotalQty190=mysqli_fetch_array($fetchTotalQty190);

    $csv_content .= '"' . $resultRcd_VLSS1["date"] . '",' .
                    '"' . $resultTotalQty190[0]. '",' .
					'"' . $resultusers['useridtext']. '",' .
					'"' . $resultusers['name']. '",' .
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