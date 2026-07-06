<?php include("checksession.php"); error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Approved Withdraw Request : <?php echo $business_name;?></title>

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


<?php 
if($_REQUEST['frdate']!=NULL)
								{
						$from_date=$_REQUEST['frdate'];
						$to_date=$_REQUEST['todate'];
								}
								else
								{
						$to_date=date("Y-m-d");
						$from_date=date("Y-m-d", strtotime("-2 days", strtotime($to_date)));;	
								}
								?>
                                    <h2>
									<table class="headertble">
									<tr>
									<td>Approved Withdraw</td>
									<td><a href="wallet_request" title="Pending Withdraw">Pending Withdraw</a></td>
									<td><a href="excel_approved_withdraw?frd=<?=$from_date;?>&&tod=<?=$to_date;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
									</tr>
									</table>
									</h2>
									<br/>
									
									<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>

<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>
<div id="searchbuttoncont">
<button type="button" onclick="Javascript:window.location='wallet_request_approved';" style="margin-left:10px;" class="btn btn-primary"><i class="material-icons"></i>Reset</button>
</div>

							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>	
							
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
													<th>Approved Timestamp</th>
													<th>TDS(%)</th>
													<th>TDS Amount</th>
													<th>Amount</th>
													<th>Remarks</th>
                                                </tr>
                                            </thead>
											
											<tbody>
				<?php 
				$select_product_list="select * from wallet_withdraw where req_status='approved' and updated_date between '$from_date' and '$to_date' order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{

									$WD_user_type=$result_product_list['user_type'];
									$WD_user_id=$result_product_list['user_id'];
									
$WD_user_id_esc = mysqli_real_escape_string($db_conn, $WD_user_id);
$district_name = '';
if ($WD_user_type === 'territory_partner') {
    $r = mysqli_fetch_assoc(mysqli_query($db_conn,
        "SELECT id, name, mobile, tp_id AS useridtext FROM territory_partners WHERE id='$WD_user_id_esc' LIMIT 1"));
    $result_onbaord_user_records = $r ?: [];
    $result_onbaord_user_records['mobile_number'] = $r['mobile'] ?? '';
    $result_onbaord_user_records['country_code']  = '';
} elseif ($WD_user_type === 'channel_partner') {
    $r = mysqli_fetch_assoc(mysqli_query($db_conn,
        "SELECT id, name, mobile, cp_id AS useridtext FROM channel_partners WHERE id='$WD_user_id_esc' LIMIT 1"));
    $result_onbaord_user_records = $r ?: [];
    $result_onbaord_user_records['mobile_number'] = $r['mobile'] ?? '';
    $result_onbaord_user_records['country_code']  = '';
} else {
    if ($WD_user_type=="candf")           { $tablenameWE = "c_and_f"; }
    elseif ($WD_user_type=="super_stockiest") { $tablenameWE = "super_stockiest"; }
    elseif ($WD_user_type=="stockiest")   { $tablenameWE = "stockiest"; }
    else                                  { $tablenameWE = "distributor"; }

    $result_onbaord_user_records = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT * FROM $tablenameWE WHERE temp_id='$WD_user_id_esc'")) ?: [];

    $district_id = $result_onbaord_user_records['district_id'] ?? 0;
    if ($district_id) {
        $r_dist = mysqli_fetch_array(mysqli_query($db_conn, "SELECT dist_name FROM district WHERE id='$district_id'"));
        $district_name = $r_dist['dist_name'] ?? '';
    }
}
?>

                    <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=$WD_user_type;?></td>
					<td><?=htmlspecialchars($result_onbaord_user_records['useridtext'] ?? '');?></td>
					<td>
					<b><?php echo ucwords($result_onbaord_user_records['name'] ?? '');?></b><br/>
					<?php echo htmlspecialchars($district_name);?>
					</td>

					<td><?=htmlspecialchars($result_onbaord_user_records["country_code"] ?? '');?>&nbsp;<?=htmlspecialchars($result_onbaord_user_records["mobile_number"] ?? '');?></td>
					
					<td><?=inr_format($result_product_list['amount'], 2);?></td>
					<td><?=date("d/m/Y",strtotime($result_product_list['date']));?><br/>
					<?=date("g:i A",strtotime($result_product_list['time']));?>
					</td>
					
					<td><?=date("d/m/Y",strtotime($result_product_list['updated_date']));?><br/>
					<?=date("g:i A",strtotime($result_product_list['updated_time']));?>
					</td>
					<td><?=$result_product_list['TDS_percentage'];?>%</td>
					<td><?=inr_format($result_product_list['TDS_deduction'], 2);?></td>
					<td><?=inr_format($result_product_list['sent_amount'], 2);?></td>
					<td><?=$result_product_list['remarks'];?></td>
					
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