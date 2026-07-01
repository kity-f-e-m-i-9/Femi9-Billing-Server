<?php include("checksession.php");

$title="Add C&F";
$manage_url="manage_cf";
$manage_title="Manage C&F";
$message_title="C&F";
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
									
									<?php if(isset($_REQUEST['alreadyexists'])){?><div class="alert alert-danger"><?php echo $message_title;?> already exists !</div>
									<?php }?>
									
									<?php if(isset($_REQUEST['InvalidMobileNumber'])){?><div class="alert alert-danger">Warning! Mobile number already exists.</div>
									<?php }?>
									
     
<?php include("validate-scripts.php");?>	 
<form action="cf-action" method="post" enctype="multipart/form-data" id="uploadForm">
									   
<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } $randum_number=GeraHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$tempID="".$randum_number."F9CF".$temp_date."".$temp_time."";?>

<input type="hidden" name="temp_id" value="<?=$tempID?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
            <label class="form-label">Name*</label>
            <input type="text" required="" name="name" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>
			
			<label class="form-label">Candidate Photo</label>
            <input type="file" name="user_icon" class="form-control" id="fileUpload" accept=".jpg, .jpeg, .png">
			<br/>
			
			<!-----<script type="text/javascript">
function showDistrict(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintDistrict").innerHTML=xmlhttp.responseText;}}
var invuser="super_stockiest";
xmlhttp.open("GET","loadDistrict.php?q="+str + '&invuser='+ invuser,true);
xmlhttp.send();}
</script>
---->
			
			<label for="exampleInputEmail1" class="form-label">State Name*</label>
                               <select required multiple="multiple" name="state_id[]" class="form-control">
							   <option value="" hidden="">Select</option>
							   <?php $select_stateList="select * from `state` order by `st_name` asc";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   while($result_stateList=mysqli_fetch_array($fetch_staeList))
							   {?>
						   <option value="<?php echo $result_stateList['id'];?>"><?php echo $result_stateList['st_name'];?></option>
							   <?php }?>
							   </select>
			<br/><br/>
			
			<!-----<label class="form-label">District*</label>
			<div id="txtHintDistrict">
            <select required="" name="dist_id" class="form-control">
												<option value="" hidden="">Select</option>
												</select>
												</div>
<br/>---->

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
			
				<script type="text/javascript">
function showMobileNumber(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintMobile").innerHTML=xmlhttp.responseText;}}
var invuser="candf";
xmlhttp.open("GET","loadMobileNumber.php?q="+str + '&invuser='+ invuser,true);
xmlhttp.send();}
</script>
            <div class="mobile-number">
                <label class="form-label">Mobile Number (Username)*</label>
                <input type="text" required name="mobile_number" onChange="showMobileNumber(this.value)" onkeypress="restrictnumber(event)" class="form-control" maxlength="10">
			</div>
        </div>
		<span id="txtHintMobile"></span>
        <!-- New add end -->
        
			<!--#hided <label class="form-label">Mobile Number (Username)*</label>
            <input type="text" required="" name="mobile_number" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" class="form-control" maxlength="10">-->
			<br/>
			
			<!------<label class="form-label">Password*</label>
            <input type="text" required="" name="password" class="form-control">------>
			<input type="hidden" name="password" value="12345678">
			
			<label class="form-label">Email ID</label>
            <input type="email" name="email" onkeypress="restrictemail(event)" class="form-control">
			<br/>	
			
			<label class="form-label">Address*</label>
            <textarea name="address" class="form-control" onkeypress="restrictSpecialChars(event)" required="required"></textarea>
			<br/>
			
			<label class="form-label">GST Number*</label>
            <input type="text" required="" maxlength="15" onkeypress="restrictGSTIN(event)" name="gstin" class="form-control">			
												
												
												<style type="text/css">
    .hidden {
        display: none;
    }
</style>

<script type="text/javascript">
function showcouopon(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintcoupon").innerHTML=xmlhttp.responseText;}}
var couponcategory="<?php echo $Coupon_category;?>";
xmlhttp.open("GET","loadcoupon.php?q="+str + '&couponcategory='+ couponcategory,true);
xmlhttp.send();}
</script>


<script type="text/javascript">
function showcouoponvalid(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintcouponvalid").innerHTML=xmlhttp.responseText;}}
var name="10";
var couponcategory="<?php echo $Coupon_category;?>";
xmlhttp.open("GET","loadcouponvalid.php?q="+str + '&couponcategory='+ couponcategory,true);
xmlhttp.send();}
</script>

<?php /*?>
												<br/>
			<label class="form-label">Payment Method</label>
            <div class="form-check">
                <input onclick="showcouopon(this.value)" class="form-check-input" type="radio" name="amount_method" id="gridRadios1" value="phonepe" checked>
                <label class="form-check-label" for="gridRadios1">
                    Phonepe
                </label>
            </div>
			<!----div class="form-check">
                <input onclick="showcouopon(this.value)" class="form-check-input" type="radio" name="amount_method" id="option1" value="coupon">
                <label class="form-check-label" for="option1">
                    Coupon
                </label>
            </div>---->
			
			
			<label class="form-label">Choose Plan</label>
            <select required="" name="plan_id" class="form-control">
												<option value="" hidden="">Select</option>
												<?php $select_plans="select * from plans where cat='$Coupon_category' order by amount asc";
												$fetch_plans=mysqli_query($db_conn,$select_plans);
												while($result_plans=mysqli_fetch_array($fetch_plans))
												{?>
											<option value="<?php echo $result_plans['id'];?>">&#8377; <?php echo $result_plans['amount'];?> / <?php echo $result_plans['valid_months'];?> Days</option>
												<?php } ?>
												</select>
												<?php */?>
					
<!----<div id="txtHintcoupon"></div>
<div id="txtHintcouponvalid"></div>		--->

        <!-- MerchantOrderId will be auto-generated in 3 digits -->
        <input type="hidden" name="merchantOrderId" id="merchantOrderId" value="">
        <!-- MerchantTransactionId will be auto-generated in 10 digits -->
        <input type="hidden" name="merchantTransactionId" value="<?php echo $tempID;?>" value="">
        <!-- MerchantUserId will be auto-generated as a unique 5-digit number -->
        <input type="hidden" name="merchantUserId" id="merchantUserId" value="">


 <script>
        // Auto-generate values for merchantOrderId, merchantTransactionId, and merchantUserId
        document.getElementById("merchantOrderId").value = generateRandomOrderId();
        document.getElementById("merchantUserId").value = generateRandomUserId();

        function generateRandomOrderId() {
            // Auto-generate a 3-digit random order ID
            return pad(Math.floor(Math.random() * 1000), 3);
        }


        function generateRandomUserId() {
            // Auto-generate a 5-digit random user ID
            return pad(Math.floor(Math.random() * 100000), 5);
        }

        function pad(num, size) {
            // Function to pad numbers with leading zeros
            let numStr = num.toString();
            while (numStr.length < size) numStr = "0" + numStr;
            return numStr;
        }
    </script>		
			
	<br/>
	<button type="submit" name="add-superstockiest" class="btn btn-primary">
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

    if (!validExtensions.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileUpload').value = '';
    }
}

document.getElementById('uploadForm').addEventListener('submit', function(event) {
    const fileInput = document.getElementById('fileUpload');
    if (!fileInput.value) {
        event.preventDefault();
        alert('Please select a valid file before submitting.');
    }
});
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
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/select2.js"></script>
	
</body>

</html>