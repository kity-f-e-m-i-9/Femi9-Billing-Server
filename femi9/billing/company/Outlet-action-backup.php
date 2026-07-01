<?php include("include/db-connect.php");
include("config.php");


if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
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
	
	//payment method phonepe
	if($amount_method=="phonepe")
{
	
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
	$plan_amount=$result_plans["amount"];
	$valid_months=$result_plans["valid_months"];
	
	
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
	$valid_to = date ("Y-m-d", strtotime("+$valid_months days", strtotime($valid_from)));
	
        $sql="insert into outlet (temp_id,user_icon,name,state_id,district_id,taluk_id,pincode_id,email,mobile_number,
		username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,
		ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,gstin,
		onboard_userTYPE,onboard_userID,address)

		values ('$temp_id','$insfilename','$name','$state_id','$district_id',
		'$taluk_id','$pincode_id','$user_email','$mobile_number',
		'$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','phonepe','pending','Nil','pending',
		'$merchantOrderId','$merchantTransactionId','$merchantUserId',
		'$gstin','$onboard_userTYPE','$onboard_userID','$address')";
		mysqli_query($db_conn,$sql);
		
//---------------------------------------------------------------------
//---------------------------------------------------------------------
// Set the API endpoint URL
$api_url=$api_url_own;
$get_orderid=$temp_id;
$get_remarks1="user activation";
$get_remarks2="user activation2";

// Define the payload data
$data = array(
    'customer_mobile' => ''.$mobile_number.'',
    'user_token' => ''.$user_token_own.'',
    'amount' => ''.$plan_amount.'',
    'order_id' => ''.$get_orderid.'',   //use unique order id
    'redirect_url' => ''.$CallbackURL_own_distri.'?orderid='.$get_orderid.'',
    'remark1' => ''.$get_remarks1.'',
    'remark2' => ''.$get_remarks2.'',
);

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Encode the data as form-urlencoded

// Execute the cURL request
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
} else {
    // Parse the JSON response
    $result = json_decode($response, true);

    // Check if the status is true or false
    if ($result && isset($result['status'])) {
        if ($result['status'] === true) {
            // Order was created successfully
            echo "<script>window.location='".$result['result']['payment_url']."'</script>";
        } else {
            // Plan expired
            echo 'Status: ' . $result['status'] . '<br>';
            echo 'Message: ' . $result['message'];
        }
    } else {
        // Invalid response
        echo 'Invalid API response';
    }
}

// Close cURL session
curl_close($ch);
//---------------------------------------------------------------------
//---------------------------------------------------------------------




}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='Outlet-add?distalready';</script>";
	}
	

	
	}
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	
	else
	{ //coupon method open

	$name=str_replace("'","&#39;",$_POST["name"]);
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	
	//$username_generate="".$mobile_number."".$district_id."".$taluk_id."".$pincode_id."";
$username_generate=$mobile_number;

    $ref_number=str_replace("'","&#39;",$_REQUEST['ref_number']);
	
	//plans details
	$select_plans="select * from coupons where coupon_number='$ref_number'";
	$fetch_plans=mysqli_query($db_conn,$select_plans);
	$result_plans=mysqli_fetch_array($fetch_plans);
	$plan_amount=$result_plans['plan_amount'];
	$valid_months=$result_plans['valid_months'];
	
	include("Outlet-coupon-method.php");
	
	}//payment method coupon
	
	
}



?>