<?php include("checksession.php");
include("config.php");
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	$distributor_id=$_POST["distributor_id"];
	$shop_cat=$_POST["shop_cat"];
	
	$temp_id=$_POST["temp_id"];
	$name=str_replace("'","&#39;",$_POST["name"]);
	$country_code=$_POST["country_code"];
	
	$state_id=$_POST["state_id"];
	$district_id=$_POST["district_id"];
	$taluk_id=$_POST["taluk_id"];
	$pincode_id=$_POST["pincode_id"];
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	
	$landline=str_replace("'","&#39;",$_POST["landline"]);
	
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$address=str_replace("'","&#39;",$_POST["address"]);
	
	$select_count_dist="select count(*) as numShop from shop where temp_id='$temp_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numShop']==0)
	{
		
		//upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
	   $file_extension = pathinfo($_FILES['user_icon']['name'], PATHINFO_EXTENSION);
		$rand_isd=bin2hex(random_bytes(64));
	    $filename=$rand_isd . '.' . $file_extension;
		
                 $uploaddir='../distributor/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	}else{$insfilename="Nil";}
	
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+1 months", strtotime($valid_from)));
	
	$username_generate="Nil";
	$user_password="Nil";
	$plan_amount="0";
	$valid_months="1";
	$amount_method="Nil";
	$amount_status="Nil";
	$merchantTransactionId="Nil";
	$account_status="Nil";
	$merchantOrderId="Nil";
	$merchantTransactionId="Nil";
	$merchantUserId="Nil";
	
	//get user id
	$selectmaxid="select max(userid) as numid from shop";
	$fetchmaxid=mysqli_query($db_conn,$selectmaxid);
	$resultmaxid=mysqli_fetch_array($fetchmaxid);
	$userid=$resultmaxid['numid']+1;
	$format_num = str_pad($userid, 3, '0', STR_PAD_LEFT);
	$useridtext="FEMI9-R-".$format_num."";
	
        $sql="insert into shop (state_id,temp_id,user_icon,name,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,taluk_id,distributor_id,pincode_id,gstin,onboard_userTYPE,onboard_userID,address,userid,useridtext,shop_cat,country_code,landline)

		values ('$state_id','$temp_id','$insfilename','$name','$district_id','$user_email','$mobile_number',
		'$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','$amount_method','$amount_status',
		'$merchantTransactionId','$account_status','$merchantOrderId','$merchantTransactionId',
		'$merchantUserId','$taluk_id','$distributor_id','$pincode_id','$gstin',
		'$onboard_userTYPE','$onboard_userID','$address','$userid','$useridtext','$shop_cat','$country_code','$landline')";
		mysqli_query($db_conn,$sql);
		
		echo "<script>window.location='Shop-manage.php?addedsuccess';</script>";


}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='Shop-add.php?distalready';</script>";
	}
	
	
	
}
?>