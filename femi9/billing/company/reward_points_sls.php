<?php include("checksession.php");
include("config.php");
error_reporting(0);

$getinvuser=$_REQUEST['femiusr'];

if($getinvuser=="super_stockiest")
{	$lablenamedisplay="Super Stockist";
	$tablename="super_stockiest";
}
	
else if($getinvuser=="stockiest"){
	$lablenamedisplay="Stockist";
	$tablename="stockiest";
}

else if($getinvuser=="super_distributor"){
	$lablenamedisplay="Super Distributor";
	$tablename="super_distributor";
}

else{
	$lablenamedisplay="Distributor";
	$tablename="distributor";
	}
	
	
date_default_timezone_set("Asia/Kolkata");

$numberOfDays = date('t');
$current_month=date("m");
//Current Month Period

if($_REQUEST['frdate']==NULL)
{
$current_from_date=date("Y-".$current_month."-01");
$current_to_date=date("Y-".$current_month."-".$numberOfDays."");
}else{
	
$current_from_date=date("Y-m-d",strtotime($_REQUEST['frdate']));
$current_to_date=date("Y-m-d",strtotime($_REQUEST['todate']));
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
    <title><?=$Report_LABLE;?>  : <?php echo $business_name;?></title>

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
									<td>Reward Points<br/><h3><?=$lablenamedisplay;?></h3></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
							
<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
<input type="hidden" name="femiusr" value="<?=$getinvuser;?>"/>

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$current_from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$current_to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>

							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>	
							
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
									
									<style type="text/css">
									#overflowon{width:100%;overflow-x:scroll !important;height:100%;overflow-y:hidden;}
									</style>
									
	
									<div id="overflowon">
                                        <table id="datatable1" class="display" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Name</th>
													<th>District</th>
													<th>Points</th>
												</tr>
                                            </thead>
											
											<tbody>
<?php 
$pdo = new PDO("mysql:host=".$servername.";dbname=".$dbname."", "".$username."", "".$password."");

$query_RFR_PRE = "
    SELECT from_user_id, SUM(rwpoints_sls) AS total_points
    FROM user_invoice_items where DATE between '$current_from_date' and '$current_to_date' and 
	from_user_type='$getinvuser'
    GROUP BY from_user_id
    ORDER BY total_points DESC
";

$stmt_RFR_PRE = $pdo->query($query_RFR_PRE);
$results_RFR_PRE = $stmt_RFR_PRE->fetchAll(PDO::FETCH_ASSOC);

foreach ($results_RFR_PRE as $row_RFR_PRE) {
	
    $from_user_id = $row_RFR_PRE['from_user_id'];
    $total_points = $row_RFR_PRE['total_points'];
	
	//SUM return points
	$SLCT_LM_POINTS_RTN="select sum(rwpoints_sls) from user_return_stock_items where to_usertype='$getinvuser' and to_userid='$from_user_id' and date between '$current_from_date' and '$current_to_date'";
	$FETCH_LM_POINTS_RTN=mysqli_query($db_conn,$SLCT_LM_POINTS_RTN);
	$RESULT_LM_POINTS_RTN=mysqli_fetch_array($FETCH_LM_POINTS_RTN);
	if($RESULT_LM_POINTS_RTN[0]>0){$TotalPoints_LM_RTN=$RESULT_LM_POINTS_RTN[0];}else{$TotalPoints_LM_RTN="0";}
	
	//Sum customers invoice points
	$select_sum_cus_points="select sum(rwpoints) from invoice_items where user_type='$getinvuser' and user_id='$from_user_id' and date between '$current_from_date' and '$current_to_date'";
	$fetch_sum_cus_points=mysqli_query($db_conn,$select_sum_cus_points);
	$result_sum_cus_points=mysqli_fetch_array($fetch_sum_cus_points);
	$Total_cus_points=$result_sum_cus_points[0] ?? '0';
	
	$pointsdeffer=$total_points+$Total_cus_points-$TotalPoints_LM_RTN;
	if($pointsdeffer>0){ $PointShow=$pointsdeffer;}else{ $PointShow="0";}
	
	//GET USER DETAILS
	$select_Customers="select * from ".$tablename." where temp_id='$from_user_id'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										$Cust_Name=$result_Customers['name'];
										$Cust_Mbile=$result_Customers['mobile_number'];
										
										//District
$usr_district_id=$result_Customers['district_id'] ?? 0;
if(is_numeric($usr_district_id))
{	
$select_distict="select * from district where id='$usr_district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'] ?? 'Nil';
}else{
	$district_name=$usr_district_id;
}
//District End
?>
                                            
                                                <tr>
                                                    <td><?=$sn=$sn+1;?></td>
                                                    <td><?=strtoupper($Cust_Name);?><br/><?=$Cust_Mbile;?></td>
													<td><?=$district_name;?></td>
													<td><?=$PointShow;?></td>
													</tr>
													
<?php }?>
										 </tbody>
										 
										
                                        </table>
										</div><!--overflow on end***-->
										
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