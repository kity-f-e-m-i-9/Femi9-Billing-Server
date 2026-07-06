<?php 
include("checksession.php");
include("config.php"); 

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// ========================================
// CHECK IF USER MUST CHANGE PASSWORD
// ========================================
$userMobile = $result_LoGuserDtails['username'];
$userType = 'company'; // Change based on user type

// Check if user has a pending password reset
$checkResetStmt = mysqli_prepare($db_conn, 
    "SELECT id, reset_at FROM forgotpassword 
     WHERE usertype = ? AND mobilenumber = ? AND must_change_password = 1 
     ORDER BY reset_at DESC LIMIT 1"
);
mysqli_stmt_bind_param($checkResetStmt, "ss", $userType, $userMobile);
mysqli_stmt_execute($checkResetStmt);
$resetResult = mysqli_stmt_get_result($checkResetStmt);
$resetData = mysqli_fetch_assoc($resetResult);
mysqli_stmt_close($checkResetStmt);

// If user has pending password reset, force them to change password
if ($resetData) {
    echo "<script>
        alert('For security reasons, you must change your password before continuing.');
        window.location='change-password.php?forced=1';
    </script>";
    exit;
}
// ========================================

header('Location: mis-report.php');
exit;

date_default_timezone_set("Asia/Kolkata");
$today_date=date("Y-m-d");

/*
run first time only
_______________________
1. update_gst_type.php
2. update_gst_type2.php
*/


