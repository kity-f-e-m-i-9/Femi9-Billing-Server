<?php require_once __DIR__ . '/../shared/PasswordService.php'; require_once __DIR__ . '/../shared/db-helpers.php'; require_once __DIR__ . '/../shared/safe-upload.php'; include("checksession.php");
include("include/db-connect.php");
include("config.php");
error_reporting(0);
include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	//$amount_method=$_POST["amount_method"];
	//$Coupon_category=$_POST["Coupon_category"];
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$address=str_replace("'","&#39;",$_POST["address"]);
	$country_code=$_POST["country_code"];
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	
	$Select_CNTMoblenm="select count(*) as numMob from stockiest where mobile_number=?";
$Fetch_Count_MobilenUmber=db_q($db_conn,$Select_CNTMoblenm,[$mobile_number]);
$Result_Count_MobilenUmber=mysqli_fetch_array($Fetch_Count_MobilenUmber);
if($Result_Count_MobilenUmber['numMob']==1)
{
	echo "<script>window.location='add_ss?InvalidMobileNumber';</script>";
	
}else{
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	$temp_id=$_POST["temp_id"]; //stockist id
	$name=str_replace("'","&#39;",$_POST["name"]);
	
	$state_id=$_POST["state_id"];
	$district_id=$_POST["dist_id"];
	$taluk_id=$_POST["taluk_id"];
	
	$select_count_dist="select count(*) as numdist from stockiest where state_id=? and district_id=? and taluk_id=?";
$fetc_count_dist=db_q($db_conn,$select_count_dist,[$state_id,$district_id,$taluk_id]);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
	
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	
	//$username_generate="".$mobile_number."".$district_id."".$taluk_id."";
	$username_generate=$mobile_number;
		
	//plans details
	/*$plan_id=$_POST["plan_id"];
	$select_plans="select * from plans where id=?";
	$fetch_plans=db_q($db_conn,$select_plans,[$plan_id]);
	$result_plans=mysqli_fetch_array($fetch_plans);*/
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
                 $uploaddir='user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   safe_move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	}else{$uploadfile="Nil";}
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+1 days", strtotime($valid_from)));
	
        $sql="insert into stockiest (state_id,temp_id,user_icon,name,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,taluk_id,ss_id,pincode_id,gstin,onboard_userTYPE,onboard_userID,
		address,userid,useridtext,country_code)

		values (?,?,?,?,?,?,?,
		?,?,?,?,
		?,?,'phonepe','pending','Nil','pending',
		?,?,?,?,?,
		'0',?,?,?,
		?,'0','Nil',?)";
		db_q($db_conn,$sql,[$state_id,$temp_id,$uploadfile,$name,$district_id,$user_email,$mobile_number,$username_generate,femi_password_hash((string)($user_password)),$plan_amount,$valid_months,$valid_from,$valid_to,$merchantOrderId,$merchantTransactionId,$merchantUserId,$taluk_id,$Login_user_IDvl,$gstin,$onboard_userTYPE,$onboard_userID,$address,$country_code]);
		
		
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
		values (?,?,?,?,'0')";
		db_q($db_conn,$Insref,[$temp_id,$st_cat_id,$st_ref_type,$st_ref_userid]);
		
		
//ASSIGNED STOCKIST TO TALUK
$UPTassignedID="update taluk set assigned_SID=? where state_id=? and dist_id=? and id=?";
db_q($db_conn,$UPTassignedID,[$temp_id,$state_id,$district_id,$taluk_id]);

//ASSIGNED STOCKIST TO PINCODE
$selectPincode="select * from pincode where state_id=? and dist_id=? and taluk_id=?";
$fethPincode=db_q($db_conn,$selectPincode,[$state_id,$district_id,$taluk_id]);
while($resultPincode=mysqli_fetch_array($fethPincode))
{
	$pinCodeID=$resultPincode['id'];

$update_pincode_user="update pincode set assigned_SID=? where id=?";	
db_q($db_conn,$update_pincode_user,[$temp_id,$pinCodeID]);

}

	   echo "<script>window.location='manage_ss.php?addedsuccess';</script>";


}else{
		//this districtwise super stockiest already exists.
	echo "<script>window.location='add_ss.php?distalready';</script>";
	}
	
}
	
	
}
?>