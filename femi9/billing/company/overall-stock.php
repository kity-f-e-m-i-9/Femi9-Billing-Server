<?php include("checksession.php"); require_once("include/GodownAccess.php");
error_reporting(0);
$user_type_Loginvl="company";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Overall Stocks : <?php echo $business_name;?></title>

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
								<?php 
								$select_sumclosing12="select sum(closing_qty) from stock where user_type='$user_type_Loginvl'";
										$Fetch_sumclosing12=mysqli_query($db_conn,$select_sumclosing12);
										$Result_sumclosing12=mysqli_fetch_array($Fetch_sumclosing12);
										?>
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Overall Stocks : <?=$Result_sumclosing12[0];?> (Qty)</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<form method="post" enctype="multipart/form-data" action="overstock_datewise">

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" class="form-control">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" class="form-control">
</div>
<div id="searchleftcont">
<label class="form-label">Company Profile</label>
                               <select name="godownid" class="form-control">
							   <option value="" hidden="">Select</option>
							   <?php $select_Godown="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						   <option value="<?=$result_Godown['id'];?>"><?=$result_Godown['gname'];?></option>
							   <?php }?>
							   </select>
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
									<div style="background:#fff;overflow:scroll;width:100%;">
									
									<?php
									//get Godown Details
$select_Godowndetails="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
$fetch_Godowndetails=mysqli_query($db_conn,$select_Godowndetails);
while($result_Godown=mysqli_fetch_array($fetch_Godowndetails))
{
?>
									<h1><?=$result_Godown['gname'];?></h1>
									
                                        <table class="table">
                                            <thead>
                                               <tr>
											<th>Product Name</th>
											<th>Opening Stock Qty</th>
											<th>Opening Stock Date</th>
											<th style="text-align:right;">Input Stock Qty</th>
											<th style="text-align:right;">Sales Qty</th>
											<th style="text-align:right;">Sent Qty</th>
											<th style="text-align:right;">Closing Qty</th>
											</tr>
                                            </thead>
											
											<tbody>
			<?php 
$user_id_Loginvl=$result_Godown['id'];

$select_OPStock="select * from stock where user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
										$Fetch_OPStock=mysqli_query($db_conn,$select_OPStock);
										while($Result_OPStock=mysqli_fetch_array($Fetch_OPStock))
										{
											//Get Product Details
											$StockProductID=$Result_OPStock['product_id'];
											
						$select_productDetils="select * from products where id='$StockProductID'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
						
						
										if($Result_productDetils["productName"]!=NULL){
											
						$ClosingStock=$Result_OPStock['closing_qty'];
										?>
                                                <tr>
                                                    <td><?php echo $Result_productDetils["productName"];?></td>
													<td><?php echo $Result_OPStock['opening_qty'];?></td>
													<td><?php echo date("d/M/Y",strtotime($Result_OPStock['opening_date']));?></td>
													
						<!-------PURCHASE QTY------------->
						<td align="right"><?php echo $Result_OPStock['input_qty'];?></td>
						
						<!-------SALES QTY------------->
						<td align="right"><?php echo $Result_OPStock['sales_qty'];?></td>
						
						<!-------INTERNAL TRANSFER + DEMO/FREE/DAMAGE------------->
						<td align="right"><?php echo $Result_OPStock['sent_qty'];?></td>
						
						<td align="right"><b><?php echo $ClosingStock;?></b></td>
													
                                                </tr>
                                           
										<?php }?>
										
										<?php }
										
										//sum total closing qty
										$select_sumclosing="select sum(closing_qty) from stock where user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
										$Fetch_sumclosing=mysqli_query($db_conn,$select_sumclosing);
										$Result_sumclosing=mysqli_fetch_array($Fetch_sumclosing);
											
										?>
										
										 </tbody>
										 
										 <tfoot>
										 <tr>
										<td colspan="6" style="text-align:right;">Total Stock Qty</td>
										<td align="right"><b><?=$Result_sumclosing[0];?></b></td>
										</tr>
										 </tfoot>
										 
                                        </table>
										
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
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
</body>

</html>