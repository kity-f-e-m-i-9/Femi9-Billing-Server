<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('unassigned');
include("config.php");

$title="Unassigned Super Stockist - Districtwise";
$add_title="Unassigned Super Stockist - Districtwise";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

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
									<td><?php echo $title;?></td>
									<td><a href="export_unassigned_district" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
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
                                        <table id="datatable1" class="display" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>State Name</th>
                                                    <th>District Name</th>
													<th>Status</th>
                                                </tr>
                                            </thead>
											 <tbody>
											 
							<?php $select_product_list="select * from district order by id asc";
							$fetch_product_list=mysqli_query($db_conn,$select_product_list);
							while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											//state details
											$state_id=$result_product_list['state_id'];
								$select_stateList="select * from `state` where id='$state_id'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $state_name=$result_stateList['st_name'];
											
											$district_id=base64_encode($result_product_list["id"]);
											?>
                                           
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
									<td><?php echo $state_name;?></td>
                                      <td><?php echo $result_product_list["dist_name"];?></td>
													
<td>
<?php if($result_product_list["assigned_SSID"]=="Nil"){?>
<span class='badge badge-style-bordered badge-success'>Available</span>
<?php }else{?>
<span class='badge badge-style-bordered badge-danger'>already user appointed</span>
<?php }?>
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