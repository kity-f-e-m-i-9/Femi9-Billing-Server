<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requireAdminOnly();
$title="Whatsapp Settings";

$select_wp_settings="select * from admin_whatsapp_configuration";
$fetch_wp_settings=mysqli_query($db_conn,$select_wp_settings);
$result_wp_settings=mysqli_fetch_array($fetch_wp_settings);

if ($_SERVER["REQUEST_METHOD"] == "POST") 
{
	$api_key=$_REQUEST['api_key'];
	$wa_id=$_REQUEST['wa_id'];
	$url=$_REQUEST['url'];
 
 $update_news_password="update admin_whatsapp_configuration set api_key='$api_key',wa_id='$wa_id',url='$url'";
 mysqli_query($db_conn,$update_news_password);
 
 echo "<script>window.location='wp-settings?UpdatedSuccess';</script>";
			
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
           
          <?php include("app-header.php");?>
			
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
			
			<?php if(isset($_REQUEST['PasswordUpdated'])){?><div class="alert alert-success">Changes saved successfully.</div>
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
                                    
									
                                    <div class="card-body">
                                       
<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">

                                        <div class="example-container">
                                            <div class="example-content">
											
												
				<label class="form-label">API KEY</label>
                <input type="text" required value="<?=$result_wp_settings['api_key'];?>" name="api_key" class="form-control">
				
				<label class="form-label">WA ID</label>
                <input type="text" required value="<?=$result_wp_settings['wa_id'];?>" name="wa_id" class="form-control">
				
				<label class="form-label">URL</label>
                <input type="text" required value="<?=$result_wp_settings['url'];?>" name="url" class="form-control">
												
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