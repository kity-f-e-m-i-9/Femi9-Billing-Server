<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "State.csv";

// Fetch product data from database
$select_records = "select * from state order by st_name asc";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "State ID, State Name\n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	

    // Prepare CSV row
    $csv_content .= 
													'"'.$result_product_list["id"].'",' .
                    '"' . $result_product_list["st_name"] . "\"\n";
}

// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>



