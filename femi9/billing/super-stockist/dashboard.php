<?php 
include("checksession.php");
include("config.php"); 

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// ========================================
// CHECK IF USER MUST CHANGE PASSWORD
// ========================================
$userMobile = $result_LoGuserDtails['mobile_number'];
$userType = 'super_stockiest'; // Change based on user type

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


//---------------------------------------------------
//Insert Reward points onetime - billing to customers
//---------------------------------------------------
$select_zero_points="select id,pr_id,qty from invoice_items where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and date<='2025-07-31'";
$fetch_zero_points=mysqli_query($db_conn,$select_zero_points);
while($result_zero_points=mysqli_fetch_array($fetch_zero_points))
{
	$zero_pr_id=$result_zero_points['pr_id'];
	$zero_row_id=$result_zero_points['id'];
	$zero_pr_qty=$result_zero_points['qty'];
	
$selectproducts_zero="select rwpoints from products where id='$zero_pr_id'";
$fetchproducts_zero=mysqli_query($db_conn,$selectproducts_zero);
$resultproducts_zero=mysqli_fetch_array($fetchproducts_zero);
$rwpoints_zero_update=$resultproducts_zero['rwpoints']*$zero_pr_qty;

$update_reward_points234="update invoice_items set rwpoints='$rwpoints_zero_update',
rwpoints_sls='$rwpoints_zero_update' where id='$zero_row_id'";
mysqli_query($db_conn,$update_reward_points234);
}


//Commission Amount
include 'insert_wallet_sales_target.php';

//Cashback Amount
include 'insert_wallet_cashback.php';

//-----------------------------------------------------------
//-----------------------------------------------------------
$select_zero_points2="select id,pr_id,qty from user_invoice_items where from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and date<='2025-08-04' and to_user_type='shop'";
$fetch_zero_points2=mysqli_query($db_conn,$select_zero_points2);
while($result_zero_points2=mysqli_fetch_array($fetch_zero_points2))
{
	$zero_pr_id2=$result_zero_points2['pr_id'];
	$zero_row_id2=$result_zero_points2['id'];
	$zero_pr_qty2=$result_zero_points2['qty'];
	
$selectproducts_zero2="select rwpoints from products where id='$zero_pr_id2'";
$fetchproducts_zero2=mysqli_query($db_conn,$selectproducts_zero2);
$resultproducts_zero2=mysqli_fetch_array($fetchproducts_zero2);
$rwpoints_zero_update2=$resultproducts_zero2['rwpoints']*$zero_pr_qty2;

$update_reward_points234="update user_invoice_items set rwpoints_sls='$rwpoints_zero_update2' 
where id='$zero_row_id2'";
mysqli_query($db_conn,$update_reward_points234);
}
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
	
	
	<style>
	/* Offers Section */
.offers {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 20px;
  padding: 20px;
  background-color: #D9DEFA;border-radius:10px;
}

.offer {
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  max-width: 40%;
  width: 100%;
  text-align: center;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s;padding:10px;
}

.offer:hover {
  transform: scale(1.05);
}

.offer img {
  width: 100%;
  height: 300px;
  object-fit: cover;
}

.offer h3 {
  font-size: 1.2em;
  margin: 10px 0;
  color: #DD670B;font-weight:bold;
}

.offer p {
  font-size: 0.9em;
  color: #666;
  margin: 0 10px 15px;
}

/* Responsive Design */
@media (max-width: 768px) {
  .offers {
    flex-direction: column;
    align-items: center;
  }

  .offer {
    max-width: 98%;
  }
}

@media (max-width: 480px) {
  .offer h3 {
    font-size: 1em;
  }

  .offer p {
    font-size: 0.8em;
  }
}


.scroll-horizontal {
    height: 50px;
    overflow: hidden;
    position: relative;
    background-color: #f1f1f1;
    border: 1px solid #ddd;border-radius:10px;
    padding: 10px;
    box-sizing: border-box;
    white-space: nowrap; /* Ensure the text stays in one line */
	margin-bottom:10px;
  }

  .scroll-horizontal div {
    display: inline-block;font-size:17px;
    position: absolute;font-weight:500;color:#000;
    animation: scroll-horizontal 20s linear infinite;
  }

  @keyframes scroll-horizontal {
    0% {
      transform: translateX(100%);
    }
    100% {
      transform: translateX(-100%);
    }
  }
