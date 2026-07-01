<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

$get_t1=$_REQUEST['t1'];

// Set the filename for download
$file = "GSTR3B(3.1)-Details-of-Outward-Supplies-and-inward-supplies-liable-to-reverse-charge.csv";

$csv_content = '';
$csv_content .= "Nature of Supplies, Total Taxable value, Integrated Tax, Central Tax, State/UT Tax, CESS\n";

//(a) Outward taxable supplies (other than zero rated, nil rated and exempted)	
$label1_name="(a) Outward taxable supplies (other than zero rated, nil rated and exempted)";
$label1_val1="0";
$label1_val2="0";
$label1_val3="0";
$label1_val4="0";
$label1_val5="0";

    $csv_content .= '"' . $label1_name . '",' .
                    '"' . $label1_val1 . '",' .
					'"' . $label1_val2 . '",' .
					'"' . $label1_val3 . '",' .
					'"' . $label1_val4 . '",' .
                    '"' . $label1_val5 . "\"\n";
					
					//(b) Outward taxable supplies (zero rated)		
$label2_name="(b) Outward taxable supplies (zero rated)";
$label2_val1="0";
$label2_val2="0";
$label2_val3="0";
$label2_val4="0";
$label2_val5="0";

    $csv_content .= '"' . $label2_name . '",' .
                    '"' . $label2_val1 . '",' .
					'"' . $label2_val2 . '",' .
					'"' . $label2_val3 . '",' .
					'"' . $label2_val4 . '",' .
                    '"' . $label2_val5 . "\"\n";
					
					
					//(c) Other outward supplies (Nil rated, exempted)		
$label3_name="(c) Other outward supplies (Nil rated, exempted)";
$label3_val1=$get_t1;
$label3_val2="0";
$label3_val3="0";
$label3_val4="0";
$label3_val5="0";

    $csv_content .= '"' . $label3_name . '",' .
                    '"' . $label3_val1 . '",' .
					'"' . $label3_val2 . '",' .
					'"' . $label3_val3 . '",' .
					'"' . $label3_val4 . '",' .
                    '"' . $label3_val5 . "\"\n";
					
					
					//(d) Inward supplies (liable to reverse charge)			
$label4_name="(d) Inward supplies (liable to reverse charge)";
$label4_val1="0";
$label4_val2="0";
$label4_val3="0";
$label4_val4="0";
$label4_val5="0";

    $csv_content .= '"' . $label4_name . '",' .
                    '"' . $label4_val1 . '",' .
					'"' . $label4_val2 . '",' .
					'"' . $label4_val3 . '",' .
					'"' . $label4_val4 . '",' .
                    '"' . $label4_val5 . "\"\n";
					
					
					//(e) Non-GST outward supplies		
$label5_name="(e) Non-GST outward supplies";
$label5_val1="0";
$label5_val2="0";
$label5_val3="0";
$label5_val4="0";
$label5_val5="0";

    $csv_content .= '"' . $label5_name . '",' .
                    '"' . $label5_val1 . '",' .
					'"' . $label5_val2 . '",' .
					'"' . $label5_val3 . '",' .
					'"' . $label5_val4 . '",' .
                    '"' . $label5_val5 . "\"\n";
					


// Clear any previously buffered output
ob_end_clean();
// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>



