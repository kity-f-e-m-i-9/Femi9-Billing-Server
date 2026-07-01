<?php include("checksession.php");
include("include/db-connect.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$mobile_number = RemoveSpecialChar($mobile_number);
	
	$Select_CNTMoblenm="select count(*) as numMob from stockiest where mobile_number='$mobile_number'";
$Fetch_Count_MobilenUmber=mysqli_query($db_conn,$Select_CNTMoblenm);
$Result_Count_MobilenUmber=mysqli_fetch_array($Fetch_Count_MobilenUmber);
if($Result_Count_MobilenUmber['numMob']==1)
{
	echo "<script>window.location='stockist-add?InvalidMobileNumber';</script>";
	
}else{
	
	//$amount_method=$_POST["amount_method"];
	//$amount_method = RemoveSpecialChar($amount_method);
	
	//$Coupon_category=$_POST["Coupon_category"];
	//$Coupon_category = RemoveSpecialChar($Coupon_category);
	
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$gstin = RemoveSpecialChar($gstin);
	
	$address=str_replace("'","&#39;",$_POST["address"]);
	$address = RemoveSpecialChar($address);
	
	$country_code=$_POST["country_code"];
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	$temp_id=$_POST["temp_id"]; //stockist id
	$name=str_replace("'","&#39;",$_POST["name"]);
	$name = RemoveSpecialChar($name);
	
	$state_id=$_POST["state_id"];
	$district_id=$_POST["dist_id"];
	$taluk_id=$_POST["taluk_id"];
	
	$select_count_dist="select count(*) as numdist from stockiest where state_id='$state_id' and district_id='$district_id' and taluk_id='$taluk_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
	
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_email = RemoveSpecialChar($user_email);
	
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	$user_password = RemoveSpecialChar($user_password);
	
	//$username_generate="".$mobile_number."".$district_id."".$taluk_id."";
	$username_generate=$mobile_number;
		
	//plans details
	//$plan_id=$_POST["plan_id"];
	//$select_plans="select * from plans where id='$plan_id'";
	//$fetch_plans=mysqli_query($db_conn,$select_plans);
	//$result_plans=mysqli_fetch_array($fetch_plans);
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
                 $uploaddir='../super-stockist/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	}else{$insfilename="Nil";}
	
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+1 days", strtotime($valid_from)));
	
	
	//get user id
	$selectmaxid="select max(userid) as numid from stockiest";
	$fetchmaxid=mysqli_query($db_conn,$selectmaxid);
	$resultmaxid=mysqli_fetch_array($fetchmaxid);
	$userid=$resultmaxid['numid']+1;
	$format_num = str_pad($userid, 3, '0', STR_PAD_LEFT);
	$useridtext="FEMI9-S-".$format_num."";
	
	//INSERT STOCKIST DETAILS
        $sql="insert into stockiest (state_id,temp_id,user_icon,name,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,taluk_id,ss_id,pincode_id,gstin,onboard_userTYPE,
		onboard_userID,address,userid,useridtext,country_code)

		values ('$state_id','$temp_id','$insfilename','$name','$district_id','$user_email',
		'$mobile_number','$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','phonepe','pending','Nil','active',
		'$merchantOrderId','$merchantTransactionId','$merchantUserId','$taluk_id',
		'$DummySuperStockistID','0','$gstin','$onboard_userTYPE','$onboard_userID',
		'$address','$userid','$useridtext','$country_code')";
		mysqli_query($db_conn,$sql);
		
		
		//INSERT REFERRAL DETAILS
		$st_cat_id=str_replace("'","&#39;",$_POST["st_cat_id"]);
	    $st_cat_id = RemoveSpecialChar($st_cat_id);
		
		$st_ref_type=str_replace("'","&#39;",$_POST["st_ref_type"]);
	    $st_ref_type = RemoveSpecialChar($st_ref_type);
		
		$st_ref_userid=$_POST["st_ref_userid"];
		$st_ref_userid2=$_POST["st_ref_userid2"];
		$st_ref_userid_conc="".$st_ref_userid."".$st_ref_userid2."";
	    $st_ref_userid = RemoveSpecialChar($st_ref_userid_conc);
	
		$Insref="insert into stockist_referral (stockist_id,st_cat_id,st_ref_type,st_ref_userid,updated) 
		values ('$temp_id','$st_cat_id','$st_ref_type','$st_ref_userid','0')";
		mysqli_query($db_conn,$Insref);
		
		
		//ASSIGNED STOCKIST TO TALUK
$UPTassignedID="update taluk set assigned_SID='$temp_id' where state_id='$state_id' and dist_id='$district_id' and id='$taluk_id'";
mysqli_query($db_conn,$UPTassignedID);

//ASSIGNED STOCKIST TO PINCODE
$selectPincode="select * from pincode where state_id='$state_id' and dist_id='$district_id' and taluk_id='$taluk_id'";
$fethPincode=mysqli_query($db_conn,$selectPincode);
while($resultPincode=mysqli_fetch_array($fethPincode))
{
	$pinCodeID=$resultPincode['id'];

$update_pincode_user="update pincode set assigned_SID='$temp_id' where id='$pinCodeID'";	
mysqli_query($db_conn,$update_pincode_user);

}


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

echo "<script>window.location='stockist-manage?addedsuccess';</script>";


}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='stockist-add?distalready';</script>";
	}
}
	
}
?>