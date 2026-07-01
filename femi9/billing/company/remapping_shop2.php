<?php include("checksession.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");
$current_date=date("Y-m-d");

$get_from_user_type=$_REQUEST['ssid'];
$get_from_user_id=$_REQUEST['user_id'];

if($get_from_user_type==NULL || $get_from_user_id==NULL)
{
	echo "<script>window.location='remapping_shop';</script>";
	exit;
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
    <title>Re-mapping : Shops : <?php echo $business_name;?></title>

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
								<a href="remapping_shop" id="linkbackvl">&#8630;&nbsp;Go Back</a>
                                     <h2>
									<table class="headertble margintop10">
									<tr>
									<td>
									Re-mapping : <i><span style="color:green;">Shops</span></i>
									</td>
									</tr>
									</table>
									</h2>
									
									<?php 
if($get_from_user_type!='company')
{
	
if($get_from_user_type=='candf')
{
	$Tablename="c_and_f";
	$LableName="C & F";   
}
if($get_from_user_type=='super_stockiest')
{
	$Tablename="super_stockiest";
	$LableName="Super Stockist";
}
if($get_from_user_type=='stockiest')
{
	$Tablename="stockiest";
	$LableName="Stockist";
}
if($get_from_user_type=='super_distributor')
{
	$Tablename="super_distributor";
	$LableName="Super Distributor";
}
if($get_from_user_type=='distributor')
{
	$Tablename="distributor";
	$LableName="Distributor";
}

$select890="select * from ".$Tablename." where temp_id='$get_from_user_id'";
$fetch890=mysqli_query($db_conn,$select890);
$result890=mysqli_fetch_array($fetch890);
$print_userID=$result890['useridtext'];
$print_userName=$result890['name'];
$print_userMobile=$result890['mobile_number'];
?>
			<p>Onboard By : <b><?=$LableName;?></b> | <b style="color:blue;"><?=$print_userID;?></b> | <b><?=strtoupper($print_userName);?></b> | 
									<b><?=$print_userMobile;?></b></p>
									<?php }else{?>
									<p>Onboard By : Company</p>
									<?php }?>
									
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    
                                    <div class="card-body">
									
<?php 
$select_supersotkistCOUNT="select count(*) as numMAPP from shop where onboard_userTYPE='$get_from_user_type' and onboard_userID='$get_from_user_id'";
$fetch_supersotkistCNT=mysqli_query($db_conn,$select_supersotkistCOUNT);
$result_supersotkistCNT=mysqli_fetch_array($fetch_supersotkistCNT);
if($result_supersotkistCNT['numMAPP']!=0)
{
?>						
									
<form action="remapping-action" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">

                                        <div class="example-container">
                                            <div class="example-content">
                                                
                                            <!-- Select All / Deselect All Buttons -->
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <strong>Select Shop to Remap:</strong> 
                                                    (<?php echo $result_supersotkistCNT['numMAPP']; ?> distributors found)
                                                </label>
                                                <div class="mb-2">
                                                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllDistributors()">
                                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">check_box</i> Select All
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAllDistributors()">
                                                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">check_box_outline_blank</i> Deselect All
                                                    </button>
                                                </div>
                                            </div>    
											
											<ul class="list-group">
											
					<?php 
					$select_supersotkist="select * from shop where onboard_userTYPE='$get_from_user_type' and onboard_userID='$get_from_user_id' order by id asc";
						$fetch_supersotkist=mysqli_query($db_conn,$select_supersotkist);
						while($result_supersotkist=mysqli_fetch_array($fetch_supersotkist))
										{
											?>
											
											<li class="list-group-item">
        <input class="form-check-input me-1 shop-checkbox" name="distributorid[]" type="checkbox" value="<?=$result_supersotkist['id'];?>" aria-label="...">
        <?=strtoupper($result_supersotkist['name']);?>, <?=$result_supersotkist['mobile_number'];?>
    </li>
	
	
										<?php }?>
   
</ul>
<br/>


<script type="text/javascript">
function showUserID(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintUserID").innerHTML=xmlhttp.responseText;}}
xmlhttp.open("GET","load_shop18a.php?q="+str,true);
xmlhttp.send();}
</script>

<label class="form-label">Assigned to Usertype*</label>
<div style="margin-bottom:10px;">
<select required="" name="to_usertype" class="form-control" onchange="showUserID(this.value)">
												<option value="" hidden="">Select</option>
												<option value="company">Company</option>
												<option value="candf">C & F</option>
												<option value="super_stockiest">Super Stockist</option>
												<option value="stockiest">Stockist</option>
												<option value="super_distributor">Super Distributor</option>
												<option value="distributor">Distributor</option>
												</select>
</div>

<div id="txtHintUserID" style="margin-bottom:10px;">
<select class="form-control" required>
<option value="" hidden="">Select User (Assigned to)</option>
</select>
</div>
											

<button type="submit" name="REMAPPING4" class="btn btn-primary">
<i class="material-icons"></i>Submit</button>
												
                                            </div>
                                        </div>
										</form>
										
<?php }else{?>
<img src="../../assets/images/no-records.jpg">
<?php }?>
										
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
    
    <script>
    /**
     * Select all super distributors
     */
    function selectAllDistributors() {
        var checkboxes = document.querySelectorAll('.shop-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = true;
        });
    }
    
    /**
     * Deselect all super distributors
     */
    function deselectAllDistributors() {
        var checkboxes = document.querySelectorAll('.shop-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = false;
        });
    }
    </script>
    
</body>

</html>