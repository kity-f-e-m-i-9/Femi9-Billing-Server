<?php 
session_start();
include("include/db-connect.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	
	
	if($_POST["temp_id"]!=NULL)
	{
	$country_code=$_POST["country_code"];
	
	$usertype=$_POST["usertype"];
	$usertype = RemoveSpecialChar($usertype);
	
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$gstin = RemoveSpecialChar($gstin);
	
	$address=str_replace("'","&#39;",$_POST["address"]);
	$address = RemoveSpecialChar($address);
	
	$temp_id=$_POST["temp_id"];
	
	$state_id=str_replace("'","&#39;",$_POST["state_id"]);
	$state_id = RemoveSpecialChar($state_id);
	
	$district_id=str_replace("'","&#39;",$_POST["district_id"]);
	$district_id = RemoveSpecialChar($district_id);
	
	$taluk_id=str_replace("'","&#39;",$_POST["taluk_id"]);
	$taluk_id = RemoveSpecialChar($taluk_id);
	
	$pincode_id=str_replace("'","&#39;",$_POST["pincode_id"]);
	$pincode_id = RemoveSpecialChar($pincode_id);
	
	if($_POST["shop_onboard"]!=NULL){$shop_onboard="1";}else{$shop_onboard="0";}
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$mobile_number = RemoveSpecialChar($mobile_number);
	
//Validate Mobile Number	
$Select_CNTMoblenm="select count(*) as numMob from super_distributor where mobile_number='$mobile_number'";
$Fetch_Count_MobilenUmber=mysqli_query($db_conn,$Select_CNTMoblenm);
$Result_Count_MobilenUmber=mysqli_fetch_array($Fetch_Count_MobilenUmber);
if($Result_Count_MobilenUmber['numMob']==1)
{
	echo "<script>window.location='super_Distributor_add?InvalidMobileNumber&&usertype=$usertype';</script>";
	exit;
	
}else{
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	
	$name=str_replace("'","&#39;",$_POST["name"]);
	$name = RemoveSpecialChar($name);
	
$select_count_dist="select count(*) as numdist from super_distributor where temp_id='$temp_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
		
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_email = RemoveSpecialChar($user_email);
	
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	$user_password = RemoveSpecialChar($user_password);
	
	$username_generate=$mobile_number;
		
	//plans details
	$plan_id=$_POST["plan_id"];
	$select_plans="select * from plans where id='$plan_id'";
	$fetch_plans=mysqli_query($db_conn,$select_plans);
	$result_plans=mysqli_fetch_array($fetch_plans);
	$plan_amount="0";//$result_plans["amount"];
	$valid_months="0";//$result_plans["valid_months"];
	
    $merchantOrderId = $_POST["merchantOrderId"];
    $merchantTransactionId = $_POST["merchantTransactionId"];
    $merchantUserId = $_POST["merchantUserId"];

    //upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='../super_distributor/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	}else{$insfilename="Nil";}
	
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+1 days", strtotime($valid_from)));
	
	//get user id
	/*$selectmaxid="select max(userid) as numid from super_distributor";
	$fetchmaxid=mysqli_query($db_conn,$selectmaxid);
	$resultmaxid=mysqli_fetch_array($fetchmaxid);
	$userid=$resultmaxid['numid']+1;
	$format_num = str_pad($userid, 3, '0', STR_PAD_LEFT);
	$useridtext="FEMI9-SD-".$format_num."";*/
	
		// Insert Super Distributor Details:-
        $sql="insert into super_distributor (stockiest_id,temp_id,user_icon,name,state_id,district_id,taluk_id,pincode_id,email,mobile_number,
		username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,
		ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,gstin,
		onboard_userTYPE,onboard_userID,address,userid,useridtext,usertype,country_code,shop_onboard)

		values ('$DummyStockistID','$temp_id','$insfilename','$name','$state_id','$district_id',
		'$taluk_id','$pincode_id','$user_email','$mobile_number',
		'$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','phonepe','pending','Nil','pending',
		'$merchantOrderId','$merchantTransactionId','$merchantUserId',
		'$gstin','$onboard_userTYPE','$onboard_userID','$address','0','Nil',
		'$usertype','$country_code','$shop_onboard')";
		mysqli_query($db_conn,$sql);
		
		// Insert Referral & Target Amount Details:-
		$target_amount=$_REQUEST['target_amount'];
		$ref_by_user_type=$_REQUEST['st_ref_type'];
		
		$insert_referral="insert into super_distributor_referral (sd_id,target_amount,ref_by_user_type,ref_by_user_id,updated) values 
		('$temp_id','$target_amount','$ref_by_user_type','','0')";
		mysqli_query($db_conn,$insert_referral);
		
		
echo "<script>window.location='super_Distributor_manage?useraddedsuccess';</script>";
exit;


}else{
		//this super distributor already exists.
		echo "<script>window.location='super_Distributor_add?distalready&&usertype=$usertype';</script>";
		exit;
	}
	

}

	}
	else
	{
		echo "<script>window.location='super_Distributor_add?invalidparameters';</script>";
		exit;
	}
	
	
}
else 
{
	echo "<script>window.location='dashboard';</script>";
	exit;
}
?>