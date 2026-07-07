<?php session_start(); error_reporting(0); include("include/db-connect.php");?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <!-- Title -->
    <title>Login : Femi9 - Happy day Everyday</title>

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
	
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Femi9 C and F">
    <meta name="theme-color" content="#f5b400">
    <link rel="apple-touch-icon" href="../../assets/images/pwa-icon-apple-touch.png">
    <script>
    if ("serviceWorker" in navigator) {
        window.addEventListener("load", function () {
            navigator.serviceWorker.register("service-worker.js");
        });
    }
    </script>
</head>
<body on contextmenu="return false;" onselectstart="return false;" ondragstart="return false;">
    <div class="app app-auth-sign-in align-content-stretch d-flex flex-wrap justify-content-end">
        <div class="app-auth-background">

        </div>
        <div class="app-auth-container">
            <div class="logo">
                <a href="#">Femi9 - Happy day Everyday</h1></a>
            </div>
            <p class="auth-description">C&F Login</p>
			
			<?php
// Check for error message in session
if (isset($_SESSION['errorMessage'])) {
$errorMessage = $_SESSION['errorMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'error',
                          title: 'Login Failed',
                          text: '<?php echo $errorMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['errorMessage']); } ?>


<?php
// Check for error message in session
if (isset($_SESSION['successMessage'])) {
$successMessage = $_SESSION['successMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $successMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['successMessage']); } ?>


			<?php /* if(isset($_REQUEST['invalidcaptcha'])){?><div class="alert alert-danger">Invalid security code !</div>
			<?php }?>
			
			<?php if(isset($_REQUEST['invaliduser'])){?><div class="alert alert-danger">
			Invalid Mobile Number (or) Password</div>
			<?php }?>
			
			<?php if(isset($_REQUEST['planexpired'])){?><div class="alert alert-danger">Invalid Details or Plan Expired</div>
			<?php }?>
			
			<?php if(isset($_REQUEST['outsuc'])){?><div class="alert alert-success">Logout success !</div>
			<?php }?>
			
			<?php if(isset($_REQUEST['sessionexpiry'])){?><div class="alert alert-warning">Session expired !</div><?php } */ ?>
<?php include("validate-scripts.php");?>
<form method="post" enctype="multipart/form-data" action="CheckLogin.php">
            <div class="auth-credentials m-b-xxl">
			
			
			
			<style>
        .password-container {
            position: relative;
            display: inline-block;
        }
        .eye-icon {
            position: absolute;
            right: 10px;
            top: 67%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
	
	
				<div class="password-container">
				<label class="form-label">Mobile Number</label>
                <input type="text" required="" class="form-control m-b-md" id="signInEmail" aria-describedby="signInEmail" name="signInEmail" autocomplete="off" maxlength="15" onkeypress="restrictusername(event)">
				</div>

	<div class="password-container">
                <label class="form-label">Password</label>
                <input type="password" id="password" required="" onkeypress="restrictpassword(event)" autocomplete="off" class="form-control" name="signInPassword">
				
				 <span class="eye-icon" id="togglePassword">
            <img src="../../assets/eye.png" style="width:15px;">
        </span>
				</div>
				
				
<script src="../../assets/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#togglePassword').click(function() {
                const passwordField = $('#password');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);
            });
        });
    </script>
    
	
				
				<?php
$_SESSION['randum_number']="1867";
				/*
				function GeraHash($qtd){ 
$Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); 
$QuantidadeCaracteres--; 
$Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ 
$Posicao = rand(0,$QuantidadeCaracteres); 
$Hash .= substr($Caracteres,$Posicao,1); 
}return $Hash;} 
$randum_number=GeraHash(4);
$_SESSION['randum_number']=$randum_number;
?>

				<label for="signInCaptcha" class="form-label">Security Code (<?php echo $randum_number; ?>)</label>
                <input type="text" required="" name="signInCaptcha" class="form-control" id="signInCaptcha" aria-describedby="signInCaptcha" autocomplete="off" oncut="return false;" oncopy="return false;" onpaste="return false;">
				<?php */ ?>
				<input type="hidden" name="signInCaptcha" value="1867"/>
            </div>

            <div class="auth-submit">
                <input type="submit" class="btn btn-primary" name="login" value="Sign In">
               <a href="forgot-password.php" class="auth-forgot-password float-end">Forgot password?</a>
            </div>
			</form>
			
            <div class="divider"></div>
			
            <button type="button" onclick="javascript:window.location='https://femi9billing.com/femi9/';" class="btn btn-success"><i class="material-icons">home</i>Go Home Page</button>   

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