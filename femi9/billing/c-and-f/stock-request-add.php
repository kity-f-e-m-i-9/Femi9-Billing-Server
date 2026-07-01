<?php include("checksession.php"); 
date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");

	$displaytitle="Add Stock Request";
	$lablenamedisplay="Add Stock Request";
	
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
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <!----<div class="card-header">
                                        <h5 class="card-title"><?=$displaytitle;?></h5>
                                    </div>---->
                                    <div class="card-body">
									
									<?php /* if(isset($_REQUEST['addesuccess'])){?><div class="alert alert-success">Shop details added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['alreadyexists'])){?><div class="alert alert-danger">Shop details already exists !</div>
									<?php } */ ?>
								
								<h1>
									<table class="headertble">
									<tr>
									<td><?=$displaytitle;?></td>
									<td><a href="stock-request-manage.php" title="Manage Request">&#9776;</a></td>
									</tr>
									</table>
									</h1>
									
<?php
$select_count_opstock13="select count(*) as numopstock12 from stock where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_count_opstock13=mysqli_query($db_conn,$select_count_opstock13);
$result_count_opstock13=mysqli_fetch_array($fetch_count_opstock13);
if($result_count_opstock13['numopstock12']==0)
{
?>
<div class="alert alert-danger">Please update opening stock ! <a href="op-stock.php">Click here</a></div>
<?php }else{?>

<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
						

										<div class="card-footer">
                                        <div class="row invoice-summary">
										
					<?php include("validate-scripts.php");?>					
	<form action="stock_req_action.php" method="post" id="uploadForm" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">
	
	 <?php function GeraHashshop($qtd2){ $Caracteres2 = '123456789'; 
$QuantidadeCaracteres2 = strlen($Caracteres2); $QuantidadeCaracteres2--; $Hash2=NULL; 
for($x2=1;$x2<=$qtd2;$x2++){ $Posicao2 = rand(0,$QuantidadeCaracteres2); 
$Hash2 .= substr($Caracteres2,$Posicao2,1); } 
return $Hash2; } $randum_number2=GeraHashshop(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date2=date("dmy");
$temp_time2=date("gis"); 
$tempID2="".$randum_number2."/STR/".$temp_date2."".$temp_time2."";?>
	
	
	<input type="hidden" name="reqid" value="<?=$tempID2;?>">
	
	<input type="hidden" name="fromusertype" value="<?=$Login_user_TYPEvl;?>">
	<input type="hidden" name="fromuserid" value="<?=$Login_user_IDvl;?>">
	
	<?php 
	if($result_LoGuserDtails['onboard_userTYPE']!=NULL && $result_LoGuserDtails['onboard_userID']!=NULL)
	{
	$display_tousertype=$result_LoGuserDtails['onboard_userTYPE'];
	$display_touserid=$result_LoGuserDtails['onboard_userID'];
	}else{
		$display_tousertype="company";
	$display_touserid="company";
	}
	?>
	
	<input type="hidden" name="tousertype" value="<?=$display_tousertype;?>">
	<input type="hidden" name="touserid" value="<?=$display_touserid;?>">

<label class="form-label">Date</label>
<input type="date" value="<?=date("Y-m-d");?>" disabled required="" class="form-control">
<input type="hidden" name="date" value="<?=date("Y-m-d");?>">
</br>


<?php 
//get user profile details
$selectuserprofiles23="select * from users_profile where usertype='$Login_user_TYPEvl' and user_tempid='$Login_user_IDvl'";
$fetchuserprofiles23=mysqli_query($db_conn,$selectuserprofiles23);
$resultuserprofiles23=mysqli_fetch_array($fetchuserprofiles23);
?>
<label class="form-label">Delivery Address</label>
<textarea name="delivery_address" required="" onkeypress="restrictSpecialChars(event)" class="form-control"><?=$resultuserprofiles23['deliveryaddress'];?></textarea>
</br>

<?php /*?>
<script type="text/javascript">
function showpayment(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintstate").innerHTML=xmlhttp.responseText;}}
var name="10";
xmlhttp.open("GET","loadreq.php?q="+str + '&name='+ name,true);
xmlhttp.send();}
</script>
<label class="form-label">Type</label>
<select required="" class="form-control" name="reqtype" onChange="showpayment(this.value)">
<option value="" hidden=""></option>
<option>Credit</option>
<option>Payment</option>
</select>
</br>
<div id="txtHintstate"></div>
<?php */?>
<input type="hidden" name="reqtype" value="Payment">

<label class="form-label">Upload Payment Screenshot *</label>
<input type="file" required="" id="fileUpload" accept=".jpg, .jpeg, .png" name="screenshot" class="form-control"><br/>
<label class="form-label">Upload Screenshot(2)</label>
<input type="file" id="fileUpload2" accept=".jpg, .jpeg, .png" name="screenshot2" class="form-control"><br/>

<label class="form-label">Enter Transaction Amount(Rs.) *</label>
<input type="number" required="" name="amount" min="0" onkeypress="restrictnumber(event)" class="form-control"><br/>

<label class="form-label">UTR/TRANSACTION Number *</label>
<input type="text" required="" name="utr" onkeypress="restrictSpecialChars(event)" class="form-control"><br/>	

										
		<!-------------------------SHOP CURRENT STOCK-------------------------------->
		<table class="table invoice-table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Product</th>
                                                            <th scope="col">Qty</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
										<?php 
					$select_productCurrentst="select * from products order by id asc";
					$fetch_productCurrentst=mysqli_query($db_conn,$select_productCurrentst);
					while($result_productCurrentst=mysqli_fetch_array($fetch_productCurrentst))
										{
										?>
        <input type="hidden" name="prid[]" value="<?php echo $result_productCurrentst['id']; ?>"/>
        <tr>
                                 <td><?php echo $result_productCurrentst["productName"];?></td>
		<td><input type="number" name="qty[]" class="form-control" required="" autofocus style="border-color:#000 !important;" placeholder="Qty" min="0" onkeypress="restrictnumber(event)"/></td>
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
													
													</table>
												
		<!------------------------------------------------------------------------------>
		<!------------------------------------------------------------------------------>
		
		<button type="submit" style="width:100%;" name="sendrequest" class="btn btn-primary" id="add"><i class="material-icons">send</i>Send Request</button>
		
		</form>
		
		
		<script>
		<!-----VALIDATE SCREENSHOT(1)----->
document.getElementById('fileUpload').addEventListener('change', function(event) {
    validateFile(event.target.files[0]);
});

function validateFile(file) {
    const validExtensions = ['image/jpeg', 'image/jpg', 'image/png'];
    const maxSizeMB = 5; // Maximum file size in megabytes
    const maxSizeBytes = maxSizeMB * 1024 * 1024; // Convert MB to bytes

    if (!validExtensions.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileUpload').value = '';
        return;
    }

    if (file.size > maxSizeBytes) {
        alert('File is too large. The maximum file size is 5 MB.');
        document.getElementById('fileUpload').value = '';
        return;
    }
}

<!-----VALIDATE SCREENSHOT(2)----->
document.getElementById('fileUpload2').addEventListener('change', function(event) {
    validateFile2(event.target.files[0]);
});

function validateFile2(file) {
    const validExtensions2 = ['image/jpeg', 'image/jpg', 'image/png'];
    const maxSizeMB2 = 5; // Maximum file size in megabytes
    const maxSizeBytes2 = maxSizeMB2 * 1024 * 1024; // Convert MB to bytes

    if (!validExtensions2.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileUpload2').value = '';
        return;
    }

    if (file.size > maxSizeBytes2) {
        alert('File is too large. The maximum file size is 5 MB.');
        document.getElementById('fileUpload2').value = '';
        return;
    }
}


<?php /*?>
<!-----VALIDATE SCREENSHOT(3)----->
document.getElementById('fileUpload3').addEventListener('change', function(event) {
    validateFile3(event.target.files[0]);
});

function validateFile3(file) {
    const validExtensions3 = ['image/jpeg', 'image/jpg', 'image/png'];
    const maxSizeMB3 = 5; // Maximum file size in megabytes
    const maxSizeBytes3 = maxSizeMB3 * 1024 * 1024; // Convert MB to bytes

    if (!validExtensions3.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileUpload3').value = '';
        return;
    }

    if (file.size > maxSizeBytes3) {
        alert('File is too large. The maximum file size is 5 MB.');
        document.getElementById('fileUpload3').value = '';
        return;
    }
}


<!-----VALIDATE SCREENSHOT(4)----->
document.getElementById('fileUpload4').addEventListener('change', function(event) {
    validateFile4(event.target.files[0]);
});

function validateFile4(file) {
    const validExtensions4 = ['image/jpeg', 'image/jpg', 'image/png'];
    const maxSizeMB4 = 5; // Maximum file size in megabytes
    const maxSizeBytes4 = maxSizeMB4 * 1024 * 1024; // Convert MB to bytes

    if (!validExtensions4.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileUpload4').value = '';
        return;
    }

    if (file.size > maxSizeBytes4) {
        alert('File is too large. The maximum file size is 5 MB.');
        document.getElementById('fileUpload4').value = '';
        return;
    }
}



<!-----VALIDATE SCREENSHOT(5)----->
document.getElementById('fileUpload5').addEventListener('change', function(event) {
    validateFile5(event.target.files[0]);
});

function validateFile5(file) {
    const validExtensions5 = ['image/jpeg', 'image/jpg', 'image/png'];
    const maxSizeMB5 = 5; // Maximum file size in megabytes
    const maxSizeBytes5 = maxSizeMB5 * 1024 * 1024; // Convert MB to bytes

    if (!validExtensions5.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileUpload5').value = '';
        return;
    }

    if (file.size > maxSizeBytes5) {
        alert('File is too large. The maximum file size is 5 MB.');
        document.getElementById('fileUpload5').value = '';
        return;
    }
} 
<?php */?>



<!--------------------------------------------------------------------------------->
document.getElementById('uploadForm').addEventListener('submit', function(event) {
    const fileInput = document.getElementById('fileUpload');
    if (!fileInput.value) {
        event.preventDefault();
        alert('Please select a valid file before submitting.');
    }
});
</script>
                                           
                                            <div class="col-lg-5"></div>
											
                                        </div>
                                    </div>
										
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>