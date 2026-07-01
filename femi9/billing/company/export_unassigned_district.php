<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Unassigned-Super-Stockist-Districtwise.csv";

// Fetch product data from database
$select_records = "select * from district order by id asc";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "State Name, District Name, Status\n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	
	
	//state details
											$state_id=$result_product_list['state_id'];
								$select_stateList="select * from `state` where id='$state_id'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $state_name=$result_stateList['st_name'];
							   
							   if($result_product_list["assigned_SSID"]=="Nil"){$lableshow="Available";}else{
								$lableshow="already user appointed";   
							   }
	

    // Prepare CSV row
    $csv_content .= 
													'"'.$state_name.'",' .
													'"'.$result_product_list["dist_name"].'",' .
                    '"' . $lableshow . "\"\n";
}

// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>



