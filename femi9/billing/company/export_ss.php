<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Super-Stockist.csv";

// Fetch product data from database
$select_records = "select * from super_stockiest order by id desc";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "ID, Name, State, District, Email, Mobile, GSTIN, Address \n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	
	
	
	//state details
											$state_id=$result_product_list['state_id'];
								$select_stateList="select * from state where id='$state_id'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $state_name=$result_stateList['st_name'];
							   
							   
											//district
											$district_id=$result_product_list['district_id'];
										$select_district="select * from district where id=$district_id";
										$fetch_district=mysqli_query($db_conn,$select_district);
										$result_district=mysqli_fetch_array($fetch_district);
										$district_name=$result_district['dist_name'];


    // Prepare CSV row
    $csv_content .= 
	 '"'.$result_product_list["useridtext"].'",' .
	 '"'.$result_product_list["name"].'",' .
                                                    '"'.$state_name.'",' .
													'"'.$district_name.'",' .
													'"'.$result_product_list["email"].'",' .
													'"'.$result_product_list["mobile_number"].'",' .
													'"'.$result_product_list["gstin"].'",' .
                    '"' . $result_product_list["address"] . "\"\n";
}

// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>



