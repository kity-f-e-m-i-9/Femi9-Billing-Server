<?php 
include("checksession.php");

// Page Configuration
$title = "Edit Super Stockist";
$manage_url = "manage_ss";
$manage_title = "Manage Super Stockist";
$message_title = "Super Stockist";

// Business Configuration
$business_name = "Super Stockist Management"; // Default value

// Try to get business name from database
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

// Get and validate ID
if (!isset($_REQUEST['prid']) || empty($_REQUEST['prid'])) {
    header("Location: manage_ss?error=invalid_id");
    exit;
}

$get_id = base64_decode($_REQUEST['prid']);

// Validate decoded ID
if (!is_numeric($get_id) || $get_id <= 0) {
    header("Location: manage_ss?error=invalid_id");
    exit;
}

// Fetch Super Stockist details using prepared statement
$select_product_list = "SELECT * FROM super_stockiest WHERE id = ? LIMIT 1";
$stmt_product = mysqli_prepare($db_conn, $select_product_list);
mysqli_stmt_bind_param($stmt_product, "i", $get_id);
mysqli_stmt_execute($stmt_product);
$fetch_product_list = mysqli_stmt_get_result($stmt_product);
$result_product_list = mysqli_fetch_assoc($fetch_product_list);
mysqli_stmt_close($stmt_product);

$stockistid = $result_product_list['temp_id'];

// Check if record exists
if (!$result_product_list) {
    header("Location: manage_ss?error=not_found");
    exit;
}

// State details
$display_stid = $result_product_list['state_id'];
$select_staedetails = "SELECT * FROM state WHERE id = ? LIMIT 1";
$stmt_state = mysqli_prepare($db_conn, $select_staedetails);
mysqli_stmt_bind_param($stmt_state, "i", $display_stid);
mysqli_stmt_execute($stmt_state);
$fetch_staedetails = mysqli_stmt_get_result($stmt_state);
$result_staedetails = mysqli_fetch_assoc($fetch_staedetails);
mysqli_stmt_close($stmt_state);
$display_stname = $result_staedetails['st_name'] ?? 'Unknown';

// District details
$display_disid = $result_product_list['district_id'];
$select_distict = "SELECT * FROM district WHERE id = ? LIMIT 1";
$stmt_district = mysqli_prepare($db_conn, $select_distict);
mysqli_stmt_bind_param($stmt_district, "i", $display_disid);
mysqli_stmt_execute($stmt_district);
$fetch_district = mysqli_stmt_get_result($stmt_district);
$result_district = mysqli_fetch_assoc($fetch_district);
mysqli_stmt_close($stmt_district);
$display_disname = $result_district['dist_name'] ?? 'Unknown';
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
                                    <div class="alert alert-danger">This district already has a super stockist assigned.</div>
                                <?php }?>
								
                                <h1>
									<table class="headertble">
									<tr>
									<td><?php echo htmlspecialchars($title); ?></td>
									<td><a href="<?php echo htmlspecialchars($manage_url); ?>" title="<?php echo htmlspecialchars($manage_title); ?>">&#9776;</a></td>
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

<?php include("validate-scripts.php"); ?>

<form action="edit-ss-action" method="post" enctype="multipart/form-data" id="editForm">

<input type="hidden" name="update_id" value="<?php echo htmlspecialchars($result_product_list['id']); ?>">
<input type="hidden" name="old_icon" value="<?php echo htmlspecialchars($result_product_list['user_icon']); ?>">
<input type="hidden" name="current_district_id" value="<?php echo htmlspecialchars($result_product_list['district_id']); ?>">

                                        <div class="example-container">
                                            <div class="example-content">
                                                
<?php 
// REFERRAL PERSON & CATEGORY DETAILS
$select_RFRDtailsCNG = "SELECT * FROM super_stockiest_referral WHERE super_stockiest_id = ? LIMIT 1";
$stmt_rfr = mysqli_prepare($db_conn, $select_RFRDtailsCNG);
mysqli_stmt_bind_param($stmt_rfr, "s", $stockistid);
mysqli_stmt_execute($stmt_rfr);
$fetch_RFRDtailsCNG = mysqli_stmt_get_result($stmt_rfr);
$result_RFRDtailsCNG = mysqli_fetch_assoc($fetch_RFRDtailsCNG);
mysqli_stmt_close($stmt_rfr);

