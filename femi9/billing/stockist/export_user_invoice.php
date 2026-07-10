<?php include("checksession.php");
include("config.php");
error_reporting(0);

$getinvuser=$_REQUEST['invuser'];

if($getinvuser=="super_distributor")
{
	$lablenamedisplay="Super Distributor Name";
	$tablename="super_distributor";
	$file="super-distributor-invoice-list.csv";
}
else if($getinvuser=="distributor")
{
	$lablenamedisplay="Distributor Name";
	$tablename="distributor";
	$file="distributor-invoice-list.csv";
}
else
{
	exit;
}

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=$file");

$output = fopen("php://output", "w");
fputcsv($output, ['Invoice Number', $lablenamedisplay, 'Invoice Date', 'Invoice Amount']);

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
