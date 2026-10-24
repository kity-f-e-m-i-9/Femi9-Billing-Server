<?php require_once __DIR__ . '/../shared/db-helpers.php'; require_once __DIR__ . '/../shared/safe-upload.php'; include("checksession.php");
include("include/db-connect.php");
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(0);


if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	$amount_method=$_POST["amount_method"];
    $gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$address=str_replace("'","&#39;",$_POST["address"]);
	$country_code=$_POST["country_code"];
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$usertype=$_POST["usertype"];
	
	$Select_CNTMoblenm="select count(*) as numMob from distributor where mobile_number=?";
$Fetch_Count_MobilenUmber=db_q($db_conn,$Select_CNTMoblenm,[$mobile_number]);
$Result_Count_MobilenUmber=mysqli_fetch_array($Fetch_Count_MobilenUmber);
if($Result_Count_MobilenUmber['numMob']==1)
{
	echo "<script>window.location='add_ss?InvalidMobileNumber&&usertype=".urlencode($usertype)."';</script>";
	exit;
	
}else{
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	
	$temp_id=$_POST["temp_id"];
	$name=str_replace("'","&#39;",$_POST["name"]);
	
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
	
	
	$select_count_dist="select count(*) as numdist from distributor where temp_id=?";
$fetc_count_dist=db_q($db_conn,$select_count_dist,[$temp_id]);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
	
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	$username_generate=$mobile_number;
		
	//plans details
	$plan_id=$_POST["plan_id"];
	$select_plans="select * from plans where id=?";
	$fetch_plans=db_q($db_conn,$select_plans,[$plan_id]);
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
                 $uploaddir='user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   safe_move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	}else{$uploadfile="Nil";}
	
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+1 days", strtotime($valid_from)));
	
        $sql="insert into distributor (state_id,temp_id,user_icon,name,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,taluk_id,stockiest_id,pincode_id,gstin,onboard_userTYPE,onboard_userID,address,userid,useridtext,usertype,country_code,shop_onboard)

		values (?,?,?,?,?,?,?,
		?,?,?,?,
		?,?,'phonepe','pending','Nil','pending',
		?,?,?,
		?,?,?,?,?,?,?,'0','Nil',?,?,?)";
		db_q($db_conn,$sql,[$state_id,$temp_id,$uploadfile,$name,$district_id,$user_email,$mobile_number,$username_generate,$user_password,$plan_amount,$valid_months,$valid_from,$valid_to,$merchantOrderId,$merchantTransactionId,$merchantUserId,$taluk_id,$Login_user_IDvl,$pincode_id,$gstin,$onboard_userTYPE,$onboard_userID,$address,$usertype,$country_code,$shop_onboard]);
		
		
		// Insert Referral & Target Amount Details:-
		$target_amount=$_REQUEST['target_amount'];
		$ref_by_user_type=$_REQUEST['st_ref_type'];
		
		$insert_referral="insert into distributor_referral (distributor_id,target_amount,ref_by_user_type,ref_by_user_id,updated) values 
		(?,?,?,'','0')";
		db_q($db_conn,$insert_referral,[$temp_id,$target_amount,$ref_by_user_type]);
		
		
		/*if($usertype=="Distributor")
		{
			$ex=explode(",",$pincode_id);
 
  foreach ($ex as $key => $value)
   {  
    //DISTRIBUTOR ASSIGNED TO PINCODE
$UPTassignedID="update pincode set assigned_DID=? where state_id=? and dist_id=? and taluk_id=? and id=?";
db_q($db_conn,$UPTassignedID,[$temp_id,$state_id,$district_id,$taluk_id,$value]);   
   }
		}*/

        
echo "<script>window.location='manage_ss.php?addedsuccess';</script>";

}else{
		echo "<script>window.location='add_ss.php?distalready&&usertype=".urlencode($usertype)."';</script>";
	}
	
	
}
	
}
?>