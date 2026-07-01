<?php include("checksession.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if (isset($_REQUEST['add_order_get'])) {
	
	
	$ms_id=$_POST["ms_id"];
	$order_date=$_POST["order_date"];
	$order_id=$_POST["order_id"];
	$shop_id=$_POST["shop_id"];
	
	$marketing_tool=$_POST["marketing_tool"];
	$marketing_tool=RemoveSpecialChar($marketing_tool);
	
	
	$product_id = implode("#",$_REQUEST['pr_id']);
$qty = implode("#",$_REQUEST['qty']);
	
$product_id_ex = explode ("#",$product_id); 
$qty_ex = explode ("#",$qty); 

$number = count($product_id_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $product_id_value = $product_id_ex[$i]; 
     $qty_value = $qty_ex[$i]; 
	 $qty_value = RemoveSpecialChar($qty_value);
	 
	 if($product_id_value!=NULL)
	 {
	
$select_count_dist="select count(*) as numShop from ms_orders where order_id='$order_id' and pr_id='$product_id_value'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
$result_count_dist=mysqli_fetch_array($fetc_count_dist);
if($result_count_dist['numShop']==0)
	{
	
        $sql="insert into ms_orders (order_id,shop_id,ms_id,order_date,new_order,noorder_reason,marketing_tool,pr_id,qty) values ('$order_id','$shop_id','$ms_id','$order_date','yes','nil','$marketing_tool',
		'$product_id_value','$qty_value')";
		mysqli_query($db_conn,$sql);

	}
	
	
	 }
	 
}
	
	$_SESSION['successMessage']="Product order details added successfully!";
	echo "<script>window.location='manage_order_product';</script>";
	
}
?>