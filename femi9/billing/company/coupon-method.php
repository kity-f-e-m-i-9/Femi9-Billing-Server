<?php /*
//-------------------------------------------------------------------------------
//-------------------------------------------------------------------------------	
$select_count_cpnvalid="select count(*) as numcpnsvalid from coupons where coupon_number='$ref_number' and coupon_status='none' and category='$Coupon_category' and user_type='$Login_user_TYPEvl'";
$fetch_count_cpnvalid=mysqli_query($db_conn,$select_count_cpnvalid);
$result_count_cpnvalid=mysqli_fetch_array($fetch_count_cpnvalid);
if($result_count_cpnvalid['numcpnsvalid']==1)
{
	
$select_count_dist="select count(*) as numdist from super_stockiest where state_id='$state_id' and district_id='$district_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
		
		
		//upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	}else{$uploadfile="Nil";}
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+$valid_months days", strtotime($valid_from)));
		
		//insert
		$insCpns="insert into super_stockiest (state_id,temp_id,user_icon,name,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,gstin,address,country_code)

		values ('$state_id','$temp_id','$uploadfile','$name','$district_id','$user_email','$mobile_number',
		'$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','coupon','paid','$ref_number','active',
		'Nil','Nil','Nil','$gstin','$address','$country_code')"; 
		mysqli_query($db_conn,$insCpns);
		
		//assigned super stockist to districtwise
$UPTassignedID="update district set assigned_SSID='$temp_id' where state_id='$state_id' and id='$district_id'";
mysqli_query($db_conn,$UPTassignedID);
		
		//update coupon to used
		$update_cpns="update coupons set coupon_status='used' where coupon_number='$ref_number' and category='$Coupon_category'";
		mysqli_query($db_conn,$update_cpns);
		
		echo "<script>window.location='manage_ss?addedsuccess';</script>";
		
		
	}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='add_ss?distalready';</script>";
	}
	
}else{
	
	//invalid coupon.
		echo "<script>window.location='add_ss?invalidcoupon';</script>";
}
	
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	?>