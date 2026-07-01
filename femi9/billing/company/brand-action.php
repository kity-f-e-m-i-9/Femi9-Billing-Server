<?php include("checksession.php");
include("config.php");

//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------

//insert product details
if(isset($_REQUEST['add-product']))
{
	
	$brand=$_REQUEST['brand'];
	$brand=str_replace("'","&#39;",$brand);
	
	$select_count_product="select count(*) as numProducts from competitor_brand where brand='$brand'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numProducts']==0)
	{
		$insert_products="insert into competitor_brand (brand) values ('$brand')";
		mysqli_query($db_conn,$insert_products);
		
		echo "<script>window.location='Competitor-brand-manage?addesuccess';</script>";
	}else{
		
		echo "<script>window.location='Competitor-brand?alreadyexists';</script>";
	}
}
	
	
//update product details
if(isset($_REQUEST['update-product']))
{
	$update_id=$_REQUEST['update_id'];
	
	$brand=$_REQUEST['brand'];
	$brand=str_replace("'","&#39;",$brand);
	
	$update_products="update competitor_brand set brand='$brand' where id='$update_id'";
	mysqli_query($db_conn,$update_products);
		
		echo "<script>window.location='Competitor-brand-manage?updatedSuccess';</script>";
	
}

?>