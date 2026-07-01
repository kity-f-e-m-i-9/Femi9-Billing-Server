<?php /* include("checksession.php"); 
include("config.php"); 
date_default_timezone_set("Asia/Kolkata");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Add Internal Stock Transfer : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
	<link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
	
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
									<td>Add Internal Stock Transfer</td>
		<td><a href="internal_transfer_manage" title="Manage Internal Stock Transfer">&#9776;</a></td>
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
									
<?php if(isset($_REQUEST['InvalidStock'])){?><div class="alert alert-danger">There is no stock for the quantity you entered!</div><?php }?>
									
	 
	 <?php 
	 
$slctcheckstock="select count(*) as numstockcheck from stock where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_ProductsPrice=mysqli_query($db_conn,$slctcheckstock);
$Result_ProductsPrice=mysqli_fetch_array($fetch_ProductsPrice);
if($Result_ProductsPrice['numstockcheck']!=0)
{
	?>
	 
<form action="internal_action" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">


<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$tempid="".$randum_number."INTTRNS".$temp_date."".$temp_time."";?>

<input type="hidden" name="tempid" value="<?=$tempid?>">
<input type="hidden" name="from_usertype" value="<?=$Login_user_TYPEvl?>">
<input type="hidden" name="from_userid" value="<?=$Login_user_IDvl?>">
<input type="hidden" name="to_usertype" value="super_stockiest">

                                        <div class="example-container">
                                        <div class="example-content">
							   
							   <script type="text/javascript">
function showstockavailable(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintstock").innerHTML=xmlhttp.responseText;}}
var invuser="super_stockiest";
xmlhttp.open("GET","loadstockcheck.php?q="+str + '&invuser='+ invuser,true);
xmlhttp.send();}
</script>

							   <label for="exampleInputEmail1" class="form-label">Send To</label>
                               <select required="" name="to_userid" class="form-control" onchange="showstockavailable(this.value)">
							   <option value="" hidden="">Select</option>
							   <?php $select_Godown="select * from super_stockiest order by name asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						  <option value="<?=$result_Godown['temp_id'];?>"><?=strtoupper($result_Godown['name']);?>, <?=$result_Godown['mobile_number'];?></option>
							   <?php }?>
							   </select>
							   <br/>
<!------------------------------------GODOWN------------------------------>								
<label class="form-label">Date*</label>
<input type="date" required="" name="date" value="<?php echo date("Y-m-d");?>" class="form-control">
<br/>


<label for="exampleInputEmail1" class="form-label">Product</label>
                               <select required="" name="prid" class="form-control">
							   <option value="" hidden="">Select</option>
							   <?php $select_product_list="select * from products order by id asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{?>
<option value="<?=$result_product_list['id'];?>"><?=strtoupper($result_product_list['productName']);?></option>
							   <?php }?>
							   </select>
							   <br/>

<label class="form-label">Transfer Qty*</label>
<input type="number" name="qty" class="form-control" required="" min="0" max="999999"/>
<br/>
                                           

			<span id="txtHintstock">									
<button type="submit" name="addInvoice" class="btn btn-primary"><i class="material-icons">add</i>Add</button>
</span>
												
                                            </div>
                                        </div>
										</form>
										
										<?php }else{?>
<span style="color:red;">Please update opening stock!</span>
<?php }?>
	 
										
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
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/select2.js"></script>
</body>

</html>
<?php */ ?>