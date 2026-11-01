<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if (isset($_REQUEST['add_order_no'])) {
	
	
	$ms_id=$_POST["ms_id"];
	$order_date=$_POST["order_date"];
	$order_id=$_POST["order_id"];
	$shop_id=$_POST["shop_id"];
	
	$noorder_reason=str_replace("'","&#39;",$_POST["noorder_reason"]);
	$noorder_reason=RemoveSpecialChar($noorder_reason);
	
	$marketing_tool=$_POST["marketing_tool"];
	$marketing_tool=RemoveSpecialChar($marketing_tool);

	// Which product line this No-Order visit was actually about — a No-Order
	// row carries no pr_id (always '0'), so there's nothing to derive Napkin
	// vs Diaper from the way a Get Order's own product lines already allow;
	// this is the only way a "no order" visit shows up correctly in the
	// Napkin/Diaper order+shop counts.
	$order_category = ($_POST['order_category'] ?? '') === 'diaper' ? 'diaper' : 'napkin';

	// Self-migrating — same convention used elsewhere in this app (e.g.
	// products.visible_in_get_order).
	$_catCol = $db_conn->query("SHOW COLUMNS FROM ms_orders LIKE 'order_category'");
	if ($_catCol && $_catCol->num_rows === 0) {
	    $db_conn->query("ALTER TABLE ms_orders ADD COLUMN order_category ENUM('napkin','diaper') NULL DEFAULT NULL AFTER new_order");
	}

$select_count_dist="select count(*) as numShop from ms_orders where order_id=?";
$fetc_count_dist=db_q($db_conn,$select_count_dist,[$order_id]);
$result_count_dist=mysqli_fetch_array($fetc_count_dist);
if($result_count_dist['numShop']==0)
	{

        $sql="insert into ms_orders (order_id,shop_id,ms_id,order_date,new_order,order_category,noorder_reason,marketing_tool,pr_id,qty) values (?,?,?,?,'no',?,?,?,'0','0')";
		db_q($db_conn,$sql,[$order_id,$shop_id,$ms_id,$order_date,$order_category,$noorder_reason,$marketing_tool]);

	}
	
	$_SESSION['successMessage']="No order details added successfully!";
	echo "<script>window.location='manage_order';</script>";
	
}
?>