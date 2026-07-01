<?php include("checksession.php");

$title="Edit Stockist";
$manage_url="manage_ss.php";
$manage_title="Manage Stockist";
$message_title="Stockist";

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from stockiest where id='$get_id'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				$result_product_list=mysqli_fetch_array($fetch_product_list);
				$stockistid=$result_product_list['temp_id'];
				
//state details
				$state_id=$result_product_list['state_id'];
				$select_stdetails="select * from state where id='$state_id'";
	$fetch_stdetails=mysqli_query($db_conn,$select_stdetails);
	$result_stdetails=mysqli_fetch_array($fetch_stdetails);
	$state_name=$result_stdetails['st_name'];
				
//district details
$district_id=$result_product_list['district_id'];
$select_distict="select * from district where id='$district_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

//taluk details
$taluk_id=$result_product_list['taluk_id'];
$select_distict="select * from taluk where id='$taluk_id'";
	$fetch_district=mysqli_query($db_conn,$select_distict);
	$result_district=mysqli_fetch_array($fetch_district);
$taluk_name=$result_district['taluk'];
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
								
								<?php if(isset($_REQUEST['alreadyexistsLocation'])){?><div class="alert alert-warning">Invalid Pincode, already exists.</div><?php }?>
								
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
<form action="edit-ss-action.php" method="post" enctype="multipart/form-data">

<input type="hidden" name="update_id" value="<?=$result_product_list['id'];?>">
<input type="hidden" name="old_icon" value="<?=$result_product_list['user_icon'];?>">
<input type="hidden" name="stockistid" value="<?=$stockistid;?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
											
											<?php 
											//REFERAL PERSON & CATEGORY DETAILS
$select_RFRDtailsCNG="select * from stockist_referral where stockist_id='$stockistid'";
$fetch_RFRDtailsCNG=mysqli_query($db_conn,$select_RFRDtailsCNG);
$result_RFRDtailsCNG=mysqli_fetch_array($fetch_RFRDtailsCNG);
$CNG_catID=$result_RFRDtailsCNG['st_cat_id'];

$select_RFRDtailsCNG_CAT="select * from stockist_category where id='$CNG_catID'";
$fetch_RFRDtailsCNG_CAT=mysqli_query($db_conn,$select_RFRDtailsCNG_CAT);
$result_RFRDtailsCNG_CAT=mysqli_fetch_array($fetch_RFRDtailsCNG_CAT);
$Login_person_CAT=$result_RFRDtailsCNG_CAT['catname'];
?>

		<label class="form-label">Category*</label>
           <select required="" name="st_cat_id" class="form-control">
							   <option value="<?=$CNG_catID;?>" hidden=""><?php echo $result_RFRDtailsCNG_CAT['catname'];?> (<?php echo $result_RFRDtailsCNG_CAT['target_amount'];?>)</option>
							   <?php $select_stcatlist="select * from stockist_category order by id asc";
							   $fetch_stcatlist=mysqli_query($db_conn,$select_stcatlist);
							   while($result_stcatlist=mysqli_fetch_array($fetch_stcatlist))
							   {?>
	<option value="<?php echo $result_stcatlist['id'];?>"><?php echo $result_stcatlist['catname'];?> (<?php echo $result_stcatlist['target_amount'];?>)</option>
							   <?php }?>
							   </select>
			<br/>	
											
            <label class="form-label">Name</label>
            <input type="text" required="" name="name" value="<?php echo $result_product_list['name'];?>" class="form-control" onkeypress="restrictSpecialChars(event)">
			
			<?php
			
			if($result_product_list["user_icon"]!="Nil"){
				$imgsrcname="../super-stockist/".$result_product_list["user_icon"]."";}
				else{$imgsrcname="../../assets/images/no image.jpg";}
			?>
			<br/>
			<label class="form-label">Candidate Photo</label>
            <input type="file" name="user_icon" class="form-control" accept=".jpg, .jpeg, .png">
			<br/>
			<img src="<?php echo $imgsrcname;?>" style="width:100px;"/>
