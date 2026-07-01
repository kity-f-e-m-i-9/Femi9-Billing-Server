<?php include("checksession.php");
include("config.php");
error_reporting(0);

$Report_LABLE="Outstanding";

if($_REQUEST['modelval']=="usr1")
{$DISPLAY_LABLE="Super Stockist"; $tblenma="super_stockiest"; $usertype_stock="super_stockiest";}

else if($_REQUEST['modelval']=="usr2")
{$DISPLAY_LABLE="Stockist"; $tblenma="stockiest"; $usertype_stock="stockiest";}

else if($_REQUEST['modelval']=="usr3")
{$DISPLAY_LABLE="Distributor"; $tblenma="distributor"; $usertype_stock="distributor";}

else if($_REQUEST['modelval']=="usr4")
{$DISPLAY_LABLE="Shop"; $tblenma="shop"; $usertype_stock="shop";}

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
									<td><a href="export_report7?modelval=<?=$_REQUEST['modelval'];?>&&out1=<?=$_REQUEST['out1'];?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<div class="row">
                           <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-success">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title"><?=$DISPLAY_LABLE;?></span>
                                                <span class="widget-stats-amount"><?=base64_decode($_REQUEST['out1']);?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
							</div>
							
							
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
													<th>Outstanding</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $selectRcd_VLSS1="select * from ".$tblenma." order by id asc";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
//SUM RECEIVABLE AMOUNT										
$select_marketstock_VLDIST_VLSS1="select sum(receivable) from receipt where to_user_type='$usertype_stock' and to_user_id='".$resultRcd_VLSS1['temp_id']."'";
$fetch_marketstock_VLDIST_VLSS1=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS1);
$result_marketstock_VLDIST_VLSS1=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS1);

//SUM RECEived AMOUNT										
$select_Received_VLDIST_VLSS1="select sum(receivable) from receipt where to_user_type='$usertype_stock' and to_user_id='".$resultRcd_VLSS1['temp_id']."'";
$fetch_Received_VLDIST_VLSS1=mysqli_query($db_conn,$select_Received_VLDIST_VLSS1);
$result_Received_VLDIST_VLSS1=mysqli_fetch_array($fetch_Received_VLDIST_VLSS1);=

if($result_marketstock_VLDIST_VLSS1[0]!=NULL){
$Total_available_stock_VLSS1=number_format($result_marketstock_VLDIST_VLSS1[0],2,'.','');
}else{$Total_available_stock_VLSS1="0";}

$Total_available_stock123_VLSS1+=$Total_available_stock_VLSS1;

if($Total_available_stock_VLSS1>0){
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                            <td><?=$resultRcd_VLSS1["useridtext"];?></td>
							<td><?=$resultRcd_VLSS1["name"];?></td>
							<td><?=$resultRcd_VLSS1["mobile_number"];?></td>
							<td><?=$district_name_VLSS1;?></td>
							<td align="right"><?=$Total_available_stock_VLSS1;?></td>
				
                                        </tr>
                                           
										<?php }?>
										<?php }?>
										 
										 <tfoot>
										 <tr>
										 <td colspan="5">Grand Total</td>
				<td align="right"><b><?=number_format($Total_available_stock123_VLSS1,2,'.','');?></b></td>
										 </tr>
										 </tfoot>
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
													<th>Outstanding</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $selectRcd_VLSS2="select * from ".$tblenma." order by id asc";
										$fetchRcd_VLSS2=mysqli_query($db_conn,$selectRcd_VLSS2);
										while($resultRcd_VLSS2=mysqli_fetch_array($fetchRcd_VLSS2))
										{
											
											$state_id_VLSS2=$resultRcd_VLSS2['state_id'];
											$district_id_VLSS2=$resultRcd_VLSS2['district_id'];
											

										$selectrecords_VLSS2="select * from district where state_id='$state_id_VLSS2' and id='$district_id_VLSS2'";
										$fetchrecords_VLSS2=mysqli_query($db_conn,$selectrecords_VLSS2);
										$resultrecords_VLSS2=mysqli_fetch_array($fetchrecords_VLSS2);
										$district_name_VLSS2=$resultrecords_VLSS2['dist_name'];
										
										$taluk_id_VLSS2=$resultRcd_VLSS2['taluk_id'];
										
$selectrecords_VLSS2_taluk="select * from taluk where state_id='$state_id_VLSS2' and dist_id='$district_id_VLSS2' and id='$taluk_id_VLSS2'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecords_VLSS2_taluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
										
$select_marketstock_VLDIST_VLSS2="select sum(receivable) from receipt where to_user_type='$usertype_stock' and to_user_id='".$resultRcd_VLSS2['temp_id']."'";
$fetch_marketstock_VLDIST_VLSS2=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS2);
$result_marketstock_VLDIST_VLSS2=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS2);

