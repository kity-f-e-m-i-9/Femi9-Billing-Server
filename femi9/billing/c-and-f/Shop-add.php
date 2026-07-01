<?php include("checksession.php");
include("config.php");

$title="Add Shop (Retailers)";
$manage_url="Shop-manage.php";
$manage_title="Manage Shop (Retailers)";
$message_title="Shop (Retailers)";
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
								
								<?php if(isset($_REQUEST['distalready'])){?><div class="alert alert-danger">Shop Details Already Exists.</div><?php }?>
								
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
									
									<?php if(isset($_REQUEST['alreadyexists'])){?><div class="alert alert-danger"><?php echo $message_title;?> already exists !</div>
									<?php }?>
                       <!---ss-action.php----> 
<?php include("validate-scripts.php");?>					   
<form action="Shop-action.php" method="post" enctype="multipart/form-data" id="uploadForm">
									   
<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$tempID="".$randum_number."FSHP".$temp_date."".$temp_time."";?>

<input type="hidden" name="temp_id" value="<?=$tempID?>"><!--SHOP ID-->
<input type="hidden" name="distributor_id" value="<?=$DummyDistributorID?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
											<label for="exampleInputEmail1" class="form-label">Category*</label>
    <select name="shop_cat" class="form-control" required="">
	<option value="" hidden="">Select</option>
	<?php $selectShopCat="select * from shop_category order by id asc";
	$fetchShopCat=mysqli_query($db_conn,$selectShopCat);
	while($resultShopCat=mysqli_fetch_array($fetchShopCat)){?>
	<option value="<?php echo $resultShopCat['id'];?>"><?php echo $resultShopCat['catlable'];?></option>
	<?php  } ?>
	</select>
	<br/>
											
            <label class="form-label">Name*</label>
            <input type="text" required="" name="name" class="form-control" onkeypress="restrictSpecialChars(event)">
			<br/>
			
			<label class="form-label">Shop Photo (Live)</label>
            <input type="file" name="user_icon" class="form-control" id="fileUpload" accept=".jpg, .jpeg, .png">
<br/>
	
	
	<script language="javascript" type="text/javascript">
function getXMLHTTP() { //fuction to return the xml http object
		var xmlhttp=false;	
		try{
			xmlhttp=new XMLHttpRequest();
		}
		catch(e)	{		
			try{			
				xmlhttp= new ActiveXObject("Microsoft.XMLHTTP");
			}
			catch(e){
				try{
				xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
				}
				catch(e1){
					xmlhttp=false;
				}
			}
		}
		return xmlhttp;
    }
	
	function showDistrict(courseId) {	
		var strURL="load_shop_dist.php?subcourseID="+courseId;
		var req = getXMLHTTP();
		if (req) {
			req.onreadystatechange = function() {
				if (req.readyState == 4) {
					// only if "OK"
					if (req.status == 200) {						
						document.getElementById('districtdiv').innerHTML=req.responseText;						
					} else {
						alert("There was a problem while using XMLHTTP:\n" + req.statusText);
					}
				}				
			}			
			req.open("GET", strURL, true);
			req.send(null);
		}		
	}
	
	function showTaluk(stateid,districtid) {	
		var strURL="load_shop_taluk.php?subcourseID="+stateid+"&techID="+districtid;
		var req = getXMLHTTP();
		if (req) {
			req.onreadystatechange = function() {
				if (req.readyState == 4) {
					// only if "OK"
					if (req.status == 200) {						
						document.getElementById('talukdiv').innerHTML=req.responseText;						
					} else {
						alert("There was a problem while using XMLHTTP:\n" + req.statusText);
					}
				}				
			}			
			req.open("GET", strURL, true);
			req.send(null);
		}		
	}
	
	
	function showPincode(stateid,districtid,talukid) {	
		var strURL="load_shop_pincode.php?subcourseID="+stateid+"&techID="+districtid+"&divID="+talukid;
		var req = getXMLHTTP();
		if (req) {
			req.onreadystatechange = function() {
				if (req.readyState == 4) {
					// only if "OK"
					if (req.status == 200) {						
						document.getElementById('pincodediv').innerHTML=req.responseText;						
					} else {
						alert("There was a problem while using XMLHTTP:\n" + req.statusText);
					}
				}				
			}			
			req.open("GET", strURL, true);
			req.send(null);
		}		
	}
	
	</script>
	
	<label for="exampleInputEmail1" class="form-label">State Name*</label>
    <select required="" name="state_id" class="form-control" onchange="showDistrict(this.value)">
							   <option value="" hidden="">Select</option>
							   <?php 
							   $exstID=explode("#",$loguser_StateID);
 
  foreach ($exstID as $keyss => $valuess)
   {  
   
   $select_stateList="select st_name from state where id='$valuess' order by st_name asc";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   $result_stateList=mysqli_fetch_array($fetch_staeList);
							   ?>
	<option value="<?php echo $valuess;?>"><?php echo $result_stateList['st_name'];?></option>
							   <?php }?>
							   </select>
			<br/>
			
			<label class="form-label">District*</label>
			<span id="districtdiv">
            <select required="" class="form-control">
												<option value="" hidden="">Select</option>
												</select>
												</span>
<br/>	

					<label for="exampleInputEmail1" class="form-label">Taluk Name*</label>
					<span id="talukdiv">
           <select required="" class="form-control">
<option value="" hidden="">Select</option>
												</select>
												</span>
												<br/>


	<label for="exampleInputEmail1" class="form-label">Pincode*</label>
			<div id="pincodediv">
            <select class="form-control" required="">
			<option value="" hidden="">Select</option>
			</select>
			</div>
<br/>	
								
			<!----<label class="form-label">Mobile Number</label>
            <input type="text" required="" maxlength="10" name="mobile_number" class="form-control">---->
			
			<!-- New add -->
<style>
        .form-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .form-group .country-code {
            flex: 0 0 20%;
        }
        .form-group .mobile-number {
            flex: 1;
        }
    </style>
		<div class="form-group">
            <div class="country-code">
                <label class="form-label">Country Code *</label>
<select id='country_code' name='country_code' required="" class="form-control">
<?php $selectCountry="select * from country order by id asc";
$fetchCountry=mysqli_query($db_conn,$selectCountry);
while($resultCountry=mysqli_fetch_array($fetchCountry)){?>
<option value='<?php echo $resultCountry['c_code'];?>' ><?php echo $resultCountry['c_name'];?> (<?php echo $resultCountry['c_code'];?>)</option>
<?php }?>
</select>
            </div>
            <div class="mobile-number">
                <label class="form-label">Mobile Number (Username)*</label>
                <input type="text" required="" name="mobile_number" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" class="form-control" maxlength="10">
            </div>
        </div>
        <!-- New add end -->
			
			<br/>
<label class="form-label">Landline Number</label>
            <input type="text" onkeypress="restrictlandline(event)" name="landline" class="form-control">
			<br/>
			
			<label class="form-label">Email ID</label>
            <input type="email" name="email" class="form-control" onkeypress="restrictemail(event)">
			
			<br/>									
			<label class="form-label">Address*</label>
            <textarea name="address" onkeypress="restrictSpecialChars(event)" class="form-control" required=""></textarea>
			<br/>
			
			<label class="form-label">GST Number</label>
            <input type="text" onkeypress="restrictGSTIN(event)" name="gstin" class="form-control">
	<br/>
	
	<button type="submit" name="add-superstockiest" onclick="return confirm('Please make a confirm');" class="btn btn-primary">
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