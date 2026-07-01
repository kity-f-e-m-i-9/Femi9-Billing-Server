<?php include("checksession.php"); error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage Stockist : <?php echo $business_name;?></title>

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
								
								<?php if(isset($_REQUEST['addedsuccess'])){?><div class="alert alert-success">Stockist added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['locationupdated'])){?><div class="alert alert-info">Pincode Updated Success.</div><?php }?>
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! One Stockist Details Deleted Success.</div><?php }?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage Stockist</td>
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
                              
<table id="datatable1">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
													<th>ID</th>
													<th>Name, District, Taluk</th>
													<th>Mobile Number</th>
													<!-----<th>Username</th>
													<th>Plan Details</th>
													<th>Ref Number</th>--->
													<th>Account Status</th>
													<th>Category</th>
													<th>Details</th>
													<th>Edit</th>
													<!----<th>Delete</th>--->
                                                </tr>
                                            </thead>
											
											<tbody>
				<?php $select_product_list="select * from stockiest where (ss_id='$loguser_tempid' OR onboard_userID='$loguser_tempid') AND account_status = 'active' order by id desc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
									//
$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

//
$taluk_id=$result_product_list['taluk_id'];
$select_Taluk12="select * from taluk where id='$taluk_id'";
	$fetch_Taluk12=mysqli_query($db_conn,$select_Taluk12);
	$result_Taluk12=mysqli_fetch_array($fetch_Taluk12);
$taluk_name=$result_Taluk12['taluk'];
											
					$rowid=base64_encode($result_product_list["id"]);
					
					//CATEGORY
					$stockistid=$result_product_list['temp_id'];
					$select_RFRDtailsCNG="select * from stockist_referral where stockist_id='$stockistid'";
$fetch_RFRDtailsCNG=mysqli_query($db_conn,$select_RFRDtailsCNG);
$result_RFRDtailsCNG=mysqli_fetch_array($fetch_RFRDtailsCNG);
$CNG_catID=$result_RFRDtailsCNG['st_cat_id'];

$select_RFRDtailsCNG_CAT="select * from stockist_category where id='$CNG_catID'";
$fetch_RFRDtailsCNG_CAT=mysqli_query($db_conn,$select_RFRDtailsCNG_CAT);
$result_RFRDtailsCNG_CAT=mysqli_fetch_array($fetch_RFRDtailsCNG_CAT);
$Login_person_CAT=$result_RFRDtailsCNG_CAT['catname'];
						?>
                                            
                                                <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=$result_product_list["useridtext"];?></td>
					<td>
					<b><?php echo ucwords($result_product_list["name"]);?></b><br/>
					D:&nbsp;<?php echo $district_name;?><br/>T:&nbsp;<?php echo $taluk_name;?>
					</td>
					<td><?php echo $result_product_list["country_code"];?>&nbsp;<?php echo $result_product_list["mobile_number"];?></td>
					
					<?php /*?>
					<td><b><?php echo $result_product_list["username"];?></b></td>
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

<td style="font-weight:bold;"><?=$Login_person_CAT;?><br/>(&#8377;&nbsp;<?=$result_RFRDtailsCNG_CAT['target_amount'];?>)</td>

<td>
			<a href="JavaScript:newPopup('details-ss.php?prid=<?php echo $rowid;?>&&actiondetails');" data-bs-toggle="tooltip" data-bs-placement="top" title="View Details">
			<img src="../../assets/images/details-32.png"/></a>

<script type="text/javascript">
function newPopup(url) {
	popupWindow = window.open(
		url,'popUpWindow','height=450,width=750,left=350,top=200,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes')
}
</script>

			
			</td>
													
			<td>
			<a href="edit-ss.php?prid=<?php echo $rowid;?>&&actionupdate" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Details">
			<img src="../../assets/images/edit-32.png"/></a>
			</td>
													
	<!----<td>
	<a href="delete-ss.php?prid=<?php echo $rowid;?>&&actionremove"onclick="return confirm('You want to delete confirm?');" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Details"><img src="../../assets/images/delete-32.png"/></a>
	</td>---->
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