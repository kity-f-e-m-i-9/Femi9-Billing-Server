<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('stock_request');
include("config.php");
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
    <title>Stock Request Billed : <?php echo $business_name;?></title>

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
									<td>Stock Request Billed</td>
									</tr>
									</table>
									</h1>
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
									<div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Date</th>
													<th>Candidate</th>
													<th>Billed Qty</th>
													<th>Invoice Number</th>
													<th>Invoice Date</th>
													<th>Details</th>
                                                </tr>
                                            </thead>
											
											<tbody>
					<?php $select_product_list="select * from stock_request where touserid='$Login_user_IDvl' and tousertype='$Login_user_TYPEvl' and status='billed' order by id desc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											$reqid=$result_product_list['reqid'];
											
											
											$fromusertype=$result_product_list['fromusertype'];
											$fromuserid=$result_product_list['fromuserid'];
											
											if($fromusertype=="distributor")
											{
											$tablename="distributor";
											}
											else if($fromusertype=="stockiest")
											{
												$tablename="stockiest";
											}
											else if($fromusertype=="super_stockiest")
											{
												$tablename="super_stockiest";
											}
											else 
											{
												$tablename="outlet";
											}
											
											
											//get distributor details
				$select_distlist="select * from ".$tablename." where temp_id='$fromuserid'";
				$fetch_distlist=mysqli_query($db_conn,$select_distlist);
				$result_distlist=mysqli_fetch_array($fetch_distlist);
				if($result_distlist['name']!=NULL)
				{
					$candidate_name_show=strtoupper($result_distlist['name']);
				}else{$candidate_name_show="---";}
				
				//get qty total
				$select_qtytotal="select sum(qty) from stock_request_items where reqid='$reqid'";
				$fetch_qtytotal=mysqli_query($db_conn,$select_qtytotal);
				$result_qtytotal=mysqli_fetch_array($fetch_qtytotal);
				$totalqty=$result_qtytotal[0];
				
				//invoice details
				$select_invDetails="select * from user_invoice where inv_id='$reqid'";
				$fetch_invDetails=mysqli_query($db_conn,$select_invDetails);
				$result_invDetails=mysqli_fetch_array($fetch_invDetails);
				?>
  <tr valign="top">
                                                    <td><?php echo ++$i; ?></td>
                                 <td><?php echo date("d/m/Y",strtotime($result_product_list["date"]));?></td>
								<td><b><?=$candidate_name_show;?></b>
								<br/>
								<span style="font-size:13px;color:#999;"><?=strtoupper($fromusertype);?></span>
								</td>		
<td><?=$totalqty;?></td>							

<td><?=$result_invDetails['inv_number'];?></td>	
<td><?=date("d/m/Y",strtotime($result_invDetails['date']));?></td>	
													
													<td>
			<a href="stock_request_details2?reqid=<?=base64_encode($reqid);?>" data-bs-toggle="tooltip" data-bs-placement="top" title="View Details">
			<img src="../../assets/images/details-32.png"/></a>
			</td>
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
                                        </table>
                                    
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