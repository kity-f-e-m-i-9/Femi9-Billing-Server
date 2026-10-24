<?php require_once __DIR__ . '/../shared/PasswordService.php'; require_once __DIR__ . '/../shared/db-helpers.php'; require_once __DIR__ . '/../shared/safe-upload.php';
//-------------------------------------------------------------------------------
//-------------------------------------------------------------------------------	
$select_count_cpnvalid="select count(*) as numcpnsvalid from coupons where coupon_number='".db_esc($ref_number)."' and coupon_status='none' and category='".db_esc($Coupon_category)."' and user_type='".db_esc($Login_user_TYPEvl)."'";
$fetch_count_cpnvalid=mysqli_query($db_conn,$select_count_cpnvalid);
$result_count_cpnvalid=mysqli_fetch_array($fetch_count_cpnvalid);
if($result_count_cpnvalid['numcpnsvalid']==1)
{
	
$select_count_dist="select count(*) as numdist from distributor where state_id='".db_esc($state_id)."' and district_id='".db_esc($district_id)."' and taluk_id='".db_esc($taluk_id)."' and pincode_id='".db_esc($pincode_id)."'";
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
                 $uploaddir='../stockist/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   safe_move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	}else{$insfilename="Nil";}
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+$valid_months days", strtotime($valid_from)));
		
		$insCpns="insert into distributor (stockiest_id,temp_id,user_icon,name,state_id,district_id,taluk_id,pincode_id,email,mobile_number,
		username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,
		ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,gstin,
		onboard_userTYPE,onboard_userID,address)

		values ('".db_esc($DummyStockistID)."','".db_esc($temp_id)."','".db_esc($insfilename)."','".db_esc($name)."','".db_esc($state_id)."','".db_esc($district_id)."',
		'".db_esc($taluk_id)."','".db_esc($pincode_id)."','".db_esc($user_email)."','".db_esc($mobile_number)."',
		'".db_esc($username_generate)."','".db_esc(femi_password_hash((string)($user_password)))."','".db_esc($plan_amount)."','".db_esc($valid_months)."',
		'".db_esc($valid_from)."','".db_esc($valid_to)."','coupon','paid','".db_esc($ref_number)."','active',
		'Nil','Nil','Nil','".db_esc($gstin)."','".db_esc($onboard_userTYPE)."','".db_esc($onboard_userID)."','".db_esc($address)."')";
		mysqli_query($db_conn,$insCpns);
		
		//assigned distributor to pincodewise
$UPTassignedID="update pincode set assigned_DID='".db_esc($temp_id)."' where state_id='".db_esc($state_id)."' and dist_id='".db_esc($district_id)."' and taluk_id='".db_esc($taluk_id)."' and id='".db_esc($pincode_id)."'";
mysqli_query($db_conn,$UPTassignedID);
		
		//update coupon to used
		$update_cpns="update coupons set coupon_status='used' where coupon_number='".db_esc($ref_number)."' and category='".db_esc($Coupon_category)."'";
		mysqli_query($db_conn,$update_cpns);
		
		echo "<script>window.location='Distributor-manage?addedsuccess';</script>";
		
		
	}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='Distributor-add?distalready';</script>";
	}
	
}else{
	
	//invalid coupon.
		echo "<script>window.location='Distributor-add?invalidcoupon';</script>";
}
	
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	?>