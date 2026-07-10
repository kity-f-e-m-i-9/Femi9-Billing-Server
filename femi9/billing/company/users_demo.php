<?php include("checksession.php"); error_reporting(0); include("config.php");?>
require_once("include/PermissionCheck.php"); requirePermission('users_demo');
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Users Demo Awareness : <?php echo $business_name;?></title>

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
								
								<?php 
						if($_REQUEST['frdate']!=NULL)
						{
$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];
						}
						else{
$to_date=date("Y-m-d");
$from_date = date ("Y-m-d", strtotime("-2 days", strtotime($to_date)));
						}
?>
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

							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>	
							
							
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Users Demo Awareness</td>
									<td><a href="export_users_demo?frdate=<?=$from_date;?>&&todate=<?=$to_date?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
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
													<th>Usertype</th>
													<th>Name</th>
													<th>District, Taluk</th>
													<th>Mobile Number</th>
													<th>Date</th>
													<th>Demo Title</th>
													<th>Demo Photo</th>
                                                </tr>
                                            </thead>
											
											<tbody>
<?php
$select_product_list="select * from demo_awareness where date between '$from_date' and '$to_date' order by date desc";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{
											
//USER DETAILS										
$usertype=$result_product_list['usertype'];
if($usertype=="super_stockiest"){ 

$photourl="super-stockist";  $disusertype="Super Stockist";

$select_Userdetails="select * from super_stockiest where temp_id='".$result_product_list['userid']."'";
$fetch_Userdetails=mysqli_query($db_conn,$select_Userdetails);
$result_Userdetails=mysqli_fetch_array($fetch_Userdetails);

$district_id=$result_Userdetails['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=$result_district['dist_name'];

$taluk_name="Nil";

}
else if($usertype=="stockiest"){ 

$photourl="stockist"; $disusertype="Stockist";

$select_Userdetails="select * from stockiest where temp_id='".$result_product_list['userid']."'";
$fetch_Userdetails=mysqli_query($db_conn,$select_Userdetails);
$result_Userdetails=mysqli_fetch_array($fetch_Userdetails);

$district_id=$result_Userdetails['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

//TALUK DETAILS
$taluk_id=$result_Userdetails['taluk_id'];
if($taluk_id!=NULL)
{
$select_Taluk12="select * from taluk where id='$taluk_id'";
$fetch_Taluk12=mysqli_query($db_conn,$select_Taluk12);
$result_Taluk12=mysqli_fetch_array($fetch_Taluk12);
if($result_Taluk12['taluk']!=NULL){
$taluk_name=$result_Taluk12['taluk'];}else{$taluk_name="";}
}

}
else
{ 

$photourl="distributor"; $disusertype="Distributor";

$select_Userdetails="select * from distributor where temp_id='".$result_product_list['userid']."'";
$fetch_Userdetails=mysqli_query($db_conn,$select_Userdetails);
$result_Userdetails=mysqli_fetch_array($fetch_Userdetails);

$district_id=$result_Userdetails['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

//TALUK DETAILS
$taluk_id=$result_Userdetails['taluk_id'];
if($taluk_id!=NULL)
{
$select_Taluk12="select * from taluk where id='$taluk_id'";
$fetch_Taluk12=mysqli_query($db_conn,$select_Taluk12);
$result_Taluk12=mysqli_fetch_array($fetch_Taluk12);
if($result_Taluk12['taluk']!=NULL){
$taluk_name=$result_Taluk12['taluk'];}else{$taluk_name="";}
}

}
		

											
					//PHOTO
					if($result_product_list["photo"]!="Nil"){
					$imgsrcname="../".$photourl."/".$result_product_list["photo"]."";
					}else{$imgsrcname="../../assets/images/no image.jpg";}
					
?>
                                            
                    <tr>
                    <td><?php echo ++$i; ?></td>
					<td><?=$disusertype;?></td>
					<td><b><?php echo ucwords($result_Userdetails["name"]);?></b></td>
					<td>D:&nbsp;<?php echo $district_name;?>
					<br/>T:&nbsp;<?php echo $taluk_name;?>
					</td>
					<td><b><?php echo ucwords($result_Userdetails["mobile_number"]);?></b></td>
					<td><?php echo date("d/m/Y",strtotime($result_product_list['date']));?></td>
					<td><b><?php echo ucwords($result_product_list["title"]);?></b></td>
					
					<td><a data-fslightbox="gallery" href="<?php echo $imgsrcname;?>" title="<?=$result_product_list["title"];?>"><img src="<?php echo $imgsrcname;?>" style="width:50px;border-radius:10px;" alt="<?=$result_product_list["title"];?>"></a></td>
					
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
	<script src="../../assets/plugins/lightbox/fslightbox.js"></script>
	<script src="../../assets/js/pages/lightbox.js"></script>
</body>

</html>