//---------------------------------------------------------------
//ASSIGNED SS-ID TO DISTRICT TABLE (if updated error records only)
//----------------------------------------------------------------
$select_ss_Details="select * from super_stockiest order by id asc";
$fetch_ss_Details=mysqli_query($db_conn,$select_ss_Details);
while($Result_ss_Details=mysqli_fetch_array($fetch_ss_Details))
{
	$SS_TempID=$Result_ss_Details['temp_id'];
	
	$SS_state_id=$Result_ss_Details['state_id'];
	$SS_district_id=$Result_ss_Details['district_id'];
	
$select_Count_Assigned="select * from district where assigned_SSID='$SS_TempID'";
$fetch_Count_Assigned=mysqli_query($db_conn,$select_Count_Assigned);
$result_Count_Assigned=mysqli_num_rows($fetch_Count_Assigned);
if($result_Count_Assigned==0)
{
$Update_assigned_ID="update district set assigned_SSID='$SS_TempID' where state_id='$SS_state_id' and id='$SS_district_id'";
mysqli_query($db_conn,$Update_assigned_ID);
}
	
}
//------------------------------------------------------------------
//------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Responsive Admin Dashboard Template">
    <meta name="keywords" content="admin,dashboard">
    <meta name="author" content="stacks">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    
    <!-- Title -->
    <title>Dashboard : <?php echo $business_name;?></title>

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
                    <div class="container">
                        <!-----<div class="row">
                            <div class="col">
                                <div class="page-description">
                                  <h1>Dashboard</h1>	  
                                </div>
                            </div>
                        </div>---->
						
						<div class="row">
                            <div class="col-xl-12">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
								<h1>Dashboard</h1>	
						<h2>Welcome to Femi9 - Happy day Everyday </h2>
						
						
						<?php if($resultusertypeGET['dash']==1){?>
						
						<?php

if($LoginusertypeGET=="finance"){
	$pendinshowlink="stock_request_pending_accounts";
	
	//FINANCE
$sltsumqty_request="select count(*) as numcountrequ from stock_request where status='pending' and tousertype='$Login_user_TYPEvl' and touserid='$Login_user_IDvl' and verified='0'";


}else{
	$pendinshowlink="stock_request_pending";
	
	//ADMIN	
	$sltsumqty_request="select count(*) as numcountrequ from stock_request where status='pending' and tousertype='$Login_user_TYPEvl' and touserid='$Login_user_IDvl' and verified='1'";
	
}

						$fetch_sumqty_request=mysqli_query($db_conn,$sltsumqty_request);
						$result_sumqty_request=mysqli_fetch_array($fetch_sumqty_request);
						$toalqyt=$result_sumqty_request['numcountrequ'];

						$select_count_request="select * from stock_request where status='pending' and tousertype='$Login_user_TYPEvl' and touserid='$Login_user_IDvl'";
						$fetch_count_request=mysqli_query($db_conn,$select_count_request);
						$result_count_request=mysqli_fetch_array($fetch_count_request);
						//
						$stockreqid=$result_count_request['reqid'];
						
						if($stockreqid!=NULL){
						?>
						
						  <div class="card widget widget-stats" style="background:#5DADE2;">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">task_alt</i>
                                            </div>
											<a href="<?=$pendinshowlink;?>" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title" style="color:#fff;">Stock Request Pending</span>
                                                <span class="widget-stats-amount"><?=$toalqyt[0];?></span>
                                            </div>
											</a>
                                        </div>
                                        <!----<div class="widget-stats-chart">
                                            <div id="widget-stats-chart1"></div>
                                        </div>--->
                                    </div>
                                </div>
								
						<?php }?>
						
						<?php }?>
						
						</div>
						</div>
						</div>
						</div>
						
						<?php if($LoginusertypeGET=="admin"){?>
						<!--------------------------------------------------------->
						<!-------------APPROVAL PENDING USERS---------------------->
						<!--------------------------------------------------------->
						<h3 style="color:red;"><b>APPROVAL PENDING - USERS</b></h3>
						<div class="row">
						
						<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						$select_PendingSS13="select * from super_stockiest where account_status='pending'";
						$fetch_PendingSS13=mysqli_query($db_conn,$select_PendingSS13);
						$result_PendingSS13=mysqli_num_rows($fetch_PendingSS13);
						?>
						<a href="pending-super-stockist" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Super Stockist</span>
                                                <span class="widget-stats-amount"><?=$result_PendingSS13;?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
						
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						$select_countSTOCK1223="select count(*) as numSTOCKpend from stockiest where account_status='pending'";
						$fetch_countSTOCK1223=mysqli_query($db_conn,$select_countSTOCK1223);
						$result_countSTOCK1223=mysqli_fetch_array($fetch_countSTOCK1223);
						?>
						<a href="pending-stockist" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Stockist</span>
                                                <span class="widget-stats-amount"><?=$result_countSTOCK1223['numSTOCKpend'];?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						$select_countDIST1223="select count(*) as numDIST23 from distributor where account_status='pending'";
						$fetch_countDIST1223=mysqli_query($db_conn,$select_countDIST1223);
						$result_countDIST1223=mysqli_fetch_array($fetch_countDIST1223);
						?>
						<a href="pending-distributor" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Distributors</span>
                                                <span class="widget-stats-amount"><?=$result_countDIST1223['numDIST23'];?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						$select_count_super_distributors="select * from super_distributor where account_status='pending'";
						$fetch_count_super_distributors=mysqli_query($db_conn,$select_count_super_distributors);
						?>
						<a href="pending_super_distributor" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Super Distributors</span>
                  <span class="widget-stats-amount"><?=mysqli_num_rows($fetch_count_super_distributors);?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						
						<?php } ?>
						
						<!------------------------------------------------------->
						<!------------------------------------------------------->
						<!------------------------------------------------------->
						<?php if($resultusertypeGET['dash']==1){?>
						<h3><b>USERS COUNT</b></h3>
						<div class="row">
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-success">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						//count
						//super stockist
						$select_countSS="select count(*) as numSS from super_stockiest";
						$fetch_countSS=mysqli_query($db_conn,$select_countSS);
						$result_countSS=mysqli_fetch_array($fetch_countSS);
						?>
						<a href="overview_super_stockist" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">S-Stockist</span>
                                                <span class="widget-stats-amount"><?=$result_countSS['numSS'];?></span>
                                                <!----<span class="widget-stats-info">471 Orders Total</span>--->
                                            </div>
											</a>
                                            <!----<div class="widget-stats-indicator widget-stats-indicator-negative align-self-start">
                                                <i class="material-icons">keyboard_arrow_down</i> 4%
                                            </div>--->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						//count
						//stockist
						$select_countSTOCK="select count(*) as numSTOCK from stockiest";
						$fetch_countSTOCK=mysqli_query($db_conn,$select_countSTOCK);
						$result_countSTOCK=mysqli_fetch_array($fetch_countSTOCK);
						?>
						<a href="overview_stockist" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Stockist</span>
                                                <span class="widget-stats-amount"><?=$result_countSTOCK['numSTOCK'];?></span>
                                            </div>
											</a>
                                            <!----<div class="widget-stats-indicator widget-stats-indicator-positive align-self-start">
                                                <i class="material-icons">keyboard_arrow_up</i> 12%
                                            </div>---->
                                        </div>
                                    </div>
                                </div>
                            </div>
							
                            <div class="col-xl-3"><!---Distributor Start ***--->
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
						<?php
						//count Distributors
						$select_countDIST="select count(*) as numDIST from distributor";
						$fetch_countDIST=mysqli_query($db_conn,$select_countDIST);
						$result_countDIST=mysqli_fetch_array($fetch_countDIST);
						?>
						<a href="overview_distributor" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Distributors</span>
                       <span class="widget-stats-amount"><?=$result_countDIST['numDIST'];?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div><!---Distributor End ***--->
							
							
							<div class="col-xl-3"><!---Super Distributor Start ***--->
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
						<?php
						//count S_Distributors
						$select_countDIST_super="select count(*) as numDIST from super_distributor";
						$fetch_countDIST_super=mysqli_query($db_conn,$select_countDIST_super);
						$result_countDIST_super=mysqli_fetch_array($fetch_countDIST_super);
						?>
						<a href="overview_super_distributor" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">S_Distributors</span>
                       <span class="widget-stats-amount"><?=$result_countDIST_super['numDIST'];?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div><!---Super Distributor End ***--->
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-danger">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						//count
						//Shops
						$select_countSHOP="select count(*) as numSHOP from shop";
						$fetch_countSHOP=mysqli_query($db_conn,$select_countSHOP);
						$result_countSHOP=mysqli_fetch_array($fetch_countSHOP);
						?>
                                            <a href="overview_shop" style="text-decoration:none;">
											<div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Shops</span>
                                                <span class="widget-stats-amount"><?=$result_countSHOP['numSHOP'];?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


<h3><b>SALES (TODAY)</b></h3>
<!--------------------------------------------------------------------------->
<!--------------------------------------------------------------------------->

<div class="row">
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-success">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
						<?php
						//super stockist
						$select_countSS_SLS="select sum(total) from user_invoice where date='$today_date' and from_user_type='super_stockiest'";
						$fetch_countSS_SLS=mysqli_query($db_conn,$select_countSS_SLS);
						$result_countSS_SLS=mysqli_fetch_array($fetch_countSS_SLS);
						if($result_countSS_SLS[0]!=NULL)
						{$SHOW_SLS_SS=inr_format($result_countSS_SLS[0], 2);}
						else{$SHOW_SLS_SS="0.00";}
						?>
						<a href="#" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">S-Stockist</span>
                                                <span class="widget-stats-amount"><?=$SHOW_SLS_SS;?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						//stockist
						$select_countST_SLS="select sum(total) from user_invoice where date='$today_date' and from_user_type='stockiest'";
						$fetch_countST_SLS=mysqli_query($db_conn,$select_countST_SLS);
						$result_countST_SLS=mysqli_fetch_array($fetch_countST_SLS);
						if($result_countST_SLS[0]!=NULL)
						{$SHOW_SLS_ST=inr_format($result_countST_SLS[0], 2);}
						else{$SHOW_SLS_ST="0.00";}
						?>
						<a href="#" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Stockist</span>
                                                <span class="widget-stats-amount"><?=$SHOW_SLS_ST;?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
                            <div class="col-xl-3"><!---Sales (Distributors)---->
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						//Distributors
						$select_countDT_SLS="select sum(total) from user_invoice where date='$today_date' and from_user_type='distributor'";
						$fetch_countDT_SLS=mysqli_query($db_conn,$select_countDT_SLS);
						$result_countDT_SLS=mysqli_fetch_array($fetch_countDT_SLS);
						if($result_countDT_SLS[0]!=NULL)
						{$SHOW_SLS_DT=inr_format($result_countDT_SLS[0], 2);}
						else{$SHOW_SLS_DT="0.00";}
						?>
						<a href="#" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Distributors</span>
                                                <span class="widget-stats-amount"><?=$SHOW_SLS_DT;?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div><!---end *** Sales (Distributors)---->
							
							
							<div class="col-xl-3"><!---Sales (Super Distributors)---->
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											<?php
						//Super Distributors
						$select_countDT_SLS12="select sum(total) from user_invoice where date='$today_date' and from_user_type='super_distributor'";
						$fetch_countDT_SLS12=mysqli_query($db_conn,$select_countDT_SLS12);
						$result_countDT_SLS12=mysqli_fetch_array($fetch_countDT_SLS12);
						if($result_countDT_SLS12[0]!=NULL)
						{$SHOW_SLS_DT12=inr_format($result_countDT_SLS12[0], 2);}
						else{$SHOW_SLS_DT12="0.00";}
						?>
						<a href="#" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">S_Distributors</span>
                                                <span class="widget-stats-amount"><?=$SHOW_SLS_DT12;?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div><!---end *** Sales (Super Distributors)---->
							
							
                        </div>
						

<!------------------------------------------------------------------------->

						<?php }?>

						<?php /*?>
                        <div class="row">
                            <div class="col-xl-4">
                                <div class="card widget widget-list">
                                    <div class="card-header">
                                        <h5 class="card-title">Active Tasks<span class="badge badge-success badge-style-light">14 completed</span></h5>
                                    </div>
                                    <div class="card-body">
                                        <span class="text-muted m-b-xs d-block">showing 5 out of 23 active tasks.</span>
                                        <ul class="widget-list-content list-unstyled">
                                            <li class="widget-list-item widget-list-item-green">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">article</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Dashboard UI optimisations
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Oskar Hudson
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-blue">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">verified_user</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Mailbox cleanup
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Woodrow Hawkins
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-purple">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">watch_later</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Header scroll bugfix
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Sky Meyers
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-yellow">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">extension</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Localization for file manager
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Oskar Hudson
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-red">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">invert_colors</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        New E-commerce UX/UI design
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Oskar Hudson
                                                    </span>
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="card widget widget-list">
                                    <div class="card-header">
                                        <h5 class="card-title">Todo<span class="badge badge-success badge-style-light">14 completed</span></h5>
                                    </div>
                                    <div class="card-body">
                                        <span class="text-muted m-b-xs d-block">showing 5 out of 23 active tasks.</span>
                                        <ul class="widget-list-content list-unstyled">
                                            <li class="widget-list-item widget-list-item-green">
                                                <span class="widget-list-item-check">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" value="">
                                                    </div>
                                                </span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Dashboard UI optimisations
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Oskar Hudson
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-blue">
                                                <span class="widget-list-item-check">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" value="" checked>
                                                    </div>
                                                </span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Mailbox cleanup
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Woodrow Hawkins
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-purple">
                                                <span class="widget-list-item-check">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" value="" checked>
                                                    </div>
                                                </span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Header scroll bugfix
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Sky Meyers
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-yellow">
                                                <span class="widget-list-item-check">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" value="">
                                                    </div>
                                                </span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Localization for file manager
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Oskar Hudson
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-red">
                                                <span class="widget-list-item-check">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" value="" checked>
                                                    </div>
                                                </span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        New E-commerce UX/UI design
                                                    </a>
                                                    <span class="widget-list-item-description-subtitle">
                                                        Oskar Hudson
                                                    </span>
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="card widget widget-payment-request">
                                    <div class="card-header">
                                        <h5 class="card-title">Payment Request<span class="badge badge-warning badge-style-light">8 June</span></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="widget-payment-request-container">
                                            <div class="widget-payment-request-author">
                                                <div class="avatar m-r-sm">
                                                    <img src="../../assets/images/avatars/avatar.png" alt="">
                                                </div>
                                                <div class="widget-payment-request-author-info">
                                                    <span class="widget-payment-request-author-name">Caio Yousuke</span>
                                                    <span class="widget-payment-request-author-about">Customer Journey Expert</span>
                                                </div>
                                            </div>
                                            <div class="widget-payment-request-product">
                                                <div class="widget-payment-request-product-image m-r-sm">
                                                    <img src="../../assets/images/other/facebook_logo.png" class="mt-auto" alt="">
                                                </div>
                                                <div class="widget-payment-request-product-info d-flex">
                                                    <div class="widget-payment-request-product-info-content">
                                                        <span class="widget-payment-request-product-name">Google</span>
                                                        <span class="widget-payment-request-product-about">Youtube Advertisments</span>
														
														
                                                    </div>
                                                    <span class="widget-payment-request-product-price">$2,399.99</span>
                                                </div>
                                            </div>
                                            <div class="widget-payment-request-info m-t-md">
                                                <div class="widget-payment-request-info-item">
                                                    <span class="widget-payment-request-info-title d-block">
                                                        Description
                                                    </span>
                                                    <span class="text-muted d-block">Advertisement for envato items</span>
                                                </div>
                                                <div class="widget-payment-request-info-item">
                                                    <span class="widget-payment-request-info-title d-block">
                                                        Due Date
                                                    </span>
                                                    <span class="text-muted d-block">14 June, 2021</span>
                                                </div>
                                            </div>
                                            <div class="widget-payment-request-actions m-t-lg d-flex">
                                                <a href="#" class="btn btn-light flex-grow-1 m-r-xxs">Reject</a>
                                                <a href="#" class="btn btn-primary flex-grow-1 m-l-xxs">Approve</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						<?php */?>
						
						
						<?php /* ?>
                        <div class="row">
                            <div class="col-xl-4">
                                <div class="card widget widget-list">
                                    <div class="card-header">
                                        <h5 class="card-title">In Progress Tasks<span class="badge badge-success badge-style-light">37% total</span></h5>
                                    </div>
                                    <div class="card-body">
                                        <span class="text-muted m-b-xs d-block">showing 5 out of 9 in progress tasks.</span>
                                        <ul class="widget-list-content list-unstyled">
                                            <li class="widget-list-item widget-list-item-green">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">article</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Dashboard UI optimisations
                                                    </a>
                                                    <span class="widget-list-item-description-progress">
                                                        <div class="progress">
                                                            <div class="progress-bar" role="progressbar" style="width: 45%;" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-blue">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">verified_user</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Mailbox cleanup
                                                    </a>
                                                    <span class="widget-list-item-description-progress">
                                                        <div class="progress">
                                                            <div class="progress-bar" role="progressbar" style="width: 57%;" aria-valuenow="57" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-purple">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">watch_later</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Header scroll bugfix
                                                    </a>
                                                    <span class="widget-list-item-description-progress">
                                                        <div class="progress">
                                                            <div class="progress-bar" role="progressbar" style="width: 14%;" aria-valuenow="14" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-yellow">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">extension</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        Localization for file manager
                                                    </a>
                                                    <span class="widget-list-item-description-progress">
                                                        <div class="progress">
                                                            <div class="progress-bar" role="progressbar" style="width: 79%;" aria-valuenow="79" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </span>
                                                </span>
                                            </li>
                                            <li class="widget-list-item widget-list-item-red">
                                                <span class="widget-list-item-icon"><i class="material-icons-outlined">invert_colors</i></span>
                                                <span class="widget-list-item-description">
                                                    <a href="#" class="widget-list-item-description-title">
                                                        New E-commerce UX/UI design
                                                    </a>
                                                    <span class="widget-list-item-description-progress">
                                                        <div class="progress">
                                                            <div class="progress-bar" role="progressbar" style="width: 25%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </span>
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="card widget widget-popular-product">
                                    <div class="card-body">
                                        <div class="widget-popular-product-container">
                                            <div class="widget-popular-product-image">
                                                <img src="../../assets/images/widgets/popular-product.jpeg" alt="">
                                            </div>
                                            <div class="widget-popular-product-tags">
                                                <span class="badge rounded-pill badge-secondary badge-style-light">Science</span>
                                                <span class="badge rounded-pill badge-success badge-style-light">Lifestyle</span>
                                                <span class="badge rounded-pill badge-danger badge-style-light">People</span>
                                            </div>
                                            <div class="widget-popular-product-content">
                                                <a href="#" class="widget-popular-product-title">Banana Donut</a>
                                                <p class="widget-popular-product-text m-b-md">Quisque congue risus sit amet pellentesque fermentum. Etiam nibh erat, convallis ac dui nec, imperdiet dignissim nulla. Ut tincidunt tellus sit amet elit viverra porttitor. Mauris at tellus a nisl accumsan egestas suscipit..</p>
                                                <span class="widget-popular-product-rating">
                                                    <i class="material-icons">star</i>
                                                    <i class="material-icons">star</i>
                                                    <i class="material-icons">star</i>
                                                    <i class="material-icons">star</i>
                                                    <i class="material-icons">star_half</i>
                                                    <span class="widget-popular-product-rating-num">4.4</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="card widget widget-bank-card" style="height: 220px;">
                                    <div class="card-body">
                                        <div class="widget-bank-card-container widget-bank-card-visa d-flex flex-column">
                                            <div class="widget-bank-card-logo"></div>
                                            <span class="widget-bank-card-balance-title">
                                                BALANCE
                                            </span>
                                            <span class="widget-bank-card-balance">
                                                $5,688
                                            </span>
                                            <span class="widget-bank-card-number mt-auto">
                                                **** **** **** 4408
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card widget widget-bank-card" style="height: 220px;">
                                    <div class="card-body">
                                        <div class="widget-bank-card-container widget-bank-card-mastercard d-flex flex-column">
                                            <div class="widget-bank-card-logo"></div>
                                            <span class="widget-bank-card-balance-title">
                                                BALANCE
                                            </span>
                                            <span class="widget-bank-card-balance">
                                                $12,079
                                            </span>
                                            <span class="widget-bank-card-number mt-auto">
                                                **** **** **** 0999
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						<?php */?>
						
						<?php /*?>
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="card widget widget-stats-large">
                                    <div class="row">
                                        <div class="col-xl-8">
                                            <div class="widget-stats-large-chart-container">
                                                <div class="card-header">
                                                    <h5 class="card-title">Earnings<span class="badge badge-light badge-style-light">Last Year</span></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div id="apex-earnings"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-4">
                                            <div class="widget-stats-large-info-container">
                                                <div class="card-header">
                                                    <h5 class="card-title">Report<span class="badge badge-info badge-style-light">Updated 5 min ago</span></h5>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-description">Duis fringilla eget velit sit amet lobortis. Donec rutrum, arcu auctor varius cursus. mi nulla dapibus justo, at volutpat libero</p>
                                                    <ul class="list-group list-group-flush">
                                                        <li class="list-group-item">Neptune - v1.0<span class="float-end text-success">14%<i class="material-icons align-middle">keyboard_arrow_up</i></span></li>
														
                                                        <li class="list-group-item">Space - v1.2<span class="float-end text-danger">7%<i class="material-icons align-middle">keyboard_arrow_down</i></span></li>
                                                        <li class="list-group-item">Lime - v1.0.3<span class="float-end text-success">21%<i class="material-icons align-middle">keyboard_arrow_up</i></span></li>
														
                                                        <li class="list-group-item">Circl - v2.3<span class="float-end text-success">17%<i class="material-icons align-middle">keyboard_arrow_up</i></span></li>
														
														
                                                        <li class="list-group-item">Connect - v1.7<span class="float-end text-danger">3%<i class="material-icons align-middle">keyboard_arrow_down</i></span></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						<?php */?>
						
						<?php /*?>
                        <div class="row">
                            <div class="col-xl-4">
                                <div class="card">
                                    <img src="../../assets/images/widgets/blog5.jpeg" class="card-img-top" alt="...">
                                    <div class="card-body">
                                      <h5 class="card-title">The M1 Macbook Pro is Blazing Fast</h5>
                                      <p class="card-text">Pellentesque habitant morbi tristique senectus et. Curabitur molestie in tellus sed porttitor. Etiam eget erat erat. Nullam auctor a justo lacinia varius.</p>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                      <li class="list-group-item">Small chip. Giant leap.</li>
                                      <li class="list-group-item">Creates beauty like a beast.</li>
                                      <li class="list-group-item">Make connections. Faster than ever.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">task_alt</i>
                                            </div>
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Tasks Completed</span>
                                                <span class="widget-stats-amount">1,871</span>
                                            </div>
                                            <div class="widget-stats-indicator align-self-start">
                                                <i class="material-icons">keyboard_arrow_down</i> 18%
                                            </div>
                                        </div>
                                        <div class="widget-stats-chart">
                                            <div id="widget-stats-chart1"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-danger">
                                                <i class="material-icons-outlined">star_border_purple500</i>
                                            </div>
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Engagement</span>
                                                <span class="widget-stats-amount">45,661</span>
                                            </div>
                                            <div class="widget-stats-indicator align-self-start">
                                                <i class="material-icons">keyboard_arrow_up</i> 25%
                                            </div>
                                        </div>
                                        <div class="widget-stats-chart">
                                            <div id="widget-stats-chart2"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">account_balance_wallet</i>
                                            </div>
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Balance</span>
                                                <span class="widget-stats-amount">$218,655</span>
                                            </div>
                                            <div class="widget-stats-indicator align-self-start">
                                                <i class="material-icons">keyboard_arrow_down</i> 9%
                                            </div>
                                        </div>
                                        <div class="widget-stats-chart">
                                            <div id="widget-stats-chart3"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="card widget">
                                    <div class="card-header">
                                        <h5 class="card-title">Share this Link</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted d-block">This link will be opened in a new window</p>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-solid-bordered" value="https://themeforest.net/user/stacks/portfolio" aria-label="https://themeforest.net/user/stacks/portfolio" aria-describedby="share-link1">
                                            <button class="btn btn-primary" type="button" id="share-link1"><i class="material-icons no-m fs-5">content_copy</i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card widget widget-info">
                                    <div class="card-body">
                                        <div class="widget-info-container">
                                            <div class="widget-info-image" style="background: url('../../assets/images/widgets/security.svg')"></div>
                                            <h5 class="widget-info-title">Advanced Security</h5>
                                            <p class="widget-info-text m-t-n-xs">Nunc cursus tempor sapien, et mattis libero dapibus ut. Ut a ante sit amet arcu imperdiet accumsan.</p>
                                            <a href="#" class="btn btn-primary widget-info-action">Upgrade Now</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						<?php */?>
						
						
						<?php /*?>
                        <div class="row">
                            <div class="col-xl-8">
                                <div class="card widget widget-popular-blog">
                                    <div class="card-body">
                                        <div class="widget-popular-blog-container">
                                            <div class="widget-popular-blog-image">
                                                <img src="../../assets/images/widgets/product2.jpeg" alt=""> 
                                            </div>
                                            <div class="widget-popular-blog-content ps-4">
                                                <span class="widget-popular-blog-title">
                                                    Quisque congue risus sit amet pellentesque fermentum
                                                </span>
                                                <span class="widget-popular-blog-text">
                                                    Morbi blandit, mi at lacinia ornare, turpis justo viverra risus, at tristique tortor massa ut arcu. Suspendisse potenti. Suspendisse cursus aliquam dictum. Curabitur nec fringilla orci. Vivamus ut viverra elit. Pellentesque id interdum odio. Fusce finibus maximus egestas.
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <span class="widget-popular-blog-date">
                                            Date: 6:38 PM
                                        </span>
                                        <a href="#" class="btn btn-primary float-end">Read More</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="card widget widget-connection-request">
                                    <div class="card-header">
                                        <h5 class="card-title">Connection Request<span class="badge badge-secondary badge-style-light">17 min ago</span></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="widget-connection-request-container d-flex">
                                            <div class="widget-connection-request-avatar">
                                                <div class="avatar avatar-xl m-r-xs">
                                                    <img src="../../assets/images/avatars/avatar.png" alt="">
                                                </div>
                                            </div>
                                            <div class="widget-connection-request-info flex-grow-1">
                                                <span class="widget-connection-request-info-name">
                                                    Woodrow Hawkins
                                                </span>
                                                <span class="widget-connection-request-info-count">
                                                    45 mutual connections
                                                </span>
                                                <span class="widget-connection-request-info-about">
                                                    Senior Go Developer at Google
                                                </span>
                                            </div>
                                        </div>
                                        <div class="widget-connection-request-actions d-flex">
                                            <a href="#" class="btn btn-primary btn-style-light flex-grow-1 m-r-xxs"><i class="material-icons">done</i>Accept</a>
                                            <a href="#" class="btn btn-danger btn-style-light flex-grow-1 m-l-xxs"><i class="material-icons">close</i>Ignore</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						<?php */?>
						
						
						

                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>
</body>
</html>