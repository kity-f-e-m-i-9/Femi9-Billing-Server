<?php include("checksession.php");
include("config.php");
 error_reporting(0);

$user_type_Loginvl=$Login_user_TYPEvl;
$user_id_Loginvl=$Login_user_IDvl;
	 
if(isset($_REQUEST['update-opstock']))
{
$pr_id = implode("#",$_REQUEST['pr_id']);
$op_qty = implode("#",$_REQUEST['op_qty']);
	
$pr_id_exp = explode ("#",$pr_id); 
$op_qty_exp = explode ("#",$op_qty); 

$number = count($pr_id_exp); 
for ($ops=0; $ops<=$number; $ops++) 
{ 
     $pr_id_value = $pr_id_exp[$ops]; 
     $op_qty_value = $op_qty_exp[$ops]; 
	 
	 date_default_timezone_set("Asia/Kolkata");
	 $opening_stock_date=date("Y-m-d");
	 
	 $user_type=$Login_user_TYPEvl;
	 $user_id=$Login_user_IDvl;
	 
	 $select_count_opstock="select count(*) as numopstock from stock where product_id='$pr_id_value' and user_type='$user_type' and user_id='$user_id'";
	 $fetch_count_opstock=mysqli_query($db_conn,$select_count_opstock);
	 $result_count_opstock=mysqli_fetch_array($fetch_count_opstock);
	 
	 if($result_count_opstock['numopstock']==0 && $pr_id_value!=NULL && $op_qty_value!=NULL)
	 {
		 
	 $update_stockQty="insert into stock (product_id,opening_qty,opening_date,input_qty,sales_qty,sent_qty,closing_qty,user_type,user_id,returnqty) 
	 values ('$pr_id_value','$op_qty_value','$opening_stock_date','0','0','0','$op_qty_value','$user_type','$user_id','0')";
	 mysqli_query($db_conn,$update_stockQty);
	 
	 }
} 

echo "<script>window.location='op-stock.php?StockUpdatedSuccess';</script>";
	
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
    <title>Set Opening Stock : <?php echo $business_name;?></title>

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
                            <div class="col">
                                <div class="page-description">
                                     <h1>
									<table class="headertble">
									<tr>
									<td>Set Opening Stock</td>
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
                                       
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data">

                                        <div class="example-container">
                                            <div class="example-content">
											
											
											<table class="table">
											<thead>
											<tr>
											<th>Product Name</th>
											<th>Opening Stock Qty</th>
											</tr>
											</thead>
											
											
											<tbody>
										<?php $select_product_list="select * from products order by id asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
										
	$stock_product_ID=$result_product_list['id'];						
											
	$select_count_opstock13="select * from stock where user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl' and product_id='$stock_product_ID'";
	 $fetch_count_opstock13=mysqli_query($db_conn,$select_count_opstock13);
	 $result_opstock_value=mysqli_fetch_array($fetch_count_opstock13);
	 $result_count_opstock13=mysqli_num_rows($fetch_count_opstock13);
		 if($result_count_opstock13==0)
		 {
							?>
                        <input type="hidden" name="pr_id[]" value="<?php echo $result_product_list['id']; ?>"/>
                                                <tr>
                                                    <td><?php echo $result_product_list["productName"];?></td>
													<td><input type="number" name="op_qty[]" class="form-control" required="" style="border-color:#000 !important;" placeholder="Opening Stock Qty" min="0"/></td>
                                                </tr>
                                           
										<?php 
		 }
		 else{
			 ?>
			 <tr>
                                                    <td><?php echo $result_product_list["productName"];?></td>
													<td><input type="number" value="<?=$result_opstock_value['opening_qty'];?>" disabled class="form-control"/></td>
                                                </tr>
			 <?php
											
										
		 }
		 }
										?>
										
										
										<?php if($result_count_opstock13==0){?>
										<tr>
										<td></td>
										<td>
										<button type="submit" onclick="return confirm('Please make a confirm!');" name="update-opstock" class="btn btn-primary"><i class="material-icons">update</i>Update</button></td>
										</tr>
										<?php }?>
										
										 </tbody>
                                        </table>
										
										
										<?php /*?>
										<table class="table">
											<thead>
											<tr>
											<th>Product Name</th>
											<th>Opening Stock Qty</th>
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
										
										$OPQTY=$Result_OPStock['opening_qty'];
										$OPQTY123+=$OPQTY;
										?>
                                                <tr>
												<td style="display:none;"><?php echo date("d/M/Y",strtotime($Result_OPStock['opening_date']));?></td>
                                                    <td>
													<a href="#" class="popup-trigger">
													<?php echo $Result_productDetils["productName"];?></a></td>
													<td align="right"><?php echo $Result_OPStock['opening_qty'];?></td>
                                                </tr>
                                           
										<?php }?>
										
										<tr>
										<td align="left"><b>Total</b></td>
										<td align="right"><b><?php echo $OPQTY123;?></b></td>
										</tr>
										
										 </tbody>
                                        </table>
										
										 
										 
										  <!-- Popup container -->
<div id="popup" class="popup">
    <h2>Stock Details</h2>
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
        $('#popup-content').html("<p>Stock Opening Date : <b>" + rowData[0] + "</b></p><p>Product Name : <b>" + rowData[1] + "</b></p><p>Opening Stock Qty : <b>" + rowData[2] + "</b></p>");

        // Show the popup
        $('#popup').fadeIn();
    });

    // Close popup when close button is clicked
    $('#close-popup').click(function(){
        $('#popup').fadeOut();
    });
});
</script>

<?php */?>

												
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