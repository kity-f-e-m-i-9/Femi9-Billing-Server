<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Customers.csv";

// Fetch product data from database
$select_records = "select * from customers where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' order by id ASC";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "Name, Mobile, Email, GSTIN, Address\n";

// Fetching data and formatting into CSV rows
while ($result_records = mysqli_fetch_array($fetch_records)) {
    // Prepare CSV row
    $csv_content .= '"' . $result_records["name"] . '",' .
                    '"' . $result_records["mobile"] . '",' .
                    '"' . $result_records["email"] . '",' .
                    '"' . $result_records["gstin"] . '",' .
                    '"' . $result_records["address"] . "\"\n";
}

// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>