</style>
	
	
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Femi9 Super Stockist">
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
                        </div>----->
						
						<div class="row">
                            <div class="col-xl-12">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
									
						<h1>Dashboard</h1>
						<h2>Welcome to Femi9 - Happy day Everyday</h2>
						
<!----Scroll Message----->
<?php 
						$select_Scrolld_message="select * from admin_scroll_msg";
						$fetch_Scrolld_message=mysqli_query($db_conn,$select_Scrolld_message);
						$result_Scrolld_message=mysqli_fetch_array($fetch_Scrolld_message);
						?>
						<div class="scroll-horizontal">
  <div><?=$result_Scrolld_message['ss_msg'];?></div>
</div>

						
						<!----------------------Start Offers Page----------------------->
						<?php 
						date_default_timezone_set("Asia/Kolkata");
						$dashDate=date("Y-m-d");
						
						$select_offers="select * from offers_manage where expired_date>='$dashDate' and usertype='$Login_user_TYPEvl' order by expired_date asc";
						$fetch_offers=mysqli_query($db_conn,$select_offers);
						$CountOffers=mysqli_num_rows($fetch_offers);
						if($CountOffers>0)
						{
						?>
						
						<section class="offers">
						<div style="width:100%;text-align:center;">
    <h2><span class="badge badge-style-bordered badge-success">Offers</span></h2></div>


						<?php 
						while($result_offers=mysqli_fetch_array($fetch_offers))
						{
						?>
			<div class="offer">		
			
			<?php if($result_offers['offer_img']!=NULL){?>
      <a data-fslightbox="gallery" href="../company/offers_img/<?=$result_offers['offer_img'];?>" title="<?=ucwords($result_offers['offer_title']);?>">
					<img src="../company/offers_img/<?=$result_offers['offer_img'];?>" alt="<?=ucwords($result_offers['offer_title']);?>">
					</a>
			<?php }?>
					
      <h3><?=ucwords($result_offers['offer_title']);?></h3>
	  <p>Offers Valid Upto : <?=date("d/m/Y",strtotime($result_offers['expired_date']));?></p>
    </div>
   
						<?php } ?>
						</section>
						
						<?php }?>
						
						
						<!----------------------End Offers Page----------------------->
						<!------------------------------------------------------------>
						
						
						
						<!----------------------Start Top Performers page----------------------->
						<br/>
						<?php 
						$select_top_performers="select * from top_performar where usertype='$Login_user_TYPEvl' order by id desc";
						$fetch_top_performers=mysqli_query($db_conn,$select_top_performers);
						$Count_top_performers=mysqli_num_rows($fetch_top_performers);
						$result_top_performers=mysqli_fetch_array($fetch_top_performers);
						
						if($Count_top_performers>0)
						{
						?>
						
						<section class="offers">
						<div style="width:100%;text-align:center;">
    <h2><span class="badge badge-style-bordered badge-success">Top Performers (Super Stockist)</span></h2></div>

			<div class="offer">

			<?php if($result_top_performers['particulars']!=NULL){?>
<h3><?=ucwords($result_top_performers['particulars']);?></h3>		
			<?php }?>
			
      <a data-fslightbox="gallery" href="../company/top_performers_photo/<?=$result_top_performers['per_photo'];?>" title="<?=ucwords($result_top_performers['particulars']);?>">
					<img src="../company/top_performers_photo/<?=$result_top_performers['per_photo'];?>" alt="<?=ucwords($result_top_performers['particulars']);?>">
					</a>
      
    </div>
						</section>
						
						<?php }?>
						
						
						<!----------------------End Top Performers Page----------------------->
						<!------------------------------------------------------------>
						
						

						
						
						
						
						<!----------------------Start Stock Requesting Page----------------------->
						<?php
$sltsumqty_request="select count(*) as numcountrequ from stock_request where status='pending' and tousertype='$Login_user_TYPEvl' and touserid='$Login_user_IDvl'";
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
											<a href="stock_request_pending.php" style="text-decoration:none;">
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
						
						<!----------------------End Stock Requesting Page----------------------->
						<!---------------------------------------------------------------------->
						
						</div>
						</div>
						</div>
						</div>
						
						
						
						<!---------------------------Reward Points---------------------------------------------->		
