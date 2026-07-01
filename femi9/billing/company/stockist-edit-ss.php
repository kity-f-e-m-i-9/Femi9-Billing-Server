<?php 
include("checksession.php");

// Page Configuration
$title = "Edit Stockist";
$manage_url = "stockist-manage";
$manage_title = "Manage Stockist";
$message_title = "Stockist";

// Business Configuration
$business_name = "Stockist Management"; // Default value

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
    header("Location: stockist-manage?error=invalid_id");
    exit;
}

$get_id = base64_decode($_REQUEST['prid']);

// Validate decoded ID
if (!is_numeric($get_id) || $get_id <= 0) {
    header("Location: stockist-manage?error=invalid_id");
    exit;
}

// Fetch Stockist details using prepared statement
$select_product_list = "SELECT * FROM stockiest WHERE id = ? LIMIT 1";
$stmt_product = mysqli_prepare($db_conn, $select_product_list);
mysqli_stmt_bind_param($stmt_product, "i", $get_id);
mysqli_stmt_execute($stmt_product);
$fetch_product_list = mysqli_stmt_get_result($stmt_product);
$result_product_list = mysqli_fetch_assoc($fetch_product_list);
mysqli_stmt_close($stmt_product);

// Check if record exists
if (!$result_product_list) {
    header("Location: stockist-manage?error=not_found");
    exit;
}

$stockistid = $result_product_list['temp_id'];

// State details
$state_id = $result_product_list['state_id'];
$select_stdetails = "SELECT * FROM state WHERE id = ? LIMIT 1";
$stmt_state = mysqli_prepare($db_conn, $select_stdetails);
mysqli_stmt_bind_param($stmt_state, "i", $state_id);
mysqli_stmt_execute($stmt_state);
$fetch_stdetails = mysqli_stmt_get_result($stmt_state);
$result_stdetails = mysqli_fetch_assoc($fetch_stdetails);
mysqli_stmt_close($stmt_state);
$state_name = $result_stdetails['st_name'] ?? 'Unknown';

// District details
$district_id = $result_product_list['district_id'];
$select_distict = "SELECT * FROM district WHERE id = ? LIMIT 1";
$stmt_district = mysqli_prepare($db_conn, $select_distict);
mysqli_stmt_bind_param($stmt_district, "i", $district_id);
mysqli_stmt_execute($stmt_district);
$fetch_district = mysqli_stmt_get_result($stmt_district);
$result_district = mysqli_fetch_assoc($fetch_district);
mysqli_stmt_close($stmt_district);
$district_name = $result_district['dist_name'] ?? 'Unknown';

