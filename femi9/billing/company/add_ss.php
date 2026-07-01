<?php 
include("checksession.php");

// Page Configuration
$title = "Add Super Stockist";
$manage_url = "manage_ss";
$manage_title = "Manage Super Stockist";
$message_title = "Super Stockist";
$Coupon_category = "Super-Stockist";

// Business Configuration
$business_name = "Super Stockist Management"; // Default value

// Try to get business name from database (adjust table/column names as per your database)
try {
    $query_business = "SELECT company_name FROM admin_log WHERE id = ? LIMIT 1";
    $stmt_business = mysqli_prepare($db_conn, $query_business);
    mysqli_stmt_bind_param($stmt_business, "i", $_SESSION['LOGIN_USER_ID']);
    mysqli_stmt_execute($stmt_business);
    $result_business = mysqli_stmt_get_result($stmt_business);
    
    if ($row_business = mysqli_fetch_assoc($result_business)) {
        if (!empty($row_business['company_name'])) {
            $business_name = $row_business['company_name'];
        }
    }
    mysqli_stmt_close($stmt_business);
} catch (Exception $e) {
    // Keep default business name if query fails
}

// User session variables
$user_id = $_SESSION['LOGIN_USER_ID'];
$user_type = $_SESSION['LOGIN_USER_TYPE'] ?? 'company';
$username = $_SESSION['LOGIN_USER'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Title -->
    <title><?php echo htmlspecialchars($title); ?> : <?php echo htmlspecialchars($business_name); ?></title>

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
								
								<?php if(isset($_REQUEST['distalready'])){?>
                                    <div class="alert alert-danger">This districtwise super stockiest already exists.</div>
                                <?php }?>
								
								<?php if(isset($_REQUEST['invalidcoupon'])){?>
                                    <div class="alert alert-danger">Invalid coupon code!</div>
                                <?php }?>
								
                                <h1>
									<table class="headertble">
									<tr>
									<td><?php echo htmlspecialchars($title);?></td>
									<td><a href="<?php echo htmlspecialchars($manage_url);?>" title="<?php echo htmlspecialchars($manage_title);?>">&#9776;</a></td>
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
									
									<?php if(isset($_REQUEST['alreadyexists'])){?>
                                        <div class="alert alert-danger"><?php echo htmlspecialchars($message_title);?> already exists!</div>
									<?php }?>
									
									<?php if(isset($_REQUEST['InvalidMobileNumber'])){?>
                                        <div class="alert alert-danger">Warning! Mobile number already exists.</div>
									<?php }?>
									
<?php include("validate-scripts.php");?>	 

<form action="ss-action" method="post" enctype="multipart/form-data" id="uploadForm">
									   
<?php 
// Secure random number generation
function generateSecureHash($length = 5) {
    $characters = '123456789';
    $hash = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $hash .= $characters[random_int(0, $max)];
    }
    
    return $hash;
}

$randum_number = generateSecureHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date = date("dmy");
$temp_time = date("gis"); 
$tempID = $randum_number . "FSPST" . $temp_date . $temp_time;
?>

<input type="hidden" name="temp_id" value="<?php echo htmlspecialchars($tempID); ?>">
<input type="hidden" name="Coupon_category" value="<?php echo htmlspecialchars($Coupon_category); ?>">
<input type="hidden" name="onboard_userTYPE" value="<?php echo htmlspecialchars($user_type); ?>">
<input type="hidden" name="onboard_userID" value="<?php echo htmlspecialchars($user_id); ?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
            <label class="form-label">Name*</label>
            <input type="text" required name="name" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>
			
			<label class="form-label">Candidate Photo</label>
            <input type="file" name="user_icon" class="form-control" id="fileUpload" accept=".jpg, .jpeg, .png">
			<br/>
			
			<script type="text/javascript">
