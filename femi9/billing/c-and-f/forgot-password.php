<?php session_start(); error_reporting(0); 
include("include/db-connect.php");
include("../company/whatsapp-settins.php");


if(isset($_REQUEST['subforgotbutton']))
{
	//Config
	$adminmailid="femihealthcare21@gmail.com";
	$forgottable="c_and_f";
	$frusertype="candf";
	
//super_stockiest
//stockiest
//distributor
//outlet
	
$frmobilenumber=$_REQUEST['frmobilenumber'];
$randumpassword=$_REQUEST['randumpassword'];
	
$select_LoGuserDtails="select * from ".$forgottable." where mobile_number='$frmobilenumber'";
$fetch_LoGuserDtails=mysqli_query($db_conn,$select_LoGuserDtails);
$result_LoGuserDtails=mysqli_fetch_array($fetch_LoGuserDtails);
$mailid=$result_LoGuserDtails['email'];

	
if($result_LoGuserDtails['name']!=NULL)
{

//---------------------------------------------------------------------
//----------------------MESSAGE TO WHATSAPP----------------------------
//---------------------------------------------------------------------

    $recipient = "91".$frmobilenumber."";
    $type = "text";
    $message = "Mr/Mrs. ".$result_LoGuserDtails['name'].", Your reset password is ".$randumpassword."";

    $priority = 1;

    $post_data = array(
        'secret' => $api_secret,
        'account' => $api_account,
        'recipient' => $recipient,
        'type' => $type,
        'message' => $message,
        'priority' => $priority
    );

    // Initialize cURL session
    $ch = curl_init($api_url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

    // Execute cURL session and get the response
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    // Close cURL session
    curl_close($ch);

    // Print API response for debugging
    //echo "API Response: " . $response;
	
	
//---------------------------Email Coding start------------------------------------------------
$FromEmail=$adminmailid;
$ToEmail = $mailid;
$subject = 'Femi9 | Password Reset Successfully !';
$message123 =  'Mr/Mrs. '.$result_LoGuserDtails['name'].', Your reset password is '.$randumpassword.'';       
$header  = 'MIME-Version: 1.0' . "\r\n";
$header .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
$header .= "From: ".$FromEmail."\r\nReply-To: ".$ToEmail."" . "\r\n";

mail($ToEmail, $subject, $message123, $header);
//---------------------------Email Coding start------------------------------------------------

$updatepassword="update ".$forgottable." set password='$randumpassword' where mobile_number='$frmobilenumber'";
mysqli_query($db_conn,$updatepassword);

$selectcountrows="select count(*) as numfrpass from forgotpassword where mobilenumber='$frmobilenumber'";
$fetchcountrows=mysqli_query($db_conn,$selectcountrows);
$resultcountrows=mysqli_fetch_array($fetchcountrows);
if($resultcountrows['numfrpass']==0)
{
	$insertfrpass="insert into forgotpassword (usertype,mobilenumber,reset) 
	values ('$frusertype','$frmobilenumber','0')";
	mysqli_query($db_conn,$insertfrpass);
}else{
	$updatefrpass="update forgotpassword set reset='0' where usertype='$frusertype' and mobilenumber='$frmobilenumber'";
	mysqli_query($db_conn,$updatefrpass);
}


$_SESSION['successMessage']="Password sent successfully, please check your Whatsapp.";
echo "<script>window.location='index.php?mailsentsuccess';</script>";

}else{
	
	echo "<script>window.location='forgot-password.php?nomailid';</script>";
}

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <!-- Title -->
    <title>Forgot Password : Femi9 - Pengalulagam</title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">

    
    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>
<body on contextmenu="return false;" onselectstart="return false;" ondragstart="return false;">
    <div class="app app-auth-sign-in align-content-stretch d-flex flex-wrap justify-content-end">
        <div class="app-auth-background">

        </div>
        <div class="app-auth-container">
            <div class="logo">
                <a href="#">Femi9 - Pengalulagam</a>
            </div>
            <p class="auth-description">Forgot Password  :  C&F</p>
			
			<?php if(isset($_REQUEST['nomailid'])){?><div class="alert alert-danger">
			Invalid Mobile Number</div>
			<?php }?>
			
			<?php if(isset($_REQUEST['mailsentsuccess'])){?><div class="alert alert-success">Password sent successfully, please check your Whatsapp.</div>
			<?php }?>
			<?php include("validate-scripts.php");?>
<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
		
		<?php function GeraHash($qtd){ $Caracteres = '123456789abcd'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(5);
?>
<input type="hidden" name="randumpassword" value="<?=$randum_number;?>">

            <div class="auth-credentials m-b-xxl">
                <label for="signInEmail" class="form-label">Enter Mobile Number</label>
                <input type="text" required="" class="form-control m-b-md" id="signInEmail" aria-describedby="signInEmail" name="frmobilenumber" autocomplete="off" onkeypress="restrictusername(event)" maxlength="15">
            </div>

            <div class="auth-submit">
                <input type="submit" class="btn btn-primary" name="subforgotbutton" value="Submit">
               <a href="index.php" class="auth-forgot-password float-end">Login</a>
            </div>
			</form>
					
            <div class="divider"></div>
        </div>
    </div>
    
    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
	
	<!------
	<script src="../../assets/js/drcvl131.js"></script>
<script>
$(document).ready(function(){
   $(document).bind("contextmenu",function(e){
      return false;
   });
});
</script>---->
</body>
</html>