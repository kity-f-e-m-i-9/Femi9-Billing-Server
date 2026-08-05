<?php include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

include("RemoveSpecialChar.php");
include_once("include/OrderTpBridge.php");

if (isset($_REQUEST['add_order_get'])) {


	$ms_id=$_POST["ms_id"];
	$order_date=$_POST["order_date"];
	$order_id=$_POST["order_id"];
	$shop_id=$_POST["shop_id"];

	$marketing_tool=$_POST["marketing_tool"];
	$marketing_tool=RemoveSpecialChar($marketing_tool);

	$latitude=isset($_POST["latitude"]) && $_POST["latitude"]!=='' ? floatval($_POST["latitude"]) : null;
	$longitude=isset($_POST["longitude"]) && $_POST["longitude"]!=='' ? floatval($_POST["longitude"]) : null;
	$latitude_sql=$latitude===null ? "NULL" : "'".$latitude."'";
	$longitude_sql=$longitude===null ? "NULL" : "'".$longitude."'";

	// Assign To TP — validate against active TPs; invalid/missing means "not assigned".
	$tp_id = (int)($_POST["tp_id"] ?? 0);
	if ($tp_id > 0) {
		$tp_id_esc = (int)$tp_id;
		$tpCheck = mysqli_query($db_conn, "select id from territory_partners where id='$tp_id_esc' and is_active=1 limit 1");
		if (!$tpCheck || mysqli_num_rows($tpCheck) === 0) { $tp_id = 0; }
	}
	$tp_id_sql = $tp_id > 0 ? "'".$tp_id."'" : "NULL";

	$product_id = implode("#",$_REQUEST['pr_id']);
$qty = implode("#",$_REQUEST['qty']);
$discount_pct = implode("#", $_REQUEST['discount_percentage'] ?? []);

$product_id_ex = explode ("#",$product_id);
$qty_ex = explode ("#",$qty);
$discount_pct_ex = explode ("#", $discount_pct);

$number = count($product_id_ex);
$insertedLines = []; // pr_id/qty of the lines actually inserted, for the TP bridge below
for ($i=0; $i<=$number; $i++)
{
     $product_id_value = $product_id_ex[$i];
     $qty_value = $qty_ex[$i];
	 $qty_value = RemoveSpecialChar($qty_value);
	 $discount_value = isset($discount_pct_ex[$i]) ? (float)$discount_pct_ex[$i] : 0;
	 if ($discount_value < 0) { $discount_value = 0; }
	 if ($discount_value > 100) { $discount_value = 100; }

	 if($product_id_value!=NULL)
	 {

$select_count_dist="select count(*) as numShop from ms_orders where order_id='$order_id' and pr_id='$product_id_value'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
$result_count_dist=mysqli_fetch_array($fetc_count_dist);
if($result_count_dist['numShop']==0)
	{

        $sql="insert into ms_orders (order_id,shop_id,ms_id,tp_id,order_date,new_order,noorder_reason,marketing_tool,pr_id,qty,discount_percentage,latitude,longitude) values ('$order_id','$shop_id','$ms_id',$tp_id_sql,'$order_date','yes','nil','$marketing_tool',
		'$product_id_value','$qty_value','$discount_value',$latitude_sql,$longitude_sql)";
		mysqli_query($db_conn,$sql);
		$insertedLines[] = ['pr_id' => (int)$product_id_value, 'qty' => (int)$qty_value, 'discount_percentage' => $discount_value];

	}


	 }

}

	// Best-effort mirror into the TP's own field-order pipeline — the
	// ms_orders rows above are already saved regardless of this outcome.
	if ($tp_id > 0 && !empty($insertedLines)) {
		bridgeOrderToTp($db_conn, $tp_id, $ms_id, $shop_id, $order_id, $order_date, $insertedLines);
	}

	$_SESSION['successMessage']="Product order details added successfully!";
	echo "<script>window.location='manage_order_product';</script>";

}
?>