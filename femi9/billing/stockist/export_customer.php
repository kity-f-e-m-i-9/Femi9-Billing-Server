<?php include("checksession.php");
include("config.php");

$file = "customer-list.csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['Name', 'Mobile', 'Email', 'GSTIN', 'Address', 'Country Code']);

$select_product_list = "select * from customers where user_id='$Login_user_IDvl' order by id asc";
$fetch_product_list = mysqli_query($db_conn, $select_product_list);
while ($result_product_list = mysqli_fetch_array($fetch_product_list)) {
	fputcsv($output, [
		$result_product_list["name"],
		$result_product_list["mobile"],
		$result_product_list["email"],
		$result_product_list["gstin"],
		$result_product_list["address"],
		$result_product_list["country_code"],
	]);
}

fclose($output);
?>
