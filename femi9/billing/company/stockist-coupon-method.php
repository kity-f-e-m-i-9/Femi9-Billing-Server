<?php require_once __DIR__ . '/../shared/db-helpers.php'; require_once __DIR__ . '/../shared/safe-upload.php';
//-------------------------------------------------------------------------------
//-------------------------------------------------------------------------------	
$select_count_cpnvalid="select count(*) as numcpnsvalid from coupons where coupon_number='".db_esc($ref_number)."' and coupon_status='none' and user_type='".db_esc($Login_user_TYPEvl)."' and category='".db_esc($Coupon_category)."'";
$fetch_count_cpnvalid=mysqli_query($db_conn,$select_count_cpnvalid);
$result_count_cpnvalid=mysqli_fetch_array($fetch_count_cpnvalid);
if($result_count_cpnvalid['numcpnsvalid']==1)
{
	
$select_count_dist="select count(*) as numdist from stockiest where state_id='".db_esc($state_id)."' and district_id='".db_esc($district_id)."' and taluk_id='".db_esc($taluk_id)."' and pincode_id='".db_esc($PincodeID_Implode)."'";
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
                 $uploaddir='../super-stockist/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   safe_move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	}else{$insfilename="Nil";}
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+$valid_months days", strtotime($valid_from)));
		
		//insert
		$insCpns="insert into stockiest (state_id,temp_id,user_icon,name,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,taluk_id,ss_id,pincode_id,gstin,onboard_userTYPE,onboard_userID,address)

		values ('".db_esc($state_id)."','".db_esc($temp_id)."','".db_esc($insfilename)."','".db_esc($name)."','".db_esc($district_id)."','".db_esc($user_email)."','".db_esc($mobile_number)."',
		'".db_esc($username_generate)."','".db_esc($user_password)."','".db_esc($plan_amount)."','".db_esc($valid_months)."',
		'".db_esc($valid_from)."','".db_esc($valid_to)."','coupon','paid','".db_esc($ref_number)."','active',
		'Nil','Nil','Nil','".db_esc($taluk_id)."','".db_esc($DummySuperStockistID)."',
		'".db_esc($PincodeID_Implode)."','".db_esc($gstin)."','".db_esc($onboard_userTYPE)."','".db_esc($onboard_userID)."','".db_esc($address)."')"; 
		mysqli_query($db_conn,$insCpns);
		
		//assigned stockist to talukwise
$UPTassignedID="update taluk set assigned_SID='".db_esc($temp_id)."' where state_id='".db_esc($state_id)."' and dist_id='".db_esc($district_id)."' and id='".db_esc($taluk_id)."'";
mysqli_query($db_conn,$UPTassignedID);

//assigned pincode to stockist
foreach ($PincodeID_Explode as $key => $Pinvalue)
   {  
$update_pincode_user="update pincode set assigned_SID='".db_esc($temp_id)."' where id='".db_esc($Pinvalue)."'";	
mysqli_query($db_conn,$update_pincode_user);
   }  
		
		//update coupon to used
		$update_cpns="update coupons set coupon_status='used' where coupon_number='".db_esc($ref_number)."'";
		mysqli_query($db_conn,$update_cpns);
		
		echo "<script>window.location='stockist-manage?addedsuccess';</script>";
		
		
	}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='stockist-add?distalready';</script>";
	}
	
}else{
	
	//invalid coupon.
		echo "<script>window.location='stockist-add?invalidcoupon';</script>";
}
	
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	?>