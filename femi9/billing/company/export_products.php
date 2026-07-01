<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");

// Set the filename for download
$file = "Products.csv";

// Fetch product data from database
$select_records = "SELECT * FROM products ORDER BY id ASC";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "Product Name,MRP (Rs),SS Price (Rs),Stockist Price (Rs),Distributor Price (Rs),Shop Price (Rs),GST (%),HSN\n";

// Fetching data and formatting into CSV rows
while ($result_records = mysqli_fetch_array($fetch_records)) {
    // Prepare CSV row
    $csv_content .= '"' . $result_records["productName"] . '",' .
                    '"' . $result_records["mrp"] . '.00",' .
                    '"' . $result_records["supersstock_price"] . '.00",' .
                    '"' . $result_records["stockist_price"] . '.00",' .
                    '"' . $result_records["distributor_price"] . '.00",' .
                    '"' . $result_records["outlet_price"] . '.00",' .
                    '"' . $result_records["gst"] . '%",' .
                    '"' . $result_records["hsn"] . "\"\n";
}

// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>