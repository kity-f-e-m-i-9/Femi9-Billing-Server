<?php /* include("checksession.php");

//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------

//insert coupon details
if(isset($_REQUEST['add-product']))
{
	$temp_id=$_REQUEST['temp_id'];
	$plan_id=$_REQUEST['plan_id'];
	//
	$select_plans="select * from plans where id='$plan_id'";
	$fetch_plans=mysqli_query($db_conn,$select_plans);
	$result_plans=mysqli_fetch_array($fetch_plans);
	$plan_amount=$result_plans['amount'];
	$valid_months=$result_plans['valid_months'];
	
	$length=$_REQUEST['no_of_coupon'];
	
	for ($i = 0; $i < $length; $i++) {
		
		date_default_timezone_set("Asia/Kolkata");
		$coupon_date=date("Y-m-d");
		
$temp_date=date("dmy");
$temp_time=date("gis"); 
		$couppon_unique=uniqid();
		
        $coupon="".$temp_date."".$temp_time."".$couppon_unique."";
		
	$select_count_product="select count(*) as numProducts from coupons where valid_months='$valid_months' and coupon_number='$coupon' and temp_id='$temp_id'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	
	if($result_count_product['numProducts']==0)
	{
		$insert_products="insert into coupons (temp_id,valid_months,plan_amount,coupon_number,stock_user_tempid,user_type,coupon_date,coupon_status) 
		values ('$temp_id','$valid_months','$plan_amount','$coupon','0','company','$coupon_date','none')";
		mysqli_query($db_conn,$insert_products); 
		
		echo "<script>window.location='manage-coupon?addedsuccess';</script>";
		
	}else{
		echo "<script>window.location='coupon?alreadyexists';</script>";
	}
	
    } 
	
	$select_count_couppons="select count(*) as numCopns from coupons where temp_id='$temp_id'";
	$fetc_count_couppons=mysqli_query($db_conn,$select_count_couppons);
	$rslt_count_couppons=mysqli_fetch_array($fetc_count_couppons);
	if($rslt_count_couppons['numCopns']>$length)
	{
		echo "<script>window.location='manage-coupon?addedsuccess';</script>";
	}
	
	
	
}
	
	
//update coupon details
if(isset($_REQUEST['update-product']))
{
	$update_id=$_REQUEST['update_id'];
	
	$productName=$_REQUEST['productName'];
	$productName=str_replace("'","&#39;",$productName);
	
	$update_products="update products set productName='$productName' where id='$update_id'";
	mysqli_query($db_conn,$update_products);
		
		echo "<script>window.location='manage-coupon?updatedSuccess';</script>";
	
}

?>