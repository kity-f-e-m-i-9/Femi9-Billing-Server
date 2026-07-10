<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
$title="Website Order Commission Setup";
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] == "POST") 
{
$updateid = implode("#",$_REQUEST['updateid']);
$amount = implode("#",$_REQUEST['amount']);
	
$updateid_ex = explode ("#",$updateid); 
$amount_ex = explode ("#",$amount); 

$number = count($updateid_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $updateid_value = $updateid_ex[$i]; 
     $amount_value = $amount_ex[$i]; 
	 
			$update_news_password="update admin_website_coupon_commission set amount='$amount_value' where id='$updateid_value'";
			mysqli_query($db_conn,$update_news_password);
} 
 
 echo "<script>window.location='web-commission?UpdatedSuccess';</script>";
 exit;	
 
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
											
					<?php 
$select_wp_settings="select * from admin_website_coupon_commission";
$fetch_wp_settings=mysqli_query($db_conn,$select_wp_settings);
while($result_wp_settings=mysqli_fetch_array($fetch_wp_settings))
{
?>
<input type="hidden" name="updateid[]" value="<?=$result_wp_settings['id'];?>">
<label class="form-label"><?=$result_wp_settings['usertype'];?></label>
<input type="text" required value="<?=$result_wp_settings['amount'];?>" name="amount[]" class="form-control"><br/>
<?php }?>
												
												
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