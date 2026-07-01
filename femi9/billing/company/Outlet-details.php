<?php include("checksession.php"); error_reporting(0);

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from outlet where id='$get_id'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
$result_product_list=mysqli_fetch_array($fetch_product_list);
				
//
$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

//
$taluk_id=$result_product_list['taluk_id'];
$select_taluklist="select * from taluk where id='$taluk_id'";
$fetch_taluklist=mysqli_query($db_conn,$select_taluklist);
$result_taluklist=mysqli_fetch_array($fetch_taluklist);
$taluk_name=$result_taluklist['taluk'];

//
$pincode_id=$result_product_list['pincode_id'];
$select_pincodelist="select * from pincode where id='$pincode_id'";
$fetch_pincodelist=mysqli_query($db_conn,$select_pincodelist);
$result_pincodelist=mysqli_fetch_array($fetch_pincodelist);
$pincodeshow=$result_pincodelist['pincode'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo ucwords($result_product_list['name']);?> : Outlet</title>

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
						 <h1>Details : Outlet
									</h1>
									<hr/>
</div>

 <table class="table">
 
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
					
					<?php if($result_product_list["user_icon"]!="Nil"){$imgsrcname="".$result_product_list["user_icon"]."";}else{$imgsrcname="../../assets/images/no image.jpg";}?>
					<tr>
                    <th scope="col">Candidate Photo</th>
                    <td><img src="<?php echo $imgsrcname;?>" style="width:150px;"/></td>
                    </tr>
  
                    <tr>
                    <th scope="col">Name</th>
                    <td><?php echo $result_product_list['name'];?></td>
                    </tr>
					
					<tr>
                    <th scope="col">District</th>
                    <td><?php echo $district_name;?></td>
                    </tr>
					<tr>
                    <th scope="col">Taluk</th>
                    <td><?php echo $taluk_name;?></td>
                    </tr>
					<tr>
                    <th scope="col">Pincode</th>
                    <td><?php echo $pincodeshow;?></td>
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
                    <td><?php echo $result_product_list['mobile_number'];?></td>
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
                    <th scope="col">Valid Months</th>
                    <td><?php echo $result_product_list['valid_months'];?> Months</td>
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