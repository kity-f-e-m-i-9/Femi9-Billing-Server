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
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Stock Return Pending : <?php echo $business_name;?></title>

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
							
							<?php if(isset($_REQUEST['updatedsuccess'])){?><div class="alert alert-success">Status Updated success.</div><?php }?>
							
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Stock Return Pending</td>
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
													<th>Return From</th>
													<th>Return Qty</th>
													<th>Total Amount</th>
													<th>Details</th>
                                                </tr>
                                            </thead>
											
											<tbody>
					<?php $select_product_list="select * from user_return_stock where to_usertype='$Login_user_TYPEvl' and status='pending' order by id desc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											$returnid=$result_product_list['returnid'];
											$totalamount=$result_product_list['total'];
											
											$fromusertype=$result_product_list['from_usertype'];
											$fromuserid=$result_product_list['from_userid'];
											
											if($fromusertype=="distributor")
											{
											$tablename="distributor";
											}
											if($fromusertype=="stockiest")
											{
												$tablename="stockiest";
											}
											if($fromusertype=="super_stockiest")
											{
												$tablename="super_stockiest";
											}
											if($fromusertype=="outlet")
											{
												$tablename="outlet";
											}
											
											
											//get distributor details
				$select_distlist="select * from ".$tablename." where temp_id='$fromuserid'";
				$fetch_distlist=mysqli_query($db_conn,$select_distlist);
				$result_distlist=mysqli_fetch_array($fetch_distlist);
				$distributorname=$result_distlist['name'];
				
				//get qty total
				$select_qtytotal="select sum(qty) from user_return_stock_items where returnid='$returnid'";
				$fetch_qtytotal=mysqli_query($db_conn,$select_qtytotal);
				$result_qtytotal=mysqli_fetch_array($fetch_qtytotal);
				$totalqty=$result_qtytotal[0];
				?>
  <tr valign="top">
                                                    <td><?php echo ++$i; ?></td>
                                 <td><?php echo date("d/m/Y",strtotime($result_product_list["date"]));?></td>
								 
								<td><b><?=strtoupper($result_distlist['name']);?></b>
								<br/>
								<span style="font-size:13px;color:#999;"><?=strtoupper($fromusertype);?></span>
								</td>	
								
<td><?=$totalqty;?></td>		
<td><?=number_format($totalamount,2,'.','');?></td>						
													
													<td>
			<a href="stock_return_details?returnid=<?=base64_encode($returnid);?>&&pagename=stock_return_pending" data-bs-toggle="tooltip" data-bs-placement="top" title="View Details">
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