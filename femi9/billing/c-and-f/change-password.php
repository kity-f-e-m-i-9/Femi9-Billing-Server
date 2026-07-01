<?php include("checksession.php");
$title="Change Password";
include("config.php");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">


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

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
           
          <?php include("app-header.php");
		  
		  
		  if ($_SERVER["REQUEST_METHOD"] == "POST") 
{
	$oldpassword=$_REQUEST['oldpassword'];
	$newpassword=$_REQUEST['newpassword'];
	$confirmpassword=$_REQUEST['confirmpassword'];
	
	//if password=12345678
	if($confirmpassword=="12345678")
	{
	echo "<script>window.location='change-password.php?oldpasswordnotsupported';</script>";	
	}else{
	
	$select_old_password="select count(*) as num from c_and_f where password='$oldpassword' and username='$log_username'";
		$exe_adminselect=mysqli_query($db_conn,$select_old_password);
		$fetch_old_password=mysqli_fetch_array($exe_adminselect);
		
		$confirm_old_pass=$fetch_old_password['num'];
		
		if($confirm_old_pass=='1')
		{
			if($newpassword==$confirmpassword)
			{
 
 $update_news_password="update c_and_f set password='$confirmpassword' where username='$log_username'";
 mysqli_query($db_conn,$update_news_password);
 
 $update_password1790="update forgotpassword set reset='1' where usertype='$Login_user_TYPEvl' and mobilenumber='".$_SESSION['LOGIN_USER']."'";
 mysqli_query($db_conn,$update_password1790);
 
				echo "<script>window.location='logout.php?action=reset';</script>";
			}
			else{
				echo "<script>window.location='change-password.php?ConfirmWrong';</script>";
			}
		}
		else{
			echo "<script>window.location='change-password.php?OldWrong';</script>";
		}
		
		
	}
	
	
}

?>
			
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
								
								<?php if(isset($_REQUEST['invalidcaptcha'])){?><div class="alert alert-danger">Invalid security code !</div>
			<?php }?>
			
			<?php if(isset($_REQUEST['oldpasswordnotsupported'])){?>
			<div class="alert alert-danger">The default password cannot be used</div>
			<?php }?>
			
			<?php if(isset($_REQUEST['OldWrong'])){?><div class="alert alert-danger">old password is wrong !</div>
			<?php }?>
			
			<?php if(isset($_REQUEST['ConfirmWrong'])){?><div class="alert alert-danger">confirm password is wrong !</div>
			<?php }?>
			
			<?php if(isset($_REQUEST['PasswordUpdated'])){?><div class="alert alert-success">Password changed successfully.</div>
			<?php }?>
			
			
                                     <h1>
									<table class="headertble">
									<tr>
									<td><?php echo $title;?></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <!----<div class="card-header">
                                        <h5 class="card-title">Basic Input</h5>
                                    </div>--->
                                    <div class="card-body">
                                       
<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">

                                        <div class="example-container">
                                            <div class="example-content">
											
												
				<label for="exampleInputEmail1" class="form-label">Old password</label>
                <input type="password" required="" name="oldpassword" class="form-control">
				
				<label for="exampleInputEmail1" class="form-label">New password</label>
                <input type="password" required="" name="newpassword" class="form-control">
				
				<label for="exampleInputEmail1" class="form-label">Confirm password</label>
                <input type="password" required="" name="confirmpassword" class="form-control">
												
												<br/>
												
												<button type="submit" name="update-pincode" class="btn btn-primary">Update</button>
												
                                            </div>
											
											
                                        </div>
										
										</form>
										
                                    </div>
                                </div>
                            </div>
								
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>