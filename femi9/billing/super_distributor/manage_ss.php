<?php include("checksession.php");
include("config.php"); error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage Shop (Retailers) : <?php echo $business_name;?></title>

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
								
								<?php if(isset($_REQUEST['addedsuccess'])){?><div class="alert alert-success">Shop (Retailers) added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! One Shop (Retailers) Details Deleted Success.</div><?php }?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage Shop (Retailers)</td>
									<td><a href="add_ss.php" title="Add Shop (Retailers)">&#10011;</a></td>
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
									<div style="background:#fff;overflow:scroll;width:100%;">
									
									<?php if($result_LoGuserDtails['shop_onboard']=="1"){?>
									
									
                              <table id="datatable1" width="100%">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Shop Photo (Live)</th>
													<th>Category</th>
													<th>Name</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Pincode</th>
													<th>Mobile Number</th>
													<th>Landline Number</th>
													<th>Email ID</th>
													<th>Address</th>
													<th>GSTIN</th>
													<!----<th>Details</th>---->
													<th>Edit</th>
													<!-----<th>Delete</th>---->
                                                </tr>
                                            </thead>
											
											<tbody>
<?php $select_product_list="select * from shop where distributor_id='$loguser_tempid' order by id desc";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{																

/*
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

$pincodeshow=$pincode_id;
*/
											
					$rowid=base64_encode($result_product_list["id"]);
					
					
					if($result_product_list["user_icon"]!="Nil"){$imgsrcname=$result_product_list["user_icon"];}else{$imgsrcname="../../assets/images/no image.jpg";}
					
					
					//shop category
				$shop_cat=$result_product_list['shop_cat'];
$select_shopcatt="select * from shop_category where id='$shop_cat'";
	$fetch_shopcatt=mysqli_query($db_conn,$select_shopcatt);
	$result_shopcatt=mysqli_fetch_array($fetch_shopcatt);
$shopcat_name=$result_shopcatt['catlable'];
					
						?>
                                            
                                               <tr>
                    <td><?php echo ++$i; ?></td>
                    <td>
					<a data-fslightbox="gallery" href="<?php echo $imgsrcname;?>">View&nbsp;Photo</a>
					</td>
					<td><?=$shopcat_name;?></td>
					<td>
					<b><?php echo ucwords($result_product_list["name"]);?></td>
					
					<td><?=ucwords($result_product_list['district_id']);?></td>
					<td><?=ucwords($result_product_list['taluk_id']);?></td>
					<td><?=$result_product_list['pincode_id']?></td>
					
					<td><?php echo $result_product_list["country_code"];?>&nbsp;<?php echo $result_product_list["mobile_number"];?></td>
					<td><?php if($result_product_list["landline"]!=NULL){ echo $result_product_list["landline"]; }else{ echo "---";}?></td>
					<td><?php if($result_product_list["email"]!=NULL){ echo $result_product_list["email"]; }else{ echo "---";}?></td>
					<td><?php echo $result_product_list["address"];?></td>
					<td><?php if($result_product_list["gstin"]!=NULL){ echo $result_product_list["gstin"]; }else{ echo "---";}?></td>

<!-----<td>
			<a href="details-ss.php?prid=<?php echo $rowid;?>&&actiondetails" data-bs-toggle="tooltip" data-bs-placement="top" title="View Details">
			<img src="../../assets/images/details-32.png"/></a>
			</td>----->			
													
			<td>
			<a href="edit-ss.php?prid=<?php echo $rowid;?>&&actionupdate" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Details">
			<img src="../../assets/images/edit-32.png"/></a>
			</td>
													
	<!-----<td>
	<a href="delete-shop.php?prid=<?php echo $rowid;?>&&actionremove"onclick="return confirm('You want to delete confirm?');" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Details"><img src="../../assets/images/delete-32.png"/></a>
	</td>---->
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
                                        </table>
										
										<?php }else { echo "Access denied!";}?>
										
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
	<script src="../../assets/plugins/lightbox/fslightbox.js"></script>
	<script src="../../assets/js/pages/lightbox.js"></script>
</body>

</html>