<?php include("checksession.php"); 
date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");

	$displaytitle="Stock Request Details";
	$lablenamedisplay="Stock Request";
	
	$get_req_id=$_REQUEST['reqid'];
	$get_req_id_DECODE=base64_decode($get_req_id);
	//
	 $select_requestdetails="select * from stock_request where reqid='$get_req_id_DECODE'";
	 $fetch_requestdetails=mysqli_query($db_conn,$select_requestdetails);
	 $result_requestdetails=mysqli_fetch_array($fetch_requestdetails);
	 
	 $get_from_usertype=$result_requestdetails['fromusertype'];
	
if($get_from_usertype=="super_stockiest")
{$batchname="super-stockist";}
else if($get_from_usertype=="stockiest")
{$batchname="stockist";}
else if($get_from_usertype=="distributor")
{$batchname="distributor";}
else
{$batchname="outlet";}
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
									<td><?=$displaytitle;?><br><h3>Purchase Order</h3></td>
									<td><a href="stock_request_pending_accounts" title="Manage Request">&#9776;</a></td>
									</tr>
									</table>
									</h1>
									
						
		
		

<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->

<?php if(isset($_REQUEST['AddedSuccess'])){?>
<div class="alert alert-success">one product added success.</div>
<?php }?>
								
								<?php if(isset($_REQUEST['ItemAlreadyExists'])){?><div class="alert alert-danger">invalid product, already exists.</div><?php }?>
								
								<?php if(isset($_REQUEST['InvalidStock'])){?><div class="alert alert-danger">invalid qty, out of stock.</div><?php }?>
								
								<?php if(isset($_REQUEST['DeleteSuccess'])){?><div class="alert alert-danger">Deleted ! one product deleted success.</div><?php }?>




<div class="card-footer">
                                        <div class="row invoice-summary">
										
										
										
										<table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Date of Request</th>
													<th>PO From</th>
													<th>PO Qty</th>
													<th>PO Value(Rs.)</th>
													<th>Transaction Amount(Rs.)</th>
                                                </tr>
                                            </thead>
											
											<tbody>
					<?php 
											$reqid=$result_requestdetails['reqid'];
											
											$fromusertype=$result_requestdetails['fromusertype'];
											$fromuserid=$result_requestdetails['fromuserid'];
											
											if($fromusertype=="distributor")
											{
											$tablename="distributor";
											}
											if($fromusertype=="stockiest")
											{
												$tablename="stockiest";
											}
											if($fromusertype=="super_stockiest")
											{
												$tablename="super_stockiest";
											}
											if($fromusertype=="outlet")
											{
												$tablename="outlet";
											}
											
											
											
											//get distributor details
				$select_distlist="select * from ".$tablename." where temp_id='$fromuserid'";
				$fetch_distlist=mysqli_query($db_conn,$select_distlist);
				$result_distlist=mysqli_fetch_array($fetch_distlist);
				$distributorname=$result_distlist['name'];
				
				//get qty total
				//get qty total
				$select_qtytotal="select sum(qty) from stock_request_items where reqid='$reqid'";
				$fetch_qtytotal=mysqli_query($db_conn,$select_qtytotal);
				$result_qtytotal=mysqli_fetch_array($fetch_qtytotal);
				if($result_qtytotal[0]!=NULL){
				$totalqty=$result_qtytotal[0];
				}else{$totalqty="0";}
				
				//get total amount
				$select_TotalAMont="select sum(total) from stock_request_items where reqid='$reqid'";
				$fetch_TotalAMont=mysqli_query($db_conn,$select_TotalAMont);
				$result_TotalAMont=mysqli_fetch_array($fetch_TotalAMont);
				if($result_TotalAMont[0]!=NULL){
				$totalAmount=inr_format($result_TotalAMont[0], 2);
				}else{$totalAmount="0.00";}
				
				if($result_requestdetails["amount"]!=NULL)
				{$trns_AMount=$result_requestdetails["amount"];}else{$trns_AMount="0";}
			
			if($result_requestdetails["utr"]!=NULL)
				{$utrvalue=$result_requestdetails["utr"];}else{$utrvalue="---";}
				?>
  <tr valign="top">
                                 <td><?php echo date("d/m/Y",strtotime($result_requestdetails["date"]));?></td>
								 
								<td><b><?=strtoupper($result_distlist['name']);?></b>
								<br/>
								<span style="font-size:13px;color:#999;"><?=strtoupper($fromusertype);?></span>
								</td>	
								
