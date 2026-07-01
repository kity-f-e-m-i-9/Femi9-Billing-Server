<?php include("checksession.php"); error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Overview Distributor : <?php echo $business_name;?></title>

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
	<link href="../../assets/css/vlstyle.css" rel="stylesheet">

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
									<td>Overview Distributor</td>
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
                                    <div class="card-body">
                              <div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
													<th>Photo</th>
													<th>Candidate ID</th>
													<th>Name</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Pincode</th>
													<th>Mobile Number</th>
													<th>Account Status</th>
													<th>Account Manager</th>
                                                </tr>
                                            </thead>
											
											<tbody>
				<?php $select_product_list="select * from distributor order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
									
//district details
$district_id=$result_product_list['district_id'];
if(is_numeric($district_id))
{
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
}
else
{
$district_name=	$district_id;	
}

//taluk details
$taluk_id=$result_product_list['taluk_id'];
if(is_numeric($taluk_id))
{
$select_Taluk12="select * from taluk where id='$taluk_id'";
	$fetch_Taluk12=mysqli_query($db_conn,$select_Taluk12);
	$result_Taluk12=mysqli_fetch_array($fetch_Taluk12);
$taluk_name=$result_Taluk12['taluk'];
}else{
	$taluk_name=$taluk_id;
}

//pincode details
$pincode_id=$result_product_list['pincode_id'];
if($pincode_id<6)
{
$select_pincodelist="select * from pincode where id='$pincode_id'";
$fetch_pincodelist=mysqli_query($db_conn,$select_pincodelist);
$result_pincodelist=mysqli_fetch_array($fetch_pincodelist);
$pincodeshow=$result_pincodelist['pincode'];
}
else{
	 $pincodeshow=$pincode_id;
}

//acccount manager details
if($result_product_list['onboard_userTYPE']=="company")
{
	$account_mnid="";
	$account_mnname="Company";
}else{
	
$select_ssDetails="select * from stockiest where temp_id='".$result_product_list['onboard_userID']."'";
$exe_ssDetails=mysqli_query($db_conn,$select_ssDetails);
$fetch_ssDetails=mysqli_fetch_array($exe_ssDetails);
	
	$account_mnid=$fetch_ssDetails['useridtext'];
	$account_mnname=ucwords($fetch_ssDetails['name']);
	
}
						?>
                                            
                                                <tr>
                    <td><?php echo ++$i; ?></td>
					
                    <td>
					<?php if($result_product_list["user_icon"]!="Nil"){ ?>
					<a href="../distributor/<?=$result_product_list["user_icon"];?>" target="_blank">
					<img src="../distributor/<?=$result_product_list["user_icon"];?>" style="width:90px;border-radius:10px;">
					</a>
					<?php }else{ ?>
					<img src="../../assets/images/149071.png" style="width:90px;border-radius:10px;">
					<?php }?>
					</td>
 
					<td><?=$result_product_list["useridtext"];?></td>
					<td><?php echo ucwords($result_product_list["name"]);?></td>
					<td><?php echo $district_name;?></td>
					<td><?php echo $taluk_name;?></td>
					<td><?=$pincodeshow;?></td>
					<td><?php echo $result_product_list["mobile_number"];?></td>
					
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
<td>
<b><?=$account_mnname?></b><br/><span style="color:#999;"><?=$account_mnid?></span>
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
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
</body>

</html>