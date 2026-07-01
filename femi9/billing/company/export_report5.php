<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Purchase Order";

if($_REQUEST['lable']==1 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Today";}
else if($_REQUEST['lable']==2 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Yesterday";}
else if($_REQUEST['lable']==3 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="This Month";}
else if($_REQUEST['lable']==4 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Last Month till date";}
else
{$DISPLAY_LABLE="";}

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];

// Set the filename for download
$file = "".$Report_LABLE."-".$DISPLAY_LABLE."-".$from_date."(to)".$to_date.".csv";

$select_Count_records="select count(*) as numRecords from input_stock where input_date between '$from_date' and '$to_date'";
										$fetch_Count_records=mysqli_query($db_conn,$select_Count_records);
										$result_Count_records=mysqli_fetch_array($fetch_Count_records);
										if($result_Count_records['numRecords']>0)
										{
											
											
// Fetch product data from database
$select_records = "select distinct tempid from input_stock where input_date between '$from_date' and '$to_date' order by id asc";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "Company Profile, Date, Product Qty\n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	
	
	$ot_tempid=$result_product_list['tempid'];

										$selectrecords="select * from input_stock where tempid='$ot_tempid'";
										$fetchrecords=mysqli_query($db_conn,$selectrecords);
										$resultrecords=mysqli_fetch_array($fetchrecords);
											
											//company profile details
											$godownid=$resultrecords['godownid'];
$select_Customers="select * from company_godown where id='$godownid'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										
										$selectsumTotalQTY="select sum(input_qty) from input_stock where tempid='$ot_tempid'";
				$fetchsumTotalQTY=mysqli_query($db_conn,$selectsumTotalQTY);
				$resultsumTotalQTY=mysqli_fetch_array($fetchsumTotalQTY);
				if($resultsumTotalQTY[0]!=NULL){
				$TotalPrQty=$resultsumTotalQTY[0];
				}else{$TotalPrQty="0";}
				$TotalPrQty123+=$TotalPrQty;
				

    // Prepare CSV row
    $csv_content .= '"' . $result_Customers["gname"] . '",' .
                    '"' . $resultrecords["input_date"]. '",' .
                    '"' . $TotalPrQty . "\"\n";
}


										} ///count rows end

										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>