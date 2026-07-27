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
	$team_level_id = !empty($_REQUEST['team_level_id']) ? (int)$_REQUEST['team_level_id'] : 0;
	$manager_id = !empty($_REQUEST['manager_id']) ? (int)$_REQUEST['manager_id'] : 0;

	$password="12345678";

	$_chkTL = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'team_level_id'");
	if ($_chkTL && $_chkTL->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
	}
	$_chkMgr = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'manager_id'");
	if ($_chkMgr && $_chkMgr->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
	}

	$select_count_product="select count(*) as numProducts from marketing_staff where ms_mobile='$ms_mobile'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numProducts']==0)
	{
		$insert_products="insert into marketing_staff (ms_name,ms_mobile,password,ms_email,ms_address,country_code,account_status,team_level_id,manager_id)
		values ('$ms_name','$ms_mobile','$password','$ms_email','$ms_address',
		'$country_code','active',".($team_level_id > 0 ? $team_level_id : "NULL").",".($manager_id > 0 ? $manager_id : "NULL").")";
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
	$team_level_id = !empty($_REQUEST['team_level_id']) ? (int)$_REQUEST['team_level_id'] : 0;
	$manager_id = !empty($_REQUEST['manager_id']) ? (int)$_REQUEST['manager_id'] : 0;
	if ($manager_id === (int)$update_id) { $manager_id = 0; }

	$_chkTL = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'team_level_id'");
	if ($_chkTL && $_chkTL->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
	}
	$_chkMgr = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'manager_id'");
	if ($_chkMgr && $_chkMgr->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
	}

	$update_products="update marketing_staff set ms_name='$ms_name',ms_email='$ms_email',
	ms_address='$ms_address',country_code='$country_code',team_level_id=".($team_level_id > 0 ? $team_level_id : "NULL").",manager_id=".($manager_id > 0 ? $manager_id : "NULL")." where id='$update_id'";
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