function showDistrict(str){
    if (str == "") {
        document.getElementById("txtHintDistrict").innerHTML = "";
        return;
    }
    
    if (window.XMLHttpRequest) {
        xmlhttp = new XMLHttpRequest();
    } else {
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            document.getElementById("txtHintDistrict").innerHTML = xmlhttp.responseText;
        }
    }
    
    var invuser = "super_stockiest";
    xmlhttp.open("GET", "loadDistrict.php?q=" + str + '&invuser=' + invuser, true);
    xmlhttp.send();
}
</script>
			
			<label for="exampleInputEmail1" class="form-label">State Name*</label>
            <select required name="state_id" class="form-control" onchange="showDistrict(this.value)">
                <option value="" hidden>Select</option>
                <?php 
                $select_stateList = "SELECT * FROM `state` ORDER BY `st_name` ASC";
                $fetch_stateList = mysqli_query($db_conn, $select_stateList);
                
                if ($fetch_stateList) {
                    while($result_stateList = mysqli_fetch_array($fetch_stateList)) {
                ?>
                    <option value="<?php echo htmlspecialchars($result_stateList['id']); ?>">
                        <?php echo htmlspecialchars($result_stateList['st_name']); ?>
                    </option>
                <?php 
                    }
                }
                ?>
            </select>
			<br/>
			
			<label class="form-label">District*</label>
			<div id="txtHintDistrict">
                <select required name="dist_id" class="form-control">
                    <option value="" hidden>Select</option>
                </select>
            </div>
            <br/>

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
                <select id='country_code' name='country_code' required class="form-control">
                <?php 
                $selectCountry = "SELECT * FROM country ORDER BY id ASC";
                $fetchCountry = mysqli_query($db_conn, $selectCountry);
                
                if ($fetchCountry) {
                    while($resultCountry = mysqli_fetch_array($fetchCountry)) {
                ?>
                    <option value='<?php echo htmlspecialchars($resultCountry['c_code']); ?>'>
                        <?php echo htmlspecialchars($resultCountry['c_name']); ?> 
                        (<?php echo htmlspecialchars($resultCountry['c_code']); ?>)
                    </option>
                <?php 
                    }
                }
                ?>
                </select>
            </div>
			
			<script type="text/javascript">
function showMobileNumber(str){
    if (str == "") {
        document.getElementById("txtHintMobile").innerHTML = "";
        return;
    }
    
    if (window.XMLHttpRequest) {
        xmlhttp = new XMLHttpRequest();
    } else {
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            document.getElementById("txtHintMobile").innerHTML = xmlhttp.responseText;
        }
    }
    
    var invuser = "super_stockiest";
    xmlhttp.open("GET", "loadMobileNumber.php?q=" + str + '&invuser=' + invuser, true);
    xmlhttp.send();
}
</script>

            <div class="mobile-number">
                <label class="form-label">Mobile Number (Username)*</label>
                <input type="text" required name="mobile_number" onChange="showMobileNumber(this.value)" onkeypress="restrictnumber(event)" class="form-control" maxlength="10">
			</div>
        </div>
		<span id="txtHintMobile"></span>
		<br/>
			
		<input type="hidden" name="password" value="12345678">
			
		<label class="form-label">Email ID</label>
        <input type="email" name="email" onkeypress="restrictemail(event)" class="form-control">
		<br/>	
			
		<label class="form-label">Address*</label>
        <textarea name="address" class="form-control" onkeypress="restrictSpecialChars(event)" required></textarea>
		<br/>
			
		<label class="form-label">GST Number*</label>
        <input type="text" required maxlength="15" onkeypress="restrictGSTIN(event)" name="gstin" class="form-control">			
		<br/>

        <!-- MerchantOrderId will be auto-generated in 3 digits -->
        <input type="hidden" name="merchantOrderId" id="merchantOrderId" value="">
        <!-- MerchantTransactionId will be auto-generated in 10 digits -->
        <input type="hidden" name="merchantTransactionId" value="<?php echo htmlspecialchars($tempID); ?>">
        <!-- MerchantUserId will be auto-generated as a unique 5-digit number -->
        <input type="hidden" name="merchantUserId" id="merchantUserId" value="">

        <script>
            // Auto-generate values for merchantOrderId and merchantUserId
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
		<button type="submit" name="add-superstockiest" onclick="return confirm('Please make a confirm');" class="btn btn-primary">
            <i class="material-icons">add</i> Add
        </button>
												
                                            </div>
                                        </div>
                                    </form>
										
	<script>
        document.getElementById('fileUpload').addEventListener('change', function(event) {
            validateFile(event.target.files[0]);
        });

        function validateFile(file) {
            if (!file) return;
            
            const validExtensions = ['image/jpeg', 'image/jpg', 'image/png'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!validExtensions.includes(file.type)) {
                alert('Invalid file type. Only JPEG and PNG are allowed.');
                document.getElementById('fileUpload').value = '';
                return false;
            }
            
            if (file.size > maxSize) {
                alert('File size exceeds 2MB. Please choose a smaller file.');
                document.getElementById('fileUpload').value = '';
                return false;
            }
            
            return true;
        }

        document.getElementById('uploadForm').addEventListener('submit', function(event) {
            const fileInput = document.getElementById('fileUpload');
            
            // File upload is optional, so only validate if a file is selected
            if (fileInput.files.length > 0) {
                if (!validateFile(fileInput.files[0])) {
                    event.preventDefault();
                    return false;
                }
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