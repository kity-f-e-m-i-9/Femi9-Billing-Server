<?php include("checksession.php");
include("config.php");
 error_reporting(0);
 
$user_type_Loginvl=$Login_user_TYPEvl;
$user_id_Loginvl=$Login_user_IDvl;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Overall Stock : <?php echo $business_name;?></title>

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
	<link href="../../assets/css/vlstyle.css" rel="stylesheet">

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
									<td>Overall Stock</td>
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
                                       
                                        <div class="example-container">
                                            <div class="example-content">
											
										<div style="background:#fff;overflow:scroll;width:100%;">
                                        <table class="table">
                                            <thead>
                                               <tr>
											<th>Product Name</th>
											<th>Closing Qty</th>
											<?php /*?><th></th><?php */?>
											</tr>
                                            </thead>
											
											<tbody>
			<?php $select_OPStock="select * from stock where user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
										$Fetch_OPStock=mysqli_query($db_conn,$select_OPStock);
										while($Result_OPStock=mysqli_fetch_array($Fetch_OPStock))
										{
											//Get Product Details
											$StockProductID=$Result_OPStock['product_id'];
											
						$select_productDetils="select * from products where id='$StockProductID'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
						
						if($Result_productDetils["productName"]!=NULL)
						{
						$ClosingStock=$Result_OPStock['closing_qty'];
						$ClosingStock12+=$ClosingStock;
										
										?>
                                                <tr>
                                                    <td>
													<a href="#" class="popup-trigger">
													<?php echo $Result_productDetils["productName"];?></a>
													</td>
													<td style="display:none;"><?php echo $Result_OPStock['opening_qty'];?></td>
													<td style="display:none;"><?php echo date("d/M/Y",strtotime($Result_OPStock['opening_date']));?></td>
													
						<td align="right" style="display:none;"><?=$Result_OPStock['input_qty'];?></td>
						<td align="right" style="display:none;"><?=$Result_OPStock['sales_qty'];?></td>
						<td align="right" style="display:none;"><?=$Result_OPStock['sent_qty'];?></td>
						<td align="right"><b><?=$ClosingStock;?></b></td>
						
						<?php /*?>
						<td>
						<?php 
						$cal_total=$Result_OPStock['input_qty']+$Result_OPStock['sales_qty']+$Result_OPStock['sent_qty'];
						
						if($cal_total==0){?>
						<a href="delete_stock.php?rowid=<?=base64_encode($Result_OPStock['id']);?>" onclick="return confirm('You want to delete confirm?');">
<img src="../../assets/images/delete-32.png"/></a>
						<?php }else { echo "---";}?>
						</td>
						<?php */?>
													
                                                </tr>
                                           
										<?php }?>
										<?php }?>
										
										 </tbody>
										 
										<tfoot>
										<tr>
										<td align="left">Total Stock Qty</td>
										<td align="right"><b><?php echo $ClosingStock12;?></b></td>
										<td></td>
										</tr>
										</tfoot>
										 
                                        </table>
										
										
										<div id="popup" class="popup">
    <h2>Overall Stock Details</h2>
    <div id="popup-content">
        <!-- Content will be loaded dynamically -->
    </div>
    <a href="#" id="close-popup"><img src="../../assets/images/close 32.png"></a>
</div>

<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    // Show popup when button is clicked
    $('.popup-trigger').click(function(){
        var rowData = $(this).closest('tr').find('td').map(function(){
            return $(this).text();
        }).get();

        // Populate popup content with row data
        $('#popup-content').html("<p>Product Name : <b>" + rowData[0] + "</b></p><p>Opening Stock Qty : <b>" + rowData[1] + "</b></p><p>Opening Stock Updated Date : <b>" + rowData[2] + "</b></p><p>Input Qty : <b>" + rowData[3] + "</b></p><p>Sales Qty : <b>" + rowData[4] + "</b></p><p>Sent Qty : <b>" + rowData[5] + "</b></p><p>Closing Qty : <b>" + rowData[6] + "</b></p>");

        // Show the popup
        $('#popup').fadeIn();
    });

    // Close popup when close button is clicked
    $('#close-popup').click(function(){
        $('#popup').fadeOut();
    });
});
</script>
										</div>
										
                                            </div>
                                        </div>
										</form>
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