<?php include("checksession.php");
include("config.php");
error_reporting(0);

$Report_LABLE="Channelwise Sales";

if($_REQUEST['lable']==1 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Today";}
else if($_REQUEST['lable']==2 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="Yesterday";}
else if($_REQUEST['lable']==3 && $_REQUEST['rptlable']==1)
{$DISPLAY_LABLE="This Month";}
else
{$DISPLAY_LABLE="Last Month till date";}

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
									<td><a href="export_report3?frdate=<?=$from_date;?>&&todate=<?=$to_date;?>&&lable=<?=$_REQUEST['lable'];?>&&rptlable=<?=$_REQUEST['rptlable'];?>&&out1=<?=$_REQUEST['out1'];?>&&out2=<?=$_REQUEST['out2'];?>&&out3=<?=$_REQUEST['out3'];?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
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
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$_REQUEST['out1'];?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$_REQUEST['out2'];?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$_REQUEST['out3'];?></td>
												</tr>
												</table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
							</div>
						<?php }?>
						
						
						<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
<input type="hidden" name="lable" value="<?=$_REQUEST['lable'];?>"/>
<input type="hidden" name="rptlable" value="<?=$_REQUEST['rptlable'];?>"/>
<input type="hidden" name="out1" value="<?=$_REQUEST['out1'];?>"/>
<input type="hidden" name="out2" value="<?=$_REQUEST['out2'];?>"/>
<input type="hidden" name="out3" value="<?=$_REQUEST['out3'];?>"/>
<input type="hidden" name="setrigger" value="1"/>

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
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
                                                    <th>Company Profile</th>
													<th>Category</th>
													<th>Date</th>
													<th>Total Amount</th>
													<th>Product Qty</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php
										$select_Count_records="select count(*) as numRecords from ot_sales where date between '$from_date' and '$to_date'";
										$fetch_Count_records=mysqli_query($db_conn,$select_Count_records);
										$result_Count_records=mysqli_fetch_array($fetch_Count_records);
										if($result_Count_records['numRecords']>0)
										{
										
										
										$select_product_list="select distinct tempid from ot_sales where date between '$from_date' and '$to_date' order by id asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											$ot_tempid=$result_product_list['tempid'];

										$selectrecords="select * from ot_sales where tempid='$ot_tempid'";
										$fetchrecords=mysqli_query($db_conn,$selectrecords);
										$resultrecords=mysqli_fetch_array($fetchrecords);
											
											//company profile details
											$godownid=$resultrecords['godownid'];
$select_Customers="select * from company_godown where id='$godownid'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?=$result_Customers["gname"];?></td>
													<td><?=$resultrecords['cat'];?></td>
													<td><?=date("d/M/Y",strtotime($resultrecords["date"]));?></td>
													
				<?php
				$selectsumTotalAmount="select sum(total) from ot_sales where tempid='$ot_tempid'";
				$fetchsumTotalAmount=mysqli_query($db_conn,$selectsumTotalAmount);
				$resultsumTotalAmount=mysqli_fetch_array($fetchsumTotalAmount);
				if($resultsumTotalAmount[0]!=NULL){
				$TotalAmount=$resultsumTotalAmount[0];}else{$TotalAmount="0";}
				$TotalAmount123+=$TotalAmount;
				
				$selectsumTotalQTY="select sum(qty) from ot_sales where tempid='$ot_tempid'";
				$fetchsumTotalQTY=mysqli_query($db_conn,$selectsumTotalQTY);
				$resultsumTotalQTY=mysqli_fetch_array($fetchsumTotalQTY);
				if($resultsumTotalQTY[0]!=NULL){
				$TotalPrQty=$resultsumTotalQTY[0];}else{$TotalPrQty="0";}
				$TotalPrQty123+=$TotalPrQty;
				?>
				<td align="right"><?php echo number_format($TotalAmount,2,'.','');?></td>
				<td align="right"><?=$TotalPrQty;?></td>
                                        </tr>
                                        
                                        
                                           
										<?php }?>
										<?php }?>
										
										</tbody>
										 
										 <tfoot>
										 <tr>
										 <td colspan="4">Grand Total</td>
				<td align="right"><b><?php echo number_format($TotalAmount123,2,'.','');?></b></td>
				<td align="right"><b><?=$TotalPrQty123;?></b></td>
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