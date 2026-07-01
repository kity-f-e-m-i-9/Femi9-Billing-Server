<?php include("checksession.php");
include("config.php");
error_reporting(0);

$Report_LABLE="ONBOARDED OLS BUT NOT PURCHASED";

if($_REQUEST['modelval']=="usr1" )
{$DISPLAY_LABLE="Super Stockist"; $tblenma="super_stockiest"; $usertype_stock="super_stockiest";}

else if($_REQUEST['modelval']=="usr2")
{$DISPLAY_LABLE="Stockist"; $tblenma="stockiest"; $usertype_stock="stockiest";}

else if($_REQUEST['modelval']=="usr3")
{$DISPLAY_LABLE="Distributor"; $tblenma="distributor"; $usertype_stock="distributor";}

else if($_REQUEST['modelval']=="usr4")
{$DISPLAY_LABLE="Super Distributor"; $tblenma="super_distributor"; $usertype_stock="super_distributor";}

else
{$DISPLAY_LABLE="Outlet"; $tblenma="outlet"; $usertype_stock="outlet";}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?=$Report_LABLE;?>  : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">


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
                                    <h1>
									<table class="headertble">
									<tr>
									<td><?=$Report_LABLE;?></td>
									<td><a href="Report">&#8592;&nbsp;Go&nbsp;Back</a></td>
									<td><a href="export_report_notpur?modelval=<?=$_REQUEST['modelval'];?>&&out1=<?=$_REQUEST['out1'];?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<?php if($_REQUEST['seaction']==NULL){ ?>
						<div class="row">
                           <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-success">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											
						<a href="#" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title"><?=$DISPLAY_LABLE;?></span>
                                                <span class="widget-stats-amount"><?=base64_decode($_REQUEST['out1']);?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							</div>
						<?php }?>
							
<?php
//----Continuos Serial Number In Next Page.......................
$num_rec_per_page=30;
if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=1; }; 
 $start_from = ($page-1) * $num_rec_per_page; 
