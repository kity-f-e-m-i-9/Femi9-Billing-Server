<?php
declare(strict_types=1);

include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");

error_reporting(0);

function validateCSRFTokenPLL(): bool {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function sanitizePLL(?string $input): string {
    if ($input === null) return '';
    $input = trim($input);
    $input = str_replace("'", "&#39;", $input);
    return RemoveSpecialChar($input);
}

function redirectPLL(string $location, string $qs = ''): void {
    $url = $location . ($qs ? '?' . $qs : '');
    header("Location: $url");
    exit();
}

// Ensure is_stock_location column exists
$_chk = $db_conn->query("SHOW COLUMNS FROM partner_location_layers LIKE 'is_stock_location'");
if ($_chk && $_chk->num_rows === 0) {
    $db_conn->query("ALTER TABLE partner_location_layers ADD COLUMN is_stock_location TINYINT(1) NOT NULL DEFAULT 1 AFTER layer_name");
}
// Ensure is_cp_filter_enabled column exists
$_chk2 = $db_conn->query("SHOW COLUMNS FROM partner_location_layers LIKE 'is_cp_filter_enabled'");
if ($_chk2 && $_chk2->num_rows === 0) {
    $db_conn->query("ALTER TABLE partner_location_layers ADD COLUMN is_cp_filter_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_stock_location");
}
// Ensure is_tp_filter_enabled column exists
$_chk3 = $db_conn->query("SHOW COLUMNS FROM partner_location_layers LIKE 'is_tp_filter_enabled'");
if ($_chk3 && $_chk3->num_rows === 0) {
    $db_conn->query("ALTER TABLE partner_location_layers ADD COLUMN is_tp_filter_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_cp_filter_enabled");
}

// ── INSERT ────────────────────────────────────────────────────────────────────
if (isset($_POST['insert-partner-location-layer'])) {

    if (!validateCSRFTokenPLL()) {
        redirectPLL('add-partner-location-layer', 'csrf_error');
    }

    $pll_depth         = filter_var($_POST['pll_depth'] ?? 0, FILTER_VALIDATE_INT);
    $pll_name          = sanitizePLL($_POST['pll_name'] ?? '');
    $is_stock_location   = isset($_POST['is_stock_location'])   ? 1 : 0;
    $is_cp_filter_enabled = isset($_POST['is_cp_filter_enabled']) ? 1 : 0;
    $is_tp_filter_enabled = isset($_POST['is_tp_filter_enabled']) ? 1 : 0;

    if (!$pll_depth || $pll_depth < 1 || $pll_depth > 20 || empty($pll_name)) {
        redirectPLL('add-partner-location-layer', 'invalidparameters');
    }

    // Uniqueness check on depth
    $stmt_u = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM partner_location_layers WHERE depth = ?");
    $stmt_u->bind_param("i", $pll_depth);
    $stmt_u->execute();
    $dup = (int)$stmt_u->get_result()->fetch_assoc()['cnt'];
    $stmt_u->close();

    if ($dup > 0) {
        redirectPLL('add-partner-location-layer', 'alreadyexists&depth=' . $pll_depth);
    }

    $stmt_ins = $db_conn->prepare("INSERT INTO partner_location_layers (depth, layer_name, is_stock_location, is_cp_filter_enabled, is_tp_filter_enabled) VALUES (?, ?, ?, ?, ?)");
    $stmt_ins->bind_param("isiii", $pll_depth, $pll_name, $is_stock_location, $is_cp_filter_enabled, $is_tp_filter_enabled);

    if ($stmt_ins->execute()) {
        $stmt_ins->close();
        redirectPLL('manage-partner-location-layers', 'addesuccess');
    } else {
        $stmt_ins->close();
        redirectPLL('add-partner-location-layer', 'error');
    }
}

// ── UPDATE ────────────────────────────────────────────────────────────────────
if (isset($_POST['update-partner-location-layer'])) {

    if (!validateCSRFTokenPLL()) {
        redirectPLL('manage-partner-location-layers', 'csrf_error');
    }

    $update_id         = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $prid              = $_POST['prid'] ?? '';
    $pll_name          = sanitizePLL($_POST['pll_name'] ?? '');
    $is_stock_location    = isset($_POST['is_stock_location'])    ? 1 : 0;
    $is_cp_filter_enabled = isset($_POST['is_cp_filter_enabled']) ? 1 : 0;
    $is_tp_filter_enabled = isset($_POST['is_tp_filter_enabled']) ? 1 : 0;

    if (!$update_id || empty($pll_name)) {
        redirectPLL('manage-partner-location-layers', 'invalidparameters');
    }

    $stmt_upd = $db_conn->prepare("UPDATE partner_location_layers SET layer_name = ?, is_stock_location = ?, is_cp_filter_enabled = ?, is_tp_filter_enabled = ? WHERE id = ?");
    $stmt_upd->bind_param("siiii", $pll_name, $is_stock_location, $is_cp_filter_enabled, $is_tp_filter_enabled, $update_id);

    if ($stmt_upd->execute()) {
        $stmt_upd->close();
        redirectPLL('manage-partner-location-layers', 'updatedSuccess');
    } else {
        $stmt_upd->close();
        $encoded_prid = base64_encode((string)$update_id);
        redirectPLL("edit-partner-location-layer?prid=$encoded_prid", 'error');
    }
}

// ── Fallback ──────────────────────────────────────────────────────────────────
redirectPLL('manage-partner-location-layers', 'invalid_action');
