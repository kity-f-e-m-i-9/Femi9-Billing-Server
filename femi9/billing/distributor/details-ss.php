<?php include("checksession.php");
error_reporting(0);

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from shop where id='$get_id'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				$result_product_list=mysqli_fetch_array($fetch_product_list);
				//
				$shpid=$result_product_list['temp_id'];
				
				//last invoice dteails
				$getlast_invoice="select * from user_invoice where to_user_id='$shpid' order by id desc";
				$fetch_lstinvoice=mysqli_query($db_conn,$getlast_invoice);
				$result_lastinvoicedetails=mysqli_fetch_array($fetch_lstinvoice);
				//
				$inv_number=$result_lastinvoicedetails['inv_number'];
				$invid=$result_lastinvoicedetails['inv_id'];
				$invdate=$result_lastinvoicedetails['date'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <!-- Title -->
    <title><?php echo ucwords($result_product_list['name']);?> : Shop</title>

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
		<style>
		#customersjdk{width:100%;}
		#customersjdk th{padding:5px;border:1px solid #999;}
		#customersjdk td{padding:5px;border:1px solid #999;}
		</style>
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
									<td>Details : Shop</td>
									<td><a href="manage_ss.php">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									<div style="background:#fff;overflow:scroll;width:100%;">
                             
							 <h1>Last Invoice Details</h1>
							 <table>
							 <tr>
							 <td colspan="2">Shop Name</td>
							 </tr>
							 <tr><td colspan="2" style="font-size:18px;"><b><?=$result_product_list['name'];?></b><hr/></td></tr>
							 
							 <?php if($invid!=NULL){?>
							 <tr>
							 <td colspan="2">Invoice Number</td>
							 </tr>
							 <tr><td colspan="2" style="font-size:18px;"><b><?=$inv_number;?></b>
							 <hr/>
							 </td></tr>
							 
							 <tr>
							 <td colspan="2">Invoice Date</td>
							 </tr>
							 <tr>
							 <td colspan="2" style="font-size:18px;"><b><?=date("d/M/Y",strtotime($invdate));?></b>
							 </td></tr>
							 <?php }?>
							 
							 </table>
							 <br/>
							 <hr/>
							 
							 <?php if($invid!=NULL){?>
							 <h1>Current Stock</h1>
							 <table id="customersjdk">
							 <tr>
							 <th>Product</th>
							 <th>Qty</th>
							 </tr>
							 <?php 
					$select_productCurrentst="select * from shop_current_stock where inv_id='$invid' and shop_id='$shpid' order by id asc";
					$fetch_productCurrentst=mysqli_query($db_conn,$select_productCurrentst);
					while($result_productCurrentst=mysqli_fetch_array($fetch_productCurrentst))
										{
											//
											$prid=$result_productCurrentst['prid'];
											//
											$selectprdetails="select * from products where id='$prid'";
											$fetchprdetails=mysqli_query($db_conn,$selectprdetails);
											$resultprdetails=mysqli_fetch_array($fetchprdetails);
											//
											$crntstock=$result_productCurrentst['qty'];
											$crntstock123+=$crntstock;
										?>
							  <tr>
							 <td><?=$resultprdetails['productName'];?></td>
							 <td align="right"><?=$crntstock;?></td>
							 </tr>
										<?php }?>
										<tr>
										<td></td>
										<td align="right"><?=$crntstock123;?></td>
										</tr>
							 
							 </table>
							 
							 
							 
							 <br/>
							 <h1>Competitor Stock</h1>
							 <table id="customersjdk">
							 <tr>
							 <th>Brand</th>
							 <th>Qty</th>
							 <th>Pantyliner</th>
							 </tr>
							 <?php 
					$select_productCurrentst12="select * from shop_competitor_stock where inv_id='$invid' and shop_id='$shpid' order by id desc";
					$fetch_productCurrentst12=mysqli_query($db_conn,$select_productCurrentst12);
					while($result_productCurrentst12=mysqli_fetch_array($fetch_productCurrentst12))
										{
											//
											$prid=$result_productCurrentst12['brandid'];
											//
											$selectprdetails12="select * from competitor_brand where id='$prid'";
											$fetchprdetails12=mysqli_query($db_conn,$selectprdetails12);
											$resultprdetails12=mysqli_fetch_array($fetchprdetails12);
											//
											$crntstock12=$result_productCurrentst12['qty'];
											$crntstock123cst+=$crntstock12;
										?>
							  <tr>
							 <td><?=$resultprdetails12['brand'];?></td>
							 <td align="right"><?=$crntstock12;?></td>
							 <td><?=$result_productCurrentst12['cst_panty'];?></td>
							 </tr>
										<?php }?>
										<tr>
										<td></td>
										<td align="right"><?=$crntstock123cst;?></td>
										<td></td>
										</tr>
							 
							 </table>
							 
							 <?php }else{?>
							 <span style="color:red;">No Records Found !!!</span>
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