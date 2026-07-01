<?php /* include("checksession.php"); error_reporting(0);

$user_type_Loginvl="company";
$user_id_Loginvl="company";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Overall Stock : <?php echo $business_name;?></title>

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
									<td>Overall Stock</td>
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
                                       
                                        <div class="example-container">
                                            <div class="example-content">
											
										<table class="table">
											<thead>
											<tr>
											<th>Product Name</th>
											<th>Opening Stock Qty</th>
											<th>Opening Stock Date</th>
											<th>Input Stock Qty</th>
											<th>Sales Qty</th>
											<th>Sent Qty</th>
											<th>Closing Qty</th>
											</tr>
											</thead>
											
											<tbody>
			<?php $select_OPStock="select * from stock where user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
										$Fetch_OPStock=mysqli_query($db_conn,$select_OPStock);
										while($Result_OPStock=mysqli_fetch_array($Fetch_OPStock))
										{
											//Get Product Details
											$StockProductID=$Result_OPStock['product_id'];
											
						$select_productDetils="select * from products where id='$StockProductID'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
						
						$ClosingStock=$Result_OPStock['closing_qty'];
						$ClosingStock12+=$ClosingStock;
										
										?>
                                                <tr>
                                                    <td><?php echo $Result_productDetils["productName"];?></td>
													<td><?php echo $Result_OPStock['opening_qty'];?></td>
													<td><?php echo date("d/M/Y",strtotime($Result_OPStock['opening_date']));?></td>
													
													<td><?php echo $Result_OPStock['input_qty'];?></td>
													<td><?php echo $Result_OPStock['sales_qty'];?></td>
													<td><?php echo $Result_OPStock['sent_qty'];?></td>
													<td align="right"><?php echo $ClosingStock;?></td>
													
                                                </tr>
                                           
										<?php }?>
										
										<tr>
										<td colspan="6" align="right">Total Stock Qty</td>
										<td align="right"><?php echo $ClosingStock12;?></td>
										</tr>
										
										 </tbody>
                                        </table>
										
												
                                            </div>
                                        </div>
										</form>
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
<?php */?>