if($result_marketstock_VLDIST_VLSS2[0]!=NULL){
$Total_available_stock_VLSS2=number_format($result_marketstock_VLDIST_VLSS2[0],2,'.','');
}else{$Total_available_stock_VLSS2="0";}

$Total_available_stock123_VLSS2+=$Total_available_stock_VLSS2;

if($Total_available_stock_VLSS2>0){
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                            <td><?=$resultRcd_VLSS2["useridtext"];?></td>
							<td><?=$resultRcd_VLSS2["name"];?></td>
							<td><?=$resultRcd_VLSS2["mobile_number"];?></td>
							<td><?=$district_name_VLSS2;?></td>
							<td><?=$taluk_name_VLSS2;?></td>
							<td align="right"><?=$Total_available_stock_VLSS2;?></td>
				
                                        </tr>
                                           
										<?php }?>
										<?php }?>
										 
										 <tfoot>
										 <tr>
										 <td colspan="6">Grand Total</td>
				<td align="right"><b><?=number_format($Total_available_stock123_VLSS2,2,'.','');?></b></td>
										 </tr>
										 </tfoot>
                                        </table>
										<?PHP }?>
										
										
										
										<!-----------------------DISTRIBUTOR-------------------------------->
									<?PHP if($_REQUEST['modelval']=="usr3"){?>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>User ID</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Outstanding</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $selectRcd_VLSS3="select * from ".$tblenma." order by id asc";
										$fetchRcd_VLSS3=mysqli_query($db_conn,$selectRcd_VLSS3);
										while($resultRcd_VLSS3=mysqli_fetch_array($fetchRcd_VLSS3))
										{
											
											$state_id_VLSS3=$resultRcd_VLSS3['state_id'];
											$district_id_VLSS3=$resultRcd_VLSS3['district_id'];

										$selectrecords_VLSS3="select * from district where state_id='$state_id_VLSS3' and id='$district_id_VLSS3'";
										$fetchrecords_VLSS3=mysqli_query($db_conn,$selectrecords_VLSS3);
										$resultrecords_VLSS3=mysqli_fetch_array($fetchrecords_VLSS3);
										$district_name_VLSS3=$resultrecords_VLSS3['dist_name'];
										
										$taluk_id_VLSS3=$resultRcd_VLSS3['taluk_id'];
										
$selectrecords_VLSS3_taluk="select * from taluk where state_id='$state_id_VLSS3' and dist_id='$district_id_VLSS3' and id='$taluk_id_VLSS3'";
$fetchrecords_VLSS3_taluk=mysqli_query($db_conn,$selectrecords_VLSS3_taluk);
$resultrecords_VLSS3_taluk=mysqli_fetch_array($fetchrecords_VLSS3_taluk);
$taluk_name_VLSS3=$resultrecords_VLSS3_taluk['taluk'];
										
$select_marketstock_VLDIST_VLSS3="select sum(receivable) from receipt where to_user_type='$usertype_stock' and to_user_id='".$resultRcd_VLSS3['temp_id']."'";
$fetch_marketstock_VLDIST_VLSS3=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS3);
$result_marketstock_VLDIST_VLSS3=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS3);

if($result_marketstock_VLDIST_VLSS3[0]!=NULL){
$Total_available_stock_VLSS3=number_format($result_marketstock_VLDIST_VLSS3[0],2,'.','');
}else{$Total_available_stock_VLSS3="0";}
$Total_available_stock123_VLSS3+=$Total_available_stock_VLSS3;