// Taluk details
$taluk_id = $result_product_list['taluk_id'];
$select_taluk = "SELECT * FROM taluk WHERE id = ? LIMIT 1";
$stmt_taluk = mysqli_prepare($db_conn, $select_taluk);
mysqli_stmt_bind_param($stmt_taluk, "i", $taluk_id);
mysqli_stmt_execute($stmt_taluk);
$fetch_taluk = mysqli_stmt_get_result($stmt_taluk);
$result_taluk = mysqli_fetch_assoc($fetch_taluk);
mysqli_stmt_close($stmt_taluk);
$taluk_name = $result_taluk['taluk'] ?? 'Unknown';
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
								
								<?php if(isset($_REQUEST['talukalready'])){?>
                                    <div class="alert alert-danger">This taluk already has a stockist assigned.</div>
                                <?php }?>
								
								<?php if(isset($_REQUEST['alreadyexistsLocation'])){?>
                                    <div class="alert alert-warning">Invalid location, already exists.</div>
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

<form action="stockist-edit-ss-action" method="post" enctype="multipart/form-data" id="editForm">

<input type="hidden" name="update_id" value="<?php echo htmlspecialchars($result_product_list['id']); ?>">
<input type="hidden" name="stockistid" value="<?php echo htmlspecialchars($stockistid); ?>">
<input type="hidden" name="old_icon" value="<?php echo htmlspecialchars($result_product_list['user_icon']); ?>">
<input type="hidden" name="current_taluk_id" value="<?php echo htmlspecialchars($result_product_list['taluk_id']); ?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
<?php 
// REFERRAL PERSON & CATEGORY DETAILS
$select_RFRDtailsCNG = "SELECT * FROM stockist_referral WHERE stockist_id = ? LIMIT 1";
$stmt_rfr = mysqli_prepare($db_conn, $select_RFRDtailsCNG);
mysqli_stmt_bind_param($stmt_rfr, "s", $stockistid);
mysqli_stmt_execute($stmt_rfr);
$fetch_RFRDtailsCNG = mysqli_stmt_get_result($stmt_rfr);
$result_RFRDtailsCNG = mysqli_fetch_assoc($fetch_RFRDtailsCNG);
mysqli_stmt_close($stmt_rfr);

$CNG_catID = $result_RFRDtailsCNG['st_cat_id'] ?? 0;

if ($CNG_catID > 0) {
    $select_RFRDtailsCNG_CAT = "SELECT * FROM stockist_category WHERE id = ? LIMIT 1";
    $stmt_cat = mysqli_prepare($db_conn, $select_RFRDtailsCNG_CAT);
    mysqli_stmt_bind_param($stmt_cat, "i", $CNG_catID);
    mysqli_stmt_execute($stmt_cat);
    $fetch_RFRDtailsCNG_CAT = mysqli_stmt_get_result($stmt_cat);
    $result_RFRDtailsCNG_CAT = mysqli_fetch_assoc($fetch_RFRDtailsCNG_CAT);
    mysqli_stmt_close($stmt_cat);
    $Login_person_CAT = $result_RFRDtailsCNG_CAT['catname'] ?? '';
} else {
    $result_RFRDtailsCNG_CAT = ['catname' => 'Not Set', 'target_amount' => '0'];
}
?>

			<label class="form-label">Category*</label>
            <select required name="st_cat_id" class="form-control">
                <option value="<?php echo htmlspecialchars($CNG_catID); ?>" selected>
                    <?php echo htmlspecialchars($result_RFRDtailsCNG_CAT['catname']); ?> 
                    (<?php echo htmlspecialchars($result_RFRDtailsCNG_CAT['target_amount']); ?>)
                </option>
                <?php 
                $select_stcatlist = "SELECT * FROM stockist_category ORDER BY id ASC";
                $fetch_stcatlist = mysqli_query($db_conn, $select_stcatlist);
                
                if ($fetch_stcatlist) {
                    while($result_stcatlist = mysqli_fetch_array($fetch_stcatlist)) {
                        if ($result_stcatlist['id'] != $CNG_catID) {
                ?>
                    <option value="<?php echo htmlspecialchars($result_stcatlist['id']); ?>">
                        <?php echo htmlspecialchars($result_stcatlist['catname']); ?> 
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
                $imgsrcname = "../super-stockist/" . htmlspecialchars($result_product_list["user_icon"]);
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
function showDistrictStockistEdit(str) {
    if (str == "") {
        document.getElementById("txtHintDistrictStockistEdit").innerHTML = '<select required name="dist_id" class="form-control"><option value="" hidden>Select State First</option></select>';
        document.getElementById("txtHintTalukStockistEdit").innerHTML = '<select required name="taluk_id" class="form-control"><option value="" hidden>Select District First</option></select>';
        return;
    }
    
    if (window.XMLHttpRequest) {
        xmlhttp = new XMLHttpRequest();
    } else {
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            document.getElementById("txtHintDistrictStockistEdit").innerHTML = xmlhttp.responseText;
            
            // AUTO-TRIGGER: Load taluks if district is pre-selected (fast timeout)
            setTimeout(function() {
                var districtSelect = document.getElementById('districtSelect');
                if (districtSelect && districtSelect.value) {
                    showTalukStockistEdit(str, districtSelect.value);
                }
            }, 50);
        }
    }
    
    var invuser = "stockiest";
    var current_stockist_id = "<?php echo $result_product_list['id']; ?>";
    var current_dist_id = "<?php echo $result_product_list['district_id']; ?>";
    
    xmlhttp.open("GET", "loadDistrictStockistEdit.php?q=" + str + "&invuser=" + invuser + "&current_stockist_id=" + current_stockist_id + "&current_dist_id=" + current_dist_id, true);
    xmlhttp.send();
}

function showTalukStockistEdit(state_id, dist_id) {
    if (state_id == "" || dist_id == "") {
        document.getElementById("txtHintTalukStockistEdit").innerHTML = '<select required name="taluk_id" class="form-control"><option value="" hidden>Select District First</option></select>';
        return;
    }
    
    if (window.XMLHttpRequest) {
        xmlhttp = new XMLHttpRequest();
    } else {
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            document.getElementById("txtHintTalukStockistEdit").innerHTML = xmlhttp.responseText;
        }
    }
    
    var invuser = "stockiest";
    var current_stockist_id = "<?php echo $result_product_list['id']; ?>";
    var current_taluk_id = "<?php echo $result_product_list['taluk_id']; ?>";
    
    xmlhttp.open("GET", "loadTalukStockistEdit.php?state_id=" + state_id + "&dist_id=" + dist_id + "&invuser=" + invuser + "&current_stockist_id=" + current_stockist_id + "&current_taluk_id=" + current_taluk_id, true);
    xmlhttp.send();
}
</script>

			<label class="form-label">State Name*</label>
            <select required name="state_id" class="form-control" id="stateSelect" onchange="showDistrictStockistEdit(this.value)">
                <option value="<?php echo htmlspecialchars($state_id); ?>" selected>
                    <?php echo htmlspecialchars($state_name); ?>
                </option>
                <?php 
                $select_stateList = "SELECT * FROM `state` ORDER BY `st_name` ASC";
                $fetch_stateList = mysqli_query($db_conn, $select_stateList);
                
                if ($fetch_stateList) {
                    while($result_stateList = mysqli_fetch_array($fetch_stateList)) {
                        if ($result_stateList['id'] != $state_id) {
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
			<div id="txtHintDistrictStockistEdit">
                <select required name="dist_id" class="form-control" onchange="showTalukStockistEdit(<?php echo $state_id; ?>, this.value)">
                    <option value="<?php echo htmlspecialchars($district_id); ?>" selected>
                        <?php echo htmlspecialchars($district_name); ?>
                    </option>
                </select>
            </div>
            <br/>
            
            <label class="form-label">Taluk*</label>
			<div id="txtHintTalukStockistEdit">
                <select required name="taluk_id" class="form-control">
                    <option value="" hidden>Loading taluks...</option>
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
	
		<button type="submit" name="update-superstockiest" onclick="return confirm('Are you sure you want to update this Stockist?');" class="btn btn-primary">
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
            
            if (fileInput.files.length > 0) {
                if (!validateFile(fileInput.files[0])) {
                    event.preventDefault();
                    return false;
                }
            }
        });
        
        // Load districts on page load
        window.addEventListener('load', function() {
            showDistrictStockistEdit('<?php echo $state_id; ?>');
            
            // Also setup a MutationObserver to auto-trigger taluk loading when district dropdown is populated
            var districtContainer = document.getElementById('txtHintDistrictStockistEdit');
            if (districtContainer) {
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            // District dropdown has been updated
                            var districtSelect = document.getElementById('districtSelect');
                            if (districtSelect && districtSelect.value) {
                                // If there's a selected value, trigger taluk loading
                                var stateId = document.getElementById('stateSelect').value;
                                setTimeout(function() {
                                    showTalukStockistEdit(stateId, districtSelect.value);
                                }, 50);
                            }
                        }
                    });
                });
                
                observer.observe(districtContainer, {
                    childList: true,
                    subtree: true
                });
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