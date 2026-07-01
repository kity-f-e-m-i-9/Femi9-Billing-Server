<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

// Set the filename for download
$file = "Overall-Stockist_List.csv";

// Header row for CSV
$csv_content .= "ID, Name,Mobile Number,District,Taluk,Username,Password,Account Status,ID of Referred, Name of Referred,Type of Referred, Mobile of Referred\n";

// Fetching data and formatting into CSV rows

$select_product_list="select * from stockiest order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

	$taluk_id=$result_product_list['taluk_id'];
$select_taluk="select * from taluk where id='$taluk_id'";
	$fetch_taluk=mysqli_query($db_conn,$select_taluk);
	$result_taluk=mysqli_fetch_array($fetch_taluk);
$taluk_name=	$result_taluk['taluk'];

//REFERRAL DETAILS
	$select_referralDetails="select * from stockist_referral where stockist_id='".$result_product_list['temp_id']."'";
	$fetch_referralDetails=mysqli_query($db_conn,$select_referralDetails);
	$result_referralDetails=mysqli_fetch_array($fetch_referralDetails);
	
	if($result_referralDetails['st_ref_type']=="super_stockiest"){
		$tblename="super_stockiest";
		$labelname="Super Stockist";
		}
		else if($result_referralDetails['st_ref_type']=="stockiest"){
			$tblename="stockiest";
			$labelname="Stockist";
		}
		else{
			$tblename="distributor";
			$labelname="Distributor";
		}
		
		$select_count_REFERID="select * from ".$tblename." where useridtext='".$result_referralDetails['st_ref_userid']."'";
		$fetch_count_REFERID=mysqli_query($db_conn,$select_count_REFERID);
		$result_count_REFERID=mysqli_fetch_array($fetch_count_REFERID);
				
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
if($result_referralDetails['st_ref_type']=="company"){
	$cmp_id="";
	$cmp_name="";
	$cmplabel="Company";
	$cmp_mobile="";
}else{
	$cmp_id=$result_referralDetails['st_ref_userid'];
	$cmp_name=$result_count_REFERID['name'];
	$cmplabel=$labelname;
	$cmp_mobile=$result_count_REFERID['mobile_number'];
}
			
    // Prepare CSV row
    $csv_content .= '"' . $result_product_list["useridtext"] . '",' .
                    '"' . $result_product_list["name"] . '",' .
                    '"' . $mobilenumber . '",' .
                    '"' . $district_name . '",' .
					'"' . $taluk_name . '",' .
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