<br/><br/>									
			<!-----<label class="form-label">Mobile Number</label>
            <input type="text" value="<?php echo $result_product_list['mobile_number'];?>" required="" name="mobile_number" class="form-control">----->
			
			
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
<option value="<?php echo $result_product_list['country_code'];?>" hidden><?php echo $result_product_list['country_code'];?></option>
<?php $selectCountry="select * from country order by c_name asc";
$fetchCountry=mysqli_query($db_conn,$selectCountry);
while($resultCountry=mysqli_fetch_array($fetchCountry)){?>
<option value='<?php echo $resultCountry['c_code'];?>' ><?php echo $resultCountry['c_name'];?> (<?php echo $resultCountry['c_code'];?>)</option>
<?php }?>
</select>
            </div>
            <div class="mobile-number">
                <label class="form-label">Mobile Number (Username)*</label>
                <input type="text" required name="mobile_number" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" value="<?php echo $result_product_list['mobile_number'];?>" class="form-control" maxlength="10" readonly>
            </div>
        </div>
        <!-- New add end -->
			
			<br/>									
			<label class="form-label">Email ID</label>
            <input type="email" onkeypress="restrictemail(event)" value="<?php echo $result_product_list['email'];?>" required="" name="email" class="form-control">
			
			<br/>									
			<label class="form-label">Address*</label>
            <textarea name="address" onkeypress="restrictSpecialChars(event)" class="form-control" required="required"><?php echo $result_product_list['address'];?></textarea>
			<br/>
			
			<!-------<label class="form-label">Password</label>
            <input type="text" value="<?php echo $result_product_list['password'];?>" required="" name="password" class="form-control">
			<br/>----->
	
	<button type="submit" name="update-superstockiest" class="btn btn-primary">
	<i class="material-icons">update</i>Update</button>
												
                                            </div>
                                        </div>
										</form>
										
										<?php /*?>
										<br/><br/>
										<h1>Update Pincode</h1>
<form action="update-location.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="update_id" value="<?=$result_product_list['id'];?>">
<input type="hidden" name="stockistid" value="<?=$stockistid;?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
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
	
	function getState(courseId) {	
var StateID="<?php echo $loguser_StateID;?>";
var DistrictID="<?php echo $loguser_DistrictID;?>";
		var strURL="findsub_pincode.php?subcourseID="+courseId+"&StateID="+StateID+"&DistrictID="+DistrictID;
		var req = getXMLHTTP();
		if (req) {
			req.onreadystatechange = function() {
				if (req.readyState == 4) {
					// only if "OK"
					if (req.status == 200) {						
						document.getElementById('statediv').innerHTML=req.responseText;						
					} else {
						alert("There was a problem while using XMLHTTP:\n" + req.statusText);
					}
				}				
			}			
			req.open("GET", strURL, true);
			req.send(null);
		}		
	}
	function getCity(courseId,scourseID) {		
		
		var strURL="findtech_course.php?subcourseID="+courseId+"&techID="+scourseID;
		var req = getXMLHTTP();
		if (req) {
			req.onreadystatechange = function() {
				if (req.readyState == 4) {
					// only if "OK"
					if (req.status == 200) {						
						document.getElementById('techdiv').innerHTML=req.responseText;						
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
	
	
	<br/>
	<label for="exampleInputEmail1" class="form-label">State Name*</label>
                           <select name="state_id" class="form-control">
			<option value="<?php echo $loguser_StateID;?>"><?php echo $loguser_StateNAME;?></option>
							   </select>

<br/>
                                  <label for="exampleInputEmail1" class="form-label">District Name*</label>
                                                <select name="dist_id" class="form-control">
												<option value="<?php echo $loguser_DistrictID;?>"><?php echo $loguser_DistrictNAME;?></option>
												</select>
												<br/>
												
												<!-------TALUK--------->
					<input type="hidden" name="old_taluk_id" value="<?=$taluk_id;?>">
												
					<label for="exampleInputEmail1" class="form-label">Taluk Name*</label>
           <select onChange="getState(this.value)" required="" name="taluk_id" class="form-control">
<option value="<?=$taluk_id;?>" hidden=""><?=$taluk_name;?></option>
<?php $select_Taluk_list="select * from taluk where state_id='$loguser_StateID' and assigned_SID='Nil' and dist_id='$loguser_DistrictID' order by taluk asc";
										$fetch_Taluk_list=mysqli_query($db_conn,$select_Taluk_list);
										while($result_Taluk_list=mysqli_fetch_array($fetch_Taluk_list))
										{
											?>
											<option value="<?php echo $result_Taluk_list['id'];?>"><?php echo $result_Taluk_list['taluk'];?></option>
										<?php }?>
												</select>
												<br/>
												
												
												
												<!--------------PINCODE------------->
			<label for="exampleInputEmail1" class="form-label">Pincode*</label>
			<div id="statediv">
          <select class="form-control" disabled multiple required="" style="height:250px !important;">
			<?php 
$select_PINlist12="select * from pincode where assigned_SID='$stockistid' order by pincode asc";
										$fetch_PINlist12=mysqli_query($db_conn,$select_PINlist12);
										while($result_PINlist12=mysqli_fetch_array($fetch_PINlist12))
										{
											?>
<option value="<?php echo $result_PINlist12['id'];?>"><?php echo $result_PINlist12['pincode'];?></option>
										<?php }?>
										
			</select>
			</div>
			<br/>				
			
			
	<button type="submit" name="update-superstockiest" class="btn btn-primary">
	<i class="material-icons">update</i>Update</button>
												
                                            </div>
                                        </div>
										</form>
										<?php */?>
										
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