if($Total_available_stock_VLSS3>0)
{
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                            <td><?=$resultRcd_VLSS3["useridtext"];?></td>
							<td><?=$resultRcd_VLSS3["name"];?></td>
							<td><?=$resultRcd_VLSS3["mobile_number"];?></td>
							<td><?=$district_name_VLSS3;?></td>
							<td><?=$taluk_name_VLSS3;?></td>
							<td align="right"><?=$Total_available_stock_VLSS3;?></td>
				
                                        </tr>
                                           
										<?php }?>
										<?php }?>
										 
										 <tfoot>
										 <tr>
										 <td colspan="6">Grand Total</td>
				<td align="right"><b><?=number_format($Total_available_stock123_VLSS3,2,'.','');?></b></td>
										 </tr>
										 </tfoot>
                                        </table>
									<?PHP }?>
									
									
									
									
									<!-----------------------SHOP-------------------------------->
									<?PHP if($_REQUEST['modelval']=="usr4"){?>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>User ID</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Outstanding</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $selectRcd_VLSS4="select * from ".$tblenma." order by id asc";
										$fetchRcd_VLSS4=mysqli_query($db_conn,$selectRcd_VLSS4);
										while($resultRcd_VLSS4=mysqli_fetch_array($fetchRcd_VLSS4))
										{
											
											$state_id_VLSS4=$resultRcd_VLSS4['state_id'];
											$district_id_VLSS4=$resultRcd_VLSS4['district_id'];

										$selectrecords_VLSS4="select * from district where state_id='$state_id_VLSS4' and id='$district_id_VLSS4'";
										$fetchrecords_VLSS4=mysqli_query($db_conn,$selectrecords_VLSS4);
										$resultrecords_VLSS4=mysqli_fetch_array($fetchrecords_VLSS4);
										$district_name_VLSS4=$resultrecords_VLSS4['dist_name'];
										
										$taluk_id_VLSS4=$resultRcd_VLSS4['taluk_id'];
										
$selectrecords_VLSS4_taluk="select * from taluk where state_id='$state_id_VLSS4' and dist_id='$district_id_VLSS4' and id='$taluk_id_VLSS4'";
$fetchrecords_VLSS4_taluk=mysqli_query($db_conn,$selectrecords_VLSS4_taluk);
$resultrecords_VLSS4_taluk=mysqli_fetch_array($fetchrecords_VLSS4_taluk);
$taluk_name_VLSS4=$resultrecords_VLSS4_taluk['taluk'];
										
$select_marketstock_VLDIST_VLSS4="select sum(receivable) from receipt where to_user_type='$usertype_stock' and to_user_id='".$resultRcd_VLSS4['temp_id']."'";
$fetch_marketstock_VLDIST_VLSS4=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS4);
$result_marketstock_VLDIST_VLSS4=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS4);

if($result_marketstock_VLDIST_VLSS4[0]!=NULL){
$Total_available_stock_VLSS4=number_format($result_marketstock_VLDIST_VLSS4[0],2,'.','');
}else{$Total_available_stock_VLSS4="0";}
$Total_available_stock123_VLSS4+=$Total_available_stock_VLSS4;

if($Total_available_stock_VLSS4>0)
{
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                            <td><?=$resultRcd_VLSS4["useridtext"];?></td>
							<td><?=$resultRcd_VLSS4["name"];?></td>
							<td><?=$resultRcd_VLSS4["mobile_number"];?></td>
							<td><?=$district_name_VLSS4;?></td>
							<td><?=$taluk_name_VLSS4;?></td>
							<td align="right"><?=$Total_available_stock_VLSS4;?></td>
				
                                        </tr>
                                           
										<?php }?>
										<?php }?>
										 
										 <tfoot>
										 <tr>
										 <td colspan="6">Grand Total</td>
				<td align="right"><b><?=number_format($Total_available_stock123_VLSS4,2,'.','');?></b></td>
										 </tr>
										 </tfoot>
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