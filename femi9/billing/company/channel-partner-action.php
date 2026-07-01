<?php
include("checksession.php");
error_reporting(0);

// CSRF check
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header("Location: manage-channel-partner");
    exit;
}

// Schema migration — add columns if missing
$_addr_cols = [
    'company_name'        => "VARCHAR(255) DEFAULT NULL AFTER name",
    'referral_id'         => "VARCHAR(100) DEFAULT NULL AFTER company_name",
    'referral_percentage' => "DECIMAL(5,2) DEFAULT NULL AFTER referral_id",
    'branch_line1'     => "VARCHAR(255) DEFAULT NULL AFTER gstin",
    'branch_line2'     => "VARCHAR(255) DEFAULT NULL AFTER branch_line1",
    'branch_city'      => "VARCHAR(100) DEFAULT NULL AFTER branch_line2",
    'branch_district'  => "VARCHAR(100) DEFAULT NULL AFTER branch_city",
    'branch_state'     => "VARCHAR(100) DEFAULT NULL AFTER branch_district",
    'branch_country'   => "VARCHAR(100) DEFAULT NULL AFTER branch_state",
    'branch_pincode'   => "VARCHAR(20)  DEFAULT NULL AFTER branch_country",
    'delivery_line1'   => "VARCHAR(255) DEFAULT NULL AFTER branch_pincode",
    'delivery_line2'   => "VARCHAR(255) DEFAULT NULL AFTER delivery_line1",
    'delivery_city'    => "VARCHAR(100) DEFAULT NULL AFTER delivery_line2",
    'delivery_district'=> "VARCHAR(100) DEFAULT NULL AFTER delivery_city",
    'delivery_state'   => "VARCHAR(100) DEFAULT NULL AFTER delivery_district",
    'delivery_country' => "VARCHAR(100) DEFAULT NULL AFTER delivery_state",
    'delivery_pincode' => "VARCHAR(20)  DEFAULT NULL AFTER delivery_country",
    'gst_enabled'      => "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active",
];
foreach ($_addr_cols as $_col => $_def) {
    $_r = $db_conn->query("SHOW COLUMNS FROM channel_partners LIKE '$_col'");
    if ($_r && $_r->num_rows === 0) {
        $db_conn->query("ALTER TABLE channel_partners ADD COLUMN `$_col` $_def");
    }
}

$action = $_POST['action'] ?? '';

