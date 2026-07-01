<?php include("checksession.php"); date_default_timezone_set("Asia/Kolkata");?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Invoice : <?php echo $business_name;?></title>

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
									<td>Invoice</td>
									<td><a href="manage-invoice" title="Manage Invoice">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <!----<div class="card-header">
                                        <h5 class="card-title">Basic Input</h5>
                                    </div>--->
                                    <div class="card-body">
									<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
									
<form action="invoice-action" method="post" enctype="multipart/form-data">

                                        <div class="example-container">
                                           <div class="example-content">
          
<label class="form-label">Customer Name*</label>
<select required="" name="customer_id" class="form-control">
<option value="" hidden="">Select</option>
<?php $select_Customers_list="select * from customers order by name asc";
		$fetch_Customers_list=mysqli_query($db_conn,$select_Customers_list);
										while($result_Customers_list=mysqli_fetch_array($fetch_Customers_list))
										{
											?>
<option value="<?php echo $result_Customers_list['id'];?>"><?php echo $result_Customers_list['name'];?></option>
										<?php }?>
</select>
<br/>											

<label class="form-label">Invoice Date*</label>
<input type="date" name="date" value="<?php echo date("Y-m-d");?>" required="" class="form-control">
</br>

<div id="items">
    <div class="item">
	<select required="" name="product_id[]" class="form-control">
<option value="" hidden="">Select</option>
<?php $select_Products_list="select * from customers order by name asc";
		$fetch_Products_list=mysqli_query($db_conn,$select_Products_list);
										while($result_Products_list=mysqli_fetch_array($fetch_Products_list))
										{
											?>
<option value="<?php echo $result_Customers_list['id'];?>"><?php echo $result_Customers_list['name'];?></option>
										<?php }?>
</select>
<input type="number" min="0" name="price[]" class="price" placeholder="Price">
        <input type="number" min="0" name="quantity[]" class="quantity" placeholder="Quantity">
		 <button type="button" class="btn btn-primary remove"><i class="material-icons">remove</i>Remove</button>
    </div>
</div>

<button type="button" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add Item</button>

Total: <span id="total">0</span>

<style type="text/css">
.remove{background:#f00;border:0px;}
.remove:hover,.remove:focus{background:#f00;border:0px;}

#add{background:green;border:0px;}

.item{margin-bottom:6px;}
.item input[type=number]{margin-right:10px;float:left;}

@media(max-width:768px)
{
	.item{height:65px;}
	.item input[type=number]{margin-bottom:5px;}
}

</style>

<script>
$(document).ready(function(){
    // Add item
    $("#add").click(function(){
        $("#items").append('<div class="item"><input type="number" min="0" name="quantity[]" class="quantity" placeholder="Quantity"><input type="number" min="0" name="price[]" class="price" placeholder="Price"><button type="button" class="btn btn-primary remove"><i class="material-icons">remove</i>Remove</button></div>');
    });

    // Remove item
    $(document).on('click', '.remove', function(){
        $(this).parent().remove();
        calculateTotal();
    });

    // Calculate total
    function calculateTotal() {
        var total = 0;
        $('.item').each(function(){
            var quantity = $(this).find('.quantity').val();
            var price = $(this).find('.price').val();
            if(quantity != '' && price != '') {
                total += parseInt(quantity) * parseFloat(price);
            }
        });
        $('#total').text(total);
    }

    // Recalculate total on input change
    $(document).on('keyup', '.quantity, .price', function(){
        calculateTotal();
    });
});
</script>
						
<br/>	<br/>						
<button type="submit" name="add-customer" class="btn btn-primary">Submit Invoice</button>
												
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