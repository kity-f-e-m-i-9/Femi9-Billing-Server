<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Cash Report";

if($_REQUEST['lable']==1 )
{$DISPLAY_LABLE="Super Stockist";
$usertypevl="super_stockiest";}

else if($_REQUEST['lable']==2)
{$DISPLAY_LABLE="Stockist";
$usertypevl="stockiest";}

else if($_REQUEST['lable']==4)
{$DISPLAY_LABLE="Super Distributor";
$usertypevl="super_distributor";}

else{$DISPLAY_LABLE="Distributor";
$usertypevl="distributor";}

$start_date=$_REQUEST['frdate'];
$endDate=$_REQUEST['todate'];

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE.".csv";

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "Inv Num, Date, Type,ID,Name,Mobile, District,Amount\n";

// Fetching data and formatting into CSV rows



$select_market_SSCASH_VLSS_THISMONTH="select distinct inv_id from receipt where from_user_type='$usertypevl' and date between '$start_date' and '$endDate'";
$fetch_market_SSCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_SSCASH_VLSS_THISMONTH);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$receipt_inv_id=$result_market_SSCASH_VLSS_THISMONTH['inv_id'];
	//
	$select_ReceiptDetails="select * from receipt where inv_id='$receipt_inv_id'";
	$fetch_ReceiptDetails=mysqli_query($db_conn,$select_ReceiptDetails);
	$result_ReceiptDetails=mysqli_fetch_array($fetch_ReceiptDetails);
	$to_user_type=$result_ReceiptDetails['to_user_type'];
	$to_user_id=$result_ReceiptDetails['to_user_id'];
	
if($to_user_type=="stockiest")
{$tablename="stockiest";}
else if($to_user_type=="distributor")
{$tablename="distributor";}
else{$tablename="shop";}
	
	
	//get invoice Number
	if($to_user_type=="stockiest" || $to_user_type=="distributor" || $to_user_type=="shop")
	{
	$select_inv_number="select * from user_invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	
	$selectRcd_VLSS1="select * from ".$tablename." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
	$mobilenumber=$resultRcd_VLSS1["mobile_number"];
	$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
	$district_id_VLSS1=$resultRcd_VLSS1['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
	
	}else{
		//CUSTOMER
	$select_inv_number="select * from invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	
	$selectRcd_VLSS1="select * from customers where id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext="---";
	$usernametext=$resultRcd_VLSS1["name"];
	$district_name_VLSS1="---";
	$mobilenumber=$resultRcd_VLSS1["mobile"];
	}
										
$select_marketstock_VLDIST_VLSS1="select sum(received) from receipt where inv_id='$receipt_inv_id'";
$fetch_marketstock_VLDIST_VLSS1=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS1);
$result_marketstock_VLDIST_VLSS1=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS1);

if($result_marketstock_VLDIST_VLSS1[0]!=NULL){
$Total_available_stock_VLSS1=number_format($result_marketstock_VLDIST_VLSS1[0],2,'.','');
}else{$Total_available_stock_VLSS1="0";}

if($Total_available_stock_VLSS1>0)
{
	
$Total_available_stock123_VLSS1+=$Total_available_stock_VLSS1;


    // Prepare CSV row
    $csv_content .= '"' . $inv_number . '",' .
                    '"' . $inv_date. '",' .
					'"' . $to_user_type. '",' .
					'"' . $useridtext. '",' .
					'"' . $usernametext. '",' .
					'"' . $mobilenumber. '",' .
					'"' . $district_name_VLSS1. '",' .
                    '"' . $Total_available_stock_VLSS1 . "\"\n";
					
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