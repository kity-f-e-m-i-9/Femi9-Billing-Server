<?php /* include("checksession.php");?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Coupons : <?php echo $business_name;?></title>

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
									<td>Coupons</td>
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
                                                    <th>Plan Amount</th>
													<th>Valid Months</th>
													<th>Coupon Number</th>
													<th>Copy</th>
													<th>Available</th>
													<th>Coupon Date</th>
                                                </tr>
                                            </thead>
											
											<tbody>
					<?php $select_product_list="select * from coupons where stock_user_tempid='$loguser_tempid' and user_type='stockiest' order by id desc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											$product_id=base64_encode($result_product_list["id"]);
											$coupon_status=$result_product_list['coupon_status'];
											?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td>&#8377; <?php echo $result_product_list["plan_amount"];?></td>
													<td><?php echo $result_product_list["valid_months"];?> Months</td>
													
													<td>
													<input type="text" id="copyText<?php echo $result_product_list['id'];?>" value="<?php echo $result_product_list["coupon_number"];?>" class="txtbordernone" readonly>
													</td>
													
													<td>
													<?php if($coupon_status=="none"){?>
													<button id="copyButton" onclick="copyToClipboard<?php echo $result_product_list['id'];?>()" class="bordernone"><i class="material-icons-two-tone">copy</i></button>
													<?php }else{ echo "<span class='badge badge-style-bordered badge-danger'>Used</span>"; }?> </td>
													
													<script>
function copyToClipboard<?php echo $result_product_list['id'];?>() {
    // Find the input element with the text to copy
    var copyText = document.getElementById("copyText<?php echo $result_product_list['id'];?>");

    // Select the text
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices

    // Copy the text to the clipboard
    document.execCommand("copy");

    // Optionally, you can provide some feedback to the user
    alert("Copied the text: " + copyText.value);
}
</script>

<td>
<?php if($coupon_status=="none"){?>
<span class="badge badge-style-bordered badge-success">Available</span>
<?php }else{?>
<span class="badge badge-style-bordered badge-danger">Used</span>
<?php }?>
</td>

<td>
<?php echo date("d/m/Y",strtotime($result_product_list['coupon_date'])); ?>
</td>
													
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
                                        </table>
										
										</ style="overflow-x:scroll;"div>
                                    
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
<?php */?>