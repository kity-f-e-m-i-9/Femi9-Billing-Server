<?php include("checksession.php");
include("config.php");
error_reporting(0);

$title="Assigned Taluk";
$message_title="Assigned Taluk";
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
	<!----<link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">---->


    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
		  
		  
		  <?php
// Check for error message in session
if (isset($_SESSION['successMessage'])) {
$successMessage = $_SESSION['successMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $successMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['successMessage']); } ?>

			
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
<form action="assigned_taluk_action.php" method="post" id="uploadForm" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">


                                        <div class="example-container">
                                            <div class="example-content">
											
											
											<!-------------------New add------------>										
										
<label class="form-label">Select Stockist*</label>
           <select required="" name="stockist_tempid" class="form-control">
							   <option value="" hidden="">Select</option>
<?php 
$select_stcatlist="select temp_id,name from stockiest where onboard_userTYPE='$onboard_userTYPE' and onboard_userID='$onboard_userID' order by name asc";
   $fetch_stcatlist=mysqli_query($db_conn,$select_stcatlist);
							   while($result_stcatlist=mysqli_fetch_array($fetch_stcatlist))
							   {?>
	<option value="<?php echo $result_stcatlist['temp_id'];?>"><?php echo strtoupper($result_stcatlist['name']);?></option>
							   <?php }?>
							   </select>


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
												
					<label for="exampleInputEmail1" class="form-label">Taluk Name*</label>
           <select onChange="getState(this.value)" required="" name="taluk_id" class="form-control">
<option value="" hidden="">Select</option>
<?php $select_Taluk_list="select * from taluk where state_id='$loguser_StateID' and dist_id='$loguser_DistrictID' and assigned_SID='Nil' order by taluk asc";
										$fetch_Taluk_list=mysqli_query($db_conn,$select_Taluk_list);
										while($result_Taluk_list=mysqli_fetch_array($fetch_Taluk_list))
										{
											?>
											<option value="<?php echo $result_Taluk_list['id'];?>"><?php echo $result_Taluk_list['taluk'];?></option>
										<?php }?>
												</select>
												
												<br/>
			<label for="exampleInputEmail1" class="form-label">Pincode*</label>
			<div id="statediv">
            <select class="form-control" multiple required="" style="height:250px !important;">
			<option value="" hidden="">Select</option>
			</select>
			</div>
			
									
<br/>									
			
												

	<button type="submit" name="AssignedTalukAction" class="btn btn-primary">
	<i class="material-icons">add</i>Add</button>
												
                                            </div>
                                        </div>
										</form>
										

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
    <!----<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>--->
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <!----<script src="../../assets/js/pages/select2.js"></script>--->
</body>

</html>