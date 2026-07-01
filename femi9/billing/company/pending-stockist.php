<?php include("checksession.php");  include("config.php"); error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Pending Stockist : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


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
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
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
								
								
								<?php
// Check for error message in session
if (isset($_SESSION['SuccessMessage'])) {
$errorMessage = $_SESSION['SuccessMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $errorMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['SuccessMessage']); } ?>


								<?php /* if(isset($_REQUEST['addedsuccess'])){?><div class="alert alert-success">Stockist added success.</div><?php } */ ?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Pending Stockist</td>
									<td></td>
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
                              <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
													<th>Name, District, Taluk</th>
													<th>Mobile Number</th>
													<!---<th>Username</th>
													<th>Plan Details</th>
													<th>Ref Number</th>--->
													<th>Account Status</th>
													<th>Created By</th>
													<th>Details</th>
													<th>Actions</th>
                                                </tr>
                                            </thead>
											
											<tbody>
				<?php $select_product_list="select * from stockiest where account_status='pending' order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{

$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

$taluk_id=$result_product_list['taluk_id'];
$select_distict="select * from taluk where id='$taluk_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$taluk_name=$result_district['taluk'];
											
					$rowid=base64_encode($result_product_list["id"]);
					
					//super stockist Details
					$supersid=$result_product_list['ss_id'];
					
					if($result_product_list['onboard_userTYPE']=="candf")
					{
						$tableName="c_and_f";
						$created_by="C&F";
				}
				if($result_product_list['onboard_userTYPE']=="super_stockiest")
				{
					$tableName="super_stockiest";
					$created_by="Super Stockist";
				}

					
$select_ssdetails="select * from ".$tableName." where temp_id='$supersid'";
	$fetch_ssdetails=mysqli_query($db_conn,$select_ssdetails);
	$result_ssdetails=mysqli_fetch_array($fetch_ssdetails);
					
						?>
                                            
                                                <tr>
                    <td><?php echo ++$i; ?></td>
					<td>
					<b><?php echo ucwords($result_product_list["name"]);?></b><br/>
					D:&nbsp;<?php echo $district_name;?><br/>T:&nbsp;<?php echo $taluk_name;?>
					</td>
					<td><?php echo $result_product_list["mobile_number"];?></td>
					
					
					<?php /*?><td><b><?php echo $result_product_list["username"];?></b></td>
					<td>&#8377; <?php echo $result_product_list["plan_amount"];?><br/>
					<?php echo $result_product_list["valid_months"];?> Days<br/>
					<?php echo date("d/m/Y",strtotime($result_product_list["valid_from"]));?>
					(to) <?php echo date("d/m/Y",strtotime($result_product_list["valid_to"]));?>
					</td>
					
					<td><?php echo $result_product_list["ref_number"];?></td>
					<?php */?>
					
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
<?=ucwords($result_ssdetails['name']);?><br/>
<?=ucwords($result_ssdetails['mobile_number']);?><br/>
<?=$created_by;?>
</td>


<td>
			<a href="JavaScript:newPopup('stockist-details-ss?prid=<?php echo $rowid;?>&&actiondetails');" data-bs-toggle="tooltip" data-bs-placement="top" title="View Details">
			<img src="../../assets/images/details-32.png"/></a>

<script type="text/javascript">
function newPopup(url) {
	popupWindow = window.open(
		url,'popUpWindow','height=450,width=750,left=350,top=200,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes')
}
</script>
			</td>
			
			
						<td>
			    <div class="actions-group">
			        <a href="delete_stockist_approval.php?rowid=<?=$rowid;?>&&talukID=<?=$taluk_id;?>&&userid=<?=base64_encode($result_product_list['temp_id']);?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm this Stockist (<?php echo ucwords($result_product_list["><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
			    </div>
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