$CNG_catID = 0;
$result_RFRDtailsCNG_CAT = ['name' => '', 'target_amount' => '0'];

if ($result_RFRDtailsCNG && isset($result_RFRDtailsCNG['ss_cat_id'])) {
    $CNG_catID = intval($result_RFRDtailsCNG['ss_cat_id']);
    
    if ($CNG_catID > 0) {
        $select_RFRDtailsCNG_CAT = "SELECT * FROM super_stockiest_category WHERE id = ? LIMIT 1";
        $stmt_cat = mysqli_prepare($db_conn, $select_RFRDtailsCNG_CAT);
        mysqli_stmt_bind_param($stmt_cat, "i", $CNG_catID);
        mysqli_stmt_execute($stmt_cat);
        $fetch_RFRDtailsCNG_CAT = mysqli_stmt_get_result($stmt_cat);
        $result_RFRDtailsCNG_CAT = mysqli_fetch_assoc($fetch_RFRDtailsCNG_CAT);
        mysqli_stmt_close($stmt_cat);
        
        // If category not found, reset to defaults
        if (!$result_RFRDtailsCNG_CAT) {
            $CNG_catID = 0;
            $result_RFRDtailsCNG_CAT = ['name' => '', 'target_amount' => '0'];
        }
    }
}
?>
<label class="form-label">Category*</label>
            <select required name="ss_cat_id" class="form-control">
                <option value="<?php echo htmlspecialchars($CNG_catID); ?>" selected>
                    <?php echo htmlspecialchars($result_RFRDtailsCNG_CAT['name']); ?> 
                    (<?php echo htmlspecialchars($result_RFRDtailsCNG_CAT['target_amount']); ?>)
                </option>
                <?php 
                $select_stcatlist = "SELECT * FROM super_stockiest_category ORDER BY id ASC";
                $fetch_stcatlist = mysqli_query($db_conn, $select_stcatlist);
                
                if ($fetch_stcatlist) {
                    while($result_stcatlist = mysqli_fetch_array($fetch_stcatlist)) {
                        if ($result_stcatlist['id'] != $CNG_catID) {
                ?>
                    <option value="<?php echo htmlspecialchars($result_stcatlist['id']); ?>">
                        <?php echo htmlspecialchars($result_stcatlist['name']); ?> 
                        (<?php echo htmlspecialchars($result_stcatlist['target_amount']); ?>)
                    </option>
                <?php 
                        }
                    }
                }
                ?>
            </select>
			<br/>
											
            <label class="form-label">Name*</label>
            <input type="text" required name="name" onkeypress="restrictSpecialChars(event)" value="<?php echo htmlspecialchars($result_product_list['name']); ?>" class="form-control">
			<br/>
			
			<?php
			if($result_product_list["user_icon"] != "Nil" && !empty($result_product_list["user_icon"])) {
                $imgsrcname = htmlspecialchars($result_product_list["user_icon"]);
            } else {
                $imgsrcname = "../../assets/images/no image.jpg";
            }
			?>
			
			<label class="form-label">Candidate Photo</label>
            <input type="file" name="user_icon" class="form-control" id="fileUpload" accept=".jpg, .jpeg, .png">
			<br/>
			<img src="<?php echo $imgsrcname; ?>" style="width:100px; height:auto;" alt="Candidate Photo"/>
			<br/><br/>

            <script type="text/javascript">
