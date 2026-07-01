<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Outstanding";

if($_REQUEST['modelval']=="usr1" )
{$DISPLAY_LABLE="Super Stockist"; $tblenma="super_stockiest"; $usertype_stock="super_stockiest";}

else if($_REQUEST['modelval']=="usr2")
{$DISPLAY_LABLE="Stockist"; $tblenma="stockiest"; $usertype_stock="stockiest";}

else if($_REQUEST['modelval']=="usr3")
{$DISPLAY_LABLE="Distributor"; $tblenma="distributor"; $usertype_stock="distributor";}

else if($_REQUEST['modelval']=="usr4")
{$DISPLAY_LABLE="Shop"; $tblenma="shop"; $usertype_stock="shop";}

else if($_REQUEST['modelval']=="usr5")
{$DISPLAY_LABLE="Super Distributor"; $tblenma="super_distributor"; $usertype_stock="super_distributor";}

else
{$DISPLAY_LABLE="Outlet"; $tblenma="outlet"; $usertype_stock="outlet";}

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE.".csv";

// Initialize CSV content
$csv_content = '';
	
// Header row for CSV
$csv_content .= "User ID, Name, Mobile Number, District, Taluk, Outstanding\n";

// Fetching data and formatting into CSV rows
$selectRcd_VLSS1="select * from ".$tblenma." order by id asc";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];

//DISTRICT
if(is_numeric($district_id_VLSS1))
{
$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
	$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
			$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
}else{
	$district_name_VLSS1=$district_id_VLSS1;
}


if($resultRcd_VLSS1['taluk_id']!=NULL)
{
$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];
										
$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS1' and dist_id='$district_id_VLSS1' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
}else{ $taluk_name_VLSS2="Nil";}

//------------OUTSTANDING---------------------------------------------------------
$select_outstanding_SS_RCVD="select sum(received) from receipt where to_user_type='$usertype_stock' and to_user_id='".$resultRcd_VLSS1['temp_id']."'";
$fetch_outstanding_SS_RCVD=mysqli_query($db_conn,$select_outstanding_SS_RCVD);
$result_outstanding_SS_RCVD=mysqli_fetch_array($fetch_outstanding_SS_RCVD);

if($result_outstanding_SS_RCVD[0]!=NULL)
{ $SS_received_amount=$result_outstanding_SS_RCVD[0];}else{ $SS_received_amount="0";}

$select_outstanding_SS_RCVBL="select sum(total) from user_invoice where to_user_type='$usertype_stock' and to_user_id='".$resultRcd_VLSS1['temp_id']."'";
$fetch_outstanding_SS_RCVBL=mysqli_query($db_conn,$select_outstanding_SS_RCVBL);
$result_outstanding_SS_RCVBL=mysqli_fetch_array($fetch_outstanding_SS_RCVBL);

if($result_outstanding_SS_RCVBL[0]!=NULL)
{ $SS_receivable_amount=$result_outstanding_SS_RCVBL[0];}else{ $SS_receivable_amount="0";}

$Total_SS_outstanding=$SS_receivable_amount-$SS_received_amount;
//----------------------------------------------------------------------------------

$Total_available_stock123_VLSS1+=$Total_SS_outstanding;
	
	if($Total_SS_outstanding>0)
	{

    // Prepare CSV row
    $csv_content .= '"' . $resultRcd_VLSS1["useridtext"] . '",' .
                    '"' . $resultRcd_VLSS1["name"]. '",' .
					'"' . $resultRcd_VLSS1["mobile_number"]. '",' .
					'"' . $district_name_VLSS1. '",' .
					'"' . $taluk_name_VLSS2. '",' .
                    '"' . $Total_SS_outstanding . "\"\n";
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