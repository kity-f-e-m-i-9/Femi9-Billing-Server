<?php include("checksession.php"); error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Pending Withdraw Request : <?php echo $business_name;?></title>

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
		
		<style>
		.table {
  display: grid;
  grid-template-columns: 1fr 1fr; /* Two equal columns */
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  background-color: #f9f9f9;
  width: 100%;
  max-width: 600px;
  margin: 20px auto;
}

.table-row {
  display: contents; /* Ensures individual cells span the grid */
}

.table-cell {
  padding: 10px 15px;
  border-bottom: 1px solid #ddd;
  text-align: left;
  font-size: 14px;
  color: #333;
}

.table-row:last-child .table-cell {
  border-bottom: none; /* Remove bottom border for the last row */
}

.table-cell:nth-child(1) {
  font-weight: bold; /* Highlight the labels (first column) */
  background-color: #f4f4f4;
}

.table-cell:nth-child(2) {
  text-align: right; /* Align values (second column) to the right */
}


		</style>
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

                                    <h2>
									<table class="headertble">
									<tr>
									<td>Pending Withdraw</td>
									<td style="text-align:right;"><a href="wallet_request_approved" title="Approved Withdraw">Approved Withdraw</a></td>
									<td style="text-align:right;">
                                        <a href="export_pending_withdraw.php" title="Export">
                                            <img src="../../assets/images/excel-3-32.png" />
                                        </a>
                                    </td>
									</tr>
									</table>
									</h2>
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
													<th>User Type</th>
													<th>User ID</th>
													<th>Name<br/>(District)</th>
													<th>Mobile Number</th>
													<th>Amount</th>
													<th>Request Timestamp</th>
													<th>Approved</th>
													<th>Actions</th>
                                                </tr>
                                            </thead>
											
											<tbody>
				<?php $select_product_list="select * from wallet_withdraw where req_status='pending' order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{

									$WD_user_type=$result_product_list['user_type'];
									$WD_user_id=$result_product_list['user_id'];
									
if($WD_user_type=="candf"){$tablenameWE="c_and_f";}
elseif($WD_user_type=="super_stockiest") {$tablenameWE="super_stockiest";}
elseif($WD_user_type=="stockiest") {$tablenameWE="stockiest";}
elseif($WD_user_type=="distributor") {$tablenameWE="distributor";}
else{$tablenameWE="super_distributor";}

$select_onbaord_user_records="select * from ".$tablenameWE." where temp_id='$WD_user_id'";
$fetch_onbaord_user_records=mysqli_query($db_conn,$select_onbaord_user_records);
$result_onbaord_user_records=mysqli_fetch_array($fetch_onbaord_user_records);


