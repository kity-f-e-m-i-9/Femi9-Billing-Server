<?php include("checksession.php");
date_default_timezone_set("Asia/Kolkata");
$current_date=date("Y-m-d");

$GET_mapusertype=$_REQUEST['mapusertype'];
$GET_mapuserid=$_REQUEST['mapuserid'];

if($GET_mapusertype!="company")
{
	
	if($GET_mapusertype=="super_stockiest"){$tblename="super_stockiest"; $lablename="Super Stockist";}
	if($GET_mapusertype=="stockiest"){$tblename="stockiest"; $lablename="Stockist";}
	
$select_sslist="select * from ".$tblename." where temp_id='$GET_mapuserid'";
$fetch_sslist=mysqli_query($db_conn,$select_sslist);
$result_sslist=mysqli_fetch_array($fetch_sslist);

$superstockist_name="".$result_sslist['name']." / ".$result_sslist['mobile_number']."";
$onboardusertype=$GET_mapusertype;
$onboarduserid=$GET_mapuserid;

}else{

	$superstockist_name="company";
	$onboardusertype="company";
	$onboarduserid="company";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Re-mapping : Distributor Assigned to Stockist : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">


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
								<a href="remapping-stc" id="linkbackvl">&#8630;&nbsp;Go Back</a>
                                     <h2>
									<table class="headertble margintop10">
									<tr>
									<td>
									Re-mapping : <i><span style="color:green;">Distributor</span></i> Assigned to <b><span style="color:green;">Stockist</span></b>
									<div class="margintop10"><?=strtoupper($superstockist_name);?></div>
									</td>
									</tr>
									</table>
									</h2>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    
                                    <div class="card-body">
									
<?php 
$select_supersotkistCOUNT="select count(*) as numMAPP from distributor where onboard_userTYPE='$onboardusertype' 
and onboard_userID='$onboarduserid'";
$fetch_supersotkistCNT=mysqli_query($db_conn,$select_supersotkistCOUNT);
$result_supersotkistCNT=mysqli_fetch_array($fetch_supersotkistCNT);
if($result_supersotkistCNT['numMAPP']!=0)
{
?>
									
									
<form action="remapping-action" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">

                                        <div class="example-container">
                                            <div class="example-content">
											
											
											<ul class="list-group">
											
					<?php 
					$select_supersotkist="select * from distributor where onboard_userTYPE='$onboardusertype' 
					and onboard_userID='$onboarduserid' order by name asc";
										$fetch_supersotkist=mysqli_query($db_conn,$select_supersotkist);
										while($result_supersotkist=mysqli_fetch_array($fetch_supersotkist))
										{
											?>
											
											<li class="list-group-item">
        <input class="form-check-input me-1" name="distributorid[]" type="checkbox" value="<?=$result_supersotkist['id'];?>" aria-label="...">
        <?=strtoupper($result_supersotkist['name']);?>, <?=$result_supersotkist['mobile_number'];?>
    </li>
	
	
										<?php }?>
   
</ul>
<br/>

<label for="exampleInputEmail1" class="form-label">Assigned To*</label>
                               <select required="" name="stockistid" class="form-control">
							   <option value="" hidden="">Select Stockist</option>
				<?php $select_stateList12="select * from stockiest where temp_id!='$GET_mapuserid'";
							   $fetch_staeList12=mysqli_query($db_conn,$select_stateList12);
							   while($result_stateList12=mysqli_fetch_array($fetch_staeList12))
							   {?>
						   <option value="<?=$result_stateList12['temp_id'];?>"><?=strtoupper($result_stateList12['name']);?>, <?=$result_stateList12['mobile_number'];?></option>
							   <?php }?><br/>
							   </select>
											
												<br/>
												<button type="submit" name="REMAPPING_STOCKIST" class="btn btn-primary"><i class="material-icons"></i>Submit</button>
												
                                            </div>
                                        </div>
										</form>
										
<?php }else{?>
<img src="../../assets/images/no-records.jpg">
<?php }?>
										
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>