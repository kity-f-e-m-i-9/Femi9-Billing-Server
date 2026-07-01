<?php include("checksession.php");
$title="Other Channel Sales Report";
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
									$get_catname=$_REQUEST['catname'];
									
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
											
											<label for="exampleInputEmail1" class="form-label">Category</label>
                               <select name="catname" class="form-control">
							   <?php if($get_catname==NULL){?>
							   <option value="" hidden="">Select</option>
							   <?php }else{?>
							   <option value="<?=$get_catname;?>" hidden=""><?=$get_catname;?></option>
							   <?php }?>
							   <option value="">--All--</option>
							   <?php $select_stateList="select distinct cat from ot_sales order by cat asc";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   while($result_stateList=mysqli_fetch_array($fetch_staeList))
							   {?>
						   <option value="<?=$result_stateList['cat'];?>"><?=$result_stateList['cat'];?></option>
							   <?php }?>
							   </select>
							   
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
													<th>Category</th>
                                                    <th>Date</th>
													<th>Product</th>
													<th style="text-align:right;">Sales Qty</th>
                                                </tr>
                                            </thead>
											 <tbody>
										<?php 
										if($get_from_date!=NULL && $get_to_date!=NULL && $get_catname==NULL)
										{
						$select_picnode_list="select * from ot_sales where date between '$get_from_date' and '$get_to_date' order by id asc";
										}
										if($get_from_date!=NULL && $get_to_date!=NULL && $get_catname!=NULL)
										{
						$select_picnode_list="select * from ot_sales where date between '$get_from_date' and '$get_to_date' and cat='$get_catname' order by id asc";
										}
										
										
										$fetch_picnode_list=mysqli_query($db_conn,$select_picnode_list);
										while($result_pincode_list=mysqli_fetch_array($fetch_picnode_list))
										{
											
											//state details
											$prid=$result_pincode_list['prid'];
								$select_stateList="select * from products where id='$prid'";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   $productname=$result_stateList['productName'];
							   
							   $slsqty=$result_pincode_list['qty'];
							   $slsqty123+=$slsqty;
							   
											?>
                                                <tr>
                                           <td><?=$i=$i+1; ?></td>
										<td><?=$result_pincode_list['cat'];?></td>
						<td><?=date("d/m/Y",strtotime($result_pincode_list['date']));?></td>
                                                    <td><?=$productname;?></td>
													<td style="text-align:right;"><?=$slsqty;?></td>
                                                </tr>
										<?php }?>
										</tbody>
										
										<tr style="font-weight:bold;">
										<td colspan="4" style="text-align:right;">Grand Total</td>
										<td style="text-align:right;"><?=$slsqty123;?></td>
										</tr>
										
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