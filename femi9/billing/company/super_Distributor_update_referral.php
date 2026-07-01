<?php include("checksession.php"); error_reporting(0);

$sdid=$_REQUEST['sdid'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Update Referral Details : <?php echo $Display_label;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">


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
		<style type="text/css">
		.table123{border-collapse:collapse;margin-left:10%;}
		.table123 tr td, .table123 tr th{padding:5px;}
		#inputbox{border:1px solid #000 !important;}
		#usernamebox{background:#c6ff54;font-weight:bold;padding:5px;border-radius:5px;letter-spacing:1px;}
		.centerBox{width:95%;margin:0 auto;}
		</style>
</head>

<body>

<script>
function closerefresh()
{
	opener.location.reload(true);
     self.close();
}
</script>
    
                        <div style="padding:20px;">
						<table width="100%">
						<tr>
						<td><h1> 
						 <i><b>Super-Distributor</b></i>  Referral Details
									</h1></td>
						<td><button type="button" class="btn btn-danger btn-burger" onClick="closerefresh()"><i class="material-icons">close</i></button>&nbsp;Close</td>
						</tr>
						</table>
						 
									<hr/>
</div>




<?php 
						if(isset($_REQUEST['update-refered']))
						{
							$sdid=$_REQUEST['sdid'];
							$st_ref_type=$_REQUEST['st_ref_type'];
							
							$st_ref_userid=$_POST["st_ref_userid"];
		$st_ref_userid2=$_POST["st_ref_userid2"];
		$st_ref_userid_conc="".$st_ref_userid."".$st_ref_userid2."";
		
		$tblename=$_REQUEST['tblename'];
		
		if($st_ref_type=="company")
		{
		    
		    $UPDATE_REFERED="update super_distributor_referral set ref_by_user_type='company',
			ref_by_user_id='company',updated='1' where sd_id='$sdid'";
			mysqli_query($db_conn,$UPDATE_REFERED);
					
					$_SESSION['successMessage']="Referral Details Updated Success!";
			echo "<script> opener.location.reload(true);
     self.close();</script>";
	 exit;
		    
		}else{
		
		$select_count_REFERID="select * from ".$tblename." where useridtext='$st_ref_userid_conc'";
		$fetch_count_REFERID=mysqli_query($db_conn,$select_count_REFERID);
		$result_count_REFERID=mysqli_num_rows($fetch_count_REFERID);
		if($result_count_REFERID==1)
		{
							
							$UPDATE_REFERED="update super_distributor_referral set ref_by_user_type='$st_ref_type',
							ref_by_user_id='$st_ref_userid_conc',updated='1' where sd_id='$sdid'";
							mysqli_query($db_conn,$UPDATE_REFERED);
							
							$_SESSION['successMessage']="Referral Details Updated Success!";
			echo "<script> opener.location.reload(true);
     self.close();</script>";
	 exit;
							
		}else{
			
			echo "<script>window.location='super_Distributor_update_referral?InvalidReferedID&&sdid=$sdid';</script>";
			exit;
		}
		
		
		}
		
		
							
						}
						?>
						
					  <?php if(isset($_REQUEST['InvalidReferedID'])){?>
					  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'error',
                          title: 'Error',
                          text: 'Invalid Referral ID.',
                          confirmButtonText: 'OK'
                        });
					  </script>
					  <?php }?>
					  
					  <?php if(isset($_REQUEST['referedupdated'])){?>
					  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: 'Referral Details Updated.',
                          confirmButtonText: 'OK'
                        });
					  </script>
					  <?php }?>
						
						
						
						
<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" onsubmit="return confirm('Please make a confirm!')">
<input type="hidden" name="sdid" value="<?=$sdid;?>">

                                        <div class="example-container">
                                            <div class="example-content">
												
				<script type="text/javascript">
function showUserID(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintUserID").innerHTML=xmlhttp.responseText;}}
var couponcategory="<?php echo $Coupon_category;?>";
xmlhttp.open("GET","super_Distributor_loadUserID.php?q="+str + '&couponcategory='+ couponcategory,true);
xmlhttp.send();}
</script>

		   <label class="form-label">Referred by*</label>
           <select required name="st_ref_type" class="form-control" onchange="showUserID(this.value)">
							   <option value="" hidden="">Select</option>
							   <option value="company">Company</option>
							   <option value="super_stockiest">Super-Stockist</option>
							   <option value="stockiest">Stockist</option>
	<option value="super_distributor">Super-Distributor</option>
	<option value="distributor">Distributor</option>
							   </select>
			<br/>				
			
			<span id="txtHintUserID">
			</span>
												<br/>
												
												<button type="submit" name="update-refered" class="btn btn-primary">Update</button>
												
                                            </div>
                                        </div>
										
										</form>
                        
                   

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>
</body>

</html>