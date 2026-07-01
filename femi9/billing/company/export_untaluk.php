<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

$state_id=$_REQUEST['stid'];
$dist_id=$_REQUEST['did'];

// Set the filename for download
$file = "Unassigned-Stockist-Talukwise.csv";

// Fetch product data from database
if($state_id!=NULL && $dist_id!=NULL){
							$select_records="select * from taluk where state_id='$state_id' and dist_id='$dist_id' order by id asc";
							}else{
							$select_records="select * from taluk where state_id='$state_id' order by id asc";	
							}
							
							
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "State, District, Taluk, Status\n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	
	
	//state details
											$dis_state_id=$result_product_list['state_id'];
								$select_stateList12="select * from `state` where id='$dis_state_id'";
							   $fetch_staeList12=mysqli_query($db_conn,$select_stateList12);
							   $result_stateList12=mysqli_fetch_array($fetch_staeList12);
							   $dis_state_name=$result_stateList12['st_name'];
							   
							   //district details
							   $dis_district_id=$result_product_list['dist_id'];
							   $select_district12="select * from district where id=$dis_district_id";
										$fetch_district12=mysqli_query($db_conn,$select_district12);
										$result_district12=mysqli_fetch_array($fetch_district12);
										$dis_district_name=$result_district12['dist_name'];
							   
							   if($result_product_list["assigned_SID"]=="Nil"){$lableshow="Available";}else{
								$lableshow="already user appointed";   
							   }
	

    // Prepare CSV row
    $csv_content .= 
													'"'.$dis_state_name.'",' .
													'"'.$dis_district_name.'",' .
													'"'.$result_product_list["taluk"].'",' .
                    '"' . $lableshow . "\"\n";
}

// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>



