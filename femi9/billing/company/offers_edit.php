<?php include("checksession.php");
error_reporting(0);

include("RemoveSpecialChar.php");
include("config.php");

$get_id=base64_decode($_REQUEST['prid']);

$select_product_list="select * from offers_manage where id='$get_id'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
$result_product_list=mysqli_fetch_array($fetch_product_list);

if($result_product_list["offer_img"]!=NULL){
					$imgsrcname="offers_img/".$result_product_list["offer_img"]."";
					}else{$imgsrcname="../../assets/images/no image.jpg";}
	  
if(isset($_REQUEST['add-offers']))
{

$tempid=$_REQUEST['tempid'];
$old_img=$_REQUEST['old_img'];

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
   
   unlink("offers_img/".$old_img."");
}
else
{
	$filename_PRPH4=$old_img;
	}
	
	
	$offer_title=str_replace("'","&#39;",$_REQUEST['offer_title']);
	$offer_title = RemoveSpecialChar($offer_title); 
	
	$expired_date=date("Y-m-d",strtotime($_REQUEST['expired_date']));
	$posted_date=date("Y-m-d");
	
	$usertype=$_REQUEST['usertype'];
		
		$insertrecords="UPDATE offers_manage SET usertype='$usertype',offer_title='$offer_title',
		offer_img='$filename_PRPH4',expired_date='$expired_date' WHERE tempid='$tempid'";
		mysqli_query($db_conn,$insertrecords);

	echo "<script>window.location='offers_manage?updatedSuccess';</script>";
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
    <title>Update Offers : <?php echo $business_name;?></title>

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
									<td>Update Offers</td>
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

<input type="hidden" name="tempid" value="<?=$result_product_list['tempid'];?>">
<input type="hidden" name="old_img" value="<?=$result_product_list['offer_img'];?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
											<label class="form-label">User*</label>
                               <select required name="usertype" class="form-control">
							   <option value="<?=$result_product_list['usertype'];?>" hidden><?=$result_product_list['usertype'];?></option>
							   <option value="super_stockiest">Super Stockist</option>
							   <option value="stockiest">Stockist</option>
							   <option value="distributor">Distributor</option>
							   </select>
												<br/>
                                                
												<label class="form-label">Offer Title*</label>
                               <input type="text" required name="offer_title" value="<?=$result_product_list['offer_title'];?>" onkeypress="restrictSpecialChars(event)" class="form-control">
												<br/>
												
												<label class="form-label">Offer Image (optional)</label>
            <input type="file" name="offer_img" class="form-control" id="fileLogo" accept=".jpg, .jpeg, .png">
			<br/>
			<img src="<?php echo $imgsrcname;?>" style="width:300px;border-radius:10px;" alt="<?=$result_product_list["title"];?>">
			<br/>
			<br/>
			
			<label class="form-label">Expired Date*</label>
                               <input type="date" id="bookingDateu" value="<?=$result_product_list['expired_date'];?>" required name="expired_date" class="form-control">
												<br/>
												
												<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
flatpickr("#bookingDateu", {
        dateFormat: "Y-m-d",
            minDate: "today" // Disallow future dates
        });
</script>
												
												
												
												<button type="submit" name="add-offers" class="btn btn-primary">
												<i class="material-icons">update</i>Update</button>
												
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