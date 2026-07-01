<?php include("checksession.php");
include("config.php");
error_reporting(0);

$title="Edit Expenses";
$manage_url="exp_manage";
$manage_title="Manage Expenses";
$message_title="Expenses";

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from ms_exp where id='$get_id'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				$result_product_list=mysqli_fetch_array($fetch_product_list);
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
<form action="exp_action_update" method="post" enctype="multipart/form-data" id="uploadForm">

<input type="hidden" name="update_id" value="<?=$get_id;?>">
<input type="hidden" name="old_icon" value="<?=$result_product_list["photos"];?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
											
					<label class="form-label">Date*</label>
            <input type="date" value="<?=$result_product_list['date'];?>" disabled required class="form-control">
			<br/>
			
            <label class="form-label">Amount*</label>
            <input type="number" name="amount" value="<?=$result_product_list['amount'];?>" min="1" max="99999" required class="form-control">
			<br/>
			
			<label class="form-label">Bill Copy*</label>
            <input type="file" name="photos" id="fileUpload" accept=".jpg, .jpeg, .png" class="form-control">
			
			<?php 
			if($result_product_list["photos"]!="Nil" && $result_product_list["photos"]!=NULL){
						$imgsrcname="bill_copy_photos/".$result_product_list["photos"]."";}else{
							$imgsrcname="../../assets/images/no image.jpg";}
							?>
							<img src="<?=$imgsrcname;?>" style="height:200px;margin-top:10px;margin-bottom:20px;">
			
			<br/>

			
			<label class="form-label">Remarks*</label>
            <textarea name="remarks" onkeypress="restrictSpecialChars(event)" class="form-control" required><?=$result_product_list['remarks'];?></textarea>
			<br/>
			
			
	<button type="submit" name="add-shop" onclick="return confirm('Please make a confirm');" class="btn btn-primary">
	<i class="material-icons">add</i>Update</button>
												
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

/*
document.getElementById('uploadForm').addEventListener('submit', function(event) {
    const fileInput = document.getElementById('fileUpload');
    if (!fileInput.files.length || !fileInput.files[0]) {
        event.preventDefault();
        alert('Please select a valid file before submitting.');
    }
});
*/

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