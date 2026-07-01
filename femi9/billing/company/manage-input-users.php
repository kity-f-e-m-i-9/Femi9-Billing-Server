<?php include("checksession.php"); error_reporting(0);?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage Input Stocks Users : <?php echo $business_name;?></title>

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
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
    </style>
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
								
								<?php if(isset($_REQUEST['addesuccess'])){?><div class="alert alert-success">Input stock details added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one input stock details deleted success.</div><?php }?>
								
								
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
									<td>Manage Input Stock : Users</td>
									<td><a href="add-input-users" title="Add Input Stocks Users">&#10011;</a></td>
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
                                        <div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>User Type</th>
													<th>User Name</th>
													<th>District</th>
													<th>Taluk</th>
													<th>Date</th>
                                                    <th>Product Name</th>
													<th>Input Qty</th>
													<th>Remarks</th>
													<th>Actions</th>
                                                </tr>
                                            </thead>
											
										<tbody>
										<?php $select_product_list="select * from input_stock_users where input_date between '$from_date' and '$to_date' and still_maintain='1' order by input_date desc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
						$RowID=base64_encode($result_product_list["id"]);
						//
						$product_id=$result_product_list['product_id'];
						$select_productDetils="select * from products where id='$product_id'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
						//User Details 
						$st_user_type=$result_product_list['usertype'];
						$to_userid=$result_product_list['userid'];
						//
		if($st_user_type=="super_stockiest"){
		$tblename="super_stockiest";
		}
		else if($st_user_type=="stockiest"){
			$tblename="stockiest";
		}
		else if($st_user_type=="super_distributor"){
			$tblename="super_distributor";
		}
		else{
			$tblename="distributor";
		}
	//
	if($st_user_type!="super_stockiest")
	{
	$select_usrdetails="select temp_id,name,mobile_number,district_id,taluk_id 
	from ".$tblename." where temp_id='$to_userid' order by name asc";
	}
	else
	{
	$select_usrdetails="select temp_id,name,mobile_number,district_id 
	from ".$tblename." where temp_id='$to_userid' order by name asc";	
	}
		$fetch_userdetails=mysqli_query($db_conn,$select_usrdetails);
		$result_userdetails=mysqli_fetch_array($fetch_userdetails);
			
//District
$usr_district_id=$result_userdetails['district_id'] ?? 0;
$user_name=$result_userdetails['name'];

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

//Taluk
if($st_user_type!="super_stockiest")
{
$usr_taluk_id=$result_userdetails['taluk_id'] ?? 0;
if(is_numeric($usr_taluk_id))
{	
$select_taluk="select taluk from taluk where id='$usr_taluk_id'";
$fetch_taluk=mysqli_query($db_conn,$select_taluk);
$result_taluk=mysqli_fetch_array($fetch_taluk);
$talk_name=	$result_taluk['taluk'] ?? 'Nil';
}
else
{
	$talk_name=$usr_taluk_id;
}
}
else
{
	$talk_name="Nil";
}
//Taluk End
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
													
													<td><?php echo $st_user_type;?></td>
													<td><?php echo $user_name;?></td>
													<td><?php echo $district_name;?></td>
													<td><?php echo $talk_name;?></td>
													
													<td><?php echo date("d/M/Y",strtotime($result_product_list["input_date"]));?></td>
                                                    <td><?php echo $Result_productDetils["productName"];?></td>
													<td><?php echo $result_product_list["input_qty"];?></td>
													<td><?php echo $result_product_list["remarks"];?></td>
													
													<!-----													<td>
													    <div class="actions-group">
													        <a href="edit-customer.php?prid=<?php echo $RowID;?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
													    </div>
													</td>---->
													
																										<td>
													    <div class="actions-group">
													        <a href="delete-input-users?Roowid=<?php echo $RowID;?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
													    </div>
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