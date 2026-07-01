<?php include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");

$title="Edit Order";
$manage_url="manage_order";
$manage_title="Manage Orders";
$message_title="Orders";

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from ms_orders where id='$get_id'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				$result_product_list=mysqli_fetch_array($fetch_product_list);
				
				$shop_id=$result_product_list['shop_id'];
$select_shopcatt="select * from ms_shop where id='$shop_id'";
$fetch_shopcatt=mysqli_query($db_conn,$select_shopcatt);
$result_shopcatt=mysqli_fetch_array($fetch_shopcatt);


if(isset($_REQUEST['update_no_order']))
{
	
	$update_id=$_POST["update_id"];
	$shop_id=$_POST["shop_id"];

$noorder_reason=str_replace("'","&#39;",$_POST["noorder_reason"]);
	$noorder_reason=RemoveSpecialChar($noorder_reason);
	
	$marketing_tool=$_POST["marketing_tool"];
	$marketing_tool=RemoveSpecialChar($marketing_tool);	
	
	$update_orders="update ms_orders set noorder_reason='$noorder_reason',marketing_tool='$marketing_tool',
	shop_id='$shop_id' where id='$update_id'";
	mysqli_query($db_conn,$update_orders);
	
	$_SESSION['successMessage']="Changes saved successfully!";
	echo "<script>window.location='manage_order';</script>";
	
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

<input type="hidden" name="update_id" value="<?=$get_id;?>">

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
		
<label class="form-label">Reason*</label>
            <textarea name="noorder_reason" onkeypress="restrictSpecialChars(event)" class="form-control" required=""><?=$result_product_list['noorder_reason'];?></textarea>
			<br/>
			
			<label class="form-label">Marketing Tool*</label>
            <textarea name="marketing_tool" onkeypress="restrictSpecialChars(event)" class="form-control" required=""><?=$result_product_list['marketing_tool'];?></textarea>
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