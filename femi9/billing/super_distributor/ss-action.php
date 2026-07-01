<?php /* include("include/db-connect.php");


if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	$amount_method=$_POST["amount_method"];
	$distributor_id=$_POST["distributor_id"];
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	//-----------------------------------------------------------------------------
	
	//payment method phonepe
	if($amount_method=="phonepe")
{
	
	$temp_id=$_POST["temp_id"];
	$name=str_replace("'","&#39;",$_POST["name"]);
	
	$district_id=$_POST["district_id"];
	$taluk_id=$_POST["taluk_id"];
	$pincode_id=$_POST["pincode_id"];
	
	$select_count_dist="select count(*) as numdist from shop where district_id='$district_id' and taluk_id='$taluk_id' and pincode_id='$pincode_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numdist']==0)
	{
		
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	
	$username_generate="".$mobile_number."".$district_id."".$taluk_id."";
		
	//plans details
	$plan_id=$_POST["plan_id"];
	$select_plans="select * from plans where id='$plan_id'";
	$fetch_plans=mysqli_query($db_conn,$select_plans);
	$result_plans=mysqli_fetch_array($fetch_plans);
	$plan_amount=$result_plans["amount"];
	$valid_months=$result_plans["valid_months"];
	
$apiKey ='099eb0cd-02cf-4e2a-8aca-3e6c6aff0399';
$merchantId ='PGTESTPAYUAT';
$keyIndex =1;

    // Extract form data
    $shortName = $name;
    $message = "user activation";
    $email = $user_email;
    $mobileNumber = $mobile_number;
    $amount = $plan_amount;
    $merchantOrderId = $_POST["merchantOrderId"];
    $merchantTransactionId = $_POST["merchantTransactionId"];
    $merchantUserId = $_POST["merchantUserId"];

    // Prepare the payment data
    $paymentData = array(
        'merchantId' => $merchantId,
        'merchantTransactionId' => $merchantTransactionId,
        "merchantUserId" => $merchantUserId,
        'amount' => $amount * 100, // Convert amount to paisa
        'redirectUrl' => $RedirectURL,
        'redirectMode' => "POST",
        'callbackUrl' => $CallbackURL,
        "merchantOrderId" => $merchantOrderId,
        "mobileNumber" => $mobileNumber,
        "message" => $message,
        "email" => $email,
        "shortName" => $shortName,
        "paymentInstrument" => array(
            "type" => "PAY_PAGE",
        )
    );

    // Encode payment data
    $jsonencode = json_encode($paymentData);
    $payloadMain = base64_encode($jsonencode);

    // Create payload for X-VERIFY header
    $payload = $payloadMain . "/pg/v1/pay" . $apiKey;
    $sha256 = hash("sha256", $payload);
    $final_x_header = $sha256 . '###' . $keyIndex;
    $request = json_encode(array('request' => $payloadMain));

    // Make the API request
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $request,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "X-VERIFY: " . $final_x_header,
            "accept: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $res = json_decode($response);

        if (isset($res->success) && $res->success == '1') {
            $paymentCode = $res->code;
            $paymentMsg = $res->message;
            $payUrl = $res->data->instrumentResponse->redirectInfo->url;
            header('Location:' . $payUrl);
        } 
        
        // Check if the payment was successful
    if (isset($res->success) && $res->success == '1') {
        $paymentCode = $res->code;
        $paymentMsg = $res->message;
        $payUrl = $res->data->instrumentResponse->redirectInfo->url;


   include("include/dbconn.php");

        // Create a connection to the database
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Check the connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Insert form data into the "paymentdetails" table
        //$status = "Success"; // Set the status as success
        //$transactionId = $res->data->instrumentResponse->transactionId;


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
	$valid_to = date ("Y-m-d", strtotime("+$valid_months months", strtotime($valid_from)));
	
        $sql="insert into shop (temp_id,user_icon,name,district_id,email,mobile_number,username,password,plan_amount,valid_months,valid_from,valid_to,amount_method,amount_status,ref_number,account_status,merchantOrderId,merchantTransactionId,merchantUserId,taluk_id,distributor_id,pincode_id)

		values ('$temp_id','$uploadfile','$name','$district_id','$email','$mobile_number',
		'$username_generate','$user_password','$plan_amount','$valid_months',
		'$valid_from','$valid_to','phonepe','pending','$merchantTransactionId','pending',
		'$merchantOrderId','$merchantTransactionId','$merchantUserId',
		'$taluk_id','$distributor_id','$pincode_id')";

        if ($conn->query($sql) === TRUE) {
            // Redirect to the PhonePe payment URL
            header('Location:' . $payUrl);
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }

        // Close the database connection
        $conn->close();
    } else {
            echo "Payment API Error: " . $res->message;
        }
    }


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


    $temp_id=$_POST["temp_id"];
	$name=str_replace("'","&#39;",$_POST["name"]);
	
	$district_id=$_POST["district_id"];
	$taluk_id=$_POST["taluk_id"];
	$pincode_id=$_POST["pincode_id"];
	
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$user_email=str_replace("'","&#39;",$_POST["email"]);
	$user_password=str_replace("'","&#39;",$_POST["password"]);
	
	$username_generate="".$mobile_number."".$district_id."".$taluk_id."";

    $ref_number=str_replace("'","&#39;",$_REQUEST['ref_number']);
	
	//plans details
	$select_plans="select * from coupons where coupon_number='$ref_number'";
	$fetch_plans=mysqli_query($db_conn,$select_plans);
	$result_plans=mysqli_fetch_array($fetch_plans);
	$plan_amount=$result_plans['plan_amount'];
	$valid_months=$result_plans['valid_months'];
	
	include("coupon-method.php");
	
	}//payment method coupon
	
	
} */
?>