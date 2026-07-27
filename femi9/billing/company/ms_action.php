<?php include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(1);
ini_set('display_errors','1');

//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------

//insert customer details
if(isset($_REQUEST['add-customer']))
{
	$country_code=$_POST["country_code"];
	
	$ms_name=str_replace("'","&#39;",$_REQUEST['ms_name']);
	$ms_name = RemoveSpecialChar($ms_name);
	
	$ms_mobile=str_replace("'","&#39;",$_REQUEST['ms_mobile']);
	$ms_mobile = RemoveSpecialChar($ms_mobile);
	
	$ms_email=str_replace("'","&#39;",$_REQUEST['ms_email']);
	$ms_email = RemoveSpecialChar($ms_email);
	
	$ms_address=str_replace("'","&#39;",$_REQUEST['ms_address']);
	$ms_address = RemoveSpecialChar($ms_address);
	$user_position = isset($_REQUEST['user_position']) ? 1 : 0;
	
	$password="12345678";
	
	$select_count_product="select count(*) as numProducts from marketing_staff where ms_mobile='$ms_mobile'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numProducts']==0)
	{
		$insert_products="insert into marketing_staff (ms_name,ms_mobile,password,ms_email,ms_address,country_code,account_status,user_position)
		values ('$ms_name','$ms_mobile','$password','$ms_email','$ms_address',
		'$country_code','active','$user_position')";
		mysqli_query($db_conn,$insert_products);
		$new_ms_id = mysqli_insert_id($db_conn);

		if ($new_ms_id && !empty($_POST['location_ids']) && is_array($_POST['location_ids'])) {
			$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_staff_locations (
				id INT AUTO_INCREMENT PRIMARY KEY,
				ms_id INT NOT NULL,
				location_id INT NOT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				UNIQUE KEY uk_ms_location (ms_id, location_id)
			)");
			$stmt_loc = $db_conn->prepare("INSERT IGNORE INTO marketing_staff_locations (ms_id, location_id) VALUES (?, ?)");
			foreach ($_POST['location_ids'] as $loc_id) {
				$loc_id = (int)$loc_id;
				if ($loc_id > 0) {
					$stmt_loc->bind_param('ii', $new_ms_id, $loc_id);
					$stmt_loc->execute();
				}
			}
			$stmt_loc->close();
		}

		echo "<script>window.location='ms_manage?addesuccess';</script>";
		exit;
	}else{
		
		echo "<script>window.location='ms_add?alreadyexists';</script>";
		exit;
	}
}
	
	
//update customer details
if(isset($_REQUEST['update-customer']))
{
	$update_id=$_REQUEST['update_id'];
	
	$country_code=$_POST["country_code"];
	
	$ms_name=str_replace("'","&#39;",$_REQUEST['ms_name']);
	$ms_name = RemoveSpecialChar($ms_name);
	
	$ms_email=str_replace("'","&#39;",$_REQUEST['ms_email']);
	$ms_email = RemoveSpecialChar($ms_email);
	
	$ms_address=str_replace("'","&#39;",$_REQUEST['ms_address']);
	$ms_address = RemoveSpecialChar($ms_address);
	$user_position = isset($_REQUEST['user_position']) ? 1 : 0;
	
	$update_products="update marketing_staff set ms_name='$ms_name',ms_email='$ms_email',
	ms_address='$ms_address',country_code='$country_code',user_position='$user_position' where id='$update_id'";
	mysqli_query($db_conn,$update_products);

		$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_staff_locations (
			id INT AUTO_INCREMENT PRIMARY KEY,
			ms_id INT NOT NULL,
			location_id INT NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uk_ms_location (ms_id, location_id)
		)");
		$update_id_int = (int)$update_id;
		$stmt_del_loc = $db_conn->prepare("DELETE FROM marketing_staff_locations WHERE ms_id=?");
		$stmt_del_loc->bind_param('i', $update_id_int);
		$stmt_del_loc->execute();
		$stmt_del_loc->close();
		if (!empty($_POST['location_ids']) && is_array($_POST['location_ids'])) {
			$stmt_loc = $db_conn->prepare("INSERT IGNORE INTO marketing_staff_locations (ms_id, location_id) VALUES (?, ?)");
			foreach ($_POST['location_ids'] as $loc_id) {
				$loc_id = (int)$loc_id;
				if ($loc_id > 0) {
					$stmt_loc->bind_param('ii', $update_id_int, $loc_id);
					$stmt_loc->execute();
				}
			}
			$stmt_loc->close();
		}

		echo "<script>window.location='ms_manage?updatedSuccess';</script>";
		exit;
	
}

?>