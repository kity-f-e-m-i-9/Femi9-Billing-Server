<?php include("checksession.php");
include("config.php");
error_reporting(0);

$Report_LABLE="Outlet Visit";

if($_REQUEST['lable']==1 )
{
$DISPLAY_LABLE="Super Stockist (Stockist Visit)";  	$usertypevl="super_stockiest";
//$tblenma="super_stockiest"; 
}

else if($_REQUEST['lable']==2)
{
$DISPLAY_LABLE="Stockist (Distributor Visit)"; 			$usertypevl="stockiest";
//$tblenma="stockiest"; 
}

else
{
$DISPLAY_LABLE="Distributor (Shop Visit)";  		$usertypevl="distributor";
//$tblenma="distributor"; 
}

$start_date=$_REQUEST['frdate'];
$endDate=$_REQUEST['todate'];	
$seuser=$_REQUEST['seuser'];
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
									<td><?=$Report_LABLE;?></td>
									<td><a href="Report">&#8592;&nbsp;Go&nbsp;Back</a></td>
									<td><a href="export_report_outlet?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&lable=<?=$_REQUEST['lable'];?>&&rptlable=<?=$_REQUEST['rptlable'];?>&&out1=<?=$_REQUEST['out1'];?>&&out2=<?=$_REQUEST['out2'];?>&&out3=<?=$_REQUEST['out3'];?>&&out4=<?=$_REQUEST['out4'];?>&&seuser=<?=$seuser;?>&&indistinct=<?=$indistinct;?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<?php if(!isset($_REQUEST['seaction'])){ ?>
						<!------<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title"><?=$DISPLAY_LABLE;?></span>
												<table id="reportdash">
												<tr>
												<th>Today</th>
												<td>:&nbsp;<?=$_REQUEST['out1'];?></td>
												</tr>
												<tr>
												<th>Yesterday</th>
												<td>:&nbsp;<?=$_REQUEST['out2'];?></td>
												</tr>
												<tr>
												<th>This&nbsp;Month</th>
												<td>:&nbsp;<?=$_REQUEST['out3'];?></td>
												</tr>
												<tr>
												<th>Last&nbsp;Month&nbsp;Till&nbsp;Date</th>
												<td>:&nbsp;<?=$_REQUEST['out4'];?></td>
												</tr>
												</table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>----->
							<?php }?>
							
							
							
							
							
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

<!-----------------------DISTRIBUTOR-------------------------------->
									<?PHP if($_REQUEST['lable']==3){?>
									
<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
<input type="hidden" name="lable" value="<?=$_REQUEST['lable'];?>"/>
<input type="hidden" name="rptlable" value="<?=$_REQUEST['rptlable'];?>"/>
<input type="hidden" name="out1" value="<?=$_REQUEST['out1'];?>"/>
<input type="hidden" name="out2" value="<?=$_REQUEST['out2'];?>"/>
<input type="hidden" name="out3" value="<?=$_REQUEST['out3'];?>"/>
<input type="hidden" name="out4" value="<?=$_REQUEST['out4'];?>"/>
<input type="hidden" name="seaction" value="searchfilter"/>

<div class="overviewcontainar">
<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$start_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$endDate;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">Distributor</label>
<input type="text" name="seuser" placeholder="%Like%" value="<?=$seuser;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchcheckbox">
<label class="form-label">indistinct</label>
<?php if($_REQUEST['indistinct']==1){?>
<input type="checkbox" name="indistinct" value="1" checked>
<?php }else{?>
<input type="checkbox" name="indistinct" value="1">
<?php }?>
</div>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>
</div>
<div style="clear:both;"></div>
<br/>
</form>
							
							
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Distributor ID</th>
													<th>Distributor Name</th>
													<th>Mobile</th>
                                                    <th>No of Visit</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Pincode</th>
												</tr>
                                            </thead>
											
										<tbody>
<?php
if($seuser==NULL){	
$select_records="select * from distributor order by id asc";
}else{
$select_records="select * from distributor where name LIKE '%$seuser%' order by id asc";	
}	
$exe_records=mysqli_query($db_conn,$select_records);
while($fetch_records=mysqli_fetch_array($exe_records))
{
	
	$distributorID=$fetch_records['temp_id'];
	//
	if($_REQUEST['indistinct']==NULL)
	{
	$select_visitcnt="select count(distinct date) numvstoutlet from user_invoice where to_user_type='shop' and from_user_type='distributor' and from_user_id='$distributorID'";
	}else{
	$select_visitcnt="select count(*) numvstoutlet from user_invoice where to_user_type='shop' and from_user_type='distributor' and from_user_id='$distributorID'";	
	}
	$exe_visitcnt=mysqli_query($db_conn,$select_visitcnt);
	$fetch_visitcnt=mysqli_fetch_array($exe_visitcnt);
	$Total_Visit_Count=$fetch_visitcnt['numvstoutlet'];
	
$useridtext=$fetch_records["useridtext"];
$usernametext=$fetch_records["name"];

$stateid=$fetch_records['state_id'];
$distid=$fetch_records['district_id'];
						
//District Details						
$selectrecords_VLSS1="select * from district where state_id='$stateid' and id='$distid'";
$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
				
//Taluk Details				
$talukid=$fetch_records['taluk_id'];
$selectrecordstaluk="select * from taluk where state_id='$stateid' and dist_id='$distid' and id='$talukid'";
$fetchrecords_VLSS2_taluk=mysqli_query($db_conn,$selectrecordstaluk);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
										
//Pincode Details				
$pincodeid=$fetch_records['pincode_id'];
$selectrecordspincode="select * from pincode where state_id='$stateid' and dist_id='$distid' and taluk_id='$talukid' and id='$pincodeid'";
$exerecordspincode=mysqli_query($db_conn,$selectrecordspincode);
$fetchrecordspincode=mysqli_fetch_array($exerecordspincode);
$pincodeshow=$fetchrecordspincode['pincode'];

if($Total_Visit_Count>0)
{
?>
                                            
                                                <tr>
                            <td><?php echo ++$i; ?></td>
							<td><?=$useridtext;?></td>
							<td><?=$usernametext;?></td>
							<td><?=$fetch_records["mobile_number"];?></td>
							<td style="background:#EEE;font-weight:bold;"><?=$Total_Visit_Count;?></td>
							<td><?=$district_name_VLSS1;?></td>
							<td><?=$taluk_name_VLSS2;?></td>
							<td><?=$pincodeshow;?></td>
				
                                        </tr>
                                           
<?php } }?>
										
										
                                        </table>
										<?PHP }?>
									
			<!------------------END***----------------------------------------->		
										
										
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