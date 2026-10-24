<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

$Report_LABLE="Competetion Stock Report";

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
									<td><a href="export_report_competition?modelval=<?=htmlspecialchars((string)($_REQUEST['modelval']), ENT_QUOTES, 'UTF-8', false);?>&&out1=<?=htmlspecialchars((string)($_REQUEST['out1']), ENT_QUOTES, 'UTF-8', false);?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
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
											
						<a href="#" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Shop</span>
                                                <span class="widget-stats-amount"><?=htmlspecialchars((string)(base64_decode($_REQUEST['out1'])), ENT_QUOTES, 'UTF-8', false);?></span>
                                            </div>
											</a>
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
									
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Shop ID</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Available Stock</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $selectRcd_VLSS1="select * from temp_competerion_stock_report where onboard_userTYPE=? and onboard_userID=? order by id asc";
										$fetchRcd_VLSS1=db_q($db_conn,$selectRcd_VLSS1,[$Login_user_TYPEvl,$Login_user_IDvl]);
										while($resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1))
										{
											
										$shop_id=$resultRcd_VLSS1['shop_id'];

										$selectrecords_VLSS1="select * from shop where temp_id=?";
										$fetchrecords_VLSS1=db_q($db_conn,$selectrecords_VLSS1,[$shop_id]);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										
										$state_id_VLSS2=$resultrecords_VLSS1['state_id'];
										$district_id_VLSS2=$resultrecords_VLSS1['district_id'];
										$taluk_id_VLSS2=$resultrecords_VLSS1['taluk_id'];

										$selectrecords_VLSS2="select * from district where state_id=? and id=?";
										$fetchrecords_VLSS2=db_q($db_conn,$selectrecords_VLSS2,[$state_id_VLSS2,$district_id_VLSS2]);
										$resultrecords_VLSS2=mysqli_fetch_array($fetchrecords_VLSS2);
										$district_name_VLSS2=$resultrecords_VLSS2['dist_name'];
										
$selectrecords_VLSS2_taluk="select * from taluk where state_id=? and dist_id=? and id=?";
$fetchrecords_VLSS2_taluk=db_q($db_conn,$selectrecords_VLSS2_taluk,[$state_id_VLSS2,$district_id_VLSS2,$taluk_id_VLSS2]);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];

$available_stock=$resultRcd_VLSS1['qty'];
$available_stock133+=$available_stock;
?>

                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                            <td><?=htmlspecialchars((string)($resultrecords_VLSS1["useridtext"]), ENT_QUOTES, 'ISO-8859-1', false);?></td>
							<td><?=htmlspecialchars((string)($resultrecords_VLSS1["name"]), ENT_QUOTES, 'ISO-8859-1', false);?></td>
							<td><?=htmlspecialchars((string)($resultrecords_VLSS1["mobile_number"]), ENT_QUOTES, 'ISO-8859-1', false);?></td>
							<td><?=$district_name_VLSS2;?></td>
							<td><?=$taluk_name_VLSS2;?></td>
							<td align="right"><?=$available_stock?></td>
				
                                        </tr>
                                           
										<?php }?>
										 
										 <tfoot>
										 <tr>
										 <td colspan="6">Grand Total</td>
							<td align="right"><b><?=$available_stock133;?></b></td>
										 </tr>
										 </tfoot>
                                        </table>
										
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