<?php 
//Current Month
$current_from_date=date("Y-m-01");
$nof_of_days_month=date("t",strtotime($current_from_date));
$current_to_date=date("Y-m-".$nof_of_days_month."");

//Last Month
$last_month = date ("Y-m-01", strtotime("-1 month", strtotime($current_from_date)));
$nof_of_days_month2=date("t",strtotime($last_month));

$last_from_date=date("Y-m-01",strtotime($last_month));
$last_to_date=date("Y-m-".$nof_of_days_month2."",strtotime($last_month));
?>		
						<h2><b>Reward Points</b></h2>
						<div class="row">
						<div class="col-xl-4">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">check</i>
                                            </div>
	<?php 
	//SUM LAST MONTH POINT
	$SLCT_LM_POINTS="select sum(rwpoints) from user_invoice_items where to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl' and date between '$last_from_date' and '$last_to_date'";
	$FETCH_LM_POINTS=mysqli_query($db_conn,$SLCT_LM_POINTS);
	$RESULT_LM_POINTS=mysqli_fetch_array($FETCH_LM_POINTS);
	if($RESULT_LM_POINTS[0]>0){$TotalPoints_LM=$RESULT_LM_POINTS[0];}else{$TotalPoints_LM="0";}
	
	//SUM LAST MONTH POINT
	$SLCT_LM_POINTS_RTN="select sum(rwpoints) from user_return_stock_items where from_usertype='$Login_user_TYPEvl' and from_userid='$Login_user_IDvl' and date between '$last_from_date' and '$last_to_date'";
	$FETCH_LM_POINTS_RTN=mysqli_query($db_conn,$SLCT_LM_POINTS_RTN);
	$RESULT_LM_POINTS_RTN=mysqli_fetch_array($FETCH_LM_POINTS_RTN);
	if($RESULT_LM_POINTS_RTN[0]>0){$TotalPoints_LM_RTN=$RESULT_LM_POINTS_RTN[0];}else{$TotalPoints_LM_RTN="0";}
	
	$Point_Total_LM=$TotalPoints_LM-$TotalPoints_LM_RTN;
	//IF ACCURED NEGATIVE VALUE THAN SHOW ZERO
	if($Point_Total_LM>0){$Point_Total_LM_Show=$Point_Total_LM;}else{$Point_Total_LM_Show="0";}
	?>
                       <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Last Month<br/><?=date("M, Y",strtotime($last_month));?></span>
                       <span class="widget-stats-amount"><?=$Point_Total_LM_Show;?></span>
                       </div>
					   
										</div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-4">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">check</i>
                                            </div>
	<?php 
	//SUM LAST MONTH POINT
	$SLCT_CRNT_POINTS="select sum(rwpoints) from user_invoice_items where to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl' and date between '$current_from_date' and '$current_to_date'";
	$FETCH_CRNT_POINTS=mysqli_query($db_conn,$SLCT_CRNT_POINTS);
	$RESULT_CRNT_POINTS=mysqli_fetch_array($FETCH_CRNT_POINTS);
	if($RESULT_CRNT_POINTS[0]>0){$TotalPoints_CRNT=$RESULT_CRNT_POINTS[0];}else{$TotalPoints_CRNT="0";}
	
	//SUM LAST MONTH POINT
	$SLCT_CRNT_POINTS_RTN="select sum(rwpoints) from user_return_stock_items where from_usertype='$Login_user_TYPEvl' and from_userid='$Login_user_IDvl' and date between '$current_from_date' and '$current_to_date'";
	$FETCH_CRNT_POINTS_RTN=mysqli_query($db_conn,$SLCT_CRNT_POINTS_RTN);
	$RESULT_CRNT_POINTS_RTN=mysqli_fetch_array($FETCH_CRNT_POINTS_RTN);
	if($RESULT_CRNT_POINTS_RTN[0]>0){$TotalPoints_CRNT_RTN=$RESULT_CRNT_POINTS_RTN[0];}else{$TotalPoints_CRNT_RTN="0";}
	
	$Point_Total_CRNT=$TotalPoints_CRNT-$TotalPoints_CRNT_RTN;
	//IF ACCURED NEGATIVE VALUE THAN SHOW ZERO
	if($Point_Total_CRNT>0){$Point_Total_CRNT_Show=$Point_Total_CRNT;}else{$Point_Total_CRNT_Show="0";}
	?>
                       <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Current Month<br/><?=date("M, Y",strtotime($current_from_date));?></span>
                       <span class="widget-stats-amount"><?=$Point_Total_CRNT_Show;?></span>
                       </div>
					   
										</div>
                                    </div>
                                </div>
                            </div>
							
							</div>
	<!---------------------------Reward Points-----------end ***----------------------------------->	
						
						
						<h2><b>Available Stocks</b></h2>
						
                        <div class="row">
						
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">paid</i>
                                            </div>
	<?php 
	$select_availablestocks="select sum(closing_qty) from stock where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
	$fetch_availablestocks=mysqli_query($db_conn,$select_availablestocks);
	$result_availablestocks=mysqli_fetch_array($fetch_availablestocks);
	if($result_availablestocks[0]>0){$alstockst_show=$result_availablestocks[0];}else{$alstockst_show="0";}
	?>
                       <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Total</span>
                       <span class="widget-stats-amount"><?=$alstockst_show;?></span>
                       </div>
					   
										</div>
                                    </div>
                                </div>
                            </div>
							
							
							
							<!------------------------------------------------------------->
							<!------------------------------------------------------------->
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-success">
                                                <i class="material-icons-outlined">paid</i>
                                            </div>
											
											
                                            <div class="widget-stats-content flex-fill">
                                   <span class="widget-stats-title">Stockist&nbsp;wise</span>
                                   <span class="widget-stats-amount">
	<?php 
	$stokiststock_usertype_STCK="stockiest";
	
	$EMPTYSTOCKISTSTOCKS_STCK="DELETE FROM temp_stocks_stockist WHERE ss_id='$Login_user_IDvl' and usertype='$stokiststock_usertype_STCK'";
	mysqli_query($db_conn,$EMPTYSTOCKISTSTOCKS_STCK);
	
	$select_stockistcount12_STCK="select temp_id from stockiest where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
	$fetch_stockistcount12_STCK=mysqli_query($db_conn,$select_stockistcount12_STCK);
	while($result_stockistcount12_STCK=mysqli_fetch_array($fetch_stockistcount12_STCK))
	{
		
	$stockistIDvl_STCK=$result_stockistcount12_STCK['temp_id'];
	//
	$select_stockistcount_stocks_STCK="select sum(closing_qty) from stock where user_type='$stokiststock_usertype_STCK' and user_id='$stockistIDvl_STCK'";
	$fetch_stockistcount_stocks_STCK=mysqli_query($db_conn,$select_stockistcount_stocks_STCK);
	$result_stockistcount_stocks_STCK=mysqli_fetch_array($fetch_stockistcount_stocks_STCK);
	if($result_stockistcount_stocks_STCK[0]!=NULL)
	{
	$Total_stocks_stockist_STCK=$result_stockistcount_stocks_STCK[0];
	}else{$Total_stocks_stockist_STCK="0";}
	
	$sltcountduplicate_STCK="select count(*) as numDupvl from temp_stocks_stockist where usertype='$stokiststock_usertype_STCK' and userid='$stockistIDvl_STCK' and ss_id='$Login_user_IDvl'";
	$fetcghountduplicate_STCK=mysqli_query($db_conn,$sltcountduplicate_STCK);
	$resultghountduplicate_STCK=mysqli_fetch_array($fetcghountduplicate_STCK);
	if($resultghountduplicate_STCK['numDupvl']==0)
	{
		$insertstockiststocks_STCK="insert into temp_stocks_stockist (usertype,userid,stocks,ss_id) 
		values ('$stokiststock_usertype_STCK','$stockistIDvl_STCK','$Total_stocks_stockist_STCK','$Login_user_IDvl')";
		mysqli_query($db_conn,$insertstockiststocks_STCK);
	}
	
	}
	
	//display stocks - stockist wise
	$sltcountduplicate_STCK1345="select sum(stocks) from temp_stocks_stockist where ss_id='$Login_user_IDvl' and usertype='$stokiststock_usertype_STCK'";
	$fetcghountduplicate_STCK1345=mysqli_query($db_conn,$sltcountduplicate_STCK1345);
	$resultghountduplicate_STCK1345=mysqli_fetch_array($fetcghountduplicate_STCK1345);
	if($resultghountduplicate_STCK1345[0]!=NULL){echo $resultghountduplicate_STCK1345[0];}else{ echo "0";}
	?>
	
	</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
							<!--------------------end***--------------------------------------->
							
							
							
							<!------------------------------------------------------------->
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">paid</i>
                                            </div>
											
											
                                            <div class="widget-stats-content flex-fill">
                                   <span class="widget-stats-title">Distributor&nbsp;wise</span>
                                   <span class="widget-stats-amount">
								  <?php 
	$stokiststock_usertype_DIST="distributor";
	
	$EMPTYSTOCKISTSTOCKS_DIST="DELETE FROM temp_stocks_stockist WHERE ss_id='$Login_user_IDvl' and usertype='$stokiststock_usertype_DIST'";
	mysqli_query($db_conn,$EMPTYSTOCKISTSTOCKS_DIST);
	
	$select_stockistcount12_DIST="select temp_id from distributor where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
	$fetch_stockistcount12_DIST=mysqli_query($db_conn,$select_stockistcount12_DIST);
	while($result_stockistcount12_DIST=mysqli_fetch_array($fetch_stockistcount12_DIST))
	{
		
	$stockistIDvl_DIST=$result_stockistcount12_DIST['temp_id'];
	//
	$select_stockistcount_stocks_DIST="select sum(closing_qty) from stock where user_type='$stokiststock_usertype_DIST' and user_id='$stockistIDvl_DIST'";
	$fetch_stockistcount_stocks_DIST=mysqli_query($db_conn,$select_stockistcount_stocks_DIST);
	$result_stockistcount_stocks_DIST=mysqli_fetch_array($fetch_stockistcount_stocks_DIST);
	if($result_stockistcount_stocks_DIST[0]!=NULL)
	{
	$Total_stocks_stockist_DIST=$result_stockistcount_stocks_DIST[0];
	}else{$Total_stocks_stockist_DIST="0";}
	
	$sltcountduplicate_DIST="select count(*) as numDupvl from temp_stocks_stockist where usertype='$stokiststock_usertype_DIST' and userid='$stockistIDvl_DIST' and ss_id='$Login_user_IDvl'";
	$fetcghountduplicate_DIST=mysqli_query($db_conn,$sltcountduplicate_DIST);
	$resultghountduplicate_DIST=mysqli_fetch_array($fetcghountduplicate_DIST);
	if($resultghountduplicate_DIST['numDupvl']==0)
	{
		$insertstockiststocks_DIST="insert into temp_stocks_stockist (usertype,userid,stocks,ss_id) 
		values ('$stokiststock_usertype_DIST','$stockistIDvl_DIST','$Total_stocks_stockist_DIST','$Login_user_IDvl')";
		mysqli_query($db_conn,$insertstockiststocks_DIST);
	}
	
	}
	
	//display stocks - stockist wise
	$sltcountduplicate_DIST1345="select sum(stocks) from temp_stocks_stockist where ss_id='$Login_user_IDvl' and usertype='$stokiststock_usertype_DIST'";
	$fetcghountduplicate_DIST1345=mysqli_query($db_conn,$sltcountduplicate_DIST1345);
	$resultghountduplicate_DIST1345=mysqli_fetch_array($fetcghountduplicate_DIST1345);
	if($resultghountduplicate_DIST1345[0]!=NULL){echo $resultghountduplicate_DIST1345[0];}else{ echo "0";}
	?>
	
	</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
							<!--------------------end***--------------------------------------->
							
							
							
							</div>
							<h2><b>Users</b></h2>
							<div class="row">
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
                                 <?php 
	$select_stockistcount="select count(*) as numcountstockist from stockiest where ss_id='$Login_user_IDvl'";
	$fetch_stockistcount=mysqli_query($db_conn,$select_stockistcount);
	$result_stockistcount=mysqli_fetch_array($fetch_stockistcount);
	?>
                       <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Stockist</span>
                       <span class="widget-stats-amount"><?=$result_stockistcount['numcountstockist'];?></span>
                       </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-success">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
                       <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Distributor</span>
					    <?php 
	$Selectcount_DIST="select count(*) as numcountDIST from distributor where onboard_userID='$Login_user_IDvl' and onboard_userTYPE='super_stockiest'";
	$Fetchcount_DIST=mysqli_query($db_conn,$Selectcount_DIST);
	$Resultcount_DIST=mysqli_fetch_array($Fetchcount_DIST);
	?>
                       <span class="widget-stats-amount"><?=$Resultcount_DIST['numcountDIST'];?></span>
                       </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							</div>
							
							<h2><b>Locations</b></h2>
							<div class="row">
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
								<?php
	$superstockist_stateid=$result_LoGuserDtails['state_id'];
	$superstockist_districtid=$result_LoGuserDtails['district_id'];
	
	$counTalluk="select count(*) as TolTalukCount from taluk where state_id='$superstockist_stateid' and dist_id='$superstockist_districtid'";
	$fetch_counTalluk=mysqli_query($db_conn,$counTalluk);
	$result_counTalluk=mysqli_fetch_array($fetch_counTalluk);
	?>
								<!-----<a href="JavaScript:newPopup('asgn_taluk.php?sid=<?=$superstockist_stateid;?>&&did=<?=$superstockist_districtid?>&&Taluk');" style="text-decoration:none;">---->
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">map</i>
                                            </div>
                       <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Total<br/>Taluk</span>
                       <span class="widget-stats-amount"><?=$result_counTalluk['TolTalukCount'];?></span>
                       </div>
										</div>
                                    </div>
								  <!---</a>---->
                                </div>
                            </div>
							
							<script type="text/javascript">