<td><?=$totalqty;?></td>		
<td><b><?=$totalAmount;?></b></td>	
<td><?=inr_format($trns_AMount, 2);?></td>	
													
													
                                                </tr>
                                           
										
										 </tbody>
                                        </table>
										
										<hr/>
										
										


<?php if($result_requestdetails['screenshot']!="Nil"){ ?>
<div style="margin-top:0px;">
<label class="form-label"><b>PAYMENT SCREENSHOT</b></label><br/>
<img src='../<?=$batchname;?>/screenshot/<?=$result_requestdetails['screenshot'];?>' style='width:300px;'>
</br>
</div>
<?php } ?>

<?php if($result_requestdetails['screenshot2']!="Nil"){ ?>
<div style="margin-top:10px;">
<label class="form-label"><b>PAYMENT SCREENSHOT (2)</b></label><br/>
<img src='../<?=$batchname;?>/screenshot/<?=$result_requestdetails['screenshot2'];?>' style='width:300px;'>
</br>
</div>
<?php } ?>

<?php /*?>
<?php if($result_requestdetails['screenshot3']!="Nil"){ ?>
<div style="margin-top:10px;">
<label class="form-label"><b>PAYMENT SCREENSHOT (3)</b></label><br/>
<img src='../<?=$batchname;?>/screenshot/<?=$result_requestdetails['screenshot3'];?>' style='width:300px;'>
</br>
</div>
<?php } ?>

<?php if($result_requestdetails['screenshot4']!="Nil"){ ?>
<div style="margin-top:10px;">
<label class="form-label"><b>PAYMENT SCREENSHOT (4)</b></label><br/>
<img src='../<?=$batchname;?>/screenshot/<?=$result_requestdetails['screenshot4'];?>' style='width:300px;'>
</br>
</div>
<?php } ?>

<?php if($result_requestdetails['screenshot5']!="Nil"){ ?>
<div style="margin-top:10px;">
<label class="form-label"><b>PAYMENT SCREENSHOT (5)</b></label><br/>
<img src='../<?=$batchname;?>/screenshot/<?=$result_requestdetails['screenshot5'];?>' style='width:300px;'>
</br>
</div>
<?php } ?>
<?php */?>


<div style="margin-top:10px;">
<label class="form-label"><b>UTR/TRANSACTION Number</b></label><br/>
<?=$utrvalue;?>
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
											
										?>
        <input type="hidden" name="prid[]" value="<?=$prid; ?>"/>
        <tr>
        <td><a href="#" class="popup-trigger"><?php echo $result_pramount["productName"];?></a></td>
		<td><?=$result_productCurrentst['qty'];?></td>
		<td style="display:none;"><?=$result_productCurrentst['amount'];?></td>
		<td style="display:none;"><?=$result_productCurrentst['subtotal'];?></td>
		<td style="display:none;"><?=$result_productCurrentst['gsttotal'];?> (<?=$result_productCurrentst['gst'];?>%)</td>
		<td align="right" style="display:none;"><?=inr_format($result_productCurrentst['total'], 2);?></td></td>
		
                                                </tr>
                                           
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
		<a href="approved_accounts?reqid=<?=$get_req_id;?>">
<button class="btn btn-primary footersubact" type="button" onclick="return confirm('Please make a confirm!');" style="width:100%;">Approved Request</button></a>


<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
		
                                           
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