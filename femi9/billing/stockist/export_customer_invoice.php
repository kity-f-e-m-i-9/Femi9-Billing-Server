<?php include("checksession.php");
include("config.php");
error_reporting(0);

$tablename="customers";
$file="customer-invoice-list.csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['Invoice Number', 'Customer Name', 'Invoice Date', 'Invoice Amount']);

$select_product_list="select * from invoice where user_id='$Login_user_IDvl' order by id desc";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{
	$CuSTID=$result_product_list['customer_id'];
	if($CuSTID!=0)
	{
		$select_Customers="select * from ".$tablename." where id='$CuSTID'";
		$fetch_Customers=mysqli_query($db_conn,$select_Customers);
		$result_Customers=mysqli_fetch_array($fetch_Customers);
		$customerDetails=$result_Customers['name']." M: ".$result_Customers['mobile'];
	}else{
		$customerDetails="Walking Customer";
	}

	fputcsv($output, [
		$result_product_list["inv_number"],
		$customerDetails,
		date("d/M/Y",strtotime($result_product_list["date"])),
		inr_format($result_product_list["total"], 2),
	]);
}

fclose($output);
?>
