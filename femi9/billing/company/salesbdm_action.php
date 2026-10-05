<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('salesbdm_manage_edit');
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(1);
ini_set('display_errors','1');

//--------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------

//insert sales bdm details
if(isset($_REQUEST['add-salesbdm']))
{
	$country_code=$_POST["country_code"];

	$bdm_name=str_replace("'","&#39;",$_REQUEST['bdm_name']);
	$bdm_name = RemoveSpecialChar($bdm_name);

	$bdm_mobile=str_replace("'","&#39;",$_REQUEST['bdm_mobile']);
	$bdm_mobile = RemoveSpecialChar($bdm_mobile);

	$bdm_email=str_replace("'","&#39;",$_REQUEST['bdm_email']);
	$bdm_email = RemoveSpecialChar($bdm_email);

	$bdm_address=str_replace("'","&#39;",$_REQUEST['bdm_address']);
	$bdm_address = RemoveSpecialChar($bdm_address);
	$team_level_id = !empty($_REQUEST['team_level_id']) ? (int)$_REQUEST['team_level_id'] : 0;
	$manager_id = !empty($_REQUEST['manager_id']) ? (int)$_REQUEST['manager_id'] : 0;
	$monthly_target_amount = ($_REQUEST['monthly_target_amount'] !== '' && isset($_REQUEST['monthly_target_amount'])) ? (float)$_REQUEST['monthly_target_amount'] : null;

	$zone=str_replace("'","&#39;",$_REQUEST['zone'] ?? '');
	$zone = RemoveSpecialChar($zone);

	$espo_user_id = $_POST['espo_user_id'] ?? null;
	$espo_user_id = ($espo_user_id === '' || $espo_user_id === null) ? null : $espo_user_id;
	// Strict allowlist rather than escaping: this value round-trips into a
	// second (remote) EspoCRM database via espoUserFilterClause(), so make the
	// column structurally incapable of holding anything hostile regardless of
	// how it's later interpolated.
	$espo_user_id = preg_match('/^[A-Za-z0-9]{1,24}$/', (string)$espo_user_id) ? $espo_user_id : null;

	// Every Sales BDM gets the same default password on creation ("salesbdm@123")
	// rather than a random one — matches how Marketing Staff onboarding works.
	$password="salesbdm@123";

	$db_conn->query("CREATE TABLE IF NOT EXISTS sales_bdm_staff (
		id INT AUTO_INCREMENT PRIMARY KEY,
		bdm_name VARCHAR(255) NOT NULL,
		bdm_mobile VARCHAR(255) NOT NULL,
		password VARCHAR(255) NOT NULL,
		bdm_email VARCHAR(255) NULL DEFAULT NULL,
		bdm_address VARCHAR(255) NULL DEFAULT NULL,
		country_code VARCHAR(255) NULL DEFAULT NULL,
		account_status VARCHAR(255) NOT NULL DEFAULT 'active',
		user_position INT NULL DEFAULT NULL,
		team_level_id INT NULL DEFAULT NULL,
		manager_id INT NULL DEFAULT NULL,
		monthly_target_amount DECIMAL(12,2) NULL DEFAULT NULL,
		last_login TIMESTAMP NULL DEFAULT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		UNIQUE KEY uk_bdm_mobile (bdm_mobile)
	)");
	$_chkTgt = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'monthly_target_amount'");
	if ($_chkTgt && $_chkTgt->num_rows === 0) {
		$db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN monthly_target_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER manager_id");
	}
	$_chkZone = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'zone'");
	if ($_chkZone && $_chkZone->num_rows === 0) {
		$db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN zone VARCHAR(100) NULL DEFAULT NULL AFTER monthly_target_amount");
	}
	$_chkEspo = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'espo_user_id'");
	if ($_chkEspo && $_chkEspo->num_rows === 0) {
		$db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN espo_user_id VARCHAR(24) NULL DEFAULT NULL AFTER monthly_target_amount");
	}

	$select_count_product="select count(*) as numProducts from sales_bdm_staff where bdm_mobile='$bdm_mobile'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numProducts']==0)
	{
		$insert_products="insert into sales_bdm_staff (bdm_name,bdm_mobile,password,bdm_email,bdm_address,country_code,account_status,team_level_id,manager_id,monthly_target_amount,zone,espo_user_id)
		values ('$bdm_name','$bdm_mobile','$password','$bdm_email','$bdm_address',
		'$country_code','active',".($team_level_id > 0 ? $team_level_id : "NULL").",".($manager_id > 0 ? $manager_id : "NULL").",".($monthly_target_amount !== null ? $monthly_target_amount : "NULL").",'$zone',".($espo_user_id !== null ? "'$espo_user_id'" : "NULL").")";
		mysqli_query($db_conn,$insert_products);
		$new_bdm_id = mysqli_insert_id($db_conn);

		// District assignment is now entirely Zone-derived (see $zone above
		// and salesbdm/include/BdmTpScope.php) — both the old manual "Assign
		// Location" picker and the "Dual Role" picker were removed from
		// Add/Edit Sales BDM 2026-10-02, so there's nothing left to write
		// into salesbdm_locations from this form.

		echo "<script>window.location='salesbdm_manage?addesuccess';</script>";
		exit;
	}else{

		echo "<script>window.location='salesbdm_add?alreadyexists';</script>";
		exit;
	}
}


