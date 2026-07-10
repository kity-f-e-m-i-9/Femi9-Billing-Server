<?php include("checksession.php");
include("config.php");
error_reporting(0);

$getinvuser="shop";
$tablename="shop";
$file="shop-invoice-list.csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['Invoice Number', 'Shop Name', 'Invoice Date', 'Invoice Amount']);

$select_product_list="select * from user_invoice where from_user_id='$Login_user_IDvl' and to_user_type='$getinvuser' order by id desc";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{
	$CuSTID=$result_product_list['to_user_id'];
	$select_Customers="select * from ".$tablename." where temp_id='$CuSTID'";
	$fetch_Customers=mysqli_query($db_conn,$select_Customers);
	$result_Customers=mysqli_fetch_array($fetch_Customers);
	$Cust_Name=$result_Customers['name'];
	$Cust_Mbile=$result_Customers['mobile_number'];

	fputcsv($output, [
		$result_product_list["inv_number"],
		$Cust_Name.' M: '.$Cust_Mbile,
		date("d/M/Y",strtotime($result_product_list["date"])),
		inr_format($result_product_list["total"], 2),
	]);
}

fclose($output);
?>
