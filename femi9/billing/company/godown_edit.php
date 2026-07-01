<?php include("checksession.php");
error_reporting(0);

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$select_product_list="select * from company_godown where id='$prid'";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										$result_product_list=mysqli_fetch_array($fetch_product_list);
							
include("RemoveSpecialChar.php");
							
if(isset($_REQUEST['add-godown']))
{
	$updateid=$_REQUEST['updateid'];
	
	$gname=str_replace("'","&#39;",$_REQUEST['gname']);
	$gname = RemoveSpecialChar($gname); 
	
	$address_line1=str_replace("'","&#39;",$_REQUEST['address_line1']);
	$address_line1 = RemoveSpecialChar($address_line1); 
	
	$address_line2=str_replace("'","&#39;",$_REQUEST['address_line2']);
	$address_line2 = RemoveSpecialChar($address_line2); 
	
	$gstin=str_replace("'","&#39;",$_REQUEST['gstin']);
	$gstin = RemoveSpecialChar($gstin); 
	
	$state=str_replace("'","&#39;",$_REQUEST['state']);
	$state = RemoveSpecialChar($state); 
	
	$state_code=str_replace("'","&#39;",$_REQUEST['state_code']);
	$state_code = RemoveSpecialChar($state_code); 
	
	$contact=str_replace("'","&#39;",$_REQUEST['contact']);
	$contact = RemoveSpecialChar($contact); 
	
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$email = RemoveSpecialChar($email); 
	
	//BANK DETAILS
	$acname=str_replace("'","&#39;",$_REQUEST['acname']);
	$acname = RemoveSpecialChar($acname);
	
	$acnumber=str_replace("'","&#39;",$_REQUEST['acnumber']);
	$acnumber = RemoveSpecialChar($acnumber);
	
	$bankname=str_replace("'","&#39;",$_REQUEST['bankname']);
	$bankname = RemoveSpecialChar($bankname);
	
	$branchname=str_replace("'","&#39;",$_REQUEST['branchname']);
	$branchname = RemoveSpecialChar($branchname);
	
	$ifsc=str_replace("'","&#39;",$_REQUEST['ifsc']);
	$ifsc = RemoveSpecialChar($ifsc);
	
	$upinumber=str_replace("'","&#39;",$_REQUEST['upinumber']);
	$upinumber = RemoveSpecialChar($upinumber);
	
	//UPLOAD BUSINESS LOGO
	$old_logo=$_REQUEST['old_logo'];
	$small_LOGO= $_FILES['logo']['name'];
	if($small_LOGO!=NULL)
	{
		
		
$filetype=$_FILES['logo']['type'];
if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png')
{
 $_SESSION['errorMessage'] = 'Allowed extentions are:  jpg, jpeg, png.';
 echo "<script>window.location='Myprofile.php?uploadError';</script>";
}else{
	
	
	   $rand_isdLG=rand(1,9899989);
	   $fileLogo=$rand_isdLG.$small_LOGO;
                 $uploaddirLG=__DIR__.'/bussiness_logo/';
                 if(!is_dir($uploaddirLG)) mkdir($uploaddirLG, 0755, true);
                 $uploadfileLG=$uploaddirLG.$fileLogo;
	   move_uploaded_file($_FILES['logo']['tmp_name'],$uploadfileLG);
	   $insfilenameLG="bussiness_logo/".$fileLogo;
	   if($old_logo!=NULL && file_exists($old_logo)) unlink($old_logo);
	   
}

	}else{$insfilenameLG=$old_logo;}
	//--------------------------------
	
		$insertrecords="update company_godown set gname='$gname',address_line1='$address_line1',
		address_line2='$address_line2',gstin='$gstin',state='$state',state_code='$state_code',
		contact='$contact',email='$email',logo='$insfilenameLG',
		acname='$acname',acnumber='$acnumber',bankname='$bankname',
		branchname='$branchname',ifsc='$ifsc',upinumber='$upinumber' where id='$updateid'";
		mysqli_query($db_conn,$insertrecords);

	echo "<script>window.location='godown?updatedSuccess';</script>";
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
    <title>Update Company Profile : <?php echo $business_name;?></title>

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
									<td>Update Company Profile</td>
									<td><a href="godown.php" title="Add Invoice">&#9776;</a></td>
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
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="updateid" value="<?=$prid;?>">

                                        <div class="example-container">
                                            <div class="example-content">
                                                
												<label class="form-label">Company Profile Name</label>
                                                <input type="text" required="" onkeypress="restrictSpecialChars(event)" value="<?=$result_product_list['gname'];?>" name="gname" class="form-control">
												<br/>
												
												
												<label class="form-label">Business Logo</label>
			<input type="hidden" name="old_logo"  value="<?=$result_product_list['logo'];?>">
            <input type="file" name="logo" class="form-control" id="fileLogo" accept=".jpg, .jpeg, .png">
			
			<?php if($result_product_list['logo']!=NULL){?>
			<img src="<?=$result_product_list['logo'];?>" style="width:100px;margin-bottom:10px;margin-top:10px;"/>
			<div style="clear:both;"></div>
			<?php }?>
			<br/>
			
			
												<label class="form-label">Address Line-1</label>
                               <input type="text" required="" name="address_line1" value="<?=$result_product_list['address_line1'];?>" class="form-control" onkeypress="restrictSpecialChars(event)">
												<br/>
												<label class="form-label">Address Line-2</label>
                               <input type="text" required="" name="address_line2" value="<?=$result_product_list['address_line2'];?>" class="form-control" onkeypress="restrictSpecialChars(event)">
												<br/>
												<label class="form-label">GSTIN</label>
                               <input type="text" required="" name="gstin" value="<?=$result_product_list['gstin'];?>" class="form-control" maxlength="15" onkeypress="restrictGSTIN(event)">
												<br/>
												<label class="form-label">State Name</label>
                               <input type="text" required="" name="state" value="<?=$result_product_list['state'];?>" class="form-control" onkeypress="restrictSpecialChars(event)">
												<br/>
												<label class="form-label">State Code</label>
												<input type="number" min="0" max="99" required="" onkeypress="restrictnumber(event)" name="state_code" class="form-control" value="<?=$result_product_list['state_code'];?>">
												<br/>
												<label class="form-label">Contact Number</label>
												 <input type="text" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" required="" name="contact" class="form-control" value="<?=$result_product_list['contact'];?>">
												 
												<br/>
												<label class="form-label">Email ID</label>
												<input value="<?=$result_product_list['email'];?>" type="text" required="" onkeypress="restrictemail(event)" name="email" class="form-control">
												<br/>
												
												<h1>Bank Details</h1>
			
			<label class="form-label">A/c Name*</label>
            <input type="text" value="<?=$result_product_list['acname'];?>" required="" onkeypress="restrictSpecialChars(event)" name="acname" class="form-control">
			<br/>
			
			<label class="form-label">A/c Number*</label>
            <input type="text" value="<?=$result_product_list['acnumber'];?>" required="" onkeypress="restrictSpecialChars(event)" name="acnumber" class="form-control">
			<br/>
			
			<label class="form-label">Bank Name*</label>
            <input type="text" value="<?=$result_product_list['bankname'];?>" required="" onkeypress="restrictSpecialChars(event)" name="bankname" class="form-control">
			<br/>
			
			<label class="form-label">Branch Name*</label>
            <input type="text" value="<?=$result_product_list['branchname'];?>" required="" onkeypress="restrictSpecialChars(event)" name="branchname" class="form-control">
			<br/>
			
			<label class="form-label">IFS Code*</label>
            <input type="text" value="<?=$result_product_list['ifsc'];?>" required="" onkeypress="restrictSpecialChars(event)" name="ifsc" class="form-control">
			<br/>
			
			<label class="form-label">UPI Number*</label>
            <input type="text" value="<?=$result_product_list['upinumber'];?>" required="" onkeypress="restrictSpecialChars(event)" name="upinumber" class="form-control">
			<br/>
												
												<button type="submit" name="add-godown" class="btn btn-primary">
												<i class="material-icons">update</i>Update</button>
												
                                            </div>
											
											
                                        </div>
										
										</form>
										
										
										<script>
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