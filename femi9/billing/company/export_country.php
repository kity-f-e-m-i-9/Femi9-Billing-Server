<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Country.csv";

$select_records = "select * from country order by id asc";
$fetch_records = mysqli_query($db_conn, $select_records);

$csv_content = '';
$csv_content .= "Country Name, Country Code\n";

while ($result_product_list = mysqli_fetch_array($fetch_records)) {

    $csv_content .= '"' . $state_name . '",' .
                    '"' . $result_product_list["c_name"] . '",' .
                    '"' . $result_product_list["c_code"] . "\"\n";
}


// Clear any previously buffered output
ob_end_clean();
// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>



