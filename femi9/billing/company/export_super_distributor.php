<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Super-Distributors.csv";

// Fetch product data from database
$select_records = "select * from super_distributor where onboard_userTYPE='$onboard_userTYPE' and onboard_userID='$onboard_userID' order by id desc";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "ID, Name, State, District, Taluk, Pincode, Email, Mobile, GSTIN, Address\n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	
											$state_id=$result_product_list['state_id'];
											$district_id=$result_product_list['district_id'];
											$taluk_id=$result_product_list['taluk_id'];
											$pincode_id=$result_product_list['pincode_id'];

    // Prepare CSV row
    $csv_content .= '"' . $result_product_list["useridtext"] . '",' .
	'"'.$result_product_list["name"].'",' .
                    '"' . $state_id . '",' .
                    '"' . $district_id . '",' .
                    '"' . $taluk_id . '",' .
					'"' . $pincode_id . '",' .
					'"' . $result_product_list["email"] . '",' .
					'"' . $result_product_list["mobile_number"] . '",' .
					'"' . $result_product_list["gstin"] . '",' .
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



