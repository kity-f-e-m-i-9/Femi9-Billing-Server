<?php include("checksession.php");
$title="GST Report";
date_default_timezone_set("Asia/Kolkata");
$current_date=date("Y-m-d");
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
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">

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
									<td><?php echo $title;?></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    
                                    <div class="card-body">
									
									<?php
									if($_REQUEST['fromdate']!=NULL)
									{
									$get_from_date=$_REQUEST['fromdate'];
									$get_to_date=$_REQUEST['todate'];
									}else{
									$get_from_date=date("Y-m-d");
									$get_to_date=date("Y-m-d");	
										
									}
									?>
									
<form action="<?=$_SERVER['PHP_SELF'];?>" method="post" enctype="multipart/form-data">

                                        <div class="example-container">
                                            <div class="example-content">
							   
						<label for="exampleInputEmail1" class="form-label">From Date</label>
						<input type="date" name="fromdate" value="<?=$get_from_date;?>" required="" class="form-control">
							   
						<label for="exampleInputEmail1" class="form-label">To Date</label>
						<input type="date" name="todate" required="" value="<?=$get_to_date;?>" class="form-control">
						<br/>
												
				<button type="submit" name="search-network" class="btn btn-primary">
				<i class="material-icons">search</i>Search</button>
												
                                            </div>
                                        </div>
										
										</form>
										<br/>
										
										<table width="100%" class="ReportTablevl">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Date</th>
                                                    <th>Invoice Number</th>
													<th style="text-align:right;">Total Amount</th>
													<th style="text-align:right;">CGST</th>
													<th style="text-align:right;">SGST</th>
													<th style="text-align:right;">IGST</th>
                                                </tr>
                                            </thead>
											 <tbody>
										<?php 
		$select_picnode_list="select * from user_invoice where date between '$get_from_date' and '$get_to_date' order by id asc";
		$fetch_picnode_list=mysqli_query($db_conn,$select_picnode_list);
		while($result_pincode_list=mysqli_fetch_array($fetch_picnode_list))
							{
											$inv_id=$result_pincode_list['inv_id'];
											$gst_type=$result_pincode_list['gst_type'];
											
											//get product details
$select_productsitems="select sum(qty) from stock_request_items where reqid='$reqid'";
$fetch_productsitems=mysqli_query($db_conn,$select_productsitems);
$result_prodcutsitems=mysqli_fetch_array($fetch_productsitems);

//Total Amount
$select_sum_amount="select sum(total) from user_invoice_items where inv_id='$inv_id'";
$fetch_sum_amount=mysqli_query($db_conn,$select_sum_amount);
$result_sum_amount=mysqli_fetch_array($fetch_sum_amount);
$Totalbillamount=$result_sum_amount[0];

//Total gst Amount
$select_sum_amountGST="select sum(gstamount_total) from user_invoice_items where inv_id='$inv_id'";
$fetch_sum_amountGST=mysqli_query($db_conn,$select_sum_amountGST);
$result_sum_amountGST=mysqli_fetch_array($fetch_sum_amountGST);
$TotalbillamountGST=$result_sum_amountGST[0];

$TotalBillAmountFinally=$Totalbillamount-$TotalbillamountGST;


	$SGST=$TotalbillamountGST/2;	
$CGST=$TotalbillamountGST/2;		
									 
?>


                                      <tr>
									  
                        <td><?=$i=$i+1; ?></td>
						<td><?=date("d/m/Y",strtotime($result_pincode_list['date']));?></td>
                        <td><?=$result_pincode_list['inv_number'];?></td>
						<td style="text-align:right;"><?=inr_format($TotalBillAmountFinally, 2);?></td>
						<td style="text-align:right;">
						<?php if($gst_type=="inner"){ echo inr_format($CGST, 2); }?>
						</td>
						<td style="text-align:right;">
						<?php if($gst_type=="inner"){ echo inr_format($SGST, 2); }?>
						</td>
						<td style="text-align:right;">
						<?php if($gst_type!="inner"){ echo inr_format($TotalbillamountGST, 2); }?>
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
    </div>

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>