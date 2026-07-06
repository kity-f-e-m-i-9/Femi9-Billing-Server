<?php include("checksession.php"); error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Marketing Staff > Expenses Report : <?php echo $business_name;?></title>

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
								else
								{
						$to_date=date("Y-m-d");
						$from_date=date("Y-m-d", strtotime("-2 days", strtotime($to_date)));;	
								}
						
						$se_msid=$_REQUEST['se_msid'];
						//
						$select_msDetails12="select * from marketing_staff where id='$se_msid'";
$fetch_msDetails12=mysqli_query($db_conn,$select_msDetails12);
$result_msDetails12=mysqli_fetch_array($fetch_msDetails12);
						?>
						
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Marketing Staff > Expenses Report</td>
									<td>
									<a href="ms_expenses_pdf?frd=<?=$from_date;?>&&tod=<?=$to_date;?>&&se_msid=<?=$se_msid;?>" title="Export" target="_blank"><img src="32-pdf.png"></a>
									</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						
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
<div id="searchleftcont">
<label class="form-label">Marketing Staff</label>
<select name="se_msid" class="form-control">
<?php if($se_msid==NULL){?>
<option value="" hidden="">Select</option>
<?php }else{?>
<option value="<?=$se_msid;?>" hidden=""><?=strtoupper($result_msDetails12['ms_name']);?>, <?=$result_msDetails12['ms_mobile'];?></option>
<?php }?>
<?php $select_msDetailsOPT="select * from marketing_staff order by ms_name asc";
$fetch_msDetailsOPT=mysqli_query($db_conn,$select_msDetailsOPT);
while($result_msDetailsOPT=mysqli_fetch_array($fetch_msDetailsOPT))
{
?>
<option value="<?=$result_msDetailsOPT['id'];?>"><?=strtoupper($result_msDetailsOPT['ms_name']);?>, <?=$result_msDetailsOPT['ms_mobile'];?></option>
<?php }?>
</select>
</div>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>
<div id="searchbuttoncont">
<button type="button" onclick="Javascript:window.location='ms_expenses';" style="margin-left:10px;" class="btn btn-primary"><i class="material-icons"></i>Reset</button>
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
                              <div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                 <tr>
                                                    <th>#</th>
													<th>Marketing Staff</th>
                                                    <th>Date</th>
													<th>Amount</th>
													<th>Remarks</th>
													<th>Bill Copy</th>
                                                </tr>
                                            </thead>
											
											<tbody>
<?php 
if($from_date==NULL && $se_msid==NULL)
{
$select_product_list="select * from ms_exp order by id desc";
}
if($from_date!=NULL && $se_msid==NULL)
{
$select_product_list="select * from ms_exp where date between '$from_date' and '$to_date' order by date asc";
}
if($from_date!=NULL && $se_msid!=NULL)
{
$select_product_list="select * from ms_exp where ms_id='$se_msid' and date between '$from_date' and '$to_date' and ms_id='$se_msid' order by date asc";
}


$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{																

					$rowid=base64_encode($result_product_list["id"]);
					
					if($result_product_list["photos"]!="Nil" && $result_product_list["photos"]!=NULL){
						$imgsrcname="../marketing/bill_copy_photos/".$result_product_list["photos"]."";}else{
							$imgsrcname="../../assets/images/no image.jpg";}
				
				$exp_amount=$result_product_list["amount"];
				$exp_amount123+=$exp_amount;
				
				//marketing staff details
$ms_id=$result_product_list['ms_id'];
$select_msDetails="select * from marketing_staff where id='$ms_id'";
$fetch_msDetails=mysqli_query($db_conn,$select_msDetails);
$result_msDetails=mysqli_fetch_array($fetch_msDetails);
				
?>                                            
                    <tr>
                    <td><?php echo ++$i; ?></td>
                    <td><?=$result_msDetails['ms_name'];?><br/>
					<?=$result_msDetails['ms_mobile'];?>
					</td>
					<td>
					<b><?php echo date("d/m/Y",strtotime($result_product_list["date"]));?></td>
					<td><?=inr_format($exp_amount, 2);?></td>
					<td><?=ucwords($result_product_list["remarks"]);?></td>
					
					<td>
					<a data-fslightbox="gallery" href="<?php echo $imgsrcname;?>">View&nbsp;Photo</a>
					</td>
													
													
	
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
										 
										 <tfoot>
										<tr>
										 <td colspan="3">Total</td>
										 <td><?=inr_format($exp_amount123, 2);?></td>
										 <td></td>
										  <td></td>
										 </tr>
										 </tfoot>
										 
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
	<script src="../../assets/plugins/lightbox/fslightbox.js"></script>
	<script src="../../assets/js/pages/lightbox.js"></script>
	
</body>

</html>