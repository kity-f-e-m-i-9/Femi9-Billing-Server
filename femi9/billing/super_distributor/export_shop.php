<?php include("checksession.php");
include("config.php");
error_reporting(0);

//login user details
$log_username=$_SESSION['LOGIN_USER'];
$select_superstock="select * from super_distributor where username='$log_username'";
$fetch_superstock=mysqli_query($db_conn,$select_superstock);
$result_superstock=mysqli_fetch_array($fetch_superstock);
$loguser_tempid=$result_superstock['temp_id'];

$file = "Shop-list.csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['Name', 'Category', 'State', 'District', 'Taluk', 'Pincode', 'Country Code', 'Mobile Number', 'Landline', 'Email ID', 'Address', 'GSTIN']);

$select_product_list = "select s.*, sc.catlable from shop s LEFT JOIN shop_category sc ON s.shop_cat = sc.id where s.distributor_id='$loguser_tempid' order by s.id desc";
$fetch_product_list = mysqli_query($db_conn, $select_product_list);
while ($result_product_list = mysqli_fetch_array($fetch_product_list)) {

	//state details
	$state_id = $result_product_list['state_id'];
	$select_stateList = "select * from state where id='$state_id'";
	$fetch_staeList = mysqli_query($db_conn, $select_stateList);
	$result_stateList = mysqli_fetch_array($fetch_staeList);
	$state_name = $result_stateList['st_name'];

	//district
	$district_id = $result_product_list['district_id'];
	$select_district = "select * from district where id=$district_id";
	$fetch_district = mysqli_query($db_conn, $select_district);
	$result_district = mysqli_fetch_array($fetch_district);
	$district_name = $result_district['dist_name'];

	//Taluk
	$taluk_id = $result_product_list['taluk_id'];
	$select_Taluk = "select * from taluk where id=$taluk_id";
	$fetch_Taluk = mysqli_query($db_conn, $select_Taluk);
	$result_Taluk = mysqli_fetch_array($fetch_Taluk);
	$taluk_name = $result_Taluk['taluk'];

	//pincode
	$pincode_id = $result_product_list['pincode_id'];
	$select_pincodelist = "select * from pincode where id='$pincode_id'";
	$fetch_pincodelist = mysqli_query($db_conn, $select_pincodelist);
	$result_pincodelist = mysqli_fetch_array($fetch_pincodelist);
	$pincodeshow = $result_pincodelist['pincode'];

	fputcsv($output, [
		$result_product_list['name'],
		$result_product_list['catlable'],
		$state_name,
		$district_name,
		$taluk_name,
		$pincodeshow,
		$result_product_list['country_code'],
		$result_product_list['mobile_number'],
		$result_product_list['landline'],
		$result_product_list['email'],
		$result_product_list['address'],
		$result_product_list['gstin'],
	]);
}

fclose($output);
?>
