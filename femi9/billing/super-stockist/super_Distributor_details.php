<?php include("checksession.php"); error_reporting(0);

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from super_distributor where id='$get_id'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
$result_product_list=mysqli_fetch_array($fetch_product_list);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo ucwords($result_product_list['name']);?> : Super-Distributor</title>

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
						 <h1>Details : Super-Distributor
									</h1>
									<hr/>
</div>

 <table class="table">
 
 
 <?php if($result_product_list["user_icon"]!="Nil"){ ?>
 <tr>
                    <th colspan="2"><img src="../super_distributor/<?php echo $result_product_list["user_icon"];?>" style="width:150px;border-radius:10px;"></th>
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
                    <td><?php echo $result_product_list['state_id'];?></td>
                    </tr>
					<tr>
                    <th scope="col">District</th>
                    <td><?php echo $result_product_list['district_id'];?></td>
                    </tr>
					<tr>
                    <th scope="col">Taluk</th>
                    <td><?php echo $result_product_list['taluk_id'];?></td>
                    </tr>
					<tr>
                    <th scope="col">Pincode</th>
                    <td><?php echo $result_product_list['pincode_id'];?></td>
                    </tr>
					
					<tr>
                    <th scope="col">Shop Onboard</th>
                    <td>
					<?php 
			if($result_product_list['shop_onboard']==1)
			{
			echo "<span class='badge badge-style-bordered badge-success'>Enable</span>";
			}else{
			echo "<span class='badge badge-style-bordered badge-danger'>Disable</span>";
			}
			?>
			</td>
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
					
					
					<!-------------------------------------------------------------------------------------------->
					<!-----------------------------Referral Details:---------------------------------------------->
					<!-------------------------------------------------------------------------------------------->
					
					<?php
$sd_id=$result_product_list['temp_id'];
					
				$Select_ReferralDetails="select * from super_distributor_referral where sd_id='$sd_id'";
				$Fetch_ReferralDetails=mysqli_query($db_conn,$Select_ReferralDetails);
				$Result_ReferralDetails=mysqli_fetch_array($Fetch_ReferralDetails);
				
				$target_amount=$Result_ReferralDetails['target_amount'];
				$referred_user_type=$Result_ReferralDetails['ref_by_user_type'];
				$referred_user_id=$Result_ReferralDetails['ref_by_user_id'];
				
				$Select_STcatdetails="select * from super_distributor_category where amount='$target_amount'";
				$Felect_STcatdetails=mysqli_query($db_conn,$Select_STcatdetails);
				$Relect_STcatdetails=mysqli_fetch_array($Felect_STcatdetails);
				?>
				<tr>
                    <th colspan="2"><hr/></th>
                    </tr>
					<tr>
                    <th colspan="2"><h3>Referral Details</h3></th>
                    </tr>
					
				<tr>
                    <th scope="col">Target Purchase Values(Rs.)</th>
                    <td><?php echo $Relect_STcatdetails['amount'];?></td>
                    </tr>
					<tr>
                    <th scope="col">Referral (%)</th>
                    <td><?php echo $Relect_STcatdetails['ref_commission_percentage'];?>%</td>
                    </tr>
					<tr>
                    <th scope="col">Cashback (%)</th>
                    <td><?php echo $Relect_STcatdetails['cash_back_percentage'];?>%</td>
                    </tr>
					
					<?php
				
				if($target_amount!=NULL)
				{

				if($referred_user_type!="company")
				{
					
				
				if($referred_user_type=="super_distributor"){
		$tblename="super_distributor";
		$lablename="Super Distributor";
		}
		if($referred_user_type=="distributor"){
			$tblename="distributor";
			$lablename="Distributor";
		}
		
		
		$Select_USERdetails="select * from ".$tblename." where useridtext='$referred_user_id'";
				$Fetch_USERdetails=mysqli_query($db_conn,$Select_USERdetails);
				$Retch_USERdetails=mysqli_fetch_array($Fetch_USERdetails);
				//
				$ref_user_name=$Retch_USERdetails['name'];
				$ref_user_mobile=$Retch_USERdetails['mobile_number'];
				
					?>
					<tr>
                    <th scope="col">Referred by</th>
                    <td><?php echo $lablename;?></td>
                    </tr>
					<tr>
                    <th scope="col">Referred User ID</th>
                    <td><b><?php echo $referred_user_id;?></b></td>
                    </tr>
					<tr>
                    <th scope="col">Referred User Name</th>
                    <td><?php echo strtoupper($ref_user_name);?></td>
                    </tr>
					<tr>
                    <th scope="col">Referred User Mobile Number</th>
                    <td><?php echo $ref_user_mobile;?></td>
                    </tr>
					<tr>
                    <th colspan="2"><hr/></th>
                    </tr>
					
				<?php } else{ ?>
					
					<tr>
                    <th scope="col">Referred by</th>
                    <td>Company</td>
                    </tr>
					
				<?php
				} 
				}
				?>
				<!-------------------------------------------------------------------------------------------->
				<!-------------------------------------------------------------------------------------------->
					
					
					
					
<?php /*if($result_product_list['account_status']=="pending"){?>
<tr>
<td colspan="2">
<form method="post" enctype="mutipart/form-data" action="active-users.php" onSubmit="return confirm('Please make a confirm!');">
<input type="hidden" name="usertype" value="distributor">
<input type="hidden" name="userrowid" value="<?=$get_id;?>">

<div class="d-grid gap-2">
    <button class="btn btn-primary" type="submit">Click to Activate</button>
	</div>
</form>
</td>
					</tr>
					<?php } */?>
					
					
                                                   
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