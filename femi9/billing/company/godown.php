<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('company_profile');
require_once("include/GodownAccess.php");
error_reporting(0);

include("RemoveSpecialChar.php");
	  
if(isset($_REQUEST['add-godown']))
{

	//UPLOAD BUSINESS LOGO
	$small_LOGO= $_FILES['logo']['name'];
	if($small_LOGO!=NULL)
	{
		
		
$filetype=$_FILES['logo']['type'];
if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png')
{
 $_SESSION['errorMessage'] = 'Allowed extentions are:  jpg, jpeg, png.';
 echo "<script>window.location='godown.php?uploadError';</script>";
}else{
	
	   $rand_isdLG=rand(1,9899989);
	   $fileLogo=$rand_isdLG.$small_LOGO;
                 $uploaddirLG='bussiness_logo/';
                 $uploadfileLG=$uploaddirLG.$fileLogo;
	   move_uploaded_file($_FILES['logo']['tmp_name'],$uploadfileLG);
	   $insfoldernameLG="bussiness_logo/";
	   $insfilenameLG=$insfoldernameLG.$fileLogo;
	   
}

	}else{$insfilenameLG="";}
	
	
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
     	
	$selectcount="select count(*) as num from company_godown where gname='$gname'";
	$fetchcount=mysqli_query($db_conn,$selectcount);
	$resultcount=mysqli_fetch_array($fetchcount);
	if($resultcount['num']==0)
	{
		$insertrecords="insert into company_godown (gname,address_line1,address_line2,gstin,state,state_code,contact,email,logo,acname,acnumber,
		bankname,branchname,ifsc,upinumber) values ('$gname','$address_line1','$address_line2',
		'$gstin','$state','$state_code','$contact','$email','$insfilenameLG',
		'$acname','$acnumber','$bankname','$branchname','$ifsc','$upinumber')";
		mysqli_query($db_conn,$insertrecords);
	}
	echo "<script>window.location='godown?addedsuccess';</script>";
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
    <title>Company Profile : <?php echo $business_name;?></title>

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
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
    </style>
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
									<td>Company Profile</td>
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
									
									<?php if(isset($_REQUEST['alreadyexists'])){?><div class="alert alert-danger">Company Profile details already exists !</div>
									<?php }?>
									
									<?php if(isset($_REQUEST['addedsuccess'])){?><div class="alert alert-success">Company Profile details added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one Company Profile details deleted success.</div><?php }?>
      
	  <?php  if(isset($_REQUEST['uploadError'])){?><div class="alert alert-warning">Logo Allowed extentions are:  jpg, jpeg and png only.</div><?php } ?>


<?php include("validate-scripts.php");?>
<form method="post" enctype="multipart/form-data" id="uploadForm">

                                        <div class="example-container">
                                            <div class="example-content">
                                                
												<label class="form-label">Company Profile Name*</label>
                                                <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="gname" class="form-control">
												<br/>
												
												<label class="form-label">Business Logo*</label>
            <input type="file" name="logo" required="" class="form-control" id="fileLogo" accept=".jpg, .jpeg, .png">
			<br/>
			
												<label class="form-label">Address Line-1*</label>
                               <input type="text" required="" name="address_line1" onkeypress="restrictSpecialChars(event)" class="form-control">
												<br/>
												<label class="form-label">Address Line-2*</label>
                               <input type="text" required="" name="address_line2" onkeypress="restrictSpecialChars(event)" class="form-control">
												<br/>
												<label class="form-label">GSTIN*</label>
												<!---33DCNPG7779E1Z8--->
                               <input type="text" required="" name="gstin" maxlength="15" onkeypress="restrictGSTIN(event)" class="form-control">
												<br/>
												<label class="form-label">State Name*</label>
                               <input type="text" required="" name="state" onkeypress="restrictSpecialChars(event)" class="form-control">
												<br/>
												<label class="form-label">State Code*</label>
                               <input type="number" min="0" max="99" required="" onkeypress="restrictnumber(event)" name="state_code" class="form-control">
												<br/>
												<label class="form-label">Contact Number*</label>
                               <input type="text" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" required="" name="contact" class="form-control">
												<br/>
												<label class="form-label">Email ID*</label>
                               <input type="text" required="" onkeypress="restrictemail(event)" name="email" class="form-control">
												<br/>
												
												
												<h1>Bank Details</h1>
			
			<label class="form-label">A/c Name*</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="acname" class="form-control">
			<br/>
			
			<label class="form-label">A/c Number*</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="acnumber" class="form-control">
			<br/>
			
			<label class="form-label">Bank Name*</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="bankname" class="form-control">
			<br/>
			
			<label class="form-label">Branch Name*</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="branchname" class="form-control">
			<br/>
			
			<label class="form-label">IFS Code*</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="ifsc" class="form-control">
			<br/>
			
			<label class="form-label">UPI Number*</label>
            <input type="text" required="" onkeypress="restrictSpecialChars(event)" name="upinumber" class="form-control">
			<br/>
												
												
												<button type="submit" name="add-godown" class="btn btn-primary">
												<i class="material-icons">add</i>Add</button>
												
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

document.getElementById('uploadForm').addEventListener('submit', function(event) {
    const fileInput = document.getElementById('fileLogo');
    if (!fileInput.files.length || !fileInput.files[0]) {
        event.preventDefault();
        alert('Please select a valid file before submitting.');
    }
});
</script>

										<!------<script>
document.getElementById('fileLogo').addEventListener('change', function(event) {
    validateFileLogo(event.target.files[0]);
});

function validateFileLogo(file) {
    const validExtensions = ['image/jpeg', 'image/jpg', 'image/png'];

    if (!validExtensions.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileLogo').value = 'fileLogo';
    }
}
</script>---->

										
                                    </div>
                                </div>
                            </div>
								
                            </div>
							
							
							
							
							<div class="row">
                            <div class="col">
                                <div class="card">
                                    <div style="background:#fff;overflow:scroll;width:100%;">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Company Profile Name</th>
													<th>Logo</th>
													
													<th>Actions</th>
                                                </tr>
                                            </thead>
											 <tbody>
											 
										<?php 
										$select_product_list="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id desc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
										    $rowid=base64_encode($result_product_list["id"]);
											?>
                                           
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_product_list["gname"];?></td>
	<td>
	<?php if($result_product_list["logo"]!=NULL){?>
	<img src="<?php echo $result_product_list["logo"];?>" style="width:50px;border:1px solid #999;"/>
	<?php }else{ echo "---";}?>
	</td>
																										<td>
													    <div class="actions-group">
													        <a href="godown_edit?prid=<?php echo $rowid;?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
													        <a href="godown_del?prid=<?php echo $rowid;?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
													    </div>
													</td>
                                                </tr>
                                            
										<?php }?>
										</tbody>
										
                                        </table>
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