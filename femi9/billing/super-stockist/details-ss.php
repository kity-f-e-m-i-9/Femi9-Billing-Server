<?php include("checksession.php");
error_reporting(0);

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from stockiest where id='$get_id'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				$result_product_list=mysqli_fetch_array($fetch_product_list);
				

//state details
								$state_id=$result_product_list['state_id'];
								$select_stateList="select * from `state` where id='$state_id'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $state_name=$result_stateList['st_name'];
							   
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
    <title><?php echo ucwords($result_product_list['name']);?> : Stockist</title>

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
                        <div style="padding:20px;">
						 <h1>Details : Stockist
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
                    <th scope="col" colspan="2">Taluk, Pincode<br/><hr/>
					<ol>
					<?php 
					$StockistID=$result_product_list['temp_id'];
$select_distict="select * from taluk where assigned_SID='$StockistID' order by id asc";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	while($result_district=mysqli_fetch_array($fetch_district))
	{
$talukID=$result_district['id'];

?>
<li><span style="color:#000;"><?=$result_district['taluk'];?></span><hr>
<ol>
					<?php 
					$select_STockPincode="select * from pincode where assigned_SID='$StockistID' and taluk_id='$talukID' order by pincode asc";
					$fetch_STockPincode=mysqli_query($db_conn,$select_STockPincode);
					while($result_STockPincode=mysqli_fetch_array($fetch_STockPincode))
					{
					?>
					<li><?php echo $result_STockPincode['pincode'];?></li>
					<?php } ?>
</ol>

</li>
<br/>
<?php


	}

?>
</ol>
					
					</th>
                     
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
					
					<!-----<tr>
                    <th scope="col">Password</th>
                    <td><?php echo $result_product_list['password'];?></td>
                    </tr>----->
					
					<tr>
                    <th scope="col">GST Number</th>
                    <td><?php echo $result_product_list['gstin'];?></td>
                    </tr>
					
					
					<?php
$stockistID=$result_product_list['temp_id'];
					
					$Select_ReferralDetails="select * from stockist_referral where stockist_id='$stockistID'";
				$Fetch_ReferralDetails=mysqli_query($db_conn,$Select_ReferralDetails);
				$Result_ReferralDetails=mysqli_fetch_array($Fetch_ReferralDetails);
				
				$st_cat_id=$Result_ReferralDetails['st_cat_id'];
				if($st_cat_id!=NULL)
				{
				//
				$Select_STcatdetails="select * from stockist_category where id='$st_cat_id'";
				$Felect_STcatdetails=mysqli_query($db_conn,$Select_STcatdetails);
				$Relect_STcatdetails=mysqli_fetch_array($Felect_STcatdetails);
				
				if($Result_ReferralDetails['st_ref_type']=="super_stockiest"){
		$tblename="super_stockiest";
		$lablename="Super Stockist";
		}
		else if($Result_ReferralDetails['st_ref_type']=="stockiest"){
			$tblename="stockiest";
			$lablename="Stockist";
		}
		else{
			$tblename="distributor";
			$lablename="Distributor";
		}
		
		$Select_USERdetails="select * from ".$tblename." where useridtext='".$Result_ReferralDetails['st_ref_userid']."'";
				$Fetch_USERdetails=mysqli_query($db_conn,$Select_USERdetails);
				$Retch_USERdetails=mysqli_fetch_array($Fetch_USERdetails);
				//
				$ref_user_name=$Retch_USERdetails['name'];
				$ref_user_mobile=$Retch_USERdetails['mobile_number'];
				
				if($Result_ReferralDetails['st_ref_type']!="company"){
					?>
					<tr>
                    <th colspan="2"><hr/></th>
                    </tr>
					<tr>
                    <th colspan="2"><h3>Referral Details</h3></th>
                    </tr>
					<tr>
                    <th scope="col">Referred by</th>
                    <td><?php echo $lablename;?></td>
                    </tr>
					<tr>
                    <th scope="col">Referred User ID</th>
                    <td><b><?php echo $Result_ReferralDetails['st_ref_userid'];?></b></td>
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
					<tr>
                    <th scope="col">Category</th>
                    <td><b><?php echo $Relect_STcatdetails['catname'];?></b></td>
                    </tr>
					<tr>
                    <th scope="col">Target Sales Value(Rs.)</th>
                    <td><?php echo $Relect_STcatdetails['target_amount'];?></td>
                    </tr>
					<tr>
                    <th scope="col">Referral (%)</th>
                    <td><?php echo $Relect_STcatdetails['ref_commission_percentage'];?>%</td>
                    </tr>
					<tr>
                    <th scope="col">Cashback (%)</th>
                    <td><?php echo $Relect_STcatdetails['cash_back_percentage'];?>%</td>
                    </tr>
					
				<?php } else{
					
					?>
					<tr>
                    <th colspan="2"><hr/></th>
                    </tr>
					<tr>
                    <th colspan="2"><h3>Referral Details</h3></th>
                    </tr>
					<tr>
                    <th scope="col">Referred by</th>
                    <td>Company</td>
                    </tr>
					
					<?php
				} }
				?>
					
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