<?php /* include("checksession.php");
include("include/db-connect.php");
include("config.php");


if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	$amount_method=$_POST["amount_method"];
	$Coupon_category=$_POST["Coupon_category"];
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$address=str_replace("'","&#39;",$_POST["address"]);
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	$temp_id=$_POST["temp_id"]; //stockist id
	
	$PincodeID=$_POST['pincode_id']; 
	$PincodeID_Implode=implode(",",$PincodeID);
	$PincodeID_Explode=explode(",",$PincodeID_Implode);
 
	//payment method phonepe
	if($amount_method=="phonepe")
{
	
	$name=str_replace("'","&#39;",$_POST["name"]);
	
	$state_id=$_POST["state_id"];
	$district_id=$_POST["dist_id"];
	$taluk_id=$_POST["taluk_id"];
	
	$select_count_dist="select count(*) as numdist from stockiest where state_id='$state_id' and district_id='$district_id' and taluk_id='$taluk_id' and pincode_id='$PincodeID_Implode'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
		
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	
	//$username_generate="".$mobile_number."".$district_id."".$taluk_id."";
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
                 $uploaddir='user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	}else{$uploadfile="Nil";}
	
	
	date_default_timezone_set("Asia/Kolkata");
	$valid_from=date("Y-m-d");
	$valid_to = date ("Y-m-d", strtotime("+$valid_months days", strtotime($valid_from)));
	
        $sql="insert into stockiest (state_id,temp_id,user_icon,name,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,taluk_id,ss_id,pincode_id,gstin,onboard_userTYPE,onboard_userID,address)

		values ('$state_id','$temp_id','$uploadfile','$name','$district_id','$user_email','$mobile_number',
		'$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','phonepe','pending','Nil','pending',
		'$merchantOrderId','$merchantTransactionId','$merchantUserId','$taluk_id','$Login_user_IDvl',
		'$PincodeID_Implode','$gstin','$onboard_userTYPE','$onboard_userID','$address')";
		mysqli_query($db_conn,$sql);
		
		//assigned stockist to talukwise
$UPTassignedID="update taluk set assigned_SID='$temp_id' where state_id='$state_id' and dist_id='$district_id' and id='$taluk_id'";
mysqli_query($db_conn,$UPTassignedID);

//assigned pincode to stockist
foreach ($PincodeID_Explode as $key => $Pinvalue)
   {  
$update_pincode_user="update pincode set assigned_SID='$temp_id' where id='$Pinvalue'";	
mysqli_query($db_conn,$update_pincode_user);
   }  

       
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
    'redirect_url' => ''.$CallbackURL_own.'?orderid='.$get_orderid.'',
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
		echo "<script>window.location='add_ss.php?distalready';</script>";
	}
	

	
	}
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	
	else
	{ //coupon method open


	$name=str_replace("'","&#39;",$_POST["name"]);
	
	$state_id=$_POST["state_id"];
	$district_id=$_POST["dist_id"];
	$taluk_id=$_POST["taluk_id"];
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	
	//$username_generate="".$mobile_number."".$district_id."".$taluk_id."";
	$username_generate=$mobile_number;

    $ref_number=str_replace("'","&#39;",$_REQUEST['ref_number']);
	
	//plans details
	$select_plans="select * from coupons where coupon_number='$ref_number'";
	$fetch_plans=mysqli_query($db_conn,$select_plans);
	$result_plans=mysqli_fetch_array($fetch_plans);
	$plan_amount=$result_plans['plan_amount'];
	$valid_months=$result_plans['valid_months'];
	
	include("coupon-method.php");
	
	}//payment method coupon
	
	
}
?>