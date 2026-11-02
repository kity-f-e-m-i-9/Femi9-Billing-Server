<?php require_once __DIR__ . '/../shared/PasswordService.php'; require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('ms_edit');
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(1);
ini_set('display_errors','1');

// Ancestor (manager, manager's manager, ...) ids for $ms_id — same walk as
// get-ms-chain.php, just ids only. Used so the "exclusive assignment" steal
// below never evicts a person's own manager chain from a shared location.
function msManagerChainIds($db_conn, int $ms_id): array {
	$ids = [];
	$current_id = $ms_id;
	$visited = [$ms_id => true];
	$stmt = $db_conn->prepare("SELECT manager_id FROM marketing_staff WHERE id = ?");
	while ($current_id && count($ids) < 20) {
		$stmt->bind_param("i", $current_id);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		if (!$row || !$row['manager_id']) break;
		$current_id = (int)$row['manager_id'];
		if (isset($visited[$current_id])) break;
		$visited[$current_id] = true;
		$ids[] = $current_id;
	}
	$stmt->close();
	return $ids;
}

// Every staff member anywhere below $ms_id in the reporting chain (not just
// direct reports) — same BFS marketing/dashboard.php already uses to collect
// a person's DMs, generalized to any depth. Used alongside
// msManagerChainIds() so updating a DM's own locations never evicts a Firka-
// level staffer under THAT DM who legitimately shares the same district.
function msDescendantIds($db_conn, int $ms_id): array {
	$byManager = [];
	$res = $db_conn->query("SELECT id, manager_id FROM marketing_staff WHERE deleted_at IS NULL");
	while ($row = $res->fetch_assoc()) {
		$byManager[$row['manager_id'] ? (int)$row['manager_id'] : 0][] = (int)$row['id'];
	}
	$ids = [];
	$queue = [$ms_id];
	while (!empty($queue)) {
		$cur = array_shift($queue);
		foreach ($byManager[$cur] ?? [] as $childId) {
			$ids[] = $childId;
			$queue[] = $childId;
		}
	}
	return $ids;
}

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
	$monthly_target_amount = ($_REQUEST['monthly_target_amount'] !== '' && isset($_REQUEST['monthly_target_amount'])) ? (float)$_REQUEST['monthly_target_amount'] : null;

	$password="12345678";

	$_chkTL = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'team_level_id'");
	if ($_chkTL && $_chkTL->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
	}
	$_chkMgr = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'manager_id'");
	if ($_chkMgr && $_chkMgr->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
	}
	$_chkTgt = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'monthly_target_amount'");
	if ($_chkTgt && $_chkTgt->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN monthly_target_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER manager_id");
	}

	$select_count_product="select count(*) as numProducts from marketing_staff where ms_mobile=?";
	$fetch_count_product=db_q($db_conn,$select_count_product,[$ms_mobile]);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numProducts']==0)
	{
		$insert_products="insert into marketing_staff (ms_name,ms_mobile,password,ms_email,ms_address,country_code,account_status,team_level_id,manager_id,monthly_target_amount)
		values ('$ms_name','$ms_mobile','".db_esc(femi_password_hash((string)($password)))."','$ms_email','$ms_address',
		'".db_esc($country_code)."','active',".($team_level_id > 0 ? $team_level_id : "NULL").",".($manager_id > 0 ? $manager_id : "NULL").",".($monthly_target_amount !== null ? $monthly_target_amount : "NULL").")";
		mysqli_query($db_conn,$insert_products);
		femi_flag_must_change($db_conn, 'marketing', (string)$ms_mobile); // starts on the shared default password: force a change at first login
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
			$assigned_loc_ids = [];
			foreach ($_POST['location_ids'] as $loc_id) {
				$loc_id = (int)$loc_id;
				if ($loc_id > 0) {
					$stmt_loc->bind_param('ii', $new_ms_id, $loc_id);
					$stmt_loc->execute();
					$assigned_loc_ids[] = $loc_id;
				}
			}
			$stmt_loc->close();

			// Exclusive assignment: a district/division just given to this
			// person (e.g. via "Fill from Zone" on Add Marketing Staff) is
			// taken away from whoever else held it, instead of two staff
			// quietly holding the same location at once — per request
			// 2026-10-20, so a Zone-filled location always lands with
			// exactly one owner. "Whoever else" excludes this person's own
			// manager chain (ancestors): an ASM and the DM reporting to them
			// are SUPPOSED to both hold the same district at once — that's
			// how get-ms-location-options.php offers a DM only districts
			// already within their ASM's own set, and how the dashboard
			// rolls an ASM's own card up to cover their team (see
			// marketing/dashboard.php's $teamScopeIds) — so stripping the
			// manager's row here was a regression: every time a brand-new DM
			// got the same district their ASM already had, the ASM silently
			// lost it (reported 2026-10-31, Hariprakash P.'s ASM dashboard).
			if (!empty($assigned_loc_ids)) {
				$protected_ids = msManagerChainIds($db_conn, $new_ms_id);
				$protected_ids[] = $new_ms_id;
				$loc_ph  = implode(',', array_fill(0, count($assigned_loc_ids), '?'));
				$prot_ph = implode(',', array_fill(0, count($protected_ids), '?'));
				$stmt_reassign = $db_conn->prepare(
					"DELETE FROM marketing_staff_locations WHERE location_id IN ($loc_ph) AND ms_id NOT IN ($prot_ph)"
				);
				$types = str_repeat('i', count($assigned_loc_ids)) . str_repeat('i', count($protected_ids));
				$stmt_reassign->bind_param($types, ...array_merge($assigned_loc_ids, $protected_ids));
				$stmt_reassign->execute();
				$stmt_reassign->close();
			}
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
	$monthly_target_amount = ($_REQUEST['monthly_target_amount'] !== '' && isset($_REQUEST['monthly_target_amount'])) ? (float)$_REQUEST['monthly_target_amount'] : null;
	$skip_location_check = !empty($_REQUEST['skip_location_check']) ? 1 : 0;

	$_chkTL = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'team_level_id'");
	if ($_chkTL && $_chkTL->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
	}
	$_chkMgr = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'manager_id'");
	if ($_chkMgr && $_chkMgr->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
	}
	$_chkTgt = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'monthly_target_amount'");
	if ($_chkTgt && $_chkTgt->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN monthly_target_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER manager_id");
	}
	$_chkLoc = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'skip_location_check'");
	if ($_chkLoc && $_chkLoc->num_rows === 0) {
		$db_conn->query("ALTER TABLE marketing_staff ADD COLUMN skip_location_check TINYINT(1) NOT NULL DEFAULT 0 AFTER monthly_target_amount");
	}

	$update_products="update marketing_staff set ms_name='$ms_name',ms_email='$ms_email',
	ms_address='$ms_address',country_code='".db_esc($country_code)."',team_level_id=".($team_level_id > 0 ? $team_level_id : "NULL").",manager_id=".($manager_id > 0 ? $manager_id : "NULL").",monthly_target_amount=".($monthly_target_amount !== null ? $monthly_target_amount : "NULL").",skip_location_check=".$skip_location_check." where id='".db_esc($update_id)."'";
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
			$assigned_loc_ids = [];
			foreach ($_POST['location_ids'] as $loc_id) {
				$loc_id = (int)$loc_id;
				if ($loc_id > 0) {
					$stmt_loc->bind_param('ii', $update_id_int, $loc_id);
					$stmt_loc->execute();
					$assigned_loc_ids[] = $loc_id;
				}
			}
			$stmt_loc->close();

			// Exclusive assignment — see the matching comment in the
			// add-customer branch above. Re-saving this person's locations
			// (e.g. after "Fill from Zone" pulled in a district someone else
			// already held) takes it away from that other person instead of
			// leaving both holding it — except this person's own manager
			// chain AND their own reporting subtree, who are allowed (and
			// expected) to share the same district with them.
			if (!empty($assigned_loc_ids)) {
				$protected_ids   = msManagerChainIds($db_conn, $update_id_int);
				$protected_ids   = array_merge($protected_ids, msDescendantIds($db_conn, $update_id_int));
				$protected_ids[] = $update_id_int;
				$loc_ph  = implode(',', array_fill(0, count($assigned_loc_ids), '?'));
				$prot_ph = implode(',', array_fill(0, count($protected_ids), '?'));
				$stmt_reassign = $db_conn->prepare(
					"DELETE FROM marketing_staff_locations WHERE location_id IN ($loc_ph) AND ms_id NOT IN ($prot_ph)"
				);
				$types = str_repeat('i', count($assigned_loc_ids)) . str_repeat('i', count($protected_ids));
				$stmt_reassign->bind_param($types, ...array_merge($assigned_loc_ids, $protected_ids));
				$stmt_reassign->execute();
				$stmt_reassign->close();
			}
		}

		echo "<script>window.location='ms_manage?updatedSuccess';</script>";
		exit;
	
}

?>