<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

$get_t1=$_REQUEST['t1'];
$get_t2=$_REQUEST['t2'];

// Set the filename for download
$file = "GSTR3B(5)-Values-of-exempt-nil-rated-and-non-GST-inward-supplies.csv";

$csv_content = '';
$csv_content .= "Nature of Supplies, Inter-state supplies, Intra-state supplies\n";

//From a supplier under composition scheme, Exempt and Nil rated supply	
$label1_name="From a supplier under composition scheme, Exempt and Nil rated supply";
$label1_val1=$get_t1;
$label1_val2=$get_t2;

    $csv_content .= '"' . $label1_name . '",' .
                    '"' . $label1_val1 . '",' .
                    '"' . $label1_val2 . "\"\n";
					
//Non GST Supply		
$label2_name="Non GST Supply";
$label2_val1="0";
$label2_val2="0";

    $csv_content .= '"' . $label2_name . '",' .
                    '"' . $label2_val1 . '",' .
                    '"' . $label2_val2 . "\"\n";
					

// Clear any previously buffered output
ob_end_clean();
// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>



