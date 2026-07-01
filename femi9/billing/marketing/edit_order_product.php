<?php include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(0);

$title="Edit Order";
$manage_url="manage_order_product";
$manage_title="Manage Orders";
$message_title="Orders";

$orderid=$_REQUEST['orderid'];
$select_product_list="select * from ms_orders where order_id='$orderid'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				$result_product_list=mysqli_fetch_array($fetch_product_list);
				
				$shop_id=$result_product_list['shop_id'];
$select_shopcatt="select * from ms_shop where id='$shop_id'";
$fetch_shopcatt=mysqli_query($db_conn,$select_shopcatt);
$result_shopcatt=mysqli_fetch_array($fetch_shopcatt);


if(isset($_REQUEST['update_no_order']))
{
	
	$update_orderid=$_POST["update_orderid"];
	
	//Delete old order details
    $delete_old_orders="delete from ms_orders where order_id='$update_orderid'";
	mysqli_query($db_conn,$delete_old_orders);
	
	$ms_id=$_POST["ms_id"];
	$order_date=$_POST["order_date"];
	$shop_id=$_POST["shop_id"];
	
	$marketing_tool=$_POST["marketing_tool"];
	$marketing_tool=RemoveSpecialChar($marketing_tool);	
	
	$product_id = implode("#",$_REQUEST['pr_id']);
$qty = implode("#",$_REQUEST['qty']);
	
$product_id_ex = explode ("#",$product_id); 
$qty_ex = explode ("#",$qty); 

$number = count($product_id_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $product_id_value = $product_id_ex[$i]; 
     $qty_value = $qty_ex[$i]; 
	 $qty_value = RemoveSpecialChar($qty_value);
	 
	 if($product_id_value!=NULL)
	 {
	
$select_count_dist="select count(*) as numShop from ms_orders where order_id='$update_orderid' and pr_id='$product_id_value'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
$result_count_dist=mysqli_fetch_array($fetc_count_dist);
if($result_count_dist['numShop']==0)
	{
	
        $sql="insert into ms_orders (order_id,shop_id,ms_id,order_date,new_order,noorder_reason,marketing_tool,pr_id,qty) values ('$update_orderid','$shop_id','$ms_id','$order_date','yes','nil','$marketing_tool',
		'$product_id_value','$qty_value')";
		mysqli_query($db_conn,$sql);

	}
	
	
	 }
	 
}
	
	$_SESSION['successMessage']="Changes saved successfully!";
	echo "<script>window.location='manage_order_product';</script>";
	
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
		
		<style>
		table td{padding:5px !important;}
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
									<td><?php echo $title;?></td>
									<td><a href="<?php echo $manage_url;?>" title="<?php echo $manage_title;?>">&#9776;</a></td>
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
									
                               <?php include("validate-scripts.php");?>        
<form method="post" enctype="multipart/form-data">

<input type="hidden" name="update_orderid" value="<?=$orderid;?>">
<input type="hidden" name="ms_id" value="<?=$result_product_list['ms_id'];?>">
<input type="hidden" name="order_date" value="<?=$result_product_list['order_date'];?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
											<label for="exampleInputEmail1" class="form-label">Shop*</label>
    <select name="shop_id" class="form-control" required="">
	<option value="<?=$result_shopcatt['id'];?>" hidden=""><?=$result_shopcatt['name'];?></option>
	<?php $selectShopCat="select * from ms_shop where ms_id='$markeingSTFID' order by id asc";
	$fetchShopCat=mysqli_query($db_conn,$selectShopCat);
	while($resultShopCat=mysqli_fetch_array($fetchShopCat)){?>
	<option value="<?php echo $resultShopCat['id'];?>"><?php echo $resultShopCat['name'];?></option>
	<?php  } ?>
	</select>
	<br/>

			
			<label class="form-label">Marketing Tool*</label>
            <textarea name="marketing_tool" onkeypress="restrictSpecialChars(event)" class="form-control" required=""><?=$result_product_list['marketing_tool'];?></textarea>
			<br/>		
			
			<?php 
			 $select_product_listGETD="select * from ms_orders where order_id='$orderid' order by id asc";
				$fetch_product_listGETD=mysqli_query($db_conn,$select_product_listGETD);
				$coun_product_listGETD=mysqli_num_rows($fetch_product_listGETD);
				?>
			<script>
        function addRow(tableID) {
	var table = document.getElementById(tableID);
	var rowCount = table.rows.length;
	if(rowCount < 100){							// limit the user from creating fields more than your limits
		var row = table.insertRow(rowCount);
		var colCount = table.rows[<?=$coun_product_listGETD;?>].cells.length;
		for(var i=0; i<colCount; i++) {
			var newcell = row.insertCell(i);
			newcell.innerHTML = table.rows[<?=$coun_product_listGETD;?>].cells[i].innerHTML;
		}
	}else{
		 alert("Maximum allowed record is 100.");
			   
	}
}
function deleteRow(tableID) {
	var table = document.getElementById(tableID);
	var rowCount = table.rows.length;
	for(var i=0; i<rowCount; i++) {
		var row = table.rows[i];
		var chkbox = row.cells[0].childNodes[0];
		if(null != chkbox && true == chkbox.checked) {
			if(rowCount <= 1) { 						// limit the user from removing all the fields
				alert("Cannot Remove all Field .");
				break;
			}
			table.deleteRow(i);
			rowCount--;
			i--;
		}
	}
}</script> 
				
				<p> 
					<button type="button" class="btn btn-primary btn-burger" onClick="addRow('dataTable')"><i class="material-icons">add</i></button> 
					<button type="button" class="btn btn-danger btn-burger" onClick="deleteRow('dataTable')"><i class="material-icons">delete_outline</i></button>
				</p>
				
				 <table id="dataTable" border="0">
					
					
				<?php 
				while($result_product_listGETD=mysqli_fetch_array($fetch_product_listGETD))
				{
				//Product Details
				$select_PRDetails="select * from `products` where id='".$result_product_listGETD['pr_id']."'";
				$fetch_PRDetails=mysqli_query($db_conn,$select_PRDetails);
				$result_PRDetails=mysqli_fetch_array($fetch_PRDetails);
				
				?>
				  <tr>
						<td>&nbsp;</td>
						 <td>
							<select name="pr_id[]" class="form-control">
<option value="<?=$result_product_listGETD['pr_id'];?>" hidden=""><?=$result_PRDetails['productName'];?></option>
										</td>

<td><input type="number" placeholder="Qty" min="0" value="<?=$result_product_listGETD['qty'];?>" name="qty[]" class="form-control"/>
						 </td>
                    </tr>
				<?php }?>
					
					
                    <tr>
						<td><input type="checkbox" name="chk[]"/></td>
						 <td>
							<select name="pr_id[]" class="form-control">
<option value="" hidden="">Select Product</option>
<?php $select_product_list12="select * from products";
										$fetch_product_list12=mysqli_query($db_conn,$select_product_list12);
										while($result_product_list12=mysqli_fetch_array($fetch_product_list12))
										{
											?>
<option value="<?=$result_product_list12['id'];?>"><?=$result_product_list12['productName'];?></option>
										<?php }?>
										</td>

<td><input type="number" placeholder="Qty" min="0" name="qty[]" class="form-control"/>
						 </td>
                    </tr>
                </table>
				<br/>	
			
	<button type="submit" name="update_no_order" class="btn btn-primary">
	<i class="material-icons">update</i>Update</button>
												
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