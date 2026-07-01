<?php include("checksession.php");
include("config.php");
error_reporting(0);

//fetch profile Details
$selectcoutprofileDetaiils="select * from users_profile where user_tempid='$Login_user_IDvl' and usertype='$Login_user_TYPEvl'";
$fetchcountprofileDetails=mysqli_query($db_conn,$selectcoutprofileDetaiils);
$resultcountprofileDetails=mysqli_fetch_array($fetchcountprofileDetails);

//---------------------------------------------------------------------------------------
//user details
$select_userdetails234="select * from distributor where temp_id='$Login_user_IDvl'";
$fetch_userdetails234=mysqli_query($db_conn,$select_userdetails234);
$result_userdetails234=mysqli_fetch_array($fetch_userdetails234);

//state details
/*
$state_id=$result_userdetails234['state_id'];
$select_state_dtails="select * from state where id='$state_id'";
$fetch_state_dtails=mysqli_query($db_conn,$select_state_dtails);
$result_state_dtails=mysqli_fetch_array($fetch_state_dtails);
$state_name=$result_state_dtails['st_name'];

//district details
$district_id=$result_userdetails234['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

//taluk details
$taluk_id=$result_userdetails234['taluk_id'];
$select_distict="select * from taluk where id='$taluk_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$taluk_name=$result_district['taluk'];

//taluk details
$pincode_id=$result_userdetails234['pincode_id'];
$select_pincode_id="select * from pincode where id='$pincode_id'";
$fetch_pincode=mysqli_query($db_conn,$select_pincode_id);
$result_pincode=mysqli_fetch_array($fetch_pincode);
*/
//-------------------------------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>My Profile : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
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

<?php 

