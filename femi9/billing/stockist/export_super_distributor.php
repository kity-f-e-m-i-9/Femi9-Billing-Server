<?php include("checksession.php");
include("config.php");

$file = "super-distributor-list.csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['ID', 'Name', 'District', 'Taluk', 'Pincode', 'Mobile', 'Account Status']);

$select_product_list = "select * from super_distributor where onboard_userID='$onboard_userID' order by id desc";
$fetch_product_list = mysqli_query($db_conn, $select_product_list);
while ($result_product_list = mysqli_fetch_array($fetch_product_list)) {
	fputcsv($output, [
		$result_product_list["useridtext"],
		ucwords($result_product_list["name"]),
		ucwords($result_product_list["district_id"]),
		ucwords($result_product_list["taluk_id"]),
		$result_product_list["pincode_id"],
		$result_product_list["country_code"] . ' ' . $result_product_list["mobile_number"],
		ucwords($result_product_list["account_status"]),
	]);
}

fclose($output);
?>