function newPopup(url) {
	popupWindow = window.open(
		url,'popUpWindow','height=450,width=750,left=350,top=200,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes')
}
</script>

							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-success">
                                                <i class="material-icons-outlined">map</i>
                                            </div>
											<?php
											$counPincode="select count(*) as TolPINCCount from pincode where state_id='$superstockist_stateid' and dist_id='$superstockist_districtid'";
	$fetch_counPincode=mysqli_query($db_conn,$counPincode);
	$result_counPincode=mysqli_fetch_array($fetch_counPincode);
	?>
                       <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Total<br/>Pincode</span>
                       <span class="widget-stats-amount"><?=$result_counPincode['TolPINCCount'];?></span>
                       </div>
										</div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                <i class="material-icons-outlined">map</i>
                                            </div>
											<?php
	$counTallukAvailable="select count(*) as TolTalukCountAvailable from taluk where state_id='$superstockist_stateid' and dist_id='$superstockist_districtid' and assigned_SID='Nil'";
	$fetch_counTallukAvailable=mysqli_query($db_conn,$counTallukAvailable);
	$result_counTallukAvailable=mysqli_fetch_array($fetch_counTallukAvailable);
	?>
                       <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Available<br/>Taluk</span>
                       <span class="widget-stats-amount"><?=$result_counTallukAvailable['TolTalukCountAvailable'];?></span>
                       </div>
										</div>
                                    </div>
                                </div>
                            </div>
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                <i class="material-icons-outlined">map</i>
                                            </div>
											<?php
	$counPINCAvailable="select count(*) as TolPINCCountAvailable from pincode where state_id='$superstockist_stateid' and dist_id='$superstockist_districtid' and assigned_SID='Nil'";
	$FetchPINCAvailable=mysqli_query($db_conn,$counPINCAvailable);
	$ResultPINCAvailable=mysqli_fetch_array($FetchPINCAvailable);
	?>
                       <div class="widget-stats-content flex-fill">
                       <span class="widget-stats-title">Available<br/>Pincode</span>
                       <span class="widget-stats-amount"><?=$ResultPINCAvailable['TolPINCCountAvailable'];?></span>
                       </div>
										</div>
                                    </div>
                                </div>
                            </div>
							
							
							
                        </div>

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
						<?php */ ?>
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
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
	<script src="../../assets/plugins/lightbox/fslightbox.js"></script>
	<script src="../../assets/js/pages/lightbox.js"></script>
</body>
</html>