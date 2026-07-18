<?php include("checksession.php");
include("config.php");
error_reporting(0);

$loguser_tempid=$result_LoGuserDtails['temp_id'];

$file = "Shop-list.csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['Name', 'Category', 'State', 'District', 'Taluk', 'Pincode', 'Country Code', 'Mobile Number', 'Landline', 'Email ID', 'Address', 'GSTIN']);

$select_product_list = "select s.*, sc.catlable from shop s LEFT JOIN shop_category sc ON s.shop_cat = sc.id where s.distributor_id='$loguser_tempid' order by s.id desc";
$fetch_product_list = mysqli_query($db_conn, $select_product_list);
while ($result_product_list = mysqli_fetch_array($fetch_product_list)) {

	fputcsv($output, [
		$result_product_list['name'],
		$result_product_list['catlable'],
		ucwords($result_product_list['state_id']),
		ucwords($result_product_list['district_id']),
		ucwords($result_product_list['taluk_id']),
		$result_product_list['pincode_id'],
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
