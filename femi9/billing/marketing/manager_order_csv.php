<?php
// Start output buffering to prevent headers from being sent prematurely
ob_start();
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");
error_reporting(0);

$from_date=$_REQUEST['frd'];
$to_date=$_REQUEST['tod'];

// Same view_ms_id scoping as manage_order_product.php — a manager exporting
// a subordinate's report must be validated against their real downline.
$viewMsId = (int)$markeingSTFID;
if (isset($_REQUEST['view_ms_id']) && $_REQUEST['view_ms_id'] !== '') {
    $requestedMsId = (int)$_REQUEST['view_ms_id'];
    if ($requestedMsId !== (int)$markeingSTFID) {
        $allowedSubtree = getMsSubtreeIds($db_conn, (int)$markeingSTFID);
        if (in_array($requestedMsId, $allowedSubtree, true)) {
            $viewMsId = $requestedMsId;
        }
    }
}

// Set the filename for download
$file = "Datewise-Product-Orders-".$from_date."-to-".$to_date.".csv";

// Initialize CSV content
$csv_content = '';

// Header row for CSV
$csv_content .= "#,Shop Name,Shop Contact Number,Address,Taluk,Location,Date,Marketing Tool,Order Value (Est.)";

// Batched once instead of per-row/per-product — same fix as manage_order_product.php
// (this used to run a query per product per order, which was very slow with a
// wide date range).
$allProducts = [];
$productPriceMap = [];
$resAllProd = mysqli_query($db_conn, "SELECT id, productName, outlet_price FROM products ORDER BY id ASC");
while ($apr = mysqli_fetch_assoc($resAllProd)) {
	$allProducts[] = $apr;
	$productPriceMap[(int)$apr['id']] = (float)$apr['outlet_price'];
}

foreach ($allProducts as $apr) {
	$csv_content .= ',"'.str_replace('"','""',$apr['productName']).'"';
}
$csv_content .= "\n";

$viewMsIdEsc = mysqli_real_escape_string($db_conn, $viewMsId);
$orderIdsInRange = [];
$orderMeta = [];
$qtyMap = [];
$shopIds = [];
$orderValueMap = [];
$resOrders = mysqli_query($db_conn,
	"SELECT order_id, shop_id, order_date, marketing_tool, latitude, longitude, pr_id, qty, discount_percentage
	 FROM ms_orders
	 WHERE ms_id='$viewMsIdEsc' AND new_order='yes' AND order_date BETWEEN '$from_date' AND '$to_date'
	 ORDER BY order_date DESC, order_id DESC"
);
while ($orow = mysqli_fetch_assoc($resOrders)) {
	$oid = $orow['order_id'];
	if (!isset($orderMeta[$oid])) {
		$orderMeta[$oid] = $orow;
		$orderIdsInRange[] = $oid;
		if (!empty($orow['shop_id'])) { $shopIds[$orow['shop_id']] = true; }
	}
	$qtyMap[$oid][$orow['pr_id']] = (int)$orow['qty'];
	$lineQty = (int)$orow['qty'];
	$linePrice = $productPriceMap[(int)$orow['pr_id']] ?? 0;
	$lineDiscount = (float)($orow['discount_percentage'] ?? 0);
	$orderValueMap[$oid] = ($orderValueMap[$oid] ?? 0) + ($lineQty * $linePrice * (1 - $lineDiscount / 100));
}

$shopMeta = [];
if (!empty($shopIds)) {
	$shopIdList = implode(',', array_map('intval', array_keys($shopIds)));
	$resShops = mysqli_query($db_conn, "SELECT * FROM ms_shop WHERE id IN ($shopIdList)");
	while ($srow = mysqli_fetch_assoc($resShops)) { $shopMeta[$srow['id']] = $srow; }
}

$i=0;
foreach ($orderIdsInRange as $orderid)
{
	$result_product_list = $orderMeta[$orderid];

	//shop category
	$shop_id=$result_product_list['shop_id'];
	$result_shopcatt = $shopMeta[$shop_id] ?? [];

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
	$csv_content .= '"'.str_replace('"','""',$result_product_list["marketing_tool"]).'",';
	$csv_content .= '"'.number_format($orderValueMap[$orderid] ?? 0, 2, '.', '').'"';

	//------------------------PRODUCT WISE SALES QTY-------------------------------
	foreach ($allProducts as $apr) {
		$prid_header = $apr['id'];
		$showQty = $qtyMap[$orderid][$prid_header] ?? 0;
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
