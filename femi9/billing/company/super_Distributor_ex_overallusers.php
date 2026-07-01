<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Overall-Super-Distributor-Records.csv";

// Header row for CSV
$csv_content .= "ID, Name,Mobile Number,District,Taluk,Username,Password,Account Status,ID of Referred, Name of Referred,Type of Referred, Mobile of Referred\n";

// Fetching data and formatting into CSV rows

$select_product_list="select * from super_distributor order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
$district_id=$result_product_list['district_id'];
	$taluk_id=$result_product_list['taluk_id'];

//REFERRAL DETAILS
	$select_referralDetails="select * from super_distributor_referral where sd_id='".$result_product_list['temp_id']."'";
	$fetch_referralDetails=mysqli_query($db_conn,$select_referralDetails);
	$result_referralDetails=mysqli_fetch_array($fetch_referralDetails);
	
	if($result_referralDetails['ref_by_user_type']=="super_distributor")
	{
		$tblename="super_distributor";
		$labelname="Super Distributor";
		}
		if($result_referralDetails['ref_by_user_type']=="distributor")
		{
			$tblename="distributor";
			$labelname="Distributor";
		}
		
		$mobilenumber="".$result_product_list["country_code"]."".$result_product_list["mobile_number"]."";

//ACCOUNT STATUS
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
			

//REFERRAL DETAILS			
if($result_referralDetails['ref_by_user_type']=="company"){
	$cmp_id="";
	$cmp_name="";
	$cmplabel="Company";
	$cmp_mobile="";
}else{
	
	$select_count_REFERID="select * from ".$tblename." where useridtext='".$result_referralDetails['ref_by_user_id']."'";
	$fetch_count_REFERID=mysqli_query($db_conn,$select_count_REFERID);
	$result_count_REFERID=mysqli_fetch_array($fetch_count_REFERID);
		
	$cmp_id=$result_referralDetails['ref_by_user_id'];
	$cmp_name=$result_count_REFERID['name'];
	$cmplabel=$labelname;
	$cmp_mobile=$result_count_REFERID['mobile_number'];
}
			
    // Prepare CSV row
    $csv_content .= '"' . $result_product_list["useridtext"] . '",' .
                    '"' . $result_product_list["name"] . '",' .
                    '"' . $mobilenumber . '",' .
                    '"' . $district_id . '",' .
					'"' . $taluk_id . '",' .
					'"' . $result_product_list["username"] . '",' .
					'"' . $result_product_list["password"] . '",' .
					'"' . $accstatus . '",' .
					'"' . $cmp_id . '",' .
					'"' . $cmp_name . '",' .
					'"' . $cmplabel . '",' .
                    '"' . $cmp_mobile . "\"\n";
					
					
}

										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>