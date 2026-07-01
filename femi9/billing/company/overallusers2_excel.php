<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Onboard-userwise-Overall-Distributors-List.csv";

// Header row for CSV
$csv_content .= "ID, Name,Mobile Number,District,Taluk, Username,Account Status,Onboard Usertype,Onboard Userid,Name,Mobile Number, District\n";

// Fetching data and formatting into CSV rows

$select_product_list="select * from distributor order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
//District					
$district_id=$result_product_list['district_id'];					
if(is_numeric($district_id))
{	
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
}else{
	$district_name=$district_id;
}
//District end

//Taluk
	$taluk_id=$result_product_list['taluk_id'];
	if(is_numeric($taluk_id))
	{
$select_taluk="select * from taluk where id='$taluk_id'";
	$fetch_taluk=mysqli_query($db_conn,$select_taluk);
	$result_taluk=mysqli_fetch_array($fetch_taluk);
$taluk_name=$result_taluk['taluk'];
}
else{
	$taluk_name=$taluk_id;
}
//Taluk end

$mobilenumber="".$result_product_list["country_code"]." ".$result_product_list["mobile_number"]."";

if($result_product_list['account_status']=="pending")
			{
			$accstatus="Pending";
			}
			else if($result_product_list['account_status']=="active")
			{
			$accstatus="Active";
			}else{
				$accstatus="Deactive";
			}
			
			
			
			//-------------------Onboard Users Details---------------------------
$GET_onboard_usertype=$result_product_list['onboard_userTYPE'];
$GET_onboard_userid=$result_product_list['onboard_userID'];

if($GET_onboard_usertype=="candf"){$tablenameWE="c_and_f";}
else if($GET_onboard_usertype=="super_stockiest") {$tablenameWE="super_stockiest";}
else{$tablenameWE="stockiest";}

$select_onbaord_user_records="select * from ".$tablenameWE." where temp_id='$GET_onboard_userid'";
$fetch_onbaord_user_records=mysqli_query($db_conn,$select_onbaord_user_records);
$result_onbaord_user_records=mysqli_fetch_array($fetch_onbaord_user_records);

$GET_onboard_user_city_id=$result_onbaord_user_records['district_id'];

if($GET_onboard_user_city_id==0){ $GET_onboard_user_city_name="---";}
else
{
$select_onbaord_user_city_records="select * from district where id='$GET_onboard_user_city_id'";
$fetch_onbaord_user_city_records=mysqli_query($db_conn,$select_onbaord_user_city_records);
$result_onbaord_user_city_records=mysqli_fetch_array($fetch_onbaord_user_city_records);
$GET_onboard_user_city_name=$result_onbaord_user_city_records['dist_name'];
}
			
    // Prepare CSV row
    $csv_content .= '"' . $result_product_list["useridtext"] . '",' .
                    '"' . $result_product_list["name"] . '",' .
                    '"' . $mobilenumber . '",' .
                    '"' . $district_name . '",' .
					'"' . $taluk_name . '",' .
					'"' . $result_product_list["username"] . '",' .
					'"' . $accstatus . '",' .
					
					'"' . $GET_onboard_usertype . '",' .
					'"' . $result_onbaord_user_records['useridtext'] . '",' .
					'"' . $result_onbaord_user_records['name'] . '",' .
					'"' . $result_onbaord_user_records['mobile_number'] . '",' .
                    '"' . $GET_onboard_user_city_name . "\"\n";
					
}

										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>