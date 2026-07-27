<?php include("checksession.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	
		$addurl="add_ss.php?distalready";
		$viewurl="manage_ss.php?addedsuccess";
	
	$ms_id=$_POST["ms_id"];
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

	$select_count_dist="select count(*) as numShop from ms_shop where mobile_number='$mobile_number' and ms_id='$ms_id'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numShop']==0)
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
		district_name,taluk_name,pincode,email,mobile_number,
		gstin,address,shop_cat,country_code,landline,google_location,latitude,longitude) values
		('$ms_id','$uploadfile','$name','$state_name','$district_name','$taluk_name',
		'$pincode','$email','$mobile_number','$gstin','$address','$shop_cat',
		'$country_code','$landline','$google_location',$latitude_sql,$longitude_sql)";
		mysqli_query($db_conn,$sql);
		
		echo "<script>window.location='".$viewurl."';</script>";


}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='".$addurl."';</script>";
	}
	
	
	
}
?>