//district details		
$district_id=$result_onbaord_user_records['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];
?>
                                            
                    <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=$WD_user_type;?></td>
					<td><?=$result_onbaord_user_records['useridtext'];?></td>
					<td>
					<b><?php echo ucwords($result_onbaord_user_records['name']);?></b><br/>
					<?php echo $district_name;?>
					</td>
					
					<td><?=$result_onbaord_user_records["country_code"];?>&nbsp;<?=$result_onbaord_user_records["mobile_number"];?></td>
					
					<td><?=number_format($result_product_list['amount'],2,'.','');?></td>
					<td><?=date("d/m/Y",strtotime($result_product_list['date']));?><br/>
					<?=date("g:i A",strtotime($result_product_list['time']));?>
					</td>
					
					
					<!---------------Approval Form Open------------------>
					<td>
					<a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#exampleModalLive<?php echo $result_product_list["id"];?>" title="Click to Update Mobile Number">
					Approved
					</a>
					
					<div class="modal fade" id="exampleModalLive<?php echo $result_product_list["id"];?>" tabindex="-1" aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
													
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLiveLabel"><u>Approval Withdraw</u><br/>
																<?php echo ucwords($result_onbaord_user_records['name']);?></b><br/>
					<?php echo $district_name;?><br/>
					<?=$result_onbaord_user_records["country_code"];?>&nbsp;<?=$result_onbaord_user_records["mobile_number"];?><br/>
					
					<?php 
$select_admin_settings2233="select tds_percentage from admin_settings where id='1'";
$fetch_admin_settings2233=mysqli_query($db_conn,$select_admin_settings2233);
$result_admin_settings2233=mysqli_fetch_array($fetch_admin_settings2233);
$tds_percentage=$result_admin_settings2233['tds_percentage'];

$tds_deduction=$result_product_list['amount']*$tds_percentage/100;
$tds_deduction=number_format($tds_deduction,2,'.','');

$sent_amount=$result_product_list['amount']-$tds_deduction;
$sent_amount=number_format($sent_amount,2,'.','');
?>

<div class="table">
  <div class="table-row">
    <div class="table-cell">Request&nbsp;Timestamp</div>
    <div class="table-cell"><?=date("d/m/Y",strtotime($result_product_list['date']));?>,
					<?=date("g:i A",strtotime($result_product_list['time']));?></div>
  </div>
  <div class="table-row">
    <div class="table-cell">Request&nbsp;Amount</div>
    <div class="table-cell"><?=number_format($result_product_list['amount'],2,'.','');?></div>
  </div>
   <div class="table-row">
    <div class="table-cell">TDS(%)</div>
    <div class="table-cell"><?=$tds_percentage;?>%</div>
  </div>
   <div class="table-row">
    <div class="table-cell">TDS Deduction (Rs)</div>
    <div class="table-cell"><?=$tds_deduction;?></div>
  </div>
   <div class="table-row">
    <div class="table-cell">Amount (Rs)</div>
    <div class="table-cell"><?=$sent_amount;?> </div>
  </div>
  
<?php 
/*$selectcoutprofileDetaiils223="select * from users_profile where user_tempid='$WD_user_id' and usertype='$WD_user_type'";
$fetchcountprofileDetails223=mysqli_query($db_conn,$selectcoutprofileDetaiils223);
$resultcountprofileDetails223=mysqli_fetch_array($fetchcountprofileDetails223);*/
?>

<div class="table-row">
    <div class="table-cell" style="color:blue;">Bank Details:-</div>
    <div class="table-cell"></div>
  </div>

   <div class="table-row">
    <div class="table-cell">Accout Name</div>
    <div class="table-cell"><?=$result_product_list['acname'];?></div>
  </div>
  <div class="table-row">
    <div class="table-cell">Account Number</div>
    <div class="table-cell"><?=$result_product_list['acnumber'];?></div>
  </div>
   <div class="table-row">
    <div class="table-cell">Bank Name</div>
    <div class="table-cell"><?=$result_product_list['bankname'];?></div>
  </div>
   <!----<div class="table-row">
    <div class="table-cell">Branch Name</div>
    <div class="table-cell"><?=$result_product_list['branchname'];?></div>
  </div>---->
   <div class="table-row">
    <div class="table-cell">IFS Code</div>
    <div class="table-cell"><?=$result_product_list['ifsc'];?></div>
  </div>
  <div class="table-row">
    <div class="table-cell">PAN Number</div>
    <div class="table-cell"><?=$result_product_list['pannumber'];?></div>
  </div>
  
</div>


					
					
																</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
									<form method="post" onsubmit="return confirm('Please make a confirm!');" enctype="multipart/form-data" action="withdraw_approval">	
	
									<input type="hidden" name="request_row_id" value="<?=$result_product_list['id'];?>">
									<input type="hidden" name="updated_date" value="<?=date("Y-m-d");?>">
									<input type="hidden" name="updated_time" value="<?=date("H:i:s");?>">
									<input type="hidden" name="TDS_percentage" value="<?=$tds_percentage;?>">
									<input type="hidden" name="TDS_deduction" value="<?=$tds_deduction;?>">
									<input type="hidden" name="sent_amount" value="<?=$sent_amount;?>">
															
                                               <div class="example-content" style="padding:20px;">
                                                <div class="form-floating mb-3">
                                                    <textarea name="remarks" required placeholder="Remarks" class="form-control"></textarea>
                                                    <label for="floatingInput">Remarks (or) Ref Number</label>
                                                </div>
												
												<button type="submit" name="UpdateAction" class="btn btn-primary"><i class="material-icons">update</i>Send</button>
												
                                            </div>
											
											</form>
                                                        </div>
                                                    </div>
													
                                                </div>
					</td>
					<!---------------Approval Form Close---------------------------->
					
					
										<td>
					    <div class="actions-group">
					        <a href="delete_req.php?prid=<?=base64_encode($result_product_list['id']);?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
					    </div>
					</td>
					
													
	<!------	<td>
	    <div class="actions-group">
	        <a href="#" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
	    </div>
	</td>----->
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