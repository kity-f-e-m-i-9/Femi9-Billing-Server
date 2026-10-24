<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

$Report_LABLE="Onboarded Count";

if($_REQUEST['lable']==1 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Super Stockist";
$tablename="super_stockiest";}

else if($_REQUEST['lable']==2 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Stockist";
$tablename="stockiest";}

else if($_REQUEST['lable']==3 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Distributors";
$tablename="distributor";}

else if($_REQUEST['lable']==4 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Shop (Retailers)";
$tablename="shop";}

else if($_REQUEST['lable']==5 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Super Distributors";
$tablename="super_distributor";}

else
{$DISPLAY_LABLE="Outlet";
$tablename="outlet";}

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];
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
									<td><a href="export_report4?frdate=<?=htmlspecialchars((string)($from_date), ENT_QUOTES, 'UTF-8', false);?>&&todate=<?=htmlspecialchars((string)($to_date), ENT_QUOTES, 'UTF-8', false);?>&&lable=<?=htmlspecialchars((string)($_REQUEST['lable']), ENT_QUOTES, 'UTF-8', false);?>&&rptlable=<?=htmlspecialchars((string)($_REQUEST['rptlable']), ENT_QUOTES, 'UTF-8', false);?>&&out1=<?=htmlspecialchars((string)($_REQUEST['out1']), ENT_QUOTES, 'UTF-8', false);?>&&out2=<?=htmlspecialchars((string)($_REQUEST['out2']), ENT_QUOTES, 'UTF-8', false);?>&&out3=<?=htmlspecialchars((string)($_REQUEST['out3']), ENT_QUOTES, 'UTF-8', false);?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<?php if($_REQUEST['setrigger']==NULL){?>
						<div class="row">
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title"><?=$DISPLAY_LABLE;?></span>
												<table id="reportdash">
												<tr>
												<th>Today</th>
												<td>:&nbsp;<?=htmlspecialchars((string)($_REQUEST['out1']), ENT_QUOTES, 'UTF-8', false);?></td>
												</tr>
												<tr>
												<th>This&nbsp;Month</th>
												<td>:&nbsp;<?=htmlspecialchars((string)($_REQUEST['out2']), ENT_QUOTES, 'UTF-8', false);?></td>
												</tr>
												<tr>
												<th>Total</th>
												<td>:&nbsp;<?=htmlspecialchars((string)($_REQUEST['out3']), ENT_QUOTES, 'UTF-8', false);?></td>
												</tr>
												</table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
							</div>
						<?php }?>
						
						<form method="post" enctype="multipart/form-data" action="<?=htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES);?>">
<input type="hidden" name="lable" value="<?=htmlspecialchars((string)($_REQUEST['lable']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="rptlable" value="<?=htmlspecialchars((string)($_REQUEST['rptlable']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="out1" value="<?=htmlspecialchars((string)($_REQUEST['out1']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="out2" value="<?=htmlspecialchars((string)($_REQUEST['out2']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="out3" value="<?=htmlspecialchars((string)($_REQUEST['out3']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="setrigger" value="1"/>

<div class="overviewcontainar">
<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=htmlspecialchars((string)($from_date), ENT_QUOTES, 'UTF-8', false);?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=htmlspecialchars((string)($to_date), ENT_QUOTES, 'UTF-8', false);?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>

							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>	
							
							
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
                                                    <th>ID</th>
													<th>Name</th>
													<th>District</th>
													<th>Mobile Number</th>
													<th>Date of Reg</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $select_product_list="select * from ".$tablename." where valid_from between '".db_esc($from_date)."' and '".db_esc($to_date)."' order by id asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											
$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id=?";
	$fetch_district=db_q($db_conn,$select_distict,[$district_id]);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
											?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
													<td><?=$result_product_list["useridtext"];?></td>
                                                    <td><?=$result_product_list["name"];?></td>
													<td><?=$district_name;?></td>
													<td><?=$result_product_list['mobile_number'];?></td>
													<td><?=date("d/M/Y",strtotime($result_product_list["valid_from"]));?></td>
													</tr>
										<?php }?>
										</tbody>
				
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