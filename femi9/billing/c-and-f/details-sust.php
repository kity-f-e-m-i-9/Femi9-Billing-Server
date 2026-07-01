<?php include("checksession.php");

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from super_stockiest where id='$get_id'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
$result_product_list=mysqli_fetch_array($fetch_product_list);
				
//state details
$state_id=$result_product_list['state_id'];
$select_state_dtails="select * from state where id='$state_id'";
$fetch_state_dtails=mysqli_query($db_conn,$select_state_dtails);
$result_state_dtails=mysqli_fetch_array($fetch_state_dtails);
$state_name=$result_state_dtails['st_name'];

//district details
$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
					?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo ucwords($result_product_list['name']);?> : Super Stockist</title>

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
		<style type="text/css">
		#usernamebox{background:#c6ff54;font-weight:bold;padding:5px;border-radius:5px;letter-spacing:1px;}
		</style>
</head>

<body>
    
                        <div style="padding:20px;">
						 <h1>Details : Super Stockist
									</h1>
									<hr/>
</div>

 <table class="table">
  <?php if($result_product_list["user_icon"]!="Nil"){ ?>
 <tr>
                    <th colspan="2"><img src="<?php echo $result_product_list["user_icon"];?>" style="width:150px;border-radius:10px;"></th>
                    </tr>
 <?php } ?>
 <tr>
                    <th scope="col">Account Status</th>
                    <td>
					<?php 
			if($result_product_list['account_status']=="pending")
			{
			echo "<span class='badge badge-style-bordered badge-danger'>Pending</span>";
			}
			else if($result_product_list['account_status']=="active")
			{
			echo "<span class='badge badge-style-bordered badge-success'>Active</span>";
			}else{
				echo "<span class='badge badge-style-bordered badge-danger'>Deactive</span>";
			}
			?>
			</td>
                    </tr>
                    <tr>
                    <th scope="col">Name</th>
                    <td><?php echo $result_product_list['name'];?></td>
                    </tr>
					<tr>
                    <th scope="col">State</th>
                    <td><?php echo $state_name;?></td>
                    </tr>
					<tr>
                    <th scope="col">District</th>
                    <td><?php echo $district_name;?></td>
                    </tr>
					
					<tr>
                    <th scope="col">Email ID</th>
                    <td><?php echo $result_product_list['email'];?></td>
                    </tr>
					
					<tr>
                    <th scope="col">Address</th>
                    <td><?php echo $result_product_list['address'];?></td>
                    </tr>
					<tr>
                    <th scope="col">Mobile Number</th>
                    <td><?php echo $result_product_list['country_code'];?>&nbsp;<?php echo $result_product_list['mobile_number'];?></td>
                    </tr>
					
					<tr>
                    <th scope="col">Username</th>
                    <td><span id="usernamebox"><?php echo $result_product_list['username'];?></span></td>
                    </tr>
					
					<tr>
                    <th scope="col">Password</th>
                    <td><?php echo $result_product_list['password'];?></td>
                    </tr>
					
					 <tr>
                    <th scope="col">GST Number</th>
                    <td><?php echo $result_product_list['gstin'];?></td>
                    </tr>
					
					
					<?php /*?>
					<tr>
                    <th colspan="2"><hr/></th>
                    </tr>
					
					<tr>
                    <th scope="col">Plan Amount</th>
                    <td>&#8377; <?php echo $result_product_list['plan_amount'];?></td>
                    </tr>
					
					<tr>
                    <th scope="col">Valid from - to</th>
                    <td><?php echo date("d/m/Y",strtotime($result_product_list['valid_from']));?> (to) <?php echo date("d/m/Y",strtotime($result_product_list['valid_to']));?></td>
                    </tr>
					
					<tr>
                    <th scope="col">payment Method</th>
                    <td><?php echo $result_product_list['amount_method'];?></td>
                    </tr>
					
					<tr>
                    <th scope="col">payment Status</th>
                    <td>
					<?php 
			if($result_product_list['amount_status']=="pending")
			{
			echo "<span class='badge badge-style-bordered badge-danger'>Pending</span>";
			}
			else
			{
				echo "<span class='badge badge-style-bordered badge-success'>Paid</span>";
			}
			?>
			</td>
                    </tr>
					
					<tr>
                    <th scope="col">Reference Number</th>
                    <td><?php echo $result_product_list['ref_number'];?></td>
                    </tr>
					<?php */?>
					
                                                   
</table>



<?php /*?>
<h1>Overall Stock</h1>
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
			$stock_usertype="super_stockiest";
			$stock_userid=$result_product_list['temp_id'];
			
echo $select_OPStock="select * from stock where user_type='$stock_usertype' and user_id='$stock_userid'";
										$Fetch_OPStock=mysqli_query($db_conn,$select_OPStock);
										while($Result_OPStock=mysqli_fetch_array($Fetch_OPStock))
										{
<?php /*?>
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
										$select_sumclosing="select sum(closing_qty) from stock where user_type='$stock_usertype' and user_id='$stock_userid'";
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
										<?php */?>

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