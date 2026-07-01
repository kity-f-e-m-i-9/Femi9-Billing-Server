<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Onboarded Count";

if($_REQUEST['lable']==1 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Super Stockist";
$tablename="super_stockiest";}

else if($_REQUEST['lable']==2 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Stockist";
$tablename="stockiest";}

else if($_REQUEST['lable']==3 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Distributors";
$tablename="distributor";}

else if($_REQUEST['lable']==4 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Shop (Retailers)";
$tablename="shop";}

else if($_REQUEST['lable']==5 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Super Distributors";
$tablename="super_distributor";}

else
{$DISPLAY_LABLE="Outlet";
$tablename="outlet";}

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE."-".$from_date."(to)".$to_date.".csv";

											
// Fetch product data from database
$select_records = "select * from ".$tablename." where valid_from between '$from_date' and '$to_date' order by id asc";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "ID, Name, District, Mobile Number, Date of Reg\n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	
	
	$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
				

    // Prepare CSV row
    $csv_content .= '"' . $result_product_list["useridtext"] . '",' .
                    '"' . $result_product_list["name"]. '",' .
                    '"' . $district_name . '",' .
					'"' . $result_product_list["mobile_number"] . '",' .
                    '"' . $result_product_list["valid_from"] . "\"\n";
}




										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>