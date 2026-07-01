<?php include("checksession.php");
include("config.php");

//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------

//insert product details
if(isset($_REQUEST['add-product']))
{
	$temp_id=$_REQUEST['temp_id'];
	
	$productName=$_REQUEST['productName'];
	$productName=str_replace("'","&#39;",$productName);
	
	$select_count_product="select count(*) as numProducts from products where temp_id='$temp_id'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numProducts']==0)
	{
		$insert_products="insert into products (temp_id,productName) values ('$temp_id','$productName')";
		mysqli_query($db_conn,$insert_products);
		
		echo "<script>window.location='manage-products.php?addesuccess';</script>";
	}else{
		
		echo "<script>window.location='Products.php?alreadyexists';</script>";
	}
}
	
	
//update product details
if(isset($_REQUEST['update-product']))
{
	$update_id=$_REQUEST['update_id'];
	
	$productName=$_REQUEST['productName'];
	$productName=str_replace("'","&#39;",$productName);
	
	$update_products="update products set productName='$productName' where id='$update_id'";
	mysqli_query($db_conn,$update_products);
		
		echo "<script>window.location='manage-products.php?updatedSuccess';</script>";
	
}

//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------

//insert district details
if(isset($_REQUEST['add-district']))
{
	$temp_id=$_REQUEST['temp_id'];
	
	$dist_name=$_REQUEST['dist_name'];
	$dist_name=str_replace("'","&#39;",$dist_name);
	
	$select_count_product="select count(*) as numDistrict from district where temp_id='$temp_id'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numDistrict']==0)
	{
		$insert_products="insert into district (temp_id,dist_name,usertype,userid) values 
		('$temp_id','$dist_name','admin','0')";
		mysqli_query($db_conn,$insert_products);
		
		echo "<script>window.location='manage-district.php?addesuccess';</script>";
	}else{
		
		echo "<script>window.location='add-district.php?alreadyexists';</script>";
	}
}
		


//update district details
if(isset($_REQUEST['update-district']))
{
	$update_id=$_REQUEST['update_id'];
	
	$dist_name=$_REQUEST['dist_name'];
	$dist_name=str_replace("'","&#39;",$dist_name);
	
	$update_products="update district set dist_name='$dist_name' where id='$update_id'";
	mysqli_query($db_conn,$update_products);
		
		echo "<script>window.location='manage-district.php?updatedSuccess';</script>";
}


//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------


//insert taluk details
if(isset($_REQUEST['add-taluk']))
{
	$state_id=$_REQUEST['state_id'];
	$dist_id=$_REQUEST['dist_id'];
	
	$taluk = implode("#",$_REQUEST['taluk']);
$taluk_ex = explode ("#",$taluk); 

$number = count($taluk_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $taluk_value = $taluk_ex[$i];
	 $taluk_value=str_replace("'","&#39;",$taluk_value);	 
	 
	 $select_count_product="select count(*) as numDistrict from taluk where state_id='$state_id' and dist_id='$dist_id' and taluk='$taluk_value'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numDistrict']==0 && $taluk_value!=NULL)
	{
		$insert_products="insert into taluk (state_id,dist_id,taluk,usertype,userid,assigned_SID) values 
		('$state_id','$dist_id','$taluk_value','$Login_user_TYPEvl','$Login_user_IDvl','Nil')";
		mysqli_query($db_conn,$insert_products);
		
		echo "<script>window.location='manage-taluk.php?addesuccess';</script>";
	}else{
		
		echo "<script>window.location='add-taluk.php?alreadyexists';</script>";
	}
	
} 
	
}


//update taluk details
if(isset($_REQUEST['update-taluk']))
{
	$update_id=$_REQUEST['update_id'];
	$taluk=$_REQUEST['taluk'];
	$taluk=str_replace("'","&#39;",$taluk);
	
	$update_products="update taluk set taluk='$taluk' where id='$update_id'";
	mysqli_query($db_conn,$update_products);
		
		echo "<script>window.location='manage-taluk.php?updatedSuccess';</script>";
}


//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------

//insert pincode details
if(isset($_REQUEST['add-pincode']))
{
	
	$state_id=$_REQUEST['state_id'];
	$dist_id=$_REQUEST['dist_id'];
	$taluk_id=$_REQUEST['taluk_id'];
	
	$pincode = implode("#",$_REQUEST['pincode']);
$pincode_ex = explode ("#",$pincode); 

$number = count($pincode_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $pincode_value = $pincode_ex[$i];
	 $pincode_value=str_replace("'","&#39;",$pincode_value);	 
	 
	 $select_count_product="select count(*) as numpincode from pincode where state_id='$state_id' and dist_id='$dist_id' and taluk_id='$taluk_id' and pincode='$pincode_value'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numpincode']==0 && $pincode_value!=NULL)
	{
		$insert_products="insert into pincode 
		(state_id,dist_id,taluk_id,pincode,usertype,userid,assigned_SID,assigned_DID) values 
		('$state_id','$dist_id','$taluk_id','$pincode_value','$Login_user_TYPEvl',
		'$Login_user_IDvl','Nil','Nil')";
		mysqli_query($db_conn,$insert_products);
		
		echo "<script>window.location='manage-pincode.php?addesuccess';</script>";
	}else{
		
		echo "<script>window.location='add-pincode.php?alreadyexists';</script>";
	}
	
} 
	
}

//update pincode details
if(isset($_REQUEST['update-pincode']))
{
	$update_id=$_REQUEST['update_id'];
	
	$taluk_id=$_REQUEST['taluk_id'];
	$pincode=$_REQUEST['pincode'];
	$pincode=str_replace("'","&#39;",$pincode);
	
	$update_products="update pincode set taluk_id='$taluk_id',pincode='$pincode' where id='$update_id'";
	mysqli_query($db_conn,$update_products);
		
		echo "<script>window.location='manage-pincode.php?updatedSuccess';</script>";
}
?>