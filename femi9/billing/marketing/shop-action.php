<?php include("checksession.php");
include("config.php");
require_once("include/AssignedLocations.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {


		$addurl="add_ss.php?distalready";
		$viewurl="manage_ss.php?addedsuccess";

	$ms_id=$_POST["ms_id"];
	$shop_cat=$_POST["shop_cat"];

	$name=str_replace("'","&#39;",$_POST["name"]);
	$name=RemoveSpecialChar($name);

	// District/taluk are picked from this DM's assigned locations (see
	// add_ss.php) — validated again here server-side rather than trusting
	// the posted id, and the clean name text + state come from the location
	// hierarchy itself, never typed free-hand.
	$district_node_id = (int)($_POST['district_node_id'] ?? 0);
	$taluk_node_id     = (int)($_POST['taluk_node_id'] ?? 0);

	$assignedDistricts  = getMsAssignedDistricts($db_conn, (int)$ms_id);
	$assignedDistrictsById = array_column($assignedDistricts, null, 'id');
	$matchedDistrict = $assignedDistrictsById[$district_node_id] ?? null;

	if (!$matchedDistrict) {
		echo "<script>window.location='add_ss.php?invaliddistrict';</script>";
		exit;
	}
	$matchedTaluk = null;
	foreach ($matchedDistrict['taluks'] as $t) {
		if ((int)$t['id'] === $taluk_node_id) { $matchedTaluk = $t; break; }
	}
	if (!$matchedTaluk) {
		echo "<script>window.location='add_ss.php?invaliddistrict';</script>";
		exit;
	}

	$state_node_id  = (int)$matchedDistrict['state_id'];
	$state_name     = RemoveSpecialChar($matchedDistrict['state_name']);
	$district_name  = RemoveSpecialChar($matchedDistrict['name']);
	$taluk_name     = RemoveSpecialChar($matchedTaluk['name']);

	$pincode=$_POST["pincode"];
	$pincode=RemoveSpecialChar($pincode);
	
	$country_code=$_POST["country_code"];
	$mobile_number=str_replace("'","&#39;",$_POST["mobile_number"]);
	$landline=str_replace("'","&#39;",$_POST["landline"]);
	
	$email=str_replace("'","&#39;",$_POST["email"]);
	$email=RemoveSpecialChar($email);
	
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$gstin=RemoveSpecialChar($gstin);
	
	$address=str_replace("'","&#39;",$_POST["address"]);
	$address=RemoveSpecialChar($address);
	
	$google_location=str_replace("'","&#39;",$_POST["google_location"]);

	$latitude=isset($_POST["latitude"]) && $_POST["latitude"]!=='' ? floatval($_POST["latitude"]) : null;
	$longitude=isset($_POST["longitude"]) && $_POST["longitude"]!=='' ? floatval($_POST["longitude"]) : null;
	$latitude_sql=$latitude===null ? "NULL" : "'".$latitude."'";
	$longitude_sql=$longitude===null ? "NULL" : "'".$longitude."'";

	// Same shop re-added under a different DM (or the same DM typed it again)
	// with just the capitalisation/spacing changed — match address, mobile,
	// taluk, district and pincode all case-insensitive/trimmed, not just
	// mobile_number+ms_id, so this actually catches cross-DM duplicates.
	// A genuine location correction for a shop that's really already in the
	// system goes through the Location Change Request flow instead of a
	// second "Add Shop", so it stays tied to the one real shop record.
	$select_dup="select id from ms_shop where LOWER(TRIM(address))=LOWER(TRIM('$address')) and LOWER(TRIM(mobile_number))=LOWER(TRIM('$mobile_number')) and LOWER(TRIM(taluk_name))=LOWER(TRIM('$taluk_name')) and LOWER(TRIM(district_name))=LOWER(TRIM('$district_name')) and LOWER(TRIM(pincode))=LOWER(TRIM('$pincode')) limit 1";
	$fetch_dup=mysqli_query($db_conn,$select_dup);
	$dupRow=$fetch_dup ? mysqli_fetch_assoc($fetch_dup) : null;
	if($dupRow)
	{
		echo "<script>window.location='add_ss.php?distalready&existingshop=".base64_encode($dupRow['id'])."';</script>";
		exit;
	}

	{

    //upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
	   $file_extension = pathinfo($_FILES['user_icon']['name'], PATHINFO_EXTENSION);
		$rand_isd=bin2hex(random_bytes(64));
	    $filename=$rand_isd . '.' . $file_extension;
		
                 $uploaddir='shop_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	}else{$uploadfile="Nil";}
	
	
        $sql="insert into ms_shop (ms_id,user_icon,name,state_name,
		district_name,taluk_name,state_node_id,district_node_id,taluk_node_id,pincode,email,mobile_number,
		gstin,address,shop_cat,country_code,landline,google_location,latitude,longitude) values
		('$ms_id','$uploadfile','$name','$state_name','$district_name','$taluk_name',
		'$state_node_id','$district_node_id','$taluk_node_id',
		'$pincode','$email','$mobile_number','$gstin','$address','$shop_cat',
		'$country_code','$landline','$google_location',$latitude_sql,$longitude_sql)";
		mysqli_query($db_conn,$sql);
		
		echo "<script>window.location='".$viewurl."';</script>";
	}

}
?>