function showDistrictEdit(str) {
    if (str == "") {
        document.getElementById("txtHintDistrictEdit").innerHTML = '<select required name="dist_id" class="form-control"><option value="" hidden>Select State First</option></select>';
        return;
    }
    
    if (window.XMLHttpRequest) {
        xmlhttp = new XMLHttpRequest();
    } else {
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            document.getElementById("txtHintDistrictEdit").innerHTML = xmlhttp.responseText;
        }
    }
    
    var invuser = "super_stockiest";
    var current_ss_id = "<?php echo $result_product_list['id']; ?>";
    var current_dist_id = "<?php echo $result_product_list['district_id']; ?>";
    
    xmlhttp.open("GET", "loadDistrictEdit.php?q=" + str + "&invuser=" + invuser + "&current_ss_id=" + current_ss_id + "&current_dist_id=" + current_dist_id, true);
    xmlhttp.send();
}
</script>

			<label class="form-label">State Name*</label>
            <select required name="state_id" class="form-control" id="stateSelect" onchange="showDistrictEdit(this.value)">
                <option value="<?php echo htmlspecialchars($display_stid); ?>" selected>
                    <?php echo htmlspecialchars($display_stname); ?>
                </option>
                <?php 
                $select_stateList = "SELECT * FROM `state` ORDER BY `st_name` ASC";
                $fetch_stateList = mysqli_query($db_conn, $select_stateList);
                
                if ($fetch_stateList) {
                    while($result_stateList = mysqli_fetch_array($fetch_stateList)) {
                        // Don't duplicate the current state
                        if ($result_stateList['id'] != $display_stid) {
                ?>
                    <option value="<?php echo htmlspecialchars($result_stateList['id']); ?>">
                        <?php echo htmlspecialchars($result_stateList['st_name']); ?>
                    </option>
                <?php 
                        }
                    }
                }
                ?>
            </select>
			<br/>
			
			<label class="form-label">District*</label>
			<div id="txtHintDistrictEdit">
                <select required name="dist_id" class="form-control">
                    <option value="<?php echo htmlspecialchars($display_disid); ?>" selected>
                        <?php echo htmlspecialchars($display_disname); ?>
                    </option>
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
                    <option value="<?php echo htmlspecialchars($result_product_list['country_code']); ?>" selected>
                        <?php echo htmlspecialchars($result_product_list['country_code']); ?>
                    </option>
                    <?php 
                    $selectCountry = "SELECT * FROM country ORDER BY c_name ASC";
                    $fetchCountry = mysqli_query($db_conn, $selectCountry);
                    
                    if ($fetchCountry) {
                        while($resultCountry = mysqli_fetch_array($fetchCountry)) {
                            // Don't duplicate current country code
                            if ($resultCountry['c_code'] != $result_product_list['country_code']) {
                    ?>
                        <option value='<?php echo htmlspecialchars($resultCountry['c_code']); ?>'>
                            <?php echo htmlspecialchars($resultCountry['c_name']); ?> 
                            (<?php echo htmlspecialchars($resultCountry['c_code']); ?>)
                        </option>
                    <?php 
                            }
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="mobile-number">
                <label class="form-label">Mobile Number (Username)*</label>
                <input type="text" required name="mobile_number" onkeypress="restrictnumber(event)" value="<?php echo htmlspecialchars($result_product_list['mobile_number']); ?>" class="form-control" maxlength="10" readonly>
            </div>
        </div>
		<br/>
		
		<label class="form-label">Email ID</label>
        <input type="email" onkeypress="restrictemail(event)" value="<?php echo htmlspecialchars($result_product_list['email']); ?>" name="email" class="form-control">
		<br/>
									
		<label class="form-label">Address*</label>
        <textarea name="address" class="form-control" onkeypress="restrictSpecialChars(event)" required><?php echo htmlspecialchars($result_product_list['address']); ?></textarea>
		<br/>
		
		<label class="form-label">GST Number*</label>
        <input type="text" required maxlength="15" value="<?php echo htmlspecialchars($result_product_list['gstin']); ?>" onkeypress="restrictGSTIN(event)" name="gstin" class="form-control">
		<br/>
	
		<button type="submit" name="update-superstockiest" onclick="return confirm('Are you sure you want to update this Super Stockist?');" class="btn btn-primary">
            <i class="material-icons">update</i> Update
        </button>
												
                                            </div>
                                        </div>
                                    </form>

    <script>
        document.getElementById('fileUpload').addEventListener('change', function(event) {
            validateFile(event.target.files[0]);
        });

        function validateFile(file) {
            if (!file) return true;
            
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

        document.getElementById('editForm').addEventListener('submit', function(event) {
            const fileInput = document.getElementById('fileUpload');
            
            // File upload is optional, so only validate if a file is selected
            if (fileInput.files.length > 0) {
                if (!validateFile(fileInput.files[0])) {
                    event.preventDefault();
                    return false;
                }
            }
        });
        
        // Load districts for current state on page load
        window.addEventListener('load', function() {
            showDistrictEdit('<?php echo $display_stid; ?>');
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