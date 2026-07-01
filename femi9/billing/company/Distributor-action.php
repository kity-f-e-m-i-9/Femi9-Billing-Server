<?php 
include("checksession.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	
	
	if($_POST["temp_id"]!=NULL)
	{
	
	$Coupon_category=$_POST["Coupon_category"];
	$Coupon_category = RemoveSpecialChar($Coupon_category);
	$country_code=$_POST["country_code"];
	
	$usertype=$_POST["usertype"];
	$usertype = RemoveSpecialChar($usertype);
	
	/*$amount_method=$_POST["amount_method"];
	$amount_method = RemoveSpecialChar($amount_method);*/
	
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
	
	if($_POST["shop_onboard"]!=NULL)
	{$shop_onboard=$_POST["shop_onboard"];
	}else{$shop_onboard="0";}
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$mobile_number = RemoveSpecialChar($mobile_number);
	
	
	$Select_CNTMoblenm="select count(*) as numMob from distributor where mobile_number='$mobile_number'";
$Fetch_Count_MobilenUmber=mysqli_query($db_conn,$Select_CNTMoblenm);
$Result_Count_MobilenUmber=mysqli_fetch_array($Fetch_Count_MobilenUmber);
if($Result_Count_MobilenUmber['numMob']==1)
{
	echo "<script>window.location='Distributor-add?InvalidMobileNumber&&usertype=$usertype';</script>";
	
}else{
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	
	$name=str_replace("'","&#39;",$_POST["name"]);
	$name = RemoveSpecialChar($name);
	
$select_count_dist="select count(*) as numdist from distributor where temp_id='$temp_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
		
	
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_email = RemoveSpecialChar($user_email);
	
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	$user_password = RemoveSpecialChar($user_password);
	
	//$username_generate="".$mobile_number."".$district_id."".$taluk_id."".$pincode_id."";
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
                 $uploaddir='../stockist/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	}else{$insfilename="Nil";}
	
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+1 days", strtotime($valid_from)));
	
	//get user id
	$selectmaxid="select max(userid) as numid from distributor";
	$fetchmaxid=mysqli_query($db_conn,$selectmaxid);
	$resultmaxid=mysqli_fetch_array($fetchmaxid);
	$userid=$resultmaxid['numid']+1;
	$format_num = str_pad($userid, 3, '0', STR_PAD_LEFT);
	$useridtext="FEMI9-D-".$format_num."";
	
        $sql="insert into distributor (stockiest_id,temp_id,user_icon,name,state_id,district_id,taluk_id,pincode_id,email,mobile_number,
		username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,
		ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,gstin,
		onboard_userTYPE,onboard_userID,address,userid,useridtext,usertype,country_code,shop_onboard)

		values ('$DummyStockistID','$temp_id','$insfilename','$name','$state_id','$district_id',
		'$taluk_id','$pincode_id','$user_email','$mobile_number',
		'$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','phonepe','pending','Nil','active',
		'$merchantOrderId','$merchantTransactionId','$merchantUserId',
		'$gstin','$onboard_userTYPE','$onboard_userID','$address','$userid','$useridtext',
		'$usertype','$country_code','$shop_onboard')";
		mysqli_query($db_conn,$sql);
		
		
		// Insert Referral & Target Amount Details:-
		$target_amount=$_REQUEST['target_amount'];
		$ref_by_user_type=$_REQUEST['st_ref_type'];
		
		$insert_referral="insert into distributor_referral (distributor_id,target_amount,ref_by_user_type,ref_by_user_id,updated) values 
		('$temp_id','$target_amount','$ref_by_user_type','','0')";
		mysqli_query($db_conn,$insert_referral);
		
		
		/*if($usertype=="Distributor")
		{
		//assigned distributor to pincodewise
$UPTassignedID="update pincode set assigned_DID='$temp_id' where state_id='$state_id' and dist_id='$district_id' and taluk_id='$taluk_id' and id='$pincode_id'";
mysqli_query($db_conn,$UPTassignedID);
		}*/


//-----------------------------------------------------------------------------
//--------------Webhook call start---------------------------------------------
// Data to send
$data = ['coupon_code' => $useridtext];
$jsonData = json_encode($data);

// Send to actual API via cURL
$realWebhookUrl = 'https://maintain.femi9.in/api/webhooks/coupon';
$ch = curl_init($realWebhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Api-Key: femi9_9xKm2nV4pL7wQ1jR5tY8cB3vN6hM0sZ2dF9gH4kX7bP1mW8nJ5tY3cA6vN9qR4w',
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
$response = curl_exec($ch);
curl_close($ch);
//-----------------------------------------------------------------------------
//-----------------------------------------------------------------------------


echo "<script>window.location='Distributor-manage';</script>";


}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='Distributor-add?distalready&&usertype=$usertype';</script>";
	}
	

}

	}else{echo "<script>window.location='Distributor-add?invalidparameters';</script>";}
	
	
}



?>