if(isset($_REQUEST['updateprofile']))
{
	$user_tempid=$_REQUEST['user_tempid'];
	$usertype=$_REQUEST['usertype'];
	$companyname=$_REQUEST['companyname'];
	$deliveryaddress=$_REQUEST['deliveryaddress'];
	
	$acname=$_REQUEST['acname'];
	$acnumber=$_REQUEST['acnumber'];
	$bankname=$_REQUEST['bankname'];
	$branchname=$_REQUEST['branchname'];
	$ifsc=$_REQUEST['ifsc'];
	$upinumber=$_REQUEST['upinumber'];
	
	//UPLOAD BUSINESS LOGO
	$old_logo=$_REQUEST['old_logo'];
	$small_LOGO= $_FILES['logo']['name'];
	if($small_LOGO!=NULL)
	{
		
		
$filetype=$_FILES['logo']['type'];
if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png')
{
 $_SESSION['errorMessage'] = 'Allowed extentions are:  jpg, jpeg, png.';
 echo "<script>window.location='Myprofile?uploadError';</script>";
}else{
	
	
	   $rand_isdLG=rand(1,9899989);
	   $fileLogo=$rand_isdLG.$small_LOGO;
                 $uploaddirLG='bussiness_logo/';
                 $uploadfileLG=$uploaddirLG.$fileLogo;
	   move_uploaded_file($_FILES['logo']['tmp_name'],$uploadfileLG);
	   $insfoldernameLG="bussiness_logo/";
	   $insfilenameLG=$insfoldernameLG.$fileLogo;
	   unlink("bussiness_logo/".$old_logo."");
	   
}

	}else{$insfilenameLG=$old_logo;}
	
	
	$selectcoutprofile="select count(*) as numprofile from users_profile where user_tempid='$user_tempid' and usertype='$usertype'";
	$fetchcountprofile=mysqli_query($db_conn,$selectcoutprofile);
	$resultcountprofile=mysqli_fetch_array($fetchcountprofile);
	if($resultcountprofile['numprofile']==0)
	{
	$insertprofile="insert into users_profile (user_tempid,usertype,companyname,deliveryaddress,acname,acnumber,bankname,branchname,ifsc,upinumber,logo) 
	values ('$user_tempid','$usertype','$companyname','$deliveryaddress','$acname','$acnumber','$bankname',
	'$branchname','$ifsc','$upinumber','$insfilenameLG')";
	mysqli_query($db_conn,$insertprofile);
	}else{
		
		$updateprofile="update users_profile set usertype='$usertype',
	companyname='$companyname',deliveryaddress='$deliveryaddress',acname='$acname',acnumber='$acnumber',
	bankname='$bankname',branchname='$branchname',ifsc='$ifsc',upinumber='$upinumber',logo='$insfilenameLG' where user_tempid='$user_tempid'";
	mysqli_query($db_conn,$updateprofile);
		
	
	
}
	
	//----------------------------------------------------------------
	//update user details update
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$gstin=str_replace("'","&#39;",$_REQUEST['gstin']);
	$address=str_replace("'","&#39;",$_REQUEST['address']);
	
	$state_id=str_replace("'","&#39;",$_REQUEST['state_id']);
	$district_id=str_replace("'","&#39;",$_REQUEST['district_id']);
	$taluk_id=str_replace("'","&#39;",$_REQUEST['taluk_id']);
	$pincode_id=str_replace("'","&#39;",$_REQUEST['pincode_id']);
	
	//upload user icon
	$old_user_icon=$_REQUEST['old_user_icon'];
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
		
		$filetype12=$_FILES['user_icon']['type'];
if($filetype12 != 'image/jpeg' && $filetype12 != 'image/jpg' && $filetype12 != 'image/png')
{
 $_SESSION['errorMessage'] = 'Allowed extentions are:  jpg, jpeg, png.';
 echo "<script>window.location='Myprofile?uploadError';</script>";
}else{
	
	
	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='../stockist/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	   unlink("../stockist/".$old_user_icon."");
	   
}

	}else{$insfilename=$old_user_icon;}
	
	$updateusers="update distributor set user_icon='$insfilename',name='$name',email='$email',gstin='$gstin',address='$address',state_id='$state_id',district_id='$district_id',taluk_id='$taluk_id',pincode_id='$pincode_id' where temp_id='$user_tempid'";
	mysqli_query($db_conn,$updateusers);
	//-------------------------------------------------------------------
	
	
	$_SESSION['successMessage'] = 'Profile updated successfully';
	echo "<script>window.location='Myprofile.php?Updatedsuccess';</script>";
}
?>


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
									<td>My Profile</td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<?php /*
// Check for error message in session
if (isset($_SESSION['errorMessage'])) {
$errorMessage = $_SESSION['errorMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'error',
                          title: 'Upload Failed',
                          text: '<?php echo $errorMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['errorMessage']); } */ ?>


<?php  if(isset($_REQUEST['uploadError'])){?><div class="alert alert-warning">Candidate Photo & Logo Allowed extentions are:  jpg, jpeg and png only.</div><?php } ?>


						<?php  if(isset($_REQUEST['Updatedsuccess'])){?><div class="alert alert-success">Profile updated success.</div><?php } ?>
						
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
									
                       <!---ss-action.php---->   
<?php include("validate-scripts.php");?>					   
<form method="post" enctype="multipart/form-data" id="uploadForm" onSubmit="return confirm('Please make a confirm!');">
									   