// ── INSERT ────────────────────────────────────────────────────────────────────
if ($action === 'insert-channel-partner') {

    $name                 = trim($_POST['cp_name']                ?? '');
    $company_name         = trim($_POST['cp_company_name']        ?? '');
    $referral_id          = trim($_POST['cp_referral_id']         ?? '') ?: null;
    $referral_percentage  = strlen(trim($_POST['cp_referral_percentage'] ?? '')) ? (float)$_POST['cp_referral_percentage'] : null;
    $mobile               = trim($_POST['cp_mobile']              ?? '');
    $email                = trim($_POST['cp_email']               ?? '') ?: null;
    $gstin                = trim($_POST['cp_gstin']               ?? '') ?: null;
    $branch_line1         = trim($_POST['cp_branch_line1']        ?? '');
    $branch_line2         = trim($_POST['cp_branch_line2']        ?? '') ?: null;
    $branch_city          = trim($_POST['cp_branch_city']         ?? '') ?: null;
    $branch_district      = trim($_POST['cp_branch_district']     ?? '') ?: null;
    $branch_state         = trim($_POST['cp_branch_state']        ?? '') ?: null;
    $branch_country       = trim($_POST['cp_branch_country']      ?? '') ?: null;
    $branch_pincode       = trim($_POST['cp_branch_pincode']      ?? '') ?: null;
    $delivery_line1       = trim($_POST['cp_delivery_line1']      ?? '');
    $delivery_line2       = trim($_POST['cp_delivery_line2']      ?? '') ?: null;
    $delivery_city        = trim($_POST['cp_delivery_city']       ?? '') ?: null;
    $delivery_district    = trim($_POST['cp_delivery_district']   ?? '') ?: null;
    $delivery_state       = trim($_POST['cp_delivery_state']      ?? '') ?: null;
    $delivery_country     = trim($_POST['cp_delivery_country']    ?? '') ?: null;
    $delivery_pincode     = trim($_POST['cp_delivery_pincode']    ?? '') ?: null;
    $is_active            = (int)($_POST['cp_active'] ?? 1);
    $gst_enabled          = isset($_POST['cp_gst_enabled']) ? 1 : 0;
    $raw_password         = trim($_POST['cp_password'] ?? '') ?: 'Channel@123';
    $password_hash        = password_hash($raw_password, PASSWORD_DEFAULT);
    $created_by           = $_SESSION['LOGIN_USER'] ?? '';
    $location_ids         = array_filter(array_map('intval', $_POST['location_ids'] ?? []));

    if (!$name || !$mobile || !$branch_line1 || !$delivery_line1) {
        header("Location: add-channel-partner?error=1");
        exit;
    }
    $branch_line1   = $branch_line1   ?: null;
    $delivery_line1 = $delivery_line1 ?: null;

    // Check mobile uniqueness
    $chk = $db_conn->prepare("SELECT id FROM channel_partners WHERE mobile = ?");
    $chk->bind_param("s", $mobile);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        header("Location: add-channel-partner?mobiletaken=1&cp_name=" . urlencode($name) .
               "&cp_email=" . urlencode($email ?? '') .
               "&cp_gstin=" . urlencode($gstin ?? '') .
               "&cp_mobile=" . urlencode($mobile));
        exit;
    }
    $chk->close();

    // Handle photo upload
    $photo = null;
    if (!empty($_FILES['cp_photo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['cp_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed) && $_FILES['cp_photo']['size'] <= 2097152) {
            $filename = 'cp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest     = __DIR__ . '/cp_photo/' . $filename;
            if (move_uploaded_file($_FILES['cp_photo']['tmp_name'], $dest)) {
                $photo = $filename;
            }
        }
    }

    $db_conn->begin_transaction();
    try {
        // Lock the sequence row, increment, read — atomic under the transaction lock
        $db_conn->query("SELECT last_val FROM cp_id_sequence WHERE id = 1 FOR UPDATE");
        $db_conn->query("UPDATE cp_id_sequence SET last_val = last_val + 1 WHERE id = 1");
        $seq_res = $db_conn->query("SELECT last_val FROM cp_id_sequence WHERE id = 1");
        $next_num = (int)$seq_res->fetch_assoc()['last_val'];
        $cp_id = 'CP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        $stmt = $db_conn->prepare("
            INSERT INTO channel_partners
                (cp_id, name, company_name, referral_id, referral_percentage, mobile, email, gstin,
                 branch_line1, branch_line2, branch_city, branch_district, branch_state, branch_country, branch_pincode,
                 delivery_line1, delivery_line2, delivery_city, delivery_district, delivery_state, delivery_country, delivery_pincode,
                 photo, is_active, gst_enabled, password, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssdssssssssssssssssssiiss",
            $cp_id, $name, $company_name, $referral_id, $referral_percentage, $mobile, $email, $gstin,
            $branch_line1, $branch_line2, $branch_city, $branch_district, $branch_state, $branch_country, $branch_pincode,
            $delivery_line1, $delivery_line2, $delivery_city, $delivery_district, $delivery_state, $delivery_country, $delivery_pincode,
            $photo, $is_active, $gst_enabled, $password_hash, $created_by
        );
        $stmt->execute();
        $new_id = $db_conn->insert_id;
        $stmt->close();

        // Assign locations
        if (!empty($location_ids)) {
            $stmt_loc = $db_conn->prepare("
                INSERT IGNORE INTO channel_partner_locations (channel_partner_id, location_id)
                VALUES (?, ?)
            ");
            foreach ($location_ids as $lid) {
                $stmt_loc->bind_param("ii", $new_id, $lid);
                $stmt_loc->execute();
            }
            $stmt_loc->close();
        }

        $db_conn->commit();
        header("Location: manage-channel-partner?addesuccess=1");
        exit;

    } catch (Exception $e) {
        $db_conn->rollback();
        header("Location: add-channel-partner?error=1");
        exit;
    }
}