//update sales bdm details
if(isset($_REQUEST['update-salesbdm']))
{
	$update_id=$_REQUEST['update_id'];

	$country_code=$_POST["country_code"];

	$bdm_name=str_replace("'","&#39;",$_REQUEST['bdm_name']);
	$bdm_name = RemoveSpecialChar($bdm_name);

	$bdm_email=str_replace("'","&#39;",$_REQUEST['bdm_email']);
	$bdm_email = RemoveSpecialChar($bdm_email);

	$bdm_address=str_replace("'","&#39;",$_REQUEST['bdm_address']);
	$bdm_address = RemoveSpecialChar($bdm_address);
	$team_level_id = !empty($_REQUEST['team_level_id']) ? (int)$_REQUEST['team_level_id'] : 0;
	$manager_id = !empty($_REQUEST['manager_id']) ? (int)$_REQUEST['manager_id'] : 0;
	if ($manager_id === (int)$update_id) { $manager_id = 0; }
	$monthly_target_amount = ($_REQUEST['monthly_target_amount'] !== '' && isset($_REQUEST['monthly_target_amount'])) ? (float)$_REQUEST['monthly_target_amount'] : null;

	$zone=str_replace("'","&#39;",$_REQUEST['zone'] ?? '');
	$zone = RemoveSpecialChar($zone);

	// Zone is now the ONLY source of a BDM's personal district assignment
	// (see salesbdm/include/BdmTpScope.php's getBdmAssignedDistrictNames())
	// — the old manual "Assign Location" picker is gone, so a BDM left
	// without a Zone would see no TPs/reports at all. The form's own
	// <select required> already blocks this client-side; this is just the
	// authoritative re-check.
	if ($zone === '') {
		echo "<script>window.location='salesbdm_edit?prid=" . urlencode(base64_encode($update_id)) . "&error=zone_required';</script>";
		exit;
	}

	$espo_user_id = $_POST['espo_user_id'] ?? null;
	$espo_user_id = ($espo_user_id === '' || $espo_user_id === null) ? null : $espo_user_id;
	// Strict allowlist rather than escaping: this value round-trips into a
	// second (remote) EspoCRM database via espoUserFilterClause(), so make the
	// column structurally incapable of holding anything hostile regardless of
	// how it's later interpolated.
	$espo_user_id = preg_match('/^[A-Za-z0-9]{1,24}$/', (string)$espo_user_id) ? $espo_user_id : null;

	$_chkTL = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'team_level_id'");
	if ($_chkTL && $_chkTL->num_rows === 0) {
		$db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
	}
	$_chkMgr = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'manager_id'");
	if ($_chkMgr && $_chkMgr->num_rows === 0) {
		$db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
	}
	$_chkTgt = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'monthly_target_amount'");
	if ($_chkTgt && $_chkTgt->num_rows === 0) {
		$db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN monthly_target_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER manager_id");
	}
	$_chkZone = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'zone'");
	if ($_chkZone && $_chkZone->num_rows === 0) {
		$db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN zone VARCHAR(100) NULL DEFAULT NULL AFTER monthly_target_amount");
	}
	$_chkEspo = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'espo_user_id'");
	if ($_chkEspo && $_chkEspo->num_rows === 0) {
		$db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN espo_user_id VARCHAR(24) NULL DEFAULT NULL AFTER monthly_target_amount");
	}

	$update_products="update sales_bdm_staff set bdm_name='$bdm_name',bdm_email='$bdm_email',
	bdm_address='$bdm_address',country_code='$country_code',team_level_id=".($team_level_id > 0 ? $team_level_id : "NULL").",manager_id=".($manager_id > 0 ? $manager_id : "NULL").",monthly_target_amount=".($monthly_target_amount !== null ? $monthly_target_amount : "NULL").",zone='$zone',espo_user_id=".($espo_user_id !== null ? "'$espo_user_id'" : "NULL")." where id='$update_id'";
	mysqli_query($db_conn,$update_products);

	// District assignment is now entirely Zone-derived (see $zone above and
	// salesbdm/include/BdmTpScope.php) — both the old manual "Assign
	// Location" picker and the "Dual Role" picker were removed from
	// Add/Edit Sales BDM 2026-10-02, so there's nothing left to write into
	// salesbdm_locations from this form (any pre-existing rows for this BDM
	// are simply left untouched rather than wiped, since they're no longer
	// read by anything).

	echo "<script>window.location='salesbdm_manage?updatedSuccess';</script>";
	exit;

}

?>
