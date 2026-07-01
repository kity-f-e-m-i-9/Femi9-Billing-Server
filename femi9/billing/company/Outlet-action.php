<?php include("include/db-connect.php");
include("config.php");
error_reporting(0);


if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	
	if($_POST["temp_id"]!=NULL)
	{
	
	$Coupon_category=$_POST["Coupon_category"];
	
	$amount_method=$_POST["amount_method"];
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$address=str_replace("'","&#39;",$_POST["address"]);
	$temp_id=$_POST["temp_id"];
	
	$state_id=$_POST["state_id"];
	$district_id=$_POST["district_id"];
	$taluk_id=$_POST["taluk_id"];
	$pincode_id=$_POST["pincode_id"];
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	
	
	$name=str_replace("'","&#39;",$_POST["name"]);
	
	$select_count_dist="select count(*) as numdist from outlet where temp_id='$temp_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
		
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	
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
                 $uploaddir='outlet_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="outlet_icon/";
	   $insfilename=$insfoldername.$filename;
	}else{$insfilename="Nil";}
	
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+1 days", strtotime($valid_from)));
	
	//get user id
	$selectmaxid="select max(userid) as numid from outlet";
	$fetchmaxid=mysqli_query($db_conn,$selectmaxid);
	$resultmaxid=mysqli_fetch_array($fetchmaxid);
	$userid=$resultmaxid['numid']+1;
	$format_num = str_pad($userid, 3, '0', STR_PAD_LEFT);
	$useridtext="FEMI9-R-".$format_num."";
	
        $sql="insert into outlet (temp_id,user_icon,name,state_id,district_id,taluk_id,pincode_id,email,mobile_number,
		username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,
		ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,gstin,
		onboard_userTYPE,onboard_userID,address,userid,useridtext)

		values ('$temp_id','$insfilename','$name','$state_id','$district_id',
		'$taluk_id','$pincode_id','$user_email','$mobile_number',
		'$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','phonepe','pending','Nil','active',
		'$merchantOrderId','$merchantTransactionId','$merchantUserId',
		'$gstin','$onboard_userTYPE','$onboard_userID','$address','$userid','$useridtext')";
		mysqli_query($db_conn,$sql);
		

		echo "<script>window.location='Outlet-manage?distalready';</script>";


}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='Outlet-add?distalready';</script>";
	}
	
	
	}else{echo "<script>window.location='Outlet-add?invalidparameters';</script>";}
	
}


?>