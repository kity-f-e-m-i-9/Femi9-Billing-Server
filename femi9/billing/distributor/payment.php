<?php /*include("include/db-connect.php");

    // Include the PhonePe API code
	
$get_tempid=base64_decode($_REQUEST['tempid']);
$select_product_list="select * from super_stockiest where temp_id='$get_tempid'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				$result_product_list=mysqli_fetch_array($fetch_product_list); 
   
$apiKey ='099eb0cd-02cf-4e2a-8aca-3e6c6aff0399';
$merchantId ='PGTESTPAYUAT';
$keyIndex =1;

    // Extract form data
    $shortName = $result_product_list["name"];
    $message = "Femi9-Super-Stockiest-Activation";
    $email = $result_product_list["email"];
    $mobileNumber = $result_product_list["mobile_number"];
    $amount = $result_product_list["plan_amount"];
    $merchantOrderId = $result_product_list["merchantOrderId"];
    $merchantTransactionId = $result_product_list["merchantTransactionId"];
    $merchantUserId = $result_product_list["merchantUserId"];

    // Prepare the payment data
    $paymentData = array(
        'merchantId' => $merchantId,
        'merchantTransactionId' => $merchantTransactionId,
        "merchantUserId" => $merchantUserId,
        'amount' => $amount * 100, // Convert amount to paisa
        'redirectUrl' => "http://localhost/cowsic/femi9/billing/company/success_ss.php",
        'redirectMode' => "POST",
        'callbackUrl' => "http://localhost/cowsic/femi9/billing/company/success_ss.php",
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


$servername1 = "localhost";
$username1 = "root";
$password1 = "";
$dbname1 = "femi9";

        // Create a connection to the database
        $conn = new mysqli($servername1, $username1, $password1, $dbname1);

        // Check the connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Insert form data into the "paymentdetails" table
        $status = "Success"; // Set the status as success
        $transactionId = $res->data->instrumentResponse->transactionId;

        $sql = "INSERT INTO paymentdetails (merchantOrderId, amount, merchantTransactionId, merchantUserId, status, transactionId,user_temp_id)
                VALUES ('$merchantOrderId', $amount, '$merchantTransactionId', '$merchantUserId', '$status', '$transactionId','$get_tempid')";

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
	*/
?>
