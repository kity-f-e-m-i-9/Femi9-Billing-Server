<?php include("checksession.php");
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
	
$select_count_dist="select count(*) as numShop from ms_orders where order_id='$order_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
$result_count_dist=mysqli_fetch_array($fetc_count_dist);
if($result_count_dist['numShop']==0)
	{
	
        $sql="insert into ms_orders (order_id,shop_id,ms_id,order_date,new_order,noorder_reason,marketing_tool,pr_id,qty) values ('$order_id','$shop_id','$ms_id','$order_date','no','$noorder_reason','$marketing_tool','0','0')";
		mysqli_query($db_conn,$sql);

	}
	
	$_SESSION['successMessage']="No order details added successfully!";
	echo "<script>window.location='manage_order';</script>";
	
}
?>