// ── UPDATE ────────────────────────────────────────────────────────────────────
if ($action === 'update-channel-partner') {

    $cp_id_raw            = $_POST['cp_db_id'] ?? '';
    $cp_db_id             = (int)base64_decode($cp_id_raw);
    $name                 = trim($_POST['cp_name']                ?? '');
    $company_name         = trim($_POST['cp_company_name']        ?? '');
    $referral_id          = trim($_POST['cp_referral_id']         ?? '') ?: null;
    $referral_percentage  = strlen(trim($_POST['cp_referral_percentage'] ?? '')) ? (float)$_POST['cp_referral_percentage'] : null;
    $mobile               = trim($_POST['cp_mobile']              ?? '');
    $email                = trim($_POST['cp_email']               ?? '') ?: null;
    $gstin                = trim($_POST['cp_gstin']               ?? '') ?: null;
    $branch_line1         = trim($_POST['cp_branch_line1']        ?? '');
    $branch_line2         = trim($_POST['cp_branch_line2']        ?? '') ?: null;
    $branch_city          = trim($_POST['cp_branch_city']         ?? '') ?: null;
    $branch_district      = trim($_POST['cp_branch_district']     ?? '') ?: null;
    $branch_state         = trim($_POST['cp_branch_state']        ?? '') ?: null;
    $branch_country       = trim($_POST['cp_branch_country']      ?? '') ?: null;
    $branch_pincode       = trim($_POST['cp_branch_pincode']      ?? '') ?: null;
    $delivery_line1       = trim($_POST['cp_delivery_line1']      ?? '');
    $delivery_line2       = trim($_POST['cp_delivery_line2']      ?? '') ?: null;
    $delivery_city        = trim($_POST['cp_delivery_city']       ?? '') ?: null;
    $delivery_district    = trim($_POST['cp_delivery_district']   ?? '') ?: null;
    $delivery_state       = trim($_POST['cp_delivery_state']      ?? '') ?: null;
    $delivery_country     = trim($_POST['cp_delivery_country']    ?? '') ?: null;
    $delivery_pincode     = trim($_POST['cp_delivery_pincode']    ?? '') ?: null;
    $is_active            = (int)($_POST['cp_active'] ?? 1);
    $gst_enabled          = isset($_POST['cp_gst_enabled']) ? 1 : 0;
    $created_by           = $_SESSION['LOGIN_USER'] ?? '';
    $location_ids         = array_filter(array_map('intval', $_POST['location_ids'] ?? []));

    if (!$cp_db_id || !$name || !$mobile || !$branch_line1 || !$delivery_line1) {
        header("Location: manage-channel-partner?error=1");
        exit;
    }
    $branch_line1   = $branch_line1   ?: null;
    $delivery_line1 = $delivery_line1 ?: null;

    // Check mobile uniqueness excluding self
    $chk = $db_conn->prepare("SELECT id FROM channel_partners WHERE mobile = ? AND id != ?");
    $chk->bind_param("si", $mobile, $cp_db_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        header("Location: edit-channel-partner?cpid=" . urlencode($cp_id_raw) . "&mobiletaken=1");
        exit;
    }
    $chk->close();

    // Fetch current photo so we can delete the old file if a new one is uploaded
    $old_photo_res = $db_conn->prepare("SELECT photo FROM channel_partners WHERE id = ?");
    $old_photo_res->bind_param("i", $cp_db_id);
    $old_photo_res->execute();
    $old_photo = $old_photo_res->get_result()->fetch_assoc()['photo'] ?? null;
    $old_photo_res->close();

    // Handle photo upload
    $photo_update = '';
    if (!empty($_FILES['cp_photo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['cp_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed) && $_FILES['cp_photo']['size'] <= 2097152) {
            $filename = 'cp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest     = __DIR__ . '/cp_photo/' . $filename;
            if (move_uploaded_file($_FILES['cp_photo']['tmp_name'], $dest)) {
                $photo_update = $filename;
            }
        }
    }

    $db_conn->begin_transaction();
    try {
        if ($photo_update) {
            $stmt = $db_conn->prepare("
                UPDATE channel_partners
                SET name=?, company_name=?, referral_id=?, referral_percentage=?, mobile=?, email=?, gstin=?,
                    branch_line1=?, branch_line2=?, branch_city=?, branch_district=?, branch_state=?, branch_country=?, branch_pincode=?,
                    delivery_line1=?, delivery_line2=?, delivery_city=?, delivery_district=?, delivery_state=?, delivery_country=?, delivery_pincode=?,
                    photo=?, is_active=?, gst_enabled=?, updated_by=?
                WHERE id=?
            ");
            $stmt->bind_param("sssdssssssssssssssssssiisi",
                $name, $company_name, $referral_id, $referral_percentage, $mobile, $email, $gstin,
                $branch_line1, $branch_line2, $branch_city, $branch_district, $branch_state, $branch_country, $branch_pincode,
                $delivery_line1, $delivery_line2, $delivery_city, $delivery_district, $delivery_state, $delivery_country, $delivery_pincode,
                $photo_update, $is_active, $gst_enabled, $created_by, $cp_db_id
            );
        } else {
            $stmt = $db_conn->prepare("
                UPDATE channel_partners
                SET name=?, company_name=?, referral_id=?, referral_percentage=?, mobile=?, email=?, gstin=?,
                    branch_line1=?, branch_line2=?, branch_city=?, branch_district=?, branch_state=?, branch_country=?, branch_pincode=?,
                    delivery_line1=?, delivery_line2=?, delivery_city=?, delivery_district=?, delivery_state=?, delivery_country=?, delivery_pincode=?,
                    is_active=?, gst_enabled=?, updated_by=?
                WHERE id=?
            ");
            $stmt->bind_param("sssdsssssssssssssssssiisi",
                $name, $company_name, $referral_id, $referral_percentage, $mobile, $email, $gstin,
                $branch_line1, $branch_line2, $branch_city, $branch_district, $branch_state, $branch_country, $branch_pincode,
                $delivery_line1, $delivery_line2, $delivery_city, $delivery_district, $delivery_state, $delivery_country, $delivery_pincode,
                $is_active, $gst_enabled, $created_by, $cp_db_id
            );
        }
        $stmt->execute();
        $stmt->close();

        // Delete old photo file after successful DB update (only if a new one was uploaded)
        if ($photo_update && $old_photo) {
            $old_path = __DIR__ . '/cp_photo/' . $old_photo;
            if (file_exists($old_path)) @unlink($old_path);
        }

        // Re-sync locations: delete all current, re-insert the submitted list
        $stmt_del = $db_conn->prepare("DELETE FROM channel_partner_locations WHERE channel_partner_id = ?");
        $stmt_del->bind_param("i", $cp_db_id);
        $stmt_del->execute();
        $stmt_del->close();

        if (!empty($location_ids)) {
            $stmt_loc = $db_conn->prepare("
                INSERT IGNORE INTO channel_partner_locations (channel_partner_id, location_id)
                VALUES (?, ?)
            ");
            foreach ($location_ids as $lid) {
                $stmt_loc->bind_param("ii", $cp_db_id, $lid);
                $stmt_loc->execute();
            }
            $stmt_loc->close();
        }

        $db_conn->commit();
        header("Location: manage-channel-partner?updatedSuccess=1");
        exit;

    } catch (Throwable $e) {
        $db_conn->rollback();
        header("Location: edit-channel-partner?cpid=" . urlencode($cp_id_raw) . "&error=1");
        exit;
    }
}

// Fallback
header("Location: manage-channel-partner");
exit;
