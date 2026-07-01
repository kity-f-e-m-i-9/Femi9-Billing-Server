<?php include("checksession.php");
include("config.php");
error_reporting(0);
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
    <title>Account Manager : <?php echo $business_name;?></title>

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
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>Account Manager</h1>
                                </div>
                            </div>
                        </div>
						
						<div class="row">
                            <div class="col-xl-12">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
									
									<?php
           

			//Compny
		   if($result_LoGuserDtails['onboard_userTYPE']=="company")
		   {
			   ?>
						<h1>Company</h1>
						
		   <?php }
		   //C&F
		   else if($result_LoGuserDtails['onboard_userTYPE']=="candf"){
			  
			  $acman_ID=$result_LoGuserDtails['onboard_userID'];
		   
		   $select_StockistDetails="select * from c_and_f where temp_id='$acman_ID'";
		   $fetch_StockDetails=mysqli_query($db_conn,$select_StockistDetails);
		   $result_StockDetails=mysqli_fetch_array($fetch_StockDetails);
		   
$state_id=$result_StockDetails['state_id'];
$select_stae="select * from state where id='$state_id'";
	$fetch_stae=mysqli_query($db_conn,$select_stae);
	$result_stae=mysqli_fetch_array($fetch_stae);
?>
		   
<h1><?=ucwords($result_StockDetails['name']);?></h1>
<h2><?=ucwords($result_stae['st_name'])?></h2>
<h4>C&F</h4>
<table style="font-size:15px;font-weight:bold;">
<tr>
<td><i class="material-icons-two-tone">call</i></td>
<td><a href="tel://<?=$result_StockDetails['mobile_number'];?>" style="text-decoration:none;color:blue;">
<?=$result_StockDetails['mobile_number'];?></a></td>
</tr>
<?php if($result_StockDetails['email']!=NULL){?>
<tr>
<td><i class="material-icons-two-tone">email</i></td>
<td><a href="mailto:<?=$result_StockDetails['email'];?>" style="text-decoration:none;color:blue;">
<?=$result_StockDetails['email'];?></a></td>
</tr>
<?php }?>
</table>							

									<?php 
									}
									
									//Super Stockist
									 else if($result_LoGuserDtails['onboard_userTYPE']=="super_stockiest")
									 {
										 
										 $acman_ID=$result_LoGuserDtails['onboard_userID'];
		   
		   $select_StockistDetails="select * from super_stockiest where temp_id='$acman_ID'";
		   $fetch_StockDetails=mysqli_query($db_conn,$select_StockistDetails);
		   $result_StockDetails=mysqli_fetch_array($fetch_StockDetails);
		   
$district_id=$result_StockDetails['district_id'];
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

$taluk_id=$result_StockDetails['taluk_id'];
$select_taluklist="select * from taluk where id='$taluk_id'";
	$fetch_taluklist=mysqli_query($db_conn,$select_taluklist);
	$result_taluklist=mysqli_fetch_array($fetch_taluklist);
$taluk_name=$result_taluklist['taluk'];
										 
										 ?>
										 
										 <h1><?=ucwords($result_StockDetails['name']);?></h1>
<h2><?=ucwords($result_stae['st_name'])?></h2>
<h4>Super Stockist</h4>
<table style="font-size:15px;font-weight:bold;">
<tr>
<td><i class="material-icons-two-tone">call</i></td>
<td><a href="tel://<?=$result_StockDetails['mobile_number'];?>" style="text-decoration:none;color:blue;">
<?=$result_StockDetails['mobile_number'];?></a></td>
</tr>
<?php if($result_StockDetails['email']!=NULL){?>
<tr>
<td><i class="material-icons-two-tone">email</i></td>
<td><a href="mailto:<?=$result_StockDetails['email'];?>" style="text-decoration:none;color:blue;">
<?=$result_StockDetails['email'];?></a></td>
</tr>
<?php }?>
</table>

<?php
										 
									 }
									
									//Stockist
									else
									{
										
		   $acman_ID=$result_LoGuserDtails['onboard_userID'];
		   
		   $select_StockistDetails="select * from stockiest where temp_id='$acman_ID'";
		   $fetch_StockDetails=mysqli_query($db_conn,$select_StockistDetails);
		   $result_StockDetails=mysqli_fetch_array($fetch_StockDetails);
		   
$district_id=$result_StockDetails['district_id'];
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

$taluk_id=$result_StockDetails['taluk_id'];
$select_taluklist="select * from taluk where id='$taluk_id'";
	$fetch_taluklist=mysqli_query($db_conn,$select_taluklist);
	$result_taluklist=mysqli_fetch_array($fetch_taluklist);
$taluk_name=$result_taluklist['taluk'];
?>
<h1><?=ucwords($result_StockDetails['name']);?></h1>
<h2><?=ucwords($district_name)?>, <?=ucwords($taluk_name);?></h2>
<h4>Stockist</h4>
<table style="font-size:15px;font-weight:bold;">
<tr>
<td><i class="material-icons-two-tone">call</i></td>
<td><a href="tel://<?=$result_StockDetails['mobile_number'];?>" style="text-decoration:none;color:blue;">
<?=$result_StockDetails['mobile_number'];?></a></td>
</tr>
<?php if($result_StockDetails['email']!=NULL){?>
<tr>
<td><i class="material-icons-two-tone">email</i></td>
<td><a href="mailto:<?=$result_StockDetails['email'];?>" style="text-decoration:none;color:blue;">
<?=$result_StockDetails['email'];?></a></td>
</tr>
<?php }?>
</table>							
									
									<?php }?>
									
									
									
									<!----------------------Start Referral Details----------------------------------->		
									<hr/>
									<span class='badge badge-style-bordered badge-success'>Refered by</span>
									
									<?php 
		if($result_RFRDtailsCNG['ref_by_user_type']=="super_distributor"){
			$tblename="super_distributor";
			$labelname="Super Distributor";
		}
		elseif($result_RFRDtailsCNG['ref_by_user_type']=="super_stockiest"){
			$tblename="super_stockiest";
			$labelname="Super Stockist";
		}
		elseif($result_RFRDtailsCNG['ref_by_user_type']=="stockiest"){
			$tblename="stockiest";
			$labelname="Stockist";
		}
		else{
			$tblename="distributor";
			$labelname="Distributor";
		}
		
		$select_count_REFERID="select * from ".$tblename." where useridtext='".$result_RFRDtailsCNG['ref_by_user_id']."'";
		$fetch_count_REFERID=mysqli_query($db_conn,$select_count_REFERID);
		$result_count_REFERID=mysqli_fetch_array($fetch_count_REFERID);
		
		//district
		$get_stateID=$result_count_REFERID['state_id'];
		$get_districtID=$result_count_REFERID['district_id'];
		//
		$select_DistDetails="select * from district where state_id='$get_stateID' and id='$get_districtID'";
		$fetch_DistDetails=mysqli_query($db_conn,$select_DistDetails);
		$result_DistDetails=mysqli_fetch_array($fetch_DistDetails);
		
		if(is_numeric($get_districtID))
		{
			$print_distname=$result_DistDetails['dist_name'];
		}
		else
		{
			$print_distname=$get_districtID;
		}
		
		?>
		
		
		<?php if($result_RFRDtailsCNG['st_ref_type']=="company"){?>
		<h1>Company</h1>
		<?php }  else{?>
		
		<h1><?=ucwords($result_count_REFERID['name']);?>
							<span style="font-size:12px;">(<?=$labelname;?>)</span>
							</h1>
<h2>
<?php echo $print_distname;?>
</h2>
<table style="font-size:15px;font-weight:bold;">
<tr>
<td><i class="material-icons-two-tone">call</i></td>
<td><a href="tel://<?=$result_count_REFERID['mobile_number'];?>" style="text-decoration:none;color:blue;">
<?=$result_count_REFERID['mobile_number'];?></a></td>
</tr>

<?php if($result_count_REFERID['email']!=NULL){?>
<tr>
<td><i class="material-icons-two-tone">email</i></td>
<td><a href="mailto:<?=$result_count_REFERID['email'];?>" style="text-decoration:none;color:blue;">
<?=$result_count_REFERID['email'];?></a></td>
</tr>
<?php }?>

</table>
		<?php }?>
<!----------------------End **** Referral Details----------------------------------->


						
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
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>
</body>
</html>