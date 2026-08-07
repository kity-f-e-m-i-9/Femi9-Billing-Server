<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
error_reporting(0);

// CSRF check
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header("Location: manage-tp");
    exit;
}

function filterLocationIdsForBdm($db_conn, int $bdmId, array $location_ids): array {
    return array_values(array_filter($location_ids, function ($lid) use ($db_conn, $bdmId) {
        return isLocationInBdmDistricts($db_conn, $bdmId, (int)$lid);
    }));
}

$action = $_POST['action'] ?? '';

// ── INSERT ───────────────────────────────────────────────────────────────────
if ($action === 'insert-tp') {

    $name                = trim($_POST['tp_name']                ?? '');
    $company_name        = trim($_POST['tp_company_name']        ?? '');
    $referral_id         = trim($_POST['tp_referral_id']         ?? '') ?: null;
    $referral_type       = trim($_POST['tp_referral_type']       ?? '') ?: null;
    $referral_percentage = strlen(trim($_POST['tp_referral_percentage'] ?? '')) ? (float)$_POST['tp_referral_percentage'] : null;
    $mobile              = trim($_POST['tp_mobile']              ?? '');
    $email               = trim($_POST['tp_email']               ?? '') ?: null;
    $gstin               = trim($_POST['tp_gstin']               ?? '') ?: null;
    $branch_line1      = trim($_POST['tp_branch_line1']     ?? '');
    $branch_line2      = trim($_POST['tp_branch_line2']     ?? '') ?: null;
    $branch_city       = trim($_POST['tp_branch_city']      ?? '') ?: null;
    $branch_district   = trim($_POST['tp_branch_district']  ?? '') ?: null;
    $branch_state      = trim($_POST['tp_branch_state']     ?? '') ?: null;
    $branch_country    = trim($_POST['tp_branch_country']   ?? '') ?: null;
    $branch_pincode    = trim($_POST['tp_branch_pincode']   ?? '') ?: null;
    $delivery_line1    = trim($_POST['tp_delivery_line1']   ?? '');
    $delivery_line2    = trim($_POST['tp_delivery_line2']   ?? '') ?: null;
    $delivery_city     = trim($_POST['tp_delivery_city']    ?? '') ?: null;
    $delivery_district = trim($_POST['tp_delivery_district']?? '') ?: null;
    $delivery_state    = trim($_POST['tp_delivery_state']   ?? '') ?: null;
    $delivery_country  = trim($_POST['tp_delivery_country'] ?? '') ?: null;
    $delivery_pincode  = trim($_POST['tp_delivery_pincode'] ?? '') ?: null;
    $is_active         = (int)($_POST['tp_active'] ?? 1);
    $raw_password      = trim($_POST['tp_password'] ?? '') ?: 'Partner@123';
    $password_hash     = password_hash($raw_password, PASSWORD_DEFAULT);
    $created_by        = $_SESSION['LOGIN_USER'] ?? '';
    $location_ids      = array_filter(array_map('intval', $_POST['location_ids'] ?? []));

    if (!$name || !$company_name || !$mobile || !$branch_line1 || !$delivery_line1) {
        header("Location: add-tp?error=1");
        exit;
    }

    // Defense in depth — the picker only offers locations inside this BDM's
    // own districts, but re-validate server-side against a tampered request.
    if (!empty($location_ids)) {
        $location_ids = filterLocationIdsForBdm($db_conn, (int)$salesBdmID, $location_ids);
    }

    $chk = $db_conn->prepare("SELECT id FROM territory_partners WHERE mobile = ?");
    $chk->bind_param("s", $mobile);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        header("Location: add-tp?mobiletaken=1&tp_name=" . urlencode($name) .
               "&tp_email=" . urlencode($email ?? '') .
               "&tp_gstin=" . urlencode($gstin ?? '') .
               "&tp_mobile=" . urlencode($mobile));
        exit;
    }
    $chk->close();

    $photo = null;
    if (!empty($_FILES['tp_photo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['tp_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed) && $_FILES['tp_photo']['size'] <= 2097152) {
            $filename = 'tp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest     = __DIR__ . '/../company/tp_photo/' . $filename;
            if (is_dir(__DIR__ . '/../company/tp_photo') && move_uploaded_file($_FILES['tp_photo']['tmp_name'], $dest)) {
                $photo = $filename;
            }
        }
    }

    $db_conn->begin_transaction();
    try {
        $db_conn->query("SELECT last_val FROM tp_id_sequence WHERE id = 1 FOR UPDATE");
        $db_conn->query("UPDATE tp_id_sequence SET last_val = last_val + 1 WHERE id = 1");
        $seq_res = $db_conn->query("SELECT last_val FROM tp_id_sequence WHERE id = 1");
        $next_num = (int)$seq_res->fetch_assoc()['last_val'];
        $tp_id = 'TP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        $stmt = $db_conn->prepare("
            INSERT INTO territory_partners
                (tp_id, name, company_name, referral_id, referral_type, referral_percentage, mobile, email, gstin,
                 branch_line1, branch_line2, branch_city, branch_district, branch_state, branch_country, branch_pincode,
                 delivery_line1, delivery_line2, delivery_city, delivery_district, delivery_state, delivery_country, delivery_pincode,
                 photo, is_active, password, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssdssssssssssssssssssiss",
            $tp_id, $name, $company_name, $referral_id, $referral_type, $referral_percentage, $mobile, $email, $gstin,
            $branch_line1, $branch_line2, $branch_city, $branch_district, $branch_state, $branch_country, $branch_pincode,
            $delivery_line1, $delivery_line2, $delivery_city, $delivery_district, $delivery_state, $delivery_country, $delivery_pincode,
            $photo, $is_active, $password_hash, $created_by
        );
        $stmt->execute();
        $new_id = $db_conn->insert_id;
        $stmt->close();

        if (!empty($location_ids)) {
            $stmt_loc = $db_conn->prepare("INSERT IGNORE INTO territory_partner_locations (territory_partner_id, location_id) VALUES (?, ?)");
            foreach ($location_ids as $lid) {
                $stmt_loc->bind_param("ii", $new_id, $lid);
                $stmt_loc->execute();
            }
            $stmt_loc->close();
        }

        $db_conn->commit();
        header("Location: manage-tp?addesuccess=1");
        exit;

    } catch (Exception $e) {
        $db_conn->rollback();
        header("Location: add-tp?error=1");
        exit;
    }
}

