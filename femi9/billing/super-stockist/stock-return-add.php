<?php include("checksession.php"); 
date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");

	$displaytitle="Add Stock Return";
	$lablenamedisplay="Add Stock Return";
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
                        <br/>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
									
									
									<?php if(isset($_REQUEST['invlidinvoicenumber'])){?><div class="alert alert-danger">Warning ! Invalid Invoice Number.</div><?php }?>
									
									<?php if(isset($_REQUEST['returnaddedsuccess'])){?><div class="alert alert-success">Stock Return added success.</div><?php }?>
									
								<h1>
									<table class="headertble">
									<tr>
									<td><?=$displaytitle;?></td>
									<td><a href="stock-return-manage.php" title="Manage Request">&#9776;</a></td>
									</tr>
									</table>
									</h1>

<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->

<?php
if(isset($_REQUEST['sendreturn']))
{
	$returnid=$_REQUEST['returnid'];
	$invnumber=$_REQUEST['invnumber'];
	
	$selectcountinvoice="select count(*) as numinvvalid from user_invoice where to_user_type='$Login_user_TYPEvl' and to_user_id='$Login_user_IDvl' and inv_number='$invnumber'";
	$fetchcountinvoice=mysqli_query($db_conn,$selectcountinvoice);
	$resultcountinvoice=mysqli_fetch_array($fetchcountinvoice);
	if($resultcountinvoice['numinvvalid']==1)
	{
		$enc_returnid=base64_encode($returnid);
		$enc_invnumber=base64_encode($invnumber);
		
		echo "<script>window.location='stock_return_add2.php?returnid=$enc_returnid&&invnumber=$enc_invnumber';</script>";
	}else{
		
		echo "<script>window.location='stock-return-add.php?invlidinvoicenumber';</script>";
	}
	
}

?>

										<div class="card-footer">
                                        <div class="row invoice-summary">
										
	<form method="post" enctype="multipart/form-data">
	
	 <?php function GeraHashshop($qtd2){ $Caracteres2 = '123456789'; 
$QuantidadeCaracteres2 = strlen($Caracteres2); $QuantidadeCaracteres2--; $Hash2=NULL; 
for($x2=1;$x2<=$qtd2;$x2++){ $Posicao2 = rand(0,$QuantidadeCaracteres2); 
$Hash2 .= substr($Caracteres2,$Posicao2,1); } 
return $Hash2; } $randum_number2=GeraHashshop(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date2=date("dmy");
$temp_time2=date("gis"); 
$tempID2="".$randum_number2."/RTN/".$temp_date2."".$temp_time2."";?>
	
	<input type="hidden" name="returnid" value="<?=$tempID2;?>">
	
<label class="form-label">Enter Invoice Number</label>
<input type="text" name="invnumber" autofocus required="" class="form-control">
</br>	
																	
		<!------------------------------------------------------------------------------>
		<!------------------------------------------------------------------------------>
		
		<button type="submit" style="width:100%;" name="sendreturn" class="btn btn-primary" id="add"><i class="material-icons">send</i>Next</button>
		
		</form>
                                           
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