<?php
/**
 * Partner Location Action Handler
 * Handles insert and update for partner_location_nodes.
 */

declare(strict_types=1);

include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");

error_reporting(0);

function validateCSRFTokenPL(): bool {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function sanitizePL(?string $input): string {
    if ($input === null) return '';
    $input = trim($input);
    $input = str_replace("'", "&#39;", $input);
    return RemoveSpecialChar($input);
}

function redirectPL(string $location, string $qs = ''): void {
    $url = $location . ($qs ? '?' . $qs : '');
    header("Location: $url");
    exit();
}

// ── Schema migration: add target_amount if missing ───────────────────────────
$col_check = $db_conn->query("SHOW COLUMNS FROM partner_location_nodes LIKE 'target_amount'");
if ($col_check && $col_check->num_rows === 0) {
    $db_conn->query("ALTER TABLE partner_location_nodes ADD COLUMN target_amount DECIMAL(12,2) DEFAULT NULL AFTER deposit_amount");
}

// ── INSERT ───────────────────────────────────────────────────────────────────
if (isset($_POST['insert-partner-location'])) {

    if (!validateCSRFTokenPL()) {
        redirectPL('add-partner-location', 'csrf_error');
    }

    $pl_name     = sanitizePL($_POST['pl_name']   ?? '');
    $pl_code     = sanitizePL($_POST['pl_code']   ?? '') ?: null;
    $pl_deposit  = $_POST['pl_deposit'] ?? '';
    $pl_deposit  = ($pl_deposit !== '' && is_numeric($pl_deposit)) ? (float)$pl_deposit : null;
    $pl_target   = $pl_deposit; // same value: CP sees it as deposit, TP sees it as target
    $is_active   = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
    $depth       = filter_var($_POST['depth']     ?? 1, FILTER_VALIDATE_INT);
    $raw_pid     = $_POST['parent_id'] ?? '';
    $parent_id   = ($raw_pid !== '' && $raw_pid !== null) ? (int) $raw_pid : null;

    if (empty($pl_name) || !$depth) {
        $qs = 'invalidparameters' . ($parent_id !== null ? '&parent_id=' . $parent_id : '');
        redirectPL('add-partner-location', $qs);
    }

    // Verify parent exists and depth is consistent
    if ($parent_id !== null) {
        $stmt_chk = $db_conn->prepare("SELECT depth FROM partner_location_nodes WHERE id = ?");
        $stmt_chk->bind_param("i", $parent_id);
        $stmt_chk->execute();
        $parent_row = $stmt_chk->get_result()->fetch_assoc();
        $stmt_chk->close();

        if (!$parent_row) {
            redirectPL('add-partner-location', 'invalidparameters&parent_id=' . $parent_id);
        }
        // Force correct depth
        $depth = (int)$parent_row['depth'] + 1;
    } else {
        $depth = 1;
    }

    // Uniqueness check: same name at same level (same parent_id)
    if ($parent_id !== null) {
        $stmt_u = $db_conn->prepare(
            "SELECT COUNT(*) AS cnt FROM partner_location_nodes WHERE parent_id = ? AND name = ?"
        );
        $stmt_u->bind_param("is", $parent_id, $pl_name);
    } else {
        $stmt_u = $db_conn->prepare(
            "SELECT COUNT(*) AS cnt FROM partner_location_nodes WHERE parent_id IS NULL AND name = ?"
        );
        $stmt_u->bind_param("s", $pl_name);
    }
    $stmt_u->execute();
    $dup = $stmt_u->get_result()->fetch_assoc();
    $stmt_u->close();

    if ($dup['cnt'] > 0) {
        $qs = 'alreadyexists' . ($parent_id !== null ? '&parent_id=' . $parent_id : '');
        redirectPL('add-partner-location', $qs);
    }

    $created_by = $Login_user_IDvl ?? '';

    if ($parent_id !== null) {
        $stmt_ins = $db_conn->prepare(
            "INSERT INTO partner_location_nodes (parent_id, depth, name, code, deposit_amount, target_amount, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_ins->bind_param("iissddss", $parent_id, $depth, $pl_name, $pl_code, $pl_deposit, $pl_target, $is_active, $created_by);
    } else {
        $stmt_ins = $db_conn->prepare(
            "INSERT INTO partner_location_nodes (parent_id, depth, name, code, deposit_amount, target_amount, is_active, created_by)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_ins->bind_param("issddss", $depth, $pl_name, $pl_code, $pl_deposit, $pl_target, $is_active, $created_by);
    }

    if ($stmt_ins->execute()) {
        $stmt_ins->close();
        $qs = 'addesuccess' . ($parent_id !== null ? '&parent_id=' . $parent_id : '');
        redirectPL('manage-partner-location', $qs);
    } else {
        $stmt_ins->close();
        $qs = 'error' . ($parent_id !== null ? '&parent_id=' . $parent_id : '');
        redirectPL('add-partner-location', $qs);
    }
}

// ── UPDATE ───────────────────────────────────────────────────────────────────
if (isset($_POST['update-partner-location'])) {

    if (!validateCSRFTokenPL()) {
        redirectPL('manage-partner-location', 'csrf_error');
    }

    $update_id    = filter_var($_POST['update_id']     ?? 0, FILTER_VALIDATE_INT);
    $prid         = $_POST['prid']         ?? '';
    $return_parent = $_POST['return_parent'] ?? '';
    $pl_name     = sanitizePL($_POST['pl_name']   ?? '');
    $pl_code     = sanitizePL($_POST['pl_code']   ?? '') ?: null;
    $pl_deposit  = $_POST['pl_deposit'] ?? '';
    $pl_deposit  = ($pl_deposit !== '' && is_numeric($pl_deposit)) ? (float)$pl_deposit : null;
    $pl_target   = $pl_deposit; // same value: CP sees it as deposit, TP sees it as target
    $is_active   = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);

    if (!$update_id || empty($pl_name)) {
        redirectPL('manage-partner-location', 'invalidparameters' . ($return_parent !== '' ? '&parent_id=' . $return_parent : ''));
    }

    // Fetch existing node to get parent_id for uniqueness check
    $stmt_ex = $db_conn->prepare("SELECT parent_id FROM partner_location_nodes WHERE id = ?");
    $stmt_ex->bind_param("i", $update_id);
    $stmt_ex->execute();
    $existing = $stmt_ex->get_result()->fetch_assoc();
    $stmt_ex->close();

    if (!$existing) {
        redirectPL('manage-partner-location', 'notfound');
    }

    $node_parent_id = $existing['parent_id'];

    // Uniqueness check excluding current node
    if ($node_parent_id !== null) {
        $stmt_u = $db_conn->prepare(
            "SELECT COUNT(*) AS cnt FROM partner_location_nodes WHERE parent_id = ? AND name = ? AND id != ?"
        );
        $stmt_u->bind_param("isi", $node_parent_id, $pl_name, $update_id);
    } else {
        $stmt_u = $db_conn->prepare(
            "SELECT COUNT(*) AS cnt FROM partner_location_nodes WHERE parent_id IS NULL AND name = ? AND id != ?"
        );
        $stmt_u->bind_param("si", $pl_name, $update_id);
    }
    $stmt_u->execute();
    $dup = $stmt_u->get_result()->fetch_assoc();
    $stmt_u->close();

    if ($dup['cnt'] > 0) {
        $encoded_prid = base64_encode((string)$update_id);
        redirectPL("edit-partner-location?prid=$encoded_prid", 'alreadyexists');
    }

    $stmt_upd = $db_conn->prepare(
        "UPDATE partner_location_nodes SET name = ?, code = ?, deposit_amount = ?, target_amount = ?, is_active = ? WHERE id = ?"
    );
    $stmt_upd->bind_param("ssddii", $pl_name, $pl_code, $pl_deposit, $pl_target, $is_active, $update_id);

    if ($stmt_upd->execute()) {
        $stmt_upd->close();
        $qs = 'updatedSuccess' . ($return_parent !== '' ? '&parent_id=' . $return_parent : '');
        redirectPL('manage-partner-location', $qs);
    } else {
        $stmt_upd->close();
        $encoded_prid = base64_encode((string)$update_id);
        redirectPL("edit-partner-location?prid=$encoded_prid", 'error');
    }
}

// ── Fallback ─────────────────────────────────────────────────────────────────
redirectPL('manage-partner-location', 'invalid_action');
