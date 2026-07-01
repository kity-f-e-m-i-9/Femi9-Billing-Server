<?php include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(0);

//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------

//insert customer details
if(isset($_REQUEST['add-customer']))
{
	$country_code=$_POST["country_code"];
	
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	$name = RemoveSpecialChar($name);
	
	$mobile=str_replace("'","&#39;",$_REQUEST['mobile']);
	$mobile = RemoveSpecialChar($mobile);
	
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$email = RemoveSpecialChar($email);
	
	$gstin=str_replace("'","&#39;",$_REQUEST['gstin']);
	$gstin = RemoveSpecialChar($gstin);
	
	$address=str_replace("'","&#39;",$_REQUEST['address']);
	$address = RemoveSpecialChar($address);
	
	$marketing_date=date("Y-m-d",strtotime($_REQUEST['marketing_date']));
	$date=date("d",strtotime($marketing_date));
	
	$user_type=$Login_user_TYPEvl;
	$user_id=$Login_user_IDvl;
	
	$select_count_product="select count(*) as numProducts from customers where mobile='$mobile' and user_type='$Login_user_TYPEvl'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numProducts']==0)
	{
		//get user id
	$selectmaxid="select max(userid) as numid from customers";
	$fetchmaxid=mysqli_query($db_conn,$selectmaxid);
	$resultmaxid=mysqli_fetch_array($fetchmaxid);
	$userid=$resultmaxid['numid']+1;
	$format_num = str_pad($userid, 3, '0', STR_PAD_LEFT);
	$useridtext="FEMI9-".$format_num."";
	
		$insert_products="insert into customers (name,mobile,email,address,marketing_date,date,user_type,user_id,gstin,userid,useridtext,country_code)
		values ('$name','$mobile','$email','$address','$marketing_date','$date','$user_type','$user_id',
		'$gstin','$userid','$useridtext','$country_code')";
		mysqli_query($db_conn,$insert_products);
		
		echo "<script>window.location='manage-customer?addesuccess';</script>";
	}else{
		
		echo "<script>window.location='add-customer?alreadyexists';</script>";
	}
}
	
	
//update customer details
if(isset($_REQUEST['update-customer']))
{
	$update_id=$_REQUEST['update_id'];
	
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	$name = RemoveSpecialChar($name);
	
	$mobile=str_replace("'","&#39;",$_REQUEST['mobile']);
	$mobile = RemoveSpecialChar($mobile);
	
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$email = RemoveSpecialChar($email);
	
	$gstin=str_replace("'","&#39;",$_REQUEST['gstin']);
	$gstin = RemoveSpecialChar($gstin);
	
	$address=str_replace("'","&#39;",$_REQUEST['address']);
	$address = RemoveSpecialChar($address);
	
	$marketing_date=date("Y-m-d",strtotime($_REQUEST['marketing_date']));
	$date=date("d",strtotime($marketing_date));
	$country_code=$_POST["country_code"];
	
	$update_products="update customers set name='$name',mobile='$mobile',
	email='$email',address='$address',marketing_date='$marketing_date',
	date='$date',gstin='$gstin',country_code='$country_code' where id='$update_id'";
	mysqli_query($db_conn,$update_products);
		
		echo "<script>window.location='manage-customer?updatedSuccess';</script>";
	
}

?>