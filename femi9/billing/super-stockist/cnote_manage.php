<?php include("checksession.php");
include("config.php");
error_reporting(0);

//$getinvuser=$_REQUEST['invuser'];
//invuser = super_stockiest
//invuser = stockiest
//invuser = distributor
//invuser = shop	

$displaytitle="Manage Return (Credit Note)";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?=$displaytitle;?>  : <?php echo $business_name;?></title>

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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
</head>

<body>

<?php
// Check for error message in session
if (isset($_SESSION['successMessage'])) {
$successMessage = $_SESSION['successMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $successMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['successMessage']); } ?>


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
									<td><?=$displaytitle;?></td>
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
									
									<style type="text/css">
									#overflowon{width:100%;overflow-x:scroll !important;height:100%;overflow-y:hidden;}
									</style>
									
	
									<div id="overflowon">
                                        <table id="datatable1" class="display" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Invoice Number</th>
													<th>Usertype</th>
													<th>Name</th>
													<th>Return Date</th>
													<th>Return Amount</th>
													<th>Details</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php 
	$select_product_list="select * from user_return_stock where to_usertype='$Login_user_TYPEvl' and to_userid='$Login_user_IDvl' order by id desc";
	$fetch_product_list=mysqli_query($db_conn,$select_product_list);
	while($result_product_list=mysqli_fetch_array($fetch_product_list))
	{
										//customer details
										$getinvuser=$result_product_list['from_usertype'];
										
if($getinvuser=="super_stockiest")
{
	$lablenamedisplay="Super Stockist";
	$tablename="super_stockiest";
	}
else if($getinvuser=="stockiest")
{
	$lablenamedisplay="Stockist";
	$tablename="stockiest";
	}
else if($getinvuser=="super_distributor")
{
	$lablenamedisplay="Super Distributor";
	$tablename="super_distributor";
	}
	
	else if($getinvuser=="distributor")
{
	$lablenamedisplay="Distributor";
	$tablename="distributor";
	}
	
	else if($getinvuser=="outlet")
{
	$lablenamedisplay="Outlet";
	$tablename="outlet";
	}
else if($getinvuser=="shop")
{
	$lablenamedisplay="Shop";
	$tablename="shop";
	}
	else{
		$lablenamedisplay="Customer";
	}
	
	
	if($getinvuser=="customer")
	{
		$CuSTID=$result_product_list['from_userid'];
		if($CuSTID!=0)
		{
		$select_Customers="select * from customers where id='$CuSTID'";
									$fetch_Customers=mysqli_query($db_conn,$select_Customers);
									$result_Customers=mysqli_fetch_array($fetch_Customers);
										$Cust_Name=$result_Customers['name'];
										$Cust_Mbile=$result_Customers['mobile'];
		}else{
			$Cust_Name="Walking Customer";
			$Cust_Mbile="---";
		}
										
	}
	else
	{
		$CuSTID=$result_product_list['from_userid'];
		$select_Customers="select * from ".$tablename." where temp_id='$CuSTID'";
		$fetch_Customers=mysqli_query($db_conn,$select_Customers);
		$result_Customers=mysqli_fetch_array($fetch_Customers);
		$Cust_Name=$result_Customers['name'];
		$Cust_Mbile=$result_Customers['mobile_number'];
	}
										
										//INVOICE Number
										$invid=$result_product_list['invnumber'];
										if($getinvuser=="customer")
	{
		$select_userinvoice="select * from invoice where inv_id='$invid'";
	}else{
										$select_userinvoice="select * from user_invoice where inv_id='$invid'";
										}
									$fetch_userinvoice=mysqli_query($db_conn,$select_userinvoice);
									$result_userinvoice=mysqli_fetch_array($fetch_userinvoice);
											
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_userinvoice["inv_number"];?></td>
													<td><?php echo $lablenamedisplay;?></td>
													<td><?php echo $Cust_Name;?><br/>M: <?php echo $Cust_Mbile;?></td>
													<td><?php echo date("d/M/Y",strtotime($result_product_list["date"]));?></td>

				<td><?php echo inr_format($result_product_list["total"], 2);?>
				<?php if($result_product_list["status"]=="pending"){ echo "<br/>"; echo $msgpayment="<span class='badge badge-style-bordered badge-danger'>Incomplete</span>"; }?>
				</td>
													
													<td>
<a href="cnote_details?returnid=<?php echo base64_encode($result_product_list["returnid"]);?>" title="Print">
<img src="../../assets/images/details-32.png"/></a>
													</td>
													
													
	

													
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