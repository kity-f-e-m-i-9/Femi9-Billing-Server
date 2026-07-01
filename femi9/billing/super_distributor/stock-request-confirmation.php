<?php include("checksession.php"); 
date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");

	$displaytitle="Stock Request Details";
	$lablenamedisplay="Stock Request";
	
	$get_req_id=$_REQUEST['reqid'];
	$get_req_id_DECODE=base64_decode($get_req_id);
	//
	$select_reqdetaiis="select * from stock_request where reqid='$get_req_id_DECODE'";
	$fetch_reqdetaiis=mysqli_query($db_conn,$select_reqdetaiis);
	$result_reqdetaiis=mysqli_fetch_array($fetch_reqdetaiis);
	$payment_screenshot=$result_reqdetaiis['screenshot'];
	
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?php echo $displaytitle;?> : <?php echo $business_name;?></title>

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
                            <div class="col-md-12">
                                <div class="card">
                                   
                                    <div class="card-body">
								    <h1>
									<table class="headertble">
									<tr>
									<td><?=$displaytitle;?></td>
									<td><a href="stock-request-manage.php" title="Manage Request">&#9776;</a></td>
									</tr>
									</table>
									</h1>
									
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
						
										<div class="card-footer">
                                        <div class="row invoice-summary">
<label class="form-label">Date</label>
<input type="date" value="<?=date("Y-m-d");?>" disabled required="" class="form-control">
</br>

<?php 
if($payment_screenshot!="Nil"){
$imgshow="<img src='screenshot/$payment_screenshot' style='width:300px;'>";
?>
<div style="margin-top:10px;">
<label class="form-label"><b>Payent Screenshot:-</b></label><br/>
<?=$imgshow;?>
</br>
</div>
<?php } ?>

<?php 
if($result_reqdetaiis['screenshot2']!="Nil"){
$imgshow="<img src='screenshot/".$result_reqdetaiis['screenshot2']."' style='width:300px;'>";
?>
<div style="margin-top:10px;">
<label class="form-label"><b>Payent Screenshot(2):-</b></label><br/>
<?=$imgshow;?>
</br>
</div>
<?php } ?>

<?php if($result_reqdetaiis['amount']!=NULL && $result_reqdetaiis['amount']>0){?>
<div style="margin-top:10px;">
<label class="form-label"><b>Transaction Amount(Rs.)</b></label><br/>
<?=number_format($result_reqdetaiis['amount'],2,'.','');?>
</br>
</div>
<?php }?>

<?php if($result_reqdetaiis['utr']!="Nil"){?>
<div style="margin-top:10px;">
<label class="form-label"><b>UTR/TRANSACTION Number</b></label><br/>
<?=$result_reqdetaiis['utr'];?>
</br>
</div>
<?php }?>

<div style="margin-top:10px;">
<label class="form-label"><b>Delivery Address:-</b></label><br/>
<?=$result_reqdetaiis['delivery_address'];?>
</br>
</div>
										
		<!-------------------------SHOP CURRENT STOCK-------------------------------->
		<div style="background:#fff;overflow:scroll;width:100%;margin-top:20px;">
		<table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Product</th>
                                                            <th scope="col">Qty</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
										<?php 
					$select_productCurrentst="select * from stock_request_items where reqid='$get_req_id_DECODE' order by id asc";
					$fetch_productCurrentst=mysqli_query($db_conn,$select_productCurrentst);
					while($result_productCurrentst=mysqli_fetch_array($fetch_productCurrentst))
										{
											$prid=$result_productCurrentst['prid'];
											//
											 $select_pramount="select * from products where id='$prid'";
	 $fetch_pramount=mysqli_query($db_conn,$select_pramount);
	 $result_pramount=mysqli_fetch_array($fetch_pramount);
											
											if($result_productCurrentst['qty']>0)
										{
										?>
        <input type="hidden" name="prid[]" value="<?php echo $result_productCurrentst['id']; ?>"/>
        <tr>
                                 <td><a href="#" class="popup-trigger"><?php echo $result_pramount["productName"];?></a></td>
		<td><?=$result_productCurrentst['qty'];?></td>
		<td style="display:none;"><?=$result_productCurrentst['amount'];?></td>
		<td style="display:none;"><?=$result_productCurrentst['subtotal'];?></td>
		<td style="display:none;"><?=$result_productCurrentst['gsttotal'];?> (<?=$result_productCurrentst['gst'];?>%)</td>
		<td align="right" style="display:none;"><?=number_format($result_productCurrentst['total'],2,'.','');?></td>
                                                </tr>
                                           
										<?php }?>
										<?php }?>
										
										 </tbody>
													
													</table>
													
													
													
													<div id="popup" class="popup">
    <h2>Stock Request Details</h2>
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
        $('#popup-content').html("<p>Product Name : <b>" + rowData[0] + "</b></p><p>Qty : <b>" + rowData[1] + "</b></p><p>Amount : <b>" + rowData[2] + "</b></p><p>Subtotal : <b>" + rowData[3] + "</b></p><p>GST : <b>" + rowData[4] + "</b></p><p>Total : <b>" + rowData[5] + "</b></p>");

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
												
		<!------------------------------------------------------------------------------>
		<!------------------------------------------------------------------------------>
		
                                           
                                            <div class="col-lg-5"></div>
											
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