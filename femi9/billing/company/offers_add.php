<?php include("checksession.php");
error_reporting(0);

include("RemoveSpecialChar.php");
include("config.php");
	  
if(isset($_REQUEST['add-offers']))
{

$tempid=$_REQUEST['tempid'];

	//UPLOAD BUSINESS LOGO
	$file_extension_PRPH4 = pathinfo($_FILES['offer_img']['name'], PATHINFO_EXTENSION);
	$small_jpg_PRPH4= $_FILES['offer_img']['name'];
	if($small_jpg_PRPH4!=NULL)
	{
		
$DATETIME_PRPH4=date("YmdHis");
$filename_PRPH4="Pr4".$DATETIME_PRPH4."".$tempid.".".$file_extension_PRPH4."";
$uploaddir_PRPH4='offers_img/';
$uploadfile_PRPH4=$uploaddir_PRPH4.$filename_PRPH4;
move_uploaded_file($_FILES['offer_img']['tmp_name'],$uploadfile_PRPH4);
   
}
else
{
	$filename_PRPH4="";
	}
	
	
	$offer_title=str_replace("'","&#39;",$_REQUEST['offer_title']);
	$offer_title = RemoveSpecialChar($offer_title); 
	
	$expired_date=date("Y-m-d",strtotime($_REQUEST['expired_date']));
	$posted_date=date("Y-m-d");
	
	$login_username=$Result_Log_users_Dtails134['username'];
	$login_usertype=$Result_Log_users_Dtails134['usertype'];
	$usertype=$_REQUEST['usertype'];
		
	$selectcount="select count(*) as num from offers_manage where tempid='$tempid'";
	$fetchcount=mysqli_query($db_conn,$selectcount);
	$resultcount=mysqli_fetch_array($fetchcount);
	if($resultcount['num']==0)
	{
		$insertrecords="insert into offers_manage (tempid,usertype,offer_title,offer_img,expired_date,posted_date,login_username,login_usertype) values 
		('$tempid','$usertype','$offer_title','$filename_PRPH4','$expired_date','$posted_date',
		'$login_username','$login_usertype')";
		mysqli_query($db_conn,$insertrecords);
	}
	echo "<script>window.location='offers_manage?addedsuccess';</script>";
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
    <title>Offers : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
	
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">



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
									<td>Add Offers</td>
									<td><a href="offers_manage" title="Manage Offers">&#9776;</a></td>
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
<form method="post" enctype="multipart/form-data" id="uploadForm">


<?php function GeraHash($qtd){ $Caracteres = '123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(16);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("His"); 
$tempID="".$randum_number."".$temp_date."".$temp_time."";?>

<input type="hidden" name="tempid" value="<?=$tempID?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
											<label class="form-label">User*</label>
                               <select required name="usertype" class="form-control">
							   <option value="" hidden>Select User</option>
							   <option value="super_stockiest">Super Stockist</option>
							   <option value="stockiest">Stockist</option>
							   <option value="distributor">Distributor</option>
							   </select>
												<br/>
                                                
												<label class="form-label">Offer Title*</label>
                               <input type="text" required name="offer_title" onkeypress="restrictSpecialChars(event)" class="form-control">
												<br/>
												
												<label class="form-label">Offer Image (optional)</label>
            <input type="file" name="offer_img" class="form-control" id="fileLogo" accept=".jpg, .jpeg, .png">
			<br/>
			
			<label class="form-label">Expired Date*</label>
                               <input type="date" id="bookingDateu" required name="expired_date" class="form-control">
												<br/>
												
												<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
flatpickr("#bookingDateu", {
        dateFormat: "Y-m-d",
            minDate: "today" // Disallow future dates
        });
</script>
												
												
												
												<button type="submit" name="add-offers" class="btn btn-primary">
												<i class="material-icons">add</i>Submit</button>
												
                                            </div>
											
											
                                        </div>
										
										</form>
										
										
										<script>
document.getElementById('fileLogo').addEventListener('change', function(event) {
    validateFile(event.target.files[0]);
});

function validateFile(file) {
    const validExtensions = ['image/jpeg', 'image/jpg', 'image/png'];
    const maxSizeMB = 1; // Maximum file size in megabytes
    const maxSizeBytes = maxSizeMB * 1024 * 1024; // Convert MB to bytes

    if (!validExtensions.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileLogo').value = '';
        return;
    }

    if (file.size > maxSizeBytes) {
        alert('File is too large. The maximum file size is 1 MB.');
        document.getElementById('fileLogo').value = '';
        return;
    }
}

/*document.getElementById('uploadForm').addEventListener('submit', function(event) {
    const fileInput = document.getElementById('fileLogo');
    if (!fileInput.files.length || !fileInput.files[0]) {
        event.preventDefault();
        alert('Please select a valid file before submitting.');
    }
});*/
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