<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();

// Include session check
include("checksession.php");
include("config.php");

$from_date=$_REQUEST['frd'];
$to_date=$_REQUEST['tod'];

$file = "Approved-wallet-withdraw-request.csv";

$csv_content .= "Usertype, Userid, Name, District, Mobile Number, Amount, Request Timestamp, Approved Timestamp, TDS(%), TDS Amount, Amount, Remarks \n";

$select_product_list="select * from wallet_withdraw where req_status='approved' and date between '$from_date' and '$to_date' order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{

									$WD_user_type=$result_product_list['user_type'];
									$WD_user_id=$result_product_list['user_id'];
									
if($WD_user_type=="candf"){$tablenameWE="c_and_f";}
elseif($WD_user_type=="super_stockiest") {$tablenameWE="super_stockiest";}
elseif($WD_user_type=="stockiest") {$tablenameWE="stockiest";}
else{$tablenameWE="distributor";}

$select_onbaord_user_records="select * from ".$tablenameWE." where temp_id='$WD_user_id'";
$fetch_onbaord_user_records=mysqli_query($db_conn,$select_onbaord_user_records);
$result_onbaord_user_records=mysqli_fetch_array($fetch_onbaord_user_records);


//district details		
$district_id=$result_onbaord_user_records['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

$user_mob_number="".$result_onbaord_user_records["country_code"]." ".$result_onbaord_user_records["mobile_number"]."";
$req_timestamp="".date("d/m/Y",strtotime($result_product_list['date'])).", ".date("g:i A",strtotime($result_product_list['time']))."";
$approved_timestamp="".date("d/m/Y",strtotime($result_product_list['updated_date'])).", ".date("g:i A",strtotime($result_product_list['updated_time']))."";
			
    // Prepare CSV row
    $csv_content .= '"' . $WD_user_type . '",' .
                    '"' . $result_onbaord_user_records['useridtext'] . '",' .
                    '"' . $result_onbaord_user_records['name'] . '",' .
                    '"' . $district_name . '",' .
					'"' . $user_mob_number . '",' .
					'"' . $result_product_list['amount'] . '",' .
					'"' . $req_timestamp . '",' .
					
					'"' . $approved_timestamp . '",' .
					'"' . $result_product_list['TDS_percentage'] . '",' .
					'"' . $result_product_list['TDS_deduction'] . '",' .
					'"' . $result_product_list['sent_amount'] . '",' .
                    '"' . $result_product_list['remarks'] . "\"\n";
					
}

										
// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>