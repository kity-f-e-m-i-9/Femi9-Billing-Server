<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Shop.csv";

// Fetch product data from database
$select_records = "select * from shop where onboard_userTYPE='$onboard_userTYPE' and onboard_userID='$onboard_userID' order by id asc";
$fetch_records = mysqli_query($db_conn, $select_records);

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "Category, ID, Name, State, District, Taluk, Pincode, Email, Mobile, GSTIN, Address \n";

// Fetching data and formatting into CSV rows
while ($result_product_list = mysqli_fetch_array($fetch_records)) {
	
	
	
	//state details
											$state_id=$result_product_list['state_id'];
								$select_stateList="select * from state where id='$state_id'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $state_name=$result_stateList['st_name'];
							   
							   
											//district
											$district_id=$result_product_list['district_id'];
										$select_district="select * from district where id=$district_id";
										$fetch_district=mysqli_query($db_conn,$select_district);
										$result_district=mysqli_fetch_array($fetch_district);
										$district_name=$result_district['dist_name'];
										
										//Taluk
											$taluk_id=$result_product_list['taluk_id'];
										$select_Taluk="select * from taluk where id=$taluk_id";
										$fetch_Taluk=mysqli_query($db_conn,$select_Taluk);
										$result_Taluk=mysqli_fetch_array($fetch_Taluk);
										$taluk_name=$result_Taluk['taluk'];
										
										//pincode
$pincode_id=$result_product_list['pincode_id'];
$select_pincodelist="select * from pincode where id='$pincode_id'";
	$fetch_pincodelist=mysqli_query($db_conn,$select_pincodelist);
	$result_pincodelist=mysqli_fetch_array($fetch_pincodelist);
$pincodeshow=$result_pincodelist['pincode'];


//shop category
				$shop_cat=$result_product_list['shop_cat'];
$select_shopcatt="select * from shop_category where id='$shop_cat'";
	$fetch_shopcatt=mysqli_query($db_conn,$select_shopcatt);
	$result_shopcatt=mysqli_fetch_array($fetch_shopcatt);
$shopcat_name=$result_shopcatt['catlable'];


    // Prepare CSV row
    $csv_content .= 
	 '"'.$shopcat_name.'",' .
	 '"'.$result_product_list["useridtext"].'",' .
	  '"'.$result_product_list["name"].'",' .
                                                    '"'.$state_name.'",' .
													'"'.$district_name.'",' .
													'"'.$taluk_name.'",' .
													'"'.$pincodeshow.'",' .
													'"'.$result_product_list["email"].'",' .
													'"'.$result_product_list["mobile_number"].'",' .
													'"'.$result_product_list["gstin"].'",' .
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



