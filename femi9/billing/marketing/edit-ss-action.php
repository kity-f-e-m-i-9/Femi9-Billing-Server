<?php include("checksession.php");
include("RemoveSpecialChar.php");
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
	$old_icon=$_REQUEST['old_icon'];
	$shop_cat=$_POST["shop_cat"];
	
	$name=str_replace("'","&#39;",$_POST["name"]);
	$name=RemoveSpecialChar($name);
	
	$state_name=$_POST["state_name"];
	$state_name=RemoveSpecialChar($state_name);
	
	$district_name=$_POST["district_name"];
	$district_name=RemoveSpecialChar($district_name);
	
	$taluk_name=$_POST["taluk_name"];
	$taluk_name=RemoveSpecialChar($taluk_name);
	
	$pincode=$_POST["pincode"];
	$pincode=RemoveSpecialChar($pincode);
	
	$country_code=$_POST["country_code"];
	$landline=str_replace("'","&#39;",$_POST["landline"]);
	
	$email=str_replace("'","&#39;",$_POST["email"]);
	$email=RemoveSpecialChar($email);
	
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$gstin=RemoveSpecialChar($gstin);
	
	$address=str_replace("'","&#39;",$_POST["address"]);
	$address=RemoveSpecialChar($address);
	
	$google_location=str_replace("'","&#39;",$_POST["google_location"]);

	$_col = mysqli_query($db_conn, "SHOW COLUMNS FROM ms_shop LIKE 'location_recapture_count'");
	if ($_col && mysqli_num_rows($_col) === 0) {
		mysqli_query($db_conn, "ALTER TABLE ms_shop ADD COLUMN location_recapture_count INT NOT NULL DEFAULT 0");
	}

	$latitude=isset($_POST["latitude"]) && $_POST["latitude"]!=='' ? floatval($_POST["latitude"]) : null;
	$longitude=isset($_POST["longitude"]) && $_POST["longitude"]!=='' ? floatval($_POST["longitude"]) : null;

	$select_existing_latlng="select latitude, longitude, location_recapture_count from ms_shop where id='$update_id'";
	$fetch_existing_latlng=mysqli_query($db_conn,$select_existing_latlng);
	$existing_latlng=mysqli_fetch_assoc($fetch_existing_latlng);
	$existing_lat = $existing_latlng['latitude'] !== null ? (float)$existing_latlng['latitude'] : null;
	$existing_lng = $existing_latlng['longitude'] !== null ? (float)$existing_latlng['longitude'] : null;
	$recaptureCount = (int)($existing_latlng['location_recapture_count'] ?? 0);

	// A recapture is only counted when the DM actually submitted a different
	// lat/lng than what's already saved (the hidden hidden fields on
	// edit-ss.php only change value after a successful "Re-capture Location"
	// click) — max 2 recaptures per shop for life, enforced here server-side
	// since the button-disable on the form is only a UX nicety.
	$isRecapture = $latitude !== null && $longitude !== null
		&& ($existing_lat === null || $existing_lng === null || abs($latitude - $existing_lat) > 0.0000001 || abs($longitude - $existing_lng) > 0.0000001);

	if ($isRecapture && $existing_lat !== null && $existing_lng !== null && $recaptureCount >= 2) {
		// Limit reached — keep the shop's existing, locked location and
		// ignore whatever new coordinates were just submitted.
		$latitude = $existing_lat;
		$longitude = $existing_lng;
	} elseif ($isRecapture) {
		$recaptureCount++;
		mysqli_query($db_conn, "update ms_shop set location_recapture_count='$recaptureCount' where id='$update_id'");
	} else {
		if($latitude===null) { $latitude=$existing_lat; }
		if($longitude===null) { $longitude=$existing_lng; }
	}
	$latitude_sql=$latitude===null ? "NULL" : "'".$latitude."'";
	$longitude_sql=$longitude===null ? "NULL" : "'".$longitude."'";

	//upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
		
$filetype=$_FILES['user_icon']['type'];
if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png')
{
$insfilename=$old_icon;
 echo "<script>window.location='edit-ss.php?prid=".base64_encode($update_id)."&&imageinvlaid';</script>";
}else{


	   $file_extension = pathinfo($_FILES['user_icon']['name'], PATHINFO_EXTENSION);
		$rand_isd=bin2hex(random_bytes(64));
	    $filename=$rand_isd . '.' . $file_extension;
		
                 $uploaddir='shop_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfilename=$uploadfile;
	   if($old_icon!="Nil")
	   {
		   unlink("".$old_icon."");
	   }
}
	}
	else
	{
		$insfilename=$old_icon;
	}
	
	
	//update process
	$update_ss="update ms_shop set user_icon='$insfilename',name='$name',
	state_name='$state_name',district_name='$district_name',taluk_name='$taluk_name',
	pincode='$pincode',email='$email',gstin='$gstin',address='$address',shop_cat='$shop_cat',
country_code='$country_code',landline='$landline',google_location='$google_location',
latitude=$latitude_sql,longitude=$longitude_sql where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
	echo "<script>window.location='manage_ss.php?updatedSuccess';</script>";
	
}else{
	
	echo "<script>window.location='add_ss.php';</script>";
}
	
	?>