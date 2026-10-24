<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");

//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------

//insert product details
if(isset($_REQUEST['add-product']))
{
	
	$brand=$_REQUEST['brand'];
	$brand=str_replace("'","&#39;",$brand);
	
	$select_count_product="select count(*) as numProducts from competitor_brand where brand=?";
	$fetch_count_product=db_q($db_conn,$select_count_product,[$brand]);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numProducts']==0)
	{
		$insert_products="insert into competitor_brand (brand) values (?)";
		db_q($db_conn,$insert_products,[$brand]);
		
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
	
	$update_products="update competitor_brand set brand=? where id=?";
	db_q($db_conn,$update_products,[$brand,$update_id]);
		
		echo "<script>window.location='Competitor-brand-manage?updatedSuccess';</script>";
	
}

?>