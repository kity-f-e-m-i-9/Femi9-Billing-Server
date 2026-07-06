<?php include("checksession.php");
include("config.php");
$title="Update Customer";
error_reporting(0);

$invid=$_REQUEST['InvoiceID'];
$getinvuser=$_REQUEST['invuser'];

$backlink="user-manage-invoice?invuser=$getinvuser";
$invtable_name="user_invoice";

	$lablenamedisplay="Distributor Name";
	$tablename="distributor";

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
	<link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">


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
									<td><a href="<?=$backlink;?>" title="Go Back">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<?php if (isset($_REQUEST['updatedsuccess'])) { ?>
<div class="alert alert-success">New Customer Update success.</div>
<?php } ?>


                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    
                                    <div class="card-body">
									<h1>Invoice Details</h1>
									<table id="receipttble" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Invoice Number</th>
													<th><?=$lablenamedisplay;?></th>
													<th>Invoice Date</th>
													<th>Invoice Amount</th>
												</tr>
                                            </thead>
											
											<tbody>
					<?php $select_product_list="select * from ".$invtable_name." where inv_id='$invid'";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										$result_product_list=mysqli_fetch_array($fetch_product_list);
											
										$CuSTID=$result_product_list['to_user_id'];
										
										$select_Customers="select * from ".$tablename." where temp_id='$CuSTID'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										$Cust_Name=$result_Customers['name'];
										$Cust_Mbile=$result_Customers['mobile_number'];
										
											?>
                                            
                                                <tr>
                                                    <td><?php echo $result_product_list["inv_number"];?></td>
													<td><?php echo $Cust_Name;?><br/>M: <?php echo $Cust_Mbile;?></td>
													<td><?php echo date("d/M/Y",strtotime($result_product_list["date"]));?></td>
													
	
				<td><?php echo inr_format($result_product_list["total"], 2);?></td>
													
                                        </tr>
										</tbody>
                                        </table>
										
										
				<form action="update_customer_action" method="post" enctype="multipart/form-data" onsubmit="return confirm('Please make a confirm!')">
				
				<input type="hidden" name="invid" value="<?=$invid;?>"/>
				<input type="hidden" name="invuser" value="<?=$_REQUEST['invuser'];?>"/>
				<input type="hidden" name="old_customer_id" value="<?=$CuSTID;?>"/>
				

                <div class="example-container">
                <div class="example-content">
				
				
				<script type="text/javascript">
function showstockavailable(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintstock").innerHTML=xmlhttp.responseText;}}
var invuser="<?=$getinvuser;?>";
var oldcustomerid="<?=$CuSTID;?>";
xmlhttp.open("GET","loadstockcheck2.php?q="+str + '&invuser='+ invuser + '&oldcustomerid='+ oldcustomerid,true);
xmlhttp.send();}
</script>


<label class="form-label"><?=$lablenamedisplay;?>*</label>
<select required="" name="new_customer_id" class="js-states form-control" tabindex="-1" style="display: none; width: 100%" onchange="showstockavailable(this.value)">
<option value="" hidden="">Select</option>
<?php 
$selectCusList="select * from ".$tablename." where onboard_userTYPE='$onboard_userTYPE' and onboard_userID='$onboard_userID' and account_status='active' order by name asc";		
	
$fetch_Customers_list=mysqli_query($db_conn,$selectCusList);
while($result_Customers_list=mysqli_fetch_array($fetch_Customers_list))
		{
			
			$user_districtID=$result_Customers_list['district_id'];
			//
			$select_User_districtName="select * from district where id='$user_districtID'";
			$fetch_user_districtName=mysqli_query($db_conn,$select_User_districtName);
			$result_user_districtName=mysqli_fetch_array($fetch_user_districtName);
			$user_districtName=$result_user_districtName['dist_name'];
			
			if($getinvuser=="stockiest"){
			$UserName_SHOW="".strtoupper($result_Customers_list['name'])." (".strtoupper($user_districtName)."), ".$result_Customers_list['mobile_number']."";
			}else{
			$UserName_SHOW="".strtoupper($result_Customers_list['name']).", ".$result_Customers_list['mobile_number']."";	
			}
			
			
?>
<option value="<?=$result_Customers_list['temp_id'];?>"><?=$UserName_SHOW;?></option>
<?php }?>
</select>
							   
								
		 <span id="txtHintstock">
		 </span>
												
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
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/select2.js"></script>
</body>
</html>