<?php include("checksession.php"); 
include("config.php"); 
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);

//get Godown Details
$select_Godowndetails="select * from company_godown where id='".$_REQUEST['gid']."'";
$fetch_Godowndetails=mysqli_query($db_conn,$select_Godowndetails);
$result_Godown=mysqli_fetch_array($fetch_Godowndetails);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Add Return Stock : <?php echo $business_name;?></title>

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
									<td>Add Return Stock</td>
									<td><a href="manage-return" title="Manage Input Stock">&#9776;</a></td>
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
									
									
									<div class="card-body">
									<?php if(isset($_REQUEST['stocknotupdated'])){?><div class="alert alert-danger">Please update opening stock (<?=$result_Godown['gname'];?>) !</div>
									<?php }?>
									
									<?php if(isset($_REQUEST['alreadyexists'])){?><div class="alert alert-danger">Invalid Return Stock details, already exists !</div>
									<?php }?>
                                       
									   
									   
<?php include("validate-scripts.php");?>
<form action="return-action" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">


<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$tempID="".$randum_number."RTST/".$temp_date."/".$temp_time."";?>

<input type="hidden" name="tempid" value="<?=$tempID?>">

                                        <div class="example-container">
                                        <div class="example-content">
										
										<label for="exampleInputEmail1" class="form-label">Company Profile</label>
                               <select required="" name="godownid" class="form-control">
							   <option value="" hidden="">Select</option>
							   <?php $select_Godown="select * from company_godown order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						   <option value="<?=$result_Godown['id'];?>"><?=$result_Godown['gname'];?></option>
							   <?php }?>
							   </select>
							   <br/>
							   
										
<label class="form-label">Date*</label>
<input type="date" required="" name="date" value="<?php echo date("Y-m-d");?>" class="form-control">
<br/>
          
<label class="form-label">Product Name*</label>
<select required="" name="prid" class="form-control">
<option value="" hidden="">Select</option>
<?php $select_product_list="select * from products";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											?>
<option value="<?php echo $result_product_list['id'];?>"><?php echo $result_product_list['productName'];?></option>
										<?php }?>
</select>
<br/>

<label class="form-label">Return Qty*</label>
<input type="number" required="" min="0" max="9999" name="returnqty" class="form-control">
<br/>

<label class="form-label">Remarks*</label>
<input type="text" required="" name="remarks" onkeypress="restrictSpecialChars(event)" class="form-control">
<br/>
												

												
<button type="submit" name="add-record" class="btn btn-primary"><i class="material-icons">add</i>Add</button>
												
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