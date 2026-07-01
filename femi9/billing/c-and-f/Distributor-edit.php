<?php include("checksession.php");
include("config.php");

$title="Edit Distributor";
$manage_url="Distributor-manage.php";
$manage_title="Manage Distributor";
$message_title="Distributor";
//
$Coupon_category="Distributor";

$get_id=base64_decode($_REQUEST['prid']);
$select_product_list="select * from distributor where id='$get_id'";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				$result_product_list=mysqli_fetch_array($fetch_product_list);
				
				//REFERRAL DETAILS
				$distributor_temp_id=$result_product_list['temp_id'];
				$select_referral_records="select * from distributor_referral where distributor_id='$distributor_temp_id'";
				$fetch_referral_records=mysqli_query($db_conn,$select_referral_records);
				$result_referral_records=mysqli_fetch_array($fetch_referral_records);
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
<form action="Distributor-edit-action.php" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">

<input type="hidden" name="update_id" value="<?=$result_product_list['id'];?>">
<input type="hidden" name="old_icon" value="<?=$result_product_list['user_icon'];?>">
<input type="hidden" name="distributor_id" value="<?=$distributor_temp_id;?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
            <label class="form-label">Name</label>
            <input type="text" required="" name="name" value="<?php echo $result_product_list['name'];?>" class="form-control" onkeypress="restrictSpecialChars(event)">
			
			<?php
			if($result_product_list["user_icon"]!="Nil"){$imgsrcname="../stockist/".$result_product_list["user_icon"]."";}else{$imgsrcname="../../assets/images/no image.jpg";}
			
			?>
			<br/>
			
			<label class="form-label">Category*</label>
           <select required="" name="target_amount" class="form-control">
							   <option value="<?=$result_referral_records['target_amount'];?>" hidden=""><?=$result_referral_records['target_amount'];?></option>
							   <?php $select_stcatlist="select * from distributor_category order by id asc";
							   $fetch_stcatlist=mysqli_query($db_conn,$select_stcatlist);
							   while($result_stcatlist=mysqli_fetch_array($fetch_stcatlist))
							   {?>
	<option value="<?php echo $result_stcatlist['amount'];?>"><?php echo $result_stcatlist['amount'];?></option>
							   <?php }?>
							   </select>
			<br/>
			
			<label class="form-label">Candidate Photo</label>
            <input type="file" name="user_icon" class="form-control" accept=".jpg, .jpeg, .png">
			<br/>
			<img src="<?php echo $imgsrcname;?>" style="width:100px;"/>
			
			
									
<br/>									
			<!------<label class="form-label">Mobile Number</label>
            <input type="text" value="<?php echo $result_product_list['mobile_number'];?>" required="" name="mobile_number" class="form-control">---->
			
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
            <input type="email" onkeypress="restrictemail(event)" value="<?php echo $result_product_list['email'];?>" name="email" class="form-control">
			<br/>									
			
			
			<label class="form-label">State Name*</label>
            <input type="text" required="" name="state_id" value="<?php echo $result_product_list['state_id'];?>" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>
			
			<label class="form-label">District Name*</label>
            <input type="text" required="" name="district_id" value="<?php echo $result_product_list['district_id'];?>" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>
			
			<label class="form-label">Taluk Name*</label>
            <input type="text" required="" name="taluk_id" value="<?php echo $result_product_list['taluk_id'];?>" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>
			
			<label class="form-label">Pincode*</label>
            <input type="text" required="" name="pincode_id" value="<?php echo $result_product_list['pincode_id'];?>" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>
			
			
			<label class="form-label">Address*</label>
            <textarea name="address" onkeypress="restrictSpecialChars(event)" class="form-control" required="required"><?php echo $result_product_list['address'];?></textarea>
			<br/>
			
			<label class="form-label">GST Number*</label>
            <input type="text" required="" name="gstin" maxlength="15" onkeypress="restrictGSTIN(event)" class="form-control" value="<?php echo $result_product_list['gstin'];?>">
			<br/>
			
			<label class="form-label">Shop Onboard
			<?php if($result_product_list['shop_onboard']==1){?>
            <input type="checkbox" name="shop_onboard" checked value="1">&nbsp;Enable
			<?php }else{?>
			<input type="checkbox" name="shop_onboard" value="1">&nbsp;Enable
			<?php }?>
			</label>
			<br/>
	
	<button type="submit" name="update-superstockiest" class="btn btn-primary">
	<i class="material-icons">update</i>Update</button>
												
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>