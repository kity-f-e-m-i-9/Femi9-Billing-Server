<?php include("checksession.php");
$title="Purchase Order Report : Completed";
date_default_timezone_set("Asia/Kolkata");
$current_date=date("Y-m-d");
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
    <title><?php echo $title;?> : <?php echo $business_name;?></title>

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
                                     <h1>
									<table class="headertble">
									<tr>
									<td><?php echo $title;?></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    
                                    <div class="card-body">
									
									<?php
									if($_REQUEST['fromdate']!=NULL)
									{
									$get_from_date=$_REQUEST['fromdate'];
									$get_to_date=$_REQUEST['todate'];
									}else{
									$get_from_date=date("Y-m-d");
									$get_to_date=date("Y-m-d");	
										
									}
									?>
									
<form action="<?=$_SERVER['PHP_SELF'];?>" method="post" enctype="multipart/form-data">

                                        <div class="example-container">
                                            <div class="example-content">
							   
						<label for="exampleInputEmail1" class="form-label">From Date</label>
						<input type="date" name="fromdate" value="<?=$get_from_date;?>" required="" class="form-control">
							   
						<label for="exampleInputEmail1" class="form-label">To Date</label>
						<input type="date" name="todate" required="" value="<?=$get_to_date;?>" class="form-control">
						<br/>
												
				<button type="submit" name="search-network" class="btn btn-primary">
				<i class="material-icons">search</i>Search</button>
												
                                            </div>
                                        </div>
										
										</form>
										<br/>
										
										<table width="100%" class="ReportTablevl">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Date</th>
                                                    <th>Order From</th>
													<th>Order To</th>
													<th>Order Qty</th>
                                                </tr>
                                            </thead>
											 <tbody>
										<?php 
		$select_picnode_list="select * from stock_request where status='billed' and date between '$get_from_date' and '$get_to_date' order by id asc";
		$fetch_picnode_list=mysqli_query($db_conn,$select_picnode_list);
		while($result_pincode_list=mysqli_fetch_array($fetch_picnode_list))
							{
											$reqid=$result_pincode_list['reqid'];
											//get product details
$select_productsitems="select sum(qty) from stock_request_items where reqid='$reqid'";
$fetch_productsitems=mysqli_query($db_conn,$select_productsitems);
$result_prodcutsitems=mysqli_fetch_array($fetch_productsitems);
//
/*$prid=$result_prodcutsitems['prid'];
//
$select_products="select * from products where id='$prid'";
$fetch_products=mysqli_query($db_conn,$select_products);
$result_products=mysqli_fetch_array($fetch_products);
$productname=strtoupper($result_products['productName']);*/

											
											
											$fromusertype=$result_pincode_list['fromusertype'];
											$fromuserid=$result_pincode_list['fromuserid'];
//from user name
if($fromusertype=="super_stockiest"){$tablename="super_stockiest"; $lblevl="Super Stockist";}
else if($fromusertype=="stockiest"){$tablename="stockiest"; $lblevl="Stockist";}
else if($fromusertype=="distributor"){$tablename="distributor"; $lblevl="Distributor";}
else{$tablename="outlet"; $lblevl="Outlet";}
//get user details
$select_fromusers="select * from ".$tablename." where temp_id='$fromuserid'";
$fetch_fromusers=mysqli_query($db_conn,$select_fromusers);
$result_fromusers=mysqli_fetch_array($fetch_fromusers);


											$tousertype=$result_pincode_list['tousertype'];
											$touserid=$result_pincode_list['touserid'];
											
											if($tousertype=="company")
											{
												
												$displaytousersdetai="Company";
												
											}else{
//to user name
if($tousertype=="super_stockiest"){$tablename2="super_stockiest"; $lblevl2="Super Stockist";}
else if($tousertype=="stockiest"){$tablename2="stockiest"; $lblevl2="Stockist";}
else if($tousertype=="distributor"){$tablename2="distributor"; $lblevl2="Distributor";}
else{$tablename2="outlet"; $lblevl2="Outlet";}
//get user details
$select_tousers="select * from ".$tablename2." where temp_id='$touserid'";
$fetch_tousers=mysqli_query($db_conn,$select_tousers);
$result_tousers=mysqli_fetch_array($fetch_tousers);

$displaytousersdetai=" ".$lblevl2."<br/>".strtoupper($tousername)."
									  <br/><b>Mob:</b> ".$tousermobile."";
											}
											
?>
                                      <tr>
									  
                        <td><?=$i=$i+1; ?></td>
						<td><?=date("d/m/Y",strtotime($result_pincode_list['date']));?></td>
                                      
									  <td>
									  <?=$lblevl;?><br/><?=strtoupper($result_fromusers['name'])?>
									  <br/><b>Mob:</b> <?=$result_fromusers['mobile_number'];?>
									  </td>
													
									  <td>
									  <?=$displaytousersdetai;?>
									  </td>
									  <td>
									  <a href="JavaScript:newPopup('order-report-details.php?reqid=<?=$reqid;?>');" data-bs-toggle="tooltip" data-bs-placement="top" title="Product Details" style="font-weight:bold;text-decoration:none;">
									  <?=$result_prodcutsitems[0];?></a>
									  </td>

<script type="text/javascript">
function newPopup(url) {
	popupWindow = window.open(
		url,'popUpWindow','height=450,width=750,left=350,top=200,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes')
}
</script>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>