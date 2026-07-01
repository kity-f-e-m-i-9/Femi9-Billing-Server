<?php include("checksession.php");
include("config.php");
error_reporting(0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage Internal Stock Transfer : <?php echo $business_name;?></title>

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
if (isset($_SESSION['sucMessage'])) {
$sucMessage = $_SESSION['sucMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $sucMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['sucMessage']); } ?>

								
								<?php /* if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one Internal Stock Transfer details deleted success.</div><?php } */ ?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage Internal Stock Transfer</td>
									<!-----<td><a href="add_internal" title="Add Internal Stock Transfer">&#10011;</a></td>---->
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
                                                    <th>S.No</th>
													<th>Send To</th>
													<th>Date</th>
													<th>Product Name</th>
													<th>Qty</th>
													<th>Delete</th>
                                                </tr>
                                            </thead>
											
											<tbody>
										<?php 
									$select_product_list12="select * from `internal_transfer_ss` where from_usertype='$Login_user_TYPEvl' and from_userid='$Login_user_IDvl' order by date desc";
										$fetch_product_list12=mysqli_query($db_conn,$select_product_list12);
										while($ResultRecords12=mysqli_fetch_array($fetch_product_list12))
										{
											
											//product details
											$product_id=$ResultRecords12['prid'];
											$select_productDetils="select * from products where id='$product_id'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
						
						//SEND TO
						$send_to=$ResultRecords12['to_userid'];
						if($ResultRecords12['to_usertype']=="stockiest"){ $userTable="stockiest";}else { $userTable="super_stockiest";}
						$select_godowndetails2="select * from ".$userTable." where temp_id='$send_to'";
						$fetch_godowndetails2=mysqli_query($db_conn,$select_godowndetails2);
						$result_godowndetails2=mysqli_fetch_array($fetch_godowndetails2);
											?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
													<td><?php echo strtoupper($result_godowndetails2["name"]);?><br/>
													<b>
													<?php echo $result_godowndetails2["mobile_number"];?>
													</b>
													<br/>
													<?=$ResultRecords12['to_usertype'];?>
													</td>
					<td><?php echo date("d/M/Y",strtotime($ResultRecords12["date"]));?></td>
                    <td><?php echo $Result_productDetils["productName"];?></td>
				<th><?=$ResultRecords12['qty'];?></th>
													
													<td>
<a href="delete_internal.php?rowid=<?=base64_encode($ResultRecords12['id']);?>" onclick="return confirm('You want to delete confirm?');">
<img src="../../assets/images/delete-32.png"/></a>
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