<?php include("checksession.php");
include("config.php");

$title="Available Wallet";
error_reporting(0);
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
	
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
		 <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
								
								<?php
if (isset($_SESSION['errorMessage'])) {
$errorMessage = $_SESSION['errorMessage'];
?>
                      <script>
                        Swal.fire({
                          icon: 'error',
                          title: 'Warning',
                          text: '<?php echo $errorMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['errorMessage']); } ?>

                                   
                                    <div class="card-body">
									
<?php if(isset($_REQUEST['search'])){
	
	$usertype=$_REQUEST['usertype'];
	$mobile=$_REQUEST['mobile'];
	
if($usertype=="candf"){$tablenameWE="c_and_f";}
elseif($usertype=="super_stockiest") {$tablenameWE="super_stockiest";}
elseif($usertype=="stockiest") {$tablenameWE="stockiest";}
elseif($usertype=="distributor"){$tablenameWE="distributor";}
else{$tablenameWE="super_distributor";}

$select_onbaord_user_records="select temp_id from ".$tablenameWE." where mobile_number='$mobile'";
$fetch_onbaord_user_records=mysqli_query($db_conn,$select_onbaord_user_records);
$result_onbaord_user_records=mysqli_fetch_array($fetch_onbaord_user_records);

if(mysqli_num_rows($fetch_onbaord_user_records)==0)
{
	$_SESSION['errorMessage']='Invalid User';
	echo "<script>window.location='available_wallet';</script>";
	exit;
}
else{
	
	echo "<script>window.location='available_wallet?user_tempID=".base64_encode($result_onbaord_user_records['temp_id'])."&&usertype=".base64_encode($usertype)."&&tablename=".base64_encode($tablenameWE)."';</script>";
	exit;
	
}

}


$se_usertype=base64_decode($_REQUEST['usertype']);
$se_userID=base64_decode($_REQUEST['user_tempID']);
$tablename_USER=base64_decode($_REQUEST['tablename']);
?>            


<form method="post" enctype="multipart/form-data" id="uploadForm">

                                        <div class="example-container">
                                            <div class="example-content">
										
            <select name="usertype" class="form-control" required>
			<?php if($se_usertype==NULL){?>
			<option value="" hidden>Select Usertype</option>
			<?php }else{?>
			<option value="<?=$se_usertype;?>" hidden><?=$se_usertype;?></option>
			<?php }?>
			<option value="super_stockiest">super_stockiest</option>
			<option value="stockiest">stockiest</option>
			<option value="super_distributor">super_distributor</option>
			<option value="distributor">distributor</option>
			</select>
			<br/>
			
      <input type="text" placeholder="Mobile Number" required name="mobile" maxlength="10" pattern="[1-9]{1}[0-9]{9}" class="form-control">
	  
	<br/>
	<button type="submit" name="search" class="btn btn-primary">
	<i class="material-icons">search</i>Search</button>
												
                                            </div>
                                        </div>
										</form>
										
										
				<?php if($_REQUEST['user_tempID']!=NULL){?>						
		<!------------------------------------------------------------------->
		<!------------------------------------------------------------------->
		
		
		<?php 
$select_USER_Records123="select * from ".$tablename_USER." where temp_id='$se_userID'";
$fetch_USER_Records123=mysqli_query($db_conn,$select_USER_Records123);
$result_USER_Records133=mysqli_fetch_array($fetch_USER_Records123);

$user_det_Name=$result_USER_Records133['name'];
$user_det_Mobile=$result_USER_Records133['mobile_number'];
?>

	<h1><?=strtoupper($user_det_Name);?>, <?=$user_det_Mobile;?></h1>
									
									
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="card todo-container">
                                    <div class="row">
									
									
									<?php
								//Total wallet amount
$select_wallet_amount_ST="select sum(commission_amount) from wallet_monthly_sls_report where refer_by_usertype='$se_usertype' and refer_by_userid='$se_userID'";
$fetch_wallet_amount_ST=mysqli_query($db_conn,$select_wallet_amount_ST);
$result_wallet_amount_ST=mysqli_fetch_array($fetch_wallet_amount_ST);
$Total_wallet_amount_ST=$result_wallet_amount_ST[0] ?? '0';

//Total Withdraw Amount
$select_wallet_withdraw_amount_ST="select sum(amount) from wallet_withdraw where user_type='$se_usertype' and user_id='$se_userID' and req_status='approved'";
$fetch_wallet_withdraw_amount_ST=mysqli_query($db_conn,$select_wallet_withdraw_amount_ST);
$result_wallet_withdraw_amount_ST=mysqli_fetch_array($fetch_wallet_withdraw_amount_ST);
$Total_withdraw_amount_ST=$result_wallet_withdraw_amount_ST[0] ?? '0';

$Average_available_walletAmount_ST=$Total_wallet_amount_ST-$Total_withdraw_amount_ST;
								?>
									
                                        <div class="col-xl-4 col-xxl-3">
                                            <div class="todo-menu" style="text-align:center;">

                                                <h5 class="todo-menu-title">Wallet - Available Amount</h5>
                                                <ul class="list-unstyled todo-status-filter">
                                                    
                                                    <li><a><i class="material-icons-outlined">wallet</i> <b>&#8377;<?=inr_format($Average_available_walletAmount_ST, 2);?></b></a></li>
                                                   
                                                </ul>
											
                                            </div>
                                        </div>
										
                      <div class="col-xl-4 col-xxl-9" style="border-right:1px solid #ddd !important;">
                              <div class="todo-list">
							  <h5 class="todo-menu-title">Last 10 Credit</h5>
                                    <ul class="list-unstyled">		
<?php 
$select_wallet_History1234="select * from wallet_monthly_sls_report where refer_by_usertype='$se_usertype' and refer_by_userid='$se_userID' order by from_date desc LIMIT 0,10";
$fetch_wallet_History1234=mysqli_query($db_conn,$select_wallet_History1234);
while($result_wallet_History1234=mysqli_fetch_array($fetch_wallet_History1234))
{

?>
												
                                                    <li class="todo-item">
													
													<?php if($result_wallet_History1234['commission_type']=="Refferral"){?>
                                                        <div class="todo-item-content">
                                                            <span class="todo-item-title">&#8377;<?=inr_format($result_wallet_History1234['commission_amount'], 2);?><span class="badge badge-style-light rounded-pill badge-success">Credit</span></span>
															<span>Refferral (<?=$result_wallet_History1234['commission_percentage'];?>%)</span><br/>
															<span class="todo-item-subtitle"><?=$result_wallet_History1234['month'];?>, <?=$result_wallet_History1234['year'];?></span>
                                                        </div>
													<?php 
													} 
													
													//Cashback
													else{
														?>
													<div class="todo-item-content">
                                                            <span class="todo-item-title">&#8377;<?=inr_format($result_wallet_History1234['commission_amount'], 2);?><span class="badge badge-style-light rounded-pill badge-success">Credit</span></span>
                                                            <span>Cashback (<?=$result_wallet_History1234['commission_percentage'];?>%)</span><br/>
															<span><?=$result_wallet_History1234['remarks'];?></span>
															<span class="todo-item-subtitle"><?=$result_wallet_History1234['month'];?>, <?=$result_wallet_History1234['year'];?></span>
                                                        </div>
													<?php }?>
                                                        <!-----<div class="todo-item-actions">
                                                            <a href="#" class="todo-item-delete"><i class="material-icons-outlined no-m">close</i></a>
                                                            <a href="#" class="todo-item-done"><i class="material-icons-outlined no-m">done</i></a>
                                                        </div>---->
                                                    </li>
													
<?php }?>
                                                </ul>
                                            </div>
                                        </div>
										
										
										<div class="col-xl-4 col-xxl-9">
                                            <div class="todo-list">
											<h5 class="todo-menu-title">Last 10 Debit</h5>
                                                <ul class="list-unstyled">
												
<?php 
$select_wallet_History1234="select * from wallet_withdraw where user_type='$se_usertype' and user_id='$se_userID' order by date desc LIMIT 0,10";
$fetch_wallet_History1234=mysqli_query($db_conn,$select_wallet_History1234);
while($result_wallet_History1234=mysqli_fetch_array($fetch_wallet_History1234))
{
?>
												 <li class="todo-item">
                                                        <div class="todo-item-content">
                                                            <span class="todo-item-title">&#8377;<?=inr_format($result_wallet_History1234['amount'], 2);?>

<?php if($result_wallet_History1234['req_status']=='pending'){?>
<span class="badge badge-style-light rounded-pill badge-danger">Pending</span>
<?php } else {?>
<span class="badge badge-style-light rounded-pill badge-primary">Debit</span>
<?php }?>															

															</span>
															
															<span class="todo-item-subtitle"><?=date("d/m/Y",strtotime($result_wallet_History1234['date']));?>, <?=date("g:i A",strtotime($result_wallet_History1234['time']));?></span>
                                                        </div>
                                                    </li>
<?php }?>
												</ul>
												</div>
												</div>
												
												
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
		
		<!------------------------------------------------------------------->
		<!------------------------------------------------------------------->
				<?php }?>
										
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