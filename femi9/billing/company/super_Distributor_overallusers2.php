<?php include("checksession.php"); error_reporting(0);
$getinvuser=$_REQUEST['invuser'];

	$displaytitle="Onboard userwise Overall - Super Distributors";
	$tablename="super_distributor";
	$xlurl="#";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?=$displaytitle?> : <?php echo $business_name;?></title>

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
			<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


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
									<td><?=$displaytitle?></td>
									<td><a href="super_Distributor_overallusers2_excel" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						
						<?php
// Check for error message in session
if (isset($_SESSION['successMessage'])) {
$successMessage = $_SESSION['successMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $successMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['successMessage']); } ?>
						
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
													<th>ID</th>
													<th>Name</th>
													<th>Mobile Number</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Username</th>
													<th>Account Status</th>
													
													<th>Onboard Usertype</th>
													<th>Onboard UserID</th>
													<th>Name</th>
													<th>Mobile</th>
													<th>District</th>
                                                </tr>
                                            </thead>
											
											<tbody>
				<?php $select_product_list="select * from ".$tablename." order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
//District					
$district_id=$result_product_list['district_id'];					
if(is_numeric($district_id))
{	
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
}else{
	$district_name=$district_id;
}
//District end

	$taluk_id=$result_product_list['taluk_id'];
	if(is_numeric($taluk_id))
	{
$select_taluk="select * from taluk where id='$taluk_id'";
	$fetch_taluk=mysqli_query($db_conn,$select_taluk);
	$result_taluk=mysqli_fetch_array($fetch_taluk);
$taluk_name=$result_taluk['taluk'];
}
else{
	$taluk_name=$taluk_id;
}
//Taluk end
	
$rowid=base64_encode($result_product_list["id"]);
?>
                                            
                                                <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=$result_product_list["useridtext"];?></td>
					<td><b><?php echo ucwords($result_product_list["name"]);?></b></td>
					
					<td>
					<?=$result_product_list["country_code"];?>&nbsp;<?=$result_product_list["mobile_number"];?>
					</td>
					
					<td><?php echo $district_name;?></td>
					<td><?=$taluk_name;?></td>
<td><?=$result_product_list["username"];?></td>

<td>
			<?php 
			if($result_product_list['account_status']=="pending")
			{
			echo "<span class='badge badge-style-bordered badge-danger'>Pending</span>";
			}
			else if($result_product_list['account_status']=="active")
			{
			?>
			<span class='badge badge-style-bordered badge-success'>Active</span>
			<?php
			}else{
				?>
				<span class='badge badge-style-bordered badge-danger'>Deactive</span>
				
				<?php
			}
			?>
</td>


<?php 
//-------------------Onboard Users Details---------------------------
$GET_onboard_usertype=$result_product_list['onboard_userTYPE'];
$GET_onboard_userid=$result_product_list['onboard_userID'];

if($GET_onboard_usertype=="candf"){$tablenameWE="c_and_f";}
else if($GET_onboard_usertype=="super_stockiest") {$tablenameWE="super_stockiest";}
else{$tablenameWE="stockiest";}

$select_onbaord_user_records="select * from ".$tablenameWE." where temp_id='$GET_onboard_userid'";
$fetch_onbaord_user_records=mysqli_query($db_conn,$select_onbaord_user_records);
$result_onbaord_user_records=mysqli_fetch_array($fetch_onbaord_user_records);

$GET_onboard_user_city_id=$result_onbaord_user_records['district_id'];

if($GET_onboard_user_city_id==0){ $GET_onboard_user_city_name="---";}
else
{
$select_onbaord_user_city_records="select * from district where id='$GET_onboard_user_city_id'";
$fetch_onbaord_user_city_records=mysqli_query($db_conn,$select_onbaord_user_city_records);
$result_onbaord_user_city_records=mysqli_fetch_array($fetch_onbaord_user_city_records);
$GET_onboard_user_city_name=$result_onbaord_user_city_records['dist_name'];
}
?>

<td><?=$GET_onboard_usertype;?></td>
<td><?=$result_onbaord_user_records['useridtext'];?></td>
<td><?=$result_onbaord_user_records['name'];?></td>
<td><?=$result_onbaord_user_records['mobile_number'];?></td>
<td><?=$GET_onboard_user_city_name;?></td>

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