<input type="hidden" name="user_tempid" value="<?=$Login_user_IDvl?>">
<input type="hidden" name="usertype" value="<?=$Login_user_TYPEvl?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
											<label class="form-label">Candidate Name</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" value="<?=$result_userdetails234['name'];?>" name="name" class="form-control">
			<br/>
			
			<label class="form-label">Candidate Photo</label>
			<input type="hidden" name="old_user_icon" value="<?=$result_userdetails234['user_icon'];?>">
            <input type="file" name="user_icon" class="form-control" id="fileUpload" accept=".jpg, .jpeg, .png">
			
			<?php if($result_userdetails234['user_icon']!="Nil"){?>
			<img src="../stockist/<?=$result_userdetails234['user_icon'];?>" style="width:150px;margin-bottom:10px;margin-top:10px;"/>
			<div style="clear:both;"></div>
			<?php }?>
			<br/>
			
			<label class="form-label">State</label>
            <input type="text" required="" name="state_id" value="<?=$result_userdetails234['state_id'];?>" class="form-control">
			<br/>
			
			<label class="form-label">District</label>
            <input type="text" required="" name="district_id" value="<?=$result_userdetails234['district_id'];?>" class="form-control">
			<br/>
			
			<label class="form-label">Taluk</label>
            <input type="text" required="" name="taluk_id" value="<?=$result_userdetails234['taluk_id'];?>" class="form-control">
			<br/>
			
			<label class="form-label">Pincode</label>
            <input type="text" required="" name="pincode_id" value="<?=$result_userdetails234['pincode_id'];?>" class="form-control">
			<br/>
			
			<label class="form-label">Mobile Number</label>
            <input type="text" required="" value="<?=$result_userdetails234['mobile_number'];?>" disabled class="form-control">
			<br/>
			
			<label class="form-label">Email ID</label>
            <input type="email" required="" onkeypress="restrictemail(event)" value="<?=$result_userdetails234['email'];?>" name="email" class="form-control">
			<br/>
			
			<label class="form-label">GSTIN</label>
            <input type="text" required="" onkeypress="restrictGSTIN(event)" value="<?=$result_userdetails234['gstin'];?>" name="gstin" class="form-control">
			<br/>
			
            <label class="form-label">Business Name</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" value="<?=$resultcountprofileDetails['companyname'];?>" name="companyname" class="form-control">
			<br/>
			
			<label class="form-label">Business Address</label>
            <textarea required="" name="address" onkeypress="restrictSpecialChars(event)" class="form-control"><?=$result_userdetails234['address'];?></textarea>
			<br/>
			
			<label class="form-label">Delivery Address</label>
            <textarea required="" name="deliveryaddress" onkeypress="restrictSpecialChars(event)" class="form-control"><?=$resultcountprofileDetails['deliveryaddress'];?></textarea>
			<br/>
			
			<label class="form-label">Business Logo</label>
			<input type="hidden" name="old_logo" value="<?=$resultcountprofileDetails['logo'];?>">
            <input type="file" name="logo" class="form-control" id="fileLogo" accept=".jpg, .jpeg, .png">
			
			<?php if($resultcountprofileDetails['logo']!="Nil"){?>
			<img src="<?=$resultcountprofileDetails['logo'];?>" style="width:150px;margin-bottom:10px;margin-top:10px;"/>
			<div style="clear:both;"></div>
			<?php }?>
			<br/>
			
			<h1>Bank Details</h1>
			
			<label class="form-label">A/c Name</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="acname" value="<?=$resultcountprofileDetails['acname'];?>" class="form-control">
			<br/>
			
			<label class="form-label">A/c Number</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="acnumber" value="<?=$resultcountprofileDetails['acnumber'];?>" class="form-control"><br/>
			
			<label class="form-label">Bank Name</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="bankname" value="<?=$resultcountprofileDetails['bankname'];?>" class="form-control"><br/>
			
			<label class="form-label">Branch Name</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="branchname" value="<?=$resultcountprofileDetails['branchname'];?>" class="form-control"><br/>
			
			<label class="form-label">IFS Code</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="ifsc" value="<?=$resultcountprofileDetails['ifsc'];?>" class="form-control">
			<br/>
			
			<label class="form-label">UPI Number</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="upinumber" value="<?=$resultcountprofileDetails['upinumber'];?>" class="form-control">
			<br/>
			
	<button type="submit" name="updateprofile" class="btn btn-primary">
	<i class="material-icons">update</i>Update</button>
												
                                            </div>
                                        </div>
										</form>
										
										
										
										<script>
document.getElementById('fileUpload').addEventListener('change', function(event) {
    validateFile(event.target.files[0]);
});

function validateFile(file) {
    const validExtensions = ['image/jpeg', 'image/jpg', 'image/png'];

    if (!validExtensions.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileUpload').value = '';
    }
}



document.getElementById('fileLogo').addEventListener('change', function(event) {
    validateFileLogo(event.target.files[0]);
});

function validateFileLogo(file) {
    const validExtensions = ['image/jpeg', 'image/jpg', 'image/png'];

    if (!validExtensions.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileLogo').value = '';
    }
}
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