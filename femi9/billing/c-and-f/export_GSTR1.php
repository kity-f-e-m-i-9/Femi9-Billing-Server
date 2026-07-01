<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

$get_t1=$_REQUEST['t1'];
$get_t2=$_REQUEST['t2'];
$get_t3=$_REQUEST['t3'];
$get_t4=$_REQUEST['t4'];

// Set the filename for download
$file = "GSTR1.csv";

$csv_content = '';
$csv_content .= "Description, Nil Rated Supplies, Exempted (Other than Nil Rated/non GST Supply), Non GST Supplies\n";

//intra registered
$label1_name="Intra-state supplies to registered person";
$label1_val1=$get_t1;
$label1_val2="0";
$label1_val3="0";

    $csv_content .= '"' . $label1_name . '",' .
                    '"' . $label1_val1 . '",' .
					'"' . $label1_val2 . '",' .
                    '"' . $label1_val3 . "\"\n";
					
					//intra unregistered
$label2_name="Intra-state supplies to unregistered person";
$label2_val1=$get_t2;
$label2_val2="0";
$label2_val3="0";

    $csv_content .= '"' . $label2_name . '",' .
                    '"' . $label2_val1 . '",' .
					'"' . $label2_val2 . '",' .
                    '"' . $label2_val3 . "\"\n";
					
					//inter registered
$label3_name="Inter-state supplies to registered person";
$label3_val1=$get_t3;
$label3_val2="0";
$label3_val3="0";

    $csv_content .= '"' . $label3_name . '",' .
                    '"' . $label3_val1 . '",' .
					'"' . $label3_val2 . '",' .
                    '"' . $label3_val3 . "\"\n";
					
					//inter unregistered
$label4_name="Inter-state supplies to unregistered person";
$label4_val1=$get_t3;
$label4_val2="0";
$label4_val3="0";

    $csv_content .= '"' . $label4_name . '",' .
                    '"' . $label4_val1 . '",' .
					'"' . $label4_val2 . '",' .
                    '"' . $label4_val3 . "\"\n";


// Clear any previously buffered output
ob_end_clean();
// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>