// ── UPDATE ───────────────────────────────────────────────────────────────────
if ($action === 'update-tp') {

    $tp_id_raw = $_POST['tp_db_id'] ?? '';
    $tp_db_id  = (int)base64_decode($tp_id_raw);

    // Ownership check — only allow editing a TP that's actually inside this
    // BDM's own districts, regardless of what tp_db_id a tampered request sends.
    $myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
    if (!$tp_db_id || !in_array($tp_db_id, $myTpIds, true)) {
        header("Location: manage-tp?error=1");
        exit;
    }

    $name                = trim($_POST['tp_name']                ?? '');
    $company_name        = trim($_POST['tp_company_name']        ?? '');
    $referral_id         = trim($_POST['tp_referral_id']         ?? '') ?: null;
    $referral_type       = trim($_POST['tp_referral_type']       ?? '') ?: null;
    $referral_percentage = strlen(trim($_POST['tp_referral_percentage'] ?? '')) ? (float)$_POST['tp_referral_percentage'] : null;
    $mobile              = trim($_POST['tp_mobile']              ?? '');
    $email               = trim($_POST['tp_email']               ?? '') ?: null;
    $gstin               = trim($_POST['tp_gstin']               ?? '') ?: null;
    $branch_line1      = trim($_POST['tp_branch_line1']     ?? '');
    $branch_line2      = trim($_POST['tp_branch_line2']     ?? '') ?: null;
    $branch_city       = trim($_POST['tp_branch_city']      ?? '') ?: null;
    $branch_district   = trim($_POST['tp_branch_district']  ?? '') ?: null;
    $branch_state      = trim($_POST['tp_branch_state']     ?? '') ?: null;
    $branch_country    = trim($_POST['tp_branch_country']   ?? '') ?: null;
    $branch_pincode    = trim($_POST['tp_branch_pincode']   ?? '') ?: null;
    $delivery_line1    = trim($_POST['tp_delivery_line1']   ?? '');
    $delivery_line2    = trim($_POST['tp_delivery_line2']   ?? '') ?: null;
    $delivery_city     = trim($_POST['tp_delivery_city']    ?? '') ?: null;
    $delivery_district = trim($_POST['tp_delivery_district']?? '') ?: null;
    $delivery_state    = trim($_POST['tp_delivery_state']   ?? '') ?: null;
    $delivery_country  = trim($_POST['tp_delivery_country'] ?? '') ?: null;
    $delivery_pincode  = trim($_POST['tp_delivery_pincode'] ?? '') ?: null;
    $is_active         = (int)($_POST['tp_active'] ?? 1);
    $created_by        = $_SESSION['LOGIN_USER'] ?? '';
    $location_ids      = array_filter(array_map('intval', $_POST['location_ids'] ?? []));

    if (!$name || !$company_name || !$mobile || !$branch_line1 || !$delivery_line1) {
        header("Location: manage-tp?error=1");
        exit;
    }

    if (!empty($location_ids)) {
        $location_ids = filterLocationIdsForBdm($db_conn, (int)$salesBdmID, $location_ids);
    }

    $chk = $db_conn->prepare("SELECT id FROM territory_partners WHERE mobile = ? AND id != ?");
    $chk->bind_param("si", $mobile, $tp_db_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        header("Location: edit-tp?tpid=" . urlencode($tp_id_raw) . "&mobiletaken=1");
        exit;
    }
    $chk->close();

    $old_photo_res = $db_conn->prepare("SELECT photo FROM territory_partners WHERE id = ?");
    $old_photo_res->bind_param("i", $tp_db_id);
    $old_photo_res->execute();
    $old_photo = $old_photo_res->get_result()->fetch_assoc()['photo'] ?? null;
    $old_photo_res->close();

    $photo_update = '';
    if (!empty($_FILES['tp_photo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['tp_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed) && $_FILES['tp_photo']['size'] <= 2097152) {
            $filename = 'tp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest     = __DIR__ . '/../company/tp_photo/' . $filename;
            if (is_dir(__DIR__ . '/../company/tp_photo') && move_uploaded_file($_FILES['tp_photo']['tmp_name'], $dest)) {
                $photo_update = $filename;
            }
        }
    }

    $db_conn->begin_transaction();
    try {
        if ($photo_update) {
            $stmt = $db_conn->prepare("
                UPDATE territory_partners
                SET name=?, company_name=?, referral_id=?, referral_type=?, referral_percentage=?, mobile=?, email=?, gstin=?,
                    branch_line1=?, branch_line2=?, branch_city=?, branch_district=?, branch_state=?, branch_country=?, branch_pincode=?,
                    delivery_line1=?, delivery_line2=?, delivery_city=?, delivery_district=?, delivery_state=?, delivery_country=?, delivery_pincode=?,
                    photo=?, is_active=?, updated_by=?
                WHERE id=?
            ");
            $stmt->bind_param("ssssdssssssssssssssssssisi",
                $name, $company_name, $referral_id, $referral_type, $referral_percentage, $mobile, $email, $gstin,
                $branch_line1, $branch_line2, $branch_city, $branch_district, $branch_state, $branch_country, $branch_pincode,
                $delivery_line1, $delivery_line2, $delivery_city, $delivery_district, $delivery_state, $delivery_country, $delivery_pincode,
                $photo_update, $is_active, $created_by, $tp_db_id
            );
        } else {
            $stmt = $db_conn->prepare("
                UPDATE territory_partners
                SET name=?, company_name=?, referral_id=?, referral_type=?, referral_percentage=?, mobile=?, email=?, gstin=?,
                    branch_line1=?, branch_line2=?, branch_city=?, branch_district=?, branch_state=?, branch_country=?, branch_pincode=?,
                    delivery_line1=?, delivery_line2=?, delivery_city=?, delivery_district=?, delivery_state=?, delivery_country=?, delivery_pincode=?,
                    is_active=?, updated_by=?
                WHERE id=?
            ");
            $stmt->bind_param("ssssdsssssssssssssssssisi",
                $name, $company_name, $referral_id, $referral_type, $referral_percentage, $mobile, $email, $gstin,
                $branch_line1, $branch_line2, $branch_city, $branch_district, $branch_state, $branch_country, $branch_pincode,
                $delivery_line1, $delivery_line2, $delivery_city, $delivery_district, $delivery_state, $delivery_country, $delivery_pincode,
                $is_active, $created_by, $tp_db_id
            );
        }
        $stmt->execute();
        $stmt->close();

        if ($photo_update && $old_photo) {
            $old_path = __DIR__ . '/../company/tp_photo/' . $old_photo;
            if (file_exists($old_path)) @unlink($old_path);
        }

        // Re-sync locations: delete all current, re-insert the (validated) submitted list
        $stmt_del = $db_conn->prepare("DELETE FROM territory_partner_locations WHERE territory_partner_id = ?");
        $stmt_del->bind_param("i", $tp_db_id);
        $stmt_del->execute();
        $stmt_del->close();

        if (!empty($location_ids)) {
            $stmt_loc = $db_conn->prepare("INSERT IGNORE INTO territory_partner_locations (territory_partner_id, location_id) VALUES (?, ?)");
            foreach ($location_ids as $lid) {
                $stmt_loc->bind_param("ii", $tp_db_id, $lid);
                $stmt_loc->execute();
            }
            $stmt_loc->close();
        }

        $db_conn->commit();
        header("Location: manage-tp?updatedSuccess=1");
        exit;

    } catch (Exception $e) {
        $db_conn->rollback();
        header("Location: edit-tp?tpid=" . urlencode($tp_id_raw) . "&error=1");
        exit;
    }
}

header("Location: manage-tp");
exit;
?>
