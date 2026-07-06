<?php include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);

$tempid=$_REQUEST['tempid'];

$select_Invoice="select * from ot_sales_invoice where tempid='$tempid'";
$fetch_Invoice=mysqli_query($db_conn,$select_Invoice);
$result_Invoice=mysqli_fetch_array($fetch_Invoice);

$select_Invoice_Details="select * from ot_sales where tempid='$tempid'";
$fetch_Invoice_Details=mysqli_query($db_conn,$select_Invoice_Details);
$result_Invoice_Details=mysqli_fetch_array($fetch_Invoice_Details);

//SEND FROM
						$godownid=$result_Invoice_Details['godownid'];
						$select_godowndetails="select * from company_godown where id='$godownid' AND " . godown_finance_filter_sql($db_conn);
						$fetch_godowndetails=mysqli_query($db_conn,$select_godowndetails);
						$result_godowndetails=mysqli_fetch_array($fetch_godowndetails);
						
//DELETE INVOICE Number
$select_Count_Invoice="select * from ot_sales where tempid='$tempid'";
$fetch_Count_Invoice=mysqli_query($db_conn,$select_Count_Invoice);
$result_Count_Invoice=mysqli_num_rows($fetch_Count_Invoice);	
if($result_Count_Invoice==0)
{
   $deletInovice="delete from ot_sales_invoice where tempid='$tempid'";
   mysqli_query($db_conn,$deletInovice);
   
   $_SESSION['sucMessage']="One OT Sales Details Deleted Successfully!";
   echo "<script>window.location='ot-sale-view?alldeleted';</script>";
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
    <title><?=$result_Invoice['inv_number']?> : OT Sales Details : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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
                                    <h1>
									<table class="headertble">
									<tr>
									<td>OT Sales Details</td>
									<td><a href="ot-sale-view">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						
						<?php
// Check for error message in session
if (isset($_SESSION['sucMessage'])) {
$sucMessage = $_SESSION['sucMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $sucMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['sucMessage']); } ?>

						
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
										<h1>Invoice Details</h1>
										<table class="table">
										<thead>
									<tr>
									<th>Company Profile</th>
									<th>Invoice Number</th>
									<th>Invoice Date</th>
									</tr>
									</thead>
									<tbody>
									<tr>
									<td><?php echo $result_godowndetails["gname"];?></td>
									<td><?=$result_Invoice['inv_number']?></td>
									<td><?=date("d/m/Y",strtotime($result_Invoice_Details['date']));?></td>
									</tr>
									</tbody>
									</table>
									
									<h1>Product Details</h1>
                                         <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Product Name</th>
													<th>Qty</th>
													<th>Rate(Rs.)</th>
													<th>Sub Total(Rs.)</th>
													<th>Discount(Rs.)</th>
													<th>Total(Rs.)</th>
													<th>Actions</th>
                                                </tr>
                                            </thead>
											
											<tbody>
										<?php 
		$select_product_list="select * from ot_sales where tempid='$tempid' order by id asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($ResultRecords=mysqli_fetch_array($fetch_product_list))
										{
											
											$RowID=base64_encode($ResultRecords["id"]);
											
											//product details
											$product_id=$ResultRecords['prid'];
											$select_productDetils="select * from products where id='$product_id'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
						
						$PR_qty=$ResultRecords["qty"];
						$PR_rate=$ResultRecords["price"];
						$SubTotal=$PR_qty*$PR_rate;
						$PR_discount=$ResultRecords["discount"];
						$PR_total=$SubTotal-$PR_discount;
						
											?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                    <td><?php echo $Result_productDetils["productName"];?></td>
					<td><?php echo inr_format($PR_qty, 1);?></td>
					<td><?php echo inr_format($PR_rate, 2);?></td>
					<td><?php echo inr_format($SubTotal, 2);?></td>
					<td><?php echo inr_format($PR_discount, 2);?></td>
					<td><?php echo inr_format($PR_total, 2);?></td>
													
																										<td>
													    <div class="actions-group">
													        <a href="ot-sale-delete.php?id=<?=$RowID;?>&&tempid=<?=$tempid;?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
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