$i= $start_from;
//---------------------------------------------------------------
//echo ++$i; 
?>
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									
									<style type="text/css">
									#overflowon{width:100%;overflow-x:scroll !important;height:100%;overflow-y:hidden;}
									</style>
									
	
									<div id="overflowon">
									
									
									<!-----------------------SUPER STOCKIST-------------------------------->
									<?PHP if($_REQUEST['modelval']=="usr1"){?>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>User ID</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>District</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $selectRcd_VLSS1="select * from temp_not_purchased where purchse_count='0' and usertype='super_stockiest' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$userid=$resultRcd_VLSS1['userid'];
											//
										$selectusers="select * from ".$tblenma." where temp_id='$userid'";
										$fetchusers=mysqli_query($db_conn,$selectusers);
										$resultusers=mysqli_fetch_array($fetchusers);
											
											$state_id_VLSS1=$resultusers['state_id'];
											$district_id_VLSS1=$resultusers['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                            <td><?=$resultusers["useridtext"];?></td>
							<td><?=$resultusers["name"];?></td>
							<td><?=$resultusers["mobile_number"];?></td>
							<td><?=$district_name_VLSS1;?></td>
				
                                        </tr>
                                           
										<?php }?>
										</tbody>
										
                                        </table>
										<?PHP }?>
										
										
										
										
										<!-----------------------STOCKIST-------------------------------->
									<?PHP if($_REQUEST['modelval']=="usr2"){?>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>User ID</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>District</th>
													<th>Taluk</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $selectRcd_VLSS1="select * from temp_not_purchased where purchse_count='0' and usertype='stockiest' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$userid=$resultRcd_VLSS1['userid'];
											//
										$selectusers="select * from ".$tblenma." where temp_id='$userid'";
										$fetchusers=mysqli_query($db_conn,$selectusers);
										$resultusers=mysqli_fetch_array($fetchusers);
											
											$state_id_VLSS1=$resultusers['state_id'];
											$district_id_VLSS1=$resultusers['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$taluk_id_VLSS2=$resultusers['taluk_id'];
$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS1' and dist_id='$district_id_VLSS1' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
										
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                            <td><?=$resultusers["useridtext"];?></td>
							<td><?=$resultusers["name"];?></td>
							<td><?=$resultusers["mobile_number"];?></td>
							<td><?=$district_name_VLSS1;?></td>
							<td><?=$taluk_name_VLSS2;?></td>
				
                                        </tr>
                                           
										<?php }?>
										</tbody>
										
                                        </table>
										<?PHP }?>
										
										
										
										<!-----------------------DISTRIBUTOR-------------------------------->
									<?PHP if($_REQUEST['modelval']=="usr3" || $_REQUEST['modelval']=="usr4"){
										$tousertype_non_purcah3=$usertype_stock; //user-type 
										
										$get_from_date=$_REQUEST['frdate'];
										$get_to_date=$_REQUEST['todate'];
										
										if(isset($_REQUEST['sedatas']))
										{
											$get_data1=$_REQUEST['modelval'];
											$get_data2=$_REQUEST['out1'];
											$get_data3=$_REQUEST['seaction'];
											$get_data4=$_REQUEST['frdate'];
											$get_data5=$_REQUEST['todate'];
											
											//DELETE TEMP RECORDS
											mysqli_query($db_conn,"DELETE FROM temp_not_purchased where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'");
											
//distributor
$select_nonpurchase_stockiest="select * from ".$tblenma." where onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl' order by id asc";
$fetch_nonpurchase_stockiest=mysqli_query($db_conn,$select_nonpurchase_stockiest);
while($result_nonpurchase_stockiest=mysqli_fetch_array($fetch_nonpurchase_stockiest))
{
	$NNP_stockiest_ID=$result_nonpurchase_stockiest['temp_id'];
	$select_count_nonpurchase_stockiest="select count(*) as numnnss from user_invoice where to_user_id='$NNP_stockiest_ID' and to_user_type='$tousertype_non_purcah3' and date between '$get_data4' and '$get_data5'";
	$fetch_count_nonpurchase_stockiest=mysqli_query($db_conn,$select_count_nonpurchase_stockiest);
	$result_count_nonpurchase_stockiest=mysqli_fetch_array($fetch_count_nonpurchase_stockiest);
	$Total_purcahse_count_stockiest=$result_count_nonpurchase_stockiest['numnnss'];
	
	$SLCT_SS_cnt="select count(*) as numnnss23 from temp_not_purchased where userid='$NNP_stockiest_ID' and usertype='$tousertype_non_purcah3' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
	$fetch_SS_cnt=mysqli_query($db_conn,$SLCT_SS_cnt);
	$REsult_SS_cnt=mysqli_fetch_array($fetch_SS_cnt);
	if($REsult_SS_cnt['numnnss23']==0)
	{
		$insert_report_nonpurcahse_stockiest="insert into temp_not_purchased (usertype,userid,purchse_count,searchtype,onboard_userTYPE,onboard_userID) values ('$tousertype_non_purcah3','$NNP_stockiest_ID','$Total_purcahse_count_stockiest',
		'nil','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_report_nonpurcahse_stockiest);
		
	}
	
}
											
	echo "<script>window.location='overview_notpurchased?modelval=$get_data1&&out1=$get_data2&&frdate=$get_data4&&todate=$get_data5&&seaction=$get_data3';</script>";
										}
										
										
$select_count_non_DTUSER="select count(*) as numDTUSER from temp_not_purchased where usertype='$tousertype_non_purcah3' and purchse_count='0' and onboard_userTYPE='$Login_user_TYPEvl' and onboard_userID='$Login_user_IDvl'";
$fethc_ount_non_DTUSER=mysqli_query($db_conn,$select_count_non_DTUSER);
$result_count_non_DTUSER=mysqli_fetch_array($fethc_ount_non_DTUSER);
$show_nonpur_DTUSER=$result_count_non_DTUSER['numDTUSER'];

										?>
										
<h1><?=$DISPLAY_LABLE;?> <?php if($show_nonpur_DTUSER>0){ echo " (Count : ".$show_nonpur_DTUSER.")"; }?></h1>
									
<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
<input type="hidden" name="modelval" value="<?=$_REQUEST['modelval'];?>"/>
<input type="hidden" name="out1" value="<?=$_REQUEST['out1'];?>"/>
<input type="hidden" name="seaction" value="1"/>

<div class="overviewcontainar">
<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$get_from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$get_to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>
</div>
<div style="clear:both;"></div>
<br/>
</form>


                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>User ID</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>District</th>
													<th>Taluk</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $selectRcd_VLSS1="select * from temp_not_purchased where purchse_count='0' and usertype='$tousertype_non_purcah3' and onboard_userTYPE='$Login_user_TYPEvl' and 
										onboard_userID='$Login_user_IDvl'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$userid=$resultRcd_VLSS1['userid'];
											//
										$selectusers="select * from ".$tblenma." where temp_id='$userid'";
										$fetchusers=mysqli_query($db_conn,$selectusers);
										$resultusers=mysqli_fetch_array($fetchusers);
											
											$state_id_VLSS1=$resultusers['state_id'];
											$district_id_VLSS1=$resultusers['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$taluk_id_VLSS2=$resultusers['taluk_id'];
$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS1' and dist_id='$district_id_VLSS1' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
										
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                            <td><?=$resultusers["useridtext"];?></td>
							<td><?=$resultusers["name"];?></td>
							<td><?=$resultusers["mobile_number"];?></td>
							<td><?=$district_name_VLSS1;?></td>
							<td><?=$taluk_name_VLSS2;?></td>
				
                                        </tr>
                                           
										<?php }?>
										</tbody>
                                        </table>
									<?PHP }?>
										
			<!------------------END***----------------------------------------->		
										
										</div><!--overflow on end***-->
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
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
</body>

</html>