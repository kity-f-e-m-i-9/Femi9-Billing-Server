<?php include("checksession.php");
$title="Receipt";
date_default_timezone_set("Asia/Kolkata");
$current_date=date("Y-m-d");
error_reporting(0);

$invid=$_REQUEST['invid'];
$title="Invoice Payment Details";
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
									<td><a href="purchasebill" title="Go Back">&#9776;</a></td>
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
									<h1>Invoice Details</h1>
									<table id="receipttble" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Inv Number</th>
													<th>Date</th>
													<th>Sub Total</th>
													<th>Discount</th>
													<th>Total</th>
												</tr>
                                            </thead>
											
											<tbody>
					<?php $select_product_list="select * from user_invoice where inv_id='$invid'";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										$result_product_list=mysqli_fetch_array($fetch_product_list);
											//customer details
											$CuSTID=$result_product_list['to_user_id'];
											
											$RowID_encode=base64_encode($result_product_list["id"]);
											$INVID_encode=base64_encode($result_product_list["inv_id"]);
											

?>
                                            
                                                <tr>
                                                    <td><?php echo $result_product_list["inv_number"];?></td>
													<td><?php echo date("d/M/Y",strtotime($result_product_list["date"]));?></td>
													
				<td><?php echo number_format($result_product_list["sub_total"],2,'.','');?></td>
				<td><?php
$discount=$result_product_list["discount"]+$result_product_list["credit"];
				echo number_format($discount,2,'.','');?>
				</td>
	
				<td><?php echo number_format($result_product_list["total"],2,'.','');?></td>
													
                                        </tr>
										</tbody>
                                        </table>
										
										<h1>Payment Details</h1>
										<table id="receipttble" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Date</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
											 <tbody>
										<?php 
		$select_picnode_list="select * from receipt where inv_id='$invid' order by id asc";
		$fetch_picnode_list=mysqli_query($db_conn,$select_picnode_list);
		while($result_pincode_list=mysqli_fetch_array($fetch_picnode_list))
							{
								$receiptamount=$result_pincode_list['received'];
								$receiptamount123+=$receiptamount;	
if($receiptamount>0){								
?>
                                      <tr>
									  
                        <td><?=$i=$i+1; ?></td>
						<td><?=date("d/m/Y",strtotime($result_pincode_list['date']));?></td>
						<td><?=number_format($receiptamount,2,'.','');?></td>
                                      
                                     </tr>
									<?php }?>
									<?php }?>
									</tbody>
									
									<tfoot>
									<tr>
									<td colspan="2" style="text-align:right;font-weight:bold;">Total</td>
									<td style="font-weight:bold;"><?=number_format($receiptamount123,2,'.','');?></td>
									</tr>
									</tfoot>
                                    </table>
									
									<?php
		//total received Amount
		$selectsumreceiptamont="select sum(received) from receipt where inv_id='$invid'";
		$fetchsumreceiptamont=mysqli_query($db_conn,$selectsumreceiptamont);
		$resultsumreceiptamont=mysqli_fetch_array($fetchsumreceiptamont);
									$TotalReceivedAmount=$resultsumreceiptamont[0];
									$balanceAmount=$result_product_list["total"]-$TotalReceivedAmount;
									
									if($balanceAmount>0)
									{
									?>
				<div align="right"><span class='badge badge-style-bordered badge-danger' style="font-size:22px;">Payable Amount : <?=$balanceAmount;?></span></div>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>