<?php include("checksession.php");
include("config.php");
 error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage Stock Return : <?php echo $business_name;?></title>

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
								
								<?php if(isset($_REQUEST['deletedone'])){?><div class="alert alert-warning">Deleted ! One Stock Return Details Deleted Success.</div><?php }?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage Stock Return</td>
									<td><a href="stock-return-add.php" title="Add Stockist">&#10011;</a></td>
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
                                   <div class="card-body" style="overflow:scroll !important;">
                              
<table id="datatable1">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
													<th>Invoice Number</th>
													<th>Return Date</th>
													<th>Return Qty</th>
													<th>Sub Total</th>
													<th>Discount</th>
													<th>Total</th>
													<th>Status</th>
													<th>&nbsp;</th>
													<th>&nbsp;</th>
                                                </tr>
                                            </thead>
											
											<tbody>
				<?php $select_product_list="select * from user_return_stock where from_usertype='$Login_user_TYPEvl' and from_userid='$Login_user_IDvl' order by id asc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											$returnid=$result_product_list['returnid'];
											
				//count return Qty
				$select_returnQTY="select sum(qty) from user_return_stock_items where returnid='$returnid'";
				$fetch_returnQTY=mysqli_query($db_conn,$select_returnQTY);
				$result_returnQTY=mysqli_fetch_array($fetch_returnQTY);
				$TotalReturnqty=$result_returnQTY[0];
											
											
						?>
                                            
                                                <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=$result_product_list['invnumber'];?></td>
					<td><?=date("d/m/Y",strtotime($result_product_list['date']));?></td>
					<td><?=$TotalReturnqty;?></td>
					<td><?=number_format($result_product_list['subtotal'],2,'.','');?></td>
					<td><?=number_format($result_product_list['discount'],2,'.','');?></td>
					<td><?=number_format($result_product_list['total'],2,'.','');?></td>
					
					<td>
			<?php 
			if($result_product_list['status']=="pending")
			{
			echo "<span class='badge badge-style-bordered badge-danger'>Pending</span>";
			}
			else if($result_product_list['status']=="accept")
			{
			echo "<span class='badge badge-style-bordered badge-success'>Accept</span>";
			}else{
				echo "<span class='badge badge-style-bordered badge-danger'>Rejected</span>";
			}
			?>
</td>

<td>

			<a href="stock-return-details.php?returnid=<?=$returnid;?>" data-bs-toggle="tooltip" data-bs-placement="top" title="View Details">
			<img src="../../assets/images/details-32.png"/></a>
			</td>
			
			<td>
			<?php if($result_product_list['status']=="pending"){?>
<a href="stock_return_delete2.php?returnid=<?=base64_encode($returnid);?>"onclick="return confirm('You want to delete confirm?');"><img src="../../assets/images/delete-32.png"/></a>
<?php }else{ echo "-";}?>
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