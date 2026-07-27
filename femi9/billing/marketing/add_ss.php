<?php include("checksession.php");
include("config.php");
error_reporting(0);

$title="Add Shop (Retailers)";
$manage_url="manage_ss";
$manage_title="Manage Shop (Retailers)";
$message_title="Shop (Retailers)";
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
								
								<?php if(isset($_REQUEST['distalready'])){?><div class="alert alert-danger">Shop Details Already Exists.</div><?php }?>
								
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
									
<?php include("validate-scripts.php");?>
<form action="shop-action" method="post" enctype="multipart/form-data" id="uploadForm">

<input type="hidden" name="ms_id" value="<?=$markeingSTFID?>">

                                        <div class="example-container">
                                            <div class="example-content">
											
											<label for="exampleInputEmail1" class="form-label">Category*</label>
    <select name="shop_cat" class="form-control" required="">
	<option value="" hidden="">Select</option>
	<?php $selectShopCat="select * from shop_category order by id asc";
	$fetchShopCat=mysqli_query($db_conn,$selectShopCat);
	while($resultShopCat=mysqli_fetch_array($fetchShopCat)){?>
	<option value="<?php echo $resultShopCat['id'];?>"><?php echo $resultShopCat['catlable'];?></option>
	<?php  } ?>
	</select>
	<br/>
											
            <label class="form-label">Name*</label>
            <input type="text" required="" name="name" class="form-control" onkeypress="restrictSpecialChars(event)">
			<br/>
			
			<label class="form-label">Shop Photo (Live)</label>
            <input type="file" name="user_icon" id="fileUpload" accept=".jpg, .jpeg, .png" class="form-control">
			<br/>

	<label for="exampleInputEmail1" class="form-label">State Name*</label>
    <input type="text" required="" name="state_name" class="form-control" onkeypress="restrictSpecialChars(event)">
	<br/>
	
	<label for="exampleInputEmail1" class="form-label">District Name*</label>
    <input type="text" required="" name="district_name" class="form-control" onkeypress="restrictSpecialChars(event)">
	<br/>
	
	<label for="exampleInputEmail1" class="form-label">Taluk Name*</label>
    <input type="text" required="" name="taluk_name" class="form-control" onkeypress="restrictSpecialChars(event)">
	<br/>
	
	<label for="exampleInputEmail1" class="form-label">Pincode*</label>
	<input type="text" id="pincode" name="pincode" class="form-control" required="" onkeypress="restrictpincode(event)" maxlength="15">
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
<select id='country_code' name='country_code' required="" class="form-control">
<?php $selectCountry="select * from country order by id asc";
$fetchCountry=mysqli_query($db_conn,$selectCountry);
while($resultCountry=mysqli_fetch_array($fetchCountry)){?>
<option value='<?php echo $resultCountry['c_code'];?>' ><?php echo $resultCountry['c_name'];?> (<?php echo $resultCountry['c_code'];?>)</option>
<?php }?>
</select>
            </div>
            <div class="mobile-number">
                <label class="form-label">Mobile Number (Username)*</label>
                <input type="text" required name="mobile_number" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" class="form-control" maxlength="10">
            </div>
        </div>
        <!-- New add end -->
			<br/>	
			
			<label class="form-label">Landline Number</label>
            <input type="text" onkeypress="restrictlandline(event)" name="landline" class="form-control">
			<br/>
			
			<label class="form-label">Email ID</label>
            <input type="email" onkeypress="restrictemail(event)" name="email" class="form-control">
			<br/>		
			
			<label class="form-label">Address*</label>
            <textarea name="address" onkeypress="restrictSpecialChars(event)" class="form-control" required=""></textarea>
			<br/>
			
			<label class="form-label">Google Location</label>
            <textarea name="google_location" id="google_location" class="form-control" readonly placeholder="Click 'Capture My Location' below"></textarea>
			<input type="hidden" name="captured_postcode" id="captured_postcode" value="">
			<input type="hidden" name="latitude" id="latitude" value="">
			<input type="hidden" name="longitude" id="longitude" value="">
			<div style="margin-top:8px;">
				<button type="button" id="captureLocationBtn" class="btn btn-secondary btn-sm">
					<i class="material-icons" style="font-size:16px; vertical-align:middle;">my_location</i> Capture My Location
				</button>
				<span id="captureLocationStatus" style="margin-left:8px; font-size:13px;"></span>
			</div>
			<div id="latLngDisplay" style="margin-top:6px; font-size:13px; color:#333;"></div>
			<br/>
			
			<label class="form-label">GST Number</label>
            <input type="text" onkeypress="restrictGSTIN(event)" name="gstin" class="form-control">
			<br/>
			
	<button type="submit" name="add-shop" class="btn btn-primary">
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
    const maxSizeMB = 5; // Maximum file size in megabytes
    const maxSizeBytes = maxSizeMB * 1024 * 1024; // Convert MB to bytes

    if (!validExtensions.includes(file.type)) {
        alert('Invalid file type. Only JPEG and PNG are allowed.');
        document.getElementById('fileUpload').value = '';
        return;
    }

    if (file.size > maxSizeBytes) {
        alert('File is too large. The maximum file size is 5 MB.');
        document.getElementById('fileUpload').value = '';
        return;
    }
}

// Reverse-geocodes lat/lng into a human-readable place name + postcode via our server-side
// endpoint, which calls the Google Geocoding API (key stays server-side, never exposed to the browser).
function reverseGeocodeToName(lat, lng) {
    const url = 'reverse-geocode.php?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng);
    return fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.error) { throw new Error(data.error); }
            return {
                name: (data && data.name) ? data.name : (lat + ', ' + lng),
                postcode: (data && data.postcode) ? data.postcode : ''
            };
        });
}

document.getElementById('captureLocationBtn').addEventListener('click', function() {
    const statusEl = document.getElementById('captureLocationStatus');
    const locationField = document.getElementById('google_location');
    const postcodeField = document.getElementById('captured_postcode');
    const latField = document.getElementById('latitude');
    const lngField = document.getElementById('longitude');
    const latLngDisplay = document.getElementById('latLngDisplay');
    const btn = this;

    if (!navigator.geolocation) {
        statusEl.textContent = 'Geolocation is not supported on this device/browser.';
        statusEl.style.color = 'red';
        return;
    }

    btn.disabled = true;
    statusEl.textContent = 'Fetching location... please allow location access.';
    statusEl.style.color = '#555';

    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            latField.value = lat;
            lngField.value = lng;
            latLngDisplay.textContent = 'Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6) + ' (accuracy: ±' + Math.round(accuracy) + 'm)';
            statusEl.textContent = 'Location found, resolving address...';
            reverseGeocodeToName(lat, lng)
                .then(function(result) {
                    locationField.value = result.name;
                    postcodeField.value = result.postcode;
                    statusEl.textContent = 'Location captured successfully.';
                    statusEl.style.color = 'green';
                    btn.disabled = false;
                })
                .catch(function() {
                    locationField.value = lat + ', ' + lng;
                    postcodeField.value = '';
                    statusEl.textContent = 'Location captured (address lookup failed, saved coordinates).';
                    statusEl.style.color = 'orange';
                    btn.disabled = false;
                });
        },
        function(error) {
            let msg = 'Unable to fetch location.';
            if (error.code === error.PERMISSION_DENIED) {
                msg = 'Location permission denied. Please allow location access and try again.';
            } else if (error.code === error.POSITION_UNAVAILABLE) {
                msg = 'Location unavailable. Please try again.';
            } else if (error.code === error.TIMEOUT) {
                msg = 'Location request timed out. Please try again.';
            }
            statusEl.textContent = msg;
            statusEl.style.color = 'red';
            btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
});

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    if (!confirm('Please make a confirm')) {
        e.preventDefault();
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>