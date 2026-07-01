<?php include("include/db-connect.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	if($_POST["temp_id"]!=NULL)
	{
	
	$Coupon_category=$_POST["Coupon_category"];
	$country_code=$_POST["country_code"];
	
	$amount_method=$_POST["amount_method"];
	$amount_method = RemoveSpecialChar($amount_method);
	
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$gstin = RemoveSpecialChar($gstin);
	
	$address=str_replace("'","&#39;",$_POST["address"]);
	$address = RemoveSpecialChar($address);
	
	$temp_id=$_POST["temp_id"];
	$state_id=$_POST["state_id"];
	$district_id=$_POST["dist_id"];
	
	$name=str_replace("'","&#39;",$_POST["name"]);
	$name = RemoveSpecialChar($name);
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$mobile_number = RemoveSpecialChar($mobile_number);
	
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_email = RemoveSpecialChar($user_email);
	
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	$user_password = RemoveSpecialChar($user_password);
	
	//$username_generate="".$mobile_number."".$district_id."";
	$username_generate=$mobile_number;
	
	
$Select_CNTMoblenm="select count(*) as numMob from super_stockiest where mobile_number='$mobile_number'";
$Fetch_Count_MobilenUmber=mysqli_query($db_conn,$Select_CNTMoblenm);
$Result_Count_MobilenUmber=mysqli_fetch_array($Fetch_Count_MobilenUmber);
if($Result_Count_MobilenUmber['numMob']==1)
{
	echo "<script>window.location='add_ss?InvalidMobileNumber';</script>";
	
}else{
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	
	
	
	$select_count_dist="select count(*) as numdist from super_stockiest where state_id='$state_id' and district_id='$district_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
	
	//plans details
	$plan_id=$_POST["plan_id"];
	$select_plans="select * from plans where id='$plan_id'";
	$fetch_plans=mysqli_query($db_conn,$select_plans);
	$result_plans=mysqli_fetch_array($fetch_plans);
	$plan_amount="0";//$result_plans["amount"];
	$valid_months="0";//$result_plans["valid_months"];

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
	$valid_to = date ("Y-m-d", strtotime("+1 days", strtotime($valid_from)));
	
	$merchantOrderId = $_POST["merchantOrderId"];
    $merchantTransactionId = $_POST["merchantTransactionId"];
    $merchantUserId = $_POST["merchantUserId"];
	
	//get user id
	$selectmaxid="select max(userid) as numid from super_stockiest";
	$fetchmaxid=mysqli_query($db_conn,$selectmaxid);
	$resultmaxid=mysqli_fetch_array($fetchmaxid);
	$userid=$resultmaxid['numid']+1;
	$format_num = str_pad($userid, 3, '0', STR_PAD_LEFT);
	$useridtext="FEMI9-SS-".$format_num."";
	
	// Add this section before your INSERT query (around line 80)

    // Get onboard user details from POST or SESSION
    if(isset($_POST['onboard_userTYPE']) && !empty($_POST['onboard_userTYPE'])) {
        $onboard_userTYPE = $_POST['onboard_userTYPE'];
        $onboard_userID = $_POST['onboard_userID'];
    } elseif(isset($_SESSION['user_type']) && isset($_SESSION['user_id'])) {
        // Fallback to session if not in POST (logged-in user who created this)
        $onboard_userTYPE = $_SESSION['user_type'];
        $onboard_userID = $_SESSION['user_id'];
    } else {
        // Default values if nothing available
        $onboard_userTYPE = '';
        $onboard_userID = '';
    }
    
    // Apply RemoveSpecialChar for security
    $onboard_userTYPE = RemoveSpecialChar($onboard_userTYPE);
    $onboard_userID = RemoveSpecialChar($onboard_userID);
	
        $sql="insert into super_stockiest (temp_id,user_icon,name,state_id,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,gstin,address,userid,useridtext,country_code,onboard_userTYPE,onboard_userID)

		values ('$temp_id','$uploadfile','$name','$state_id','$district_id','$user_email','$mobile_number',
		'$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','phonepe','pending','Nil','active',
		'$merchantOrderId','$merchantTransactionId','$merchantUserId','$gstin','$address','$userid','$useridtext','$country_code','$onboard_userTYPE','$onboard_userID')";
		mysqli_query($db_conn,$sql);

		
		//assigned super stockist to districtwise
$UPTassignedID="update district set assigned_SSID='$temp_id' where state_id='$state_id' and id='$district_id'";
mysqli_query($db_conn,$UPTassignedID);

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

echo "<script>window.location='manage_ss?addedsuccess';</script>";


}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='add_ss?distalready';</script>";
	}
	
	
	}//count mobile number end ****
	
	}else{echo "<script>window.location='add_ss?invalidparameters';</script>";}
	

}


?>