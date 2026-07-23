<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
include("checksession.php");
include("config.php");
error_reporting(0);

$from_date=$_REQUEST['frd'];
$to_date=$_REQUEST['tod'];

// Set the filename for download
$file = "Datewise-Product-Orders-".$from_date."-to-".$to_date.".csv";

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "#,Shop Name,Shop Contact Number,Address,Taluk,Location,Date,Marketing Tool";

$select_prdetails_header="select * from products order by id asc";
$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){
	$csv_content .= ',"'.str_replace('"','""',$result_prdetails_header['productName']).'"';
}
$csv_content .= "\n";

$i=0;
$select_product_list="select distinct order_id from ms_orders where ms_id='$markeingSTFID' and new_order='yes' and order_date between '$from_date' and '$to_date'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list12=mysqli_fetch_array($fetch_product_list))
{
	$orderid=$result_product_list12['order_id'];
	$select_shopcatt12="select * from ms_orders where order_id='$orderid'";
	$fetch_shopcatt12=mysqli_query($db_conn,$select_shopcatt12);
	$result_product_list=mysqli_fetch_array($fetch_shopcatt12);

	//shop category
	$shop_id=$result_product_list['shop_id'];
	$select_shopcatt="select * from ms_shop where id='$shop_id'";
	$fetch_shopcatt=mysqli_query($db_conn,$select_shopcatt);
	$result_shopcatt=mysqli_fetch_array($fetch_shopcatt);

	$csv_content .= ++$i.',';
	$csv_content .= '"'.str_replace('"','""',$result_shopcatt['name']).'",';
	$csv_content .= '"'.str_replace('"','""',$result_shopcatt['mobile_number']).'",';
	$csv_content .= '"'.str_replace('"','""',ucwords($result_shopcatt["address"])).'",';
	$csv_content .= '"'.str_replace('"','""',ucwords($result_shopcatt["taluk_name"])).'",';

	$order_lat = $result_product_list["latitude"];
	$order_lng = $result_product_list["longitude"];
	$location_text = ($order_lat!=NULL && $order_lng!=NULL) ? 'https://www.google.com/maps?q='.$order_lat.','.$order_lng : '';
	$csv_content .= '"'.$location_text.'",';

	$csv_content .= '"'.date("d/m/Y",strtotime($result_product_list["order_date"])).'",';
	$csv_content .= '"'.str_replace('"','""',$result_product_list["marketing_tool"]).'"';

	//------------------------PRODUCT WISE SALES QTY-------------------------------
	$select_prdetails_header="select * from `products` order by `id` asc";
	$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
	while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){

		$prid_header=$result_prdetails_header['id'];

		//SALES QTY
		$select_SUM_QTY="select qty from ms_orders where order_id='".$result_product_list['order_id']."' and pr_id='$prid_header'";
		$fetch_SUM_QTY=mysqli_query($db_conn,$select_SUM_QTY);
		$result_SUM_QTY=mysqli_fetch_array($fetch_SUM_QTY);
		if($result_SUM_QTY['qty']!=NULL){ $showQty=$result_SUM_QTY['qty'];}else{$showQty="0";}

		$csv_content .= ',"'.$showQty.'"';
	}
	//--------------------------------------------------------------------

	$csv_content .= "\n";
}

// Clear any previously buffered output
ob_end_clean();

// Set headers for CSV file download
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");

// Output the CSV content
echo $csv_content;
?>
