<?php include("checksession.php");
include("config.php");

$title="Add Demo Awareness";
$manage_url="manage_demo.php";
$manage_title="Manage Demo Awareness";
$message_title="Demo Awareness";

date_default_timezone_set("Asia/Kolkata");
$Today_date=date("Y-m-d");
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
                                    <div class="card-body">

		<?php include("validate-scripts.php");?>
		
<form action="demo_action.php" method="post" id="uploadForm" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">
									   
<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$tempID="".$randum_number."DAWR".$temp_date."".$temp_time."";?>

<input type="hidden" name="tempid" value="<?=$tempID;?>">
<input type="hidden" name="date" value="<?=$Today_date;?>">
<input type="hidden" name="usertype" value="<?=$Login_user_TYPEvl;?>">
<input type="hidden" name="userid" value="<?=$Login_user_IDvl;?>">

<?php 
if($result_LoGuserDtails['onboard_userTYPE']=="stockiest")
{
//FETCH SUPER STOCKIST ID
$select_SSIDGet="select * from stockiest where temp_id='".$result_LoGuserDtails['stockiest_id']."'";
$fetch_SSIDGet=mysqli_query($db_conn,$select_SSIDGet);
$result_SSIDGet=mysqli_fetch_array($fetch_SSIDGet);
?>
<input type="hidden" name="ssid" value="<?php echo $result_SSIDGet['ss_id'];?>">
<input type="hidden" name="stockist_id" value="<?=$result_LoGuserDtails['stockiest_id'];?>">

<?php }else if($result_LoGuserDtails['onboard_userTYPE']=="super_stockiest") {?>

<input type="hidden" name="ssid" value="<?php echo $result_LoGuserDtails['onboard_userID'];?>">
<input type="hidden" name="stockist_id" value="Nil">

<?php }else{?>

<input type="hidden" name="ssid" value="Nil">
<input type="hidden" name="stockist_id" value="Nil">

<?php }?>

<input type="hidden" name="distributor_id" value="Nil">


                                        <div class="example-container">
                                            <div class="example-content">
											
											<label class="form-label">Date*</label>
            <input type="date" disabled value="<?=$Today_date;?>" class="form-control">
			<br/>
											
            <label class="form-label">Demo Title*</label>
            <input type="text" required="" autofocus onkeypress="restrictSpecialChars(event)" name="title" class="form-control">
			<br/>
			
			<label class="form-label">Demo Photo *</label>
            <input type="file" required="" name="photo" id="fileUpload" accept=".jpg, .jpeg, .png" class="form-control">
			<br/>
	
	<button type="submit" name="ADDDEMOAWR" class="btn btn-primary">
	<i class="material-icons">add</i>Add</button>
												
                                            </div>
                                        </div>
										</form>
										
										
										<script>
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

document.getElementById('uploadForm').addEventListener('submit', function(event) {
    const fileInput = document.getElementById('fileUpload');
    if (!fileInput.files.length || !fileInput.files[0]) {
        event.preventDefault();
        alert('Please select a valid file before submitting.');
    }
});
</script>
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