<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
error_reporting(0);

// Include session check
include("checksession.php");
include("config.php");

$Report_LABLE="Competetion Stock Report";

// Set the filename for download
$file = "".$Report_LABLE.".csv";

// Initialize CSV content
$csv_content = '';

//-----------------------------------------------------------usr1-------------------------------------
	
// Header row for CSV
$csv_content .= "Shop ID, Name, District, Taluk, Available Stock\n";

// Fetching data and formatting into CSV rows

$selectRcd_VLSS1="select * from temp_competerion_stock_report where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl' order by id asc";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
										$shop_id=$resultRcd_VLSS1['shop_id'];

										$selectrecords_VLSS1="select * from shop where temp_id='$shop_id'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										
										$state_id_VLSS2=$resultrecords_VLSS1['state_id'];
										$district_id_VLSS2=$resultrecords_VLSS1['district_id'];
										$taluk_id_VLSS2=$resultrecords_VLSS1['taluk_id'];

										$selectrecords_VLSS2="select * from district where state_id='$state_id_VLSS2' and id='$district_id_VLSS2'";
										$fetchrecords_VLSS2=mysqli_query($db_conn,$selectrecords_VLSS2);
										$resultrecords_VLSS2=mysqli_fetch_array($fetchrecords_VLSS2);
										$district_name_VLSS2=$resultrecords_VLSS2['dist_name'];
										
$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS2' and dist_id='$district_id_VLSS2' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];

$available_stock=$resultRcd_VLSS1['qty'];
$available_stock133+=$available_stock;


    // Prepare CSV row
    $csv_content .= '"' . $resultrecords_VLSS1["useridtext"] . '",' .
                    '"' . $resultrecords_VLSS1["name"]. '",' .
					'"' . $district_name_VLSS2. '",' .
					'"' . $taluk_name_VLSS2. '",' .
                    '"' . $available_stock . "\"\n";
					
					
					
}
										






										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>