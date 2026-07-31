<?php
declare(strict_types=1);

include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");

error_reporting(0);

function validateCSRFTokenMTL(): bool {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function sanitizeMTL(?string $input): string {
    if ($input === null) return '';
    $input = trim($input);
    $input = str_replace("'", "&#39;", $input);
    return RemoveSpecialChar($input);
}

function redirectMTL(string $location, string $qs = ''): void {
    $url = $location . ($qs ? '?' . $qs : '');
    header("Location: $url");
    exit();
}

$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");
$_chkLL = $db_conn->query("SHOW COLUMNS FROM marketing_team_levels LIKE 'location_layer_id'");
if ($_chkLL && $_chkLL->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_team_levels ADD COLUMN location_layer_id INT NULL DEFAULT NULL AFTER level_name");
}

// ── INSERT ────────────────────────────────────────────────────────────────────
if (isset($_POST['insert-marketing-team-level'])) {

    if (!validateCSRFTokenMTL()) {
        redirectMTL('add-marketing-team-level', 'csrf_error');
    }

    $level_rank = filter_var($_POST['level_rank'] ?? 0, FILTER_VALIDATE_INT);
    $level_name = sanitizeMTL($_POST['level_name'] ?? '');
    $location_layer_id = !empty($_POST['location_layer_id']) ? (int)$_POST['location_layer_id'] : 0;

    if (!$level_rank || $level_rank < 1 || $level_rank > 100 || empty($level_name)) {
        redirectMTL('add-marketing-team-level', 'invalidparameters');
    }

    $stmt_u = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM marketing_team_levels WHERE level_rank = ?");
    $stmt_u->bind_param("i", $level_rank);
    $stmt_u->execute();
    $dup = (int)$stmt_u->get_result()->fetch_assoc()['cnt'];
    $stmt_u->close();

    if ($dup > 0) {
        redirectMTL('add-marketing-team-level', 'alreadyexists&rank=' . $level_rank);
    }

    $stmt_ins = $db_conn->prepare("INSERT INTO marketing_team_levels (level_rank, level_name, location_layer_id) VALUES (?, ?, ?)");
    $location_layer_id_param = $location_layer_id > 0 ? $location_layer_id : null;
    $stmt_ins->bind_param("isi", $level_rank, $level_name, $location_layer_id_param);

    if ($stmt_ins->execute()) {
        $stmt_ins->close();
        redirectMTL('manage-marketing-team-levels', 'addesuccess');
    } else {
        $stmt_ins->close();
        redirectMTL('add-marketing-team-level', 'error');
    }
}

// ── UPDATE ────────────────────────────────────────────────────────────────────
if (isset($_POST['update-marketing-team-level'])) {

    if (!validateCSRFTokenMTL()) {
        redirectMTL('manage-marketing-team-levels', 'csrf_error');
    }

    $update_id  = filter_var($_POST['update_id'] ?? 0, FILTER_VALIDATE_INT);
    $prid       = $_POST['prid'] ?? '';
    $level_rank = filter_var($_POST['level_rank'] ?? 0, FILTER_VALIDATE_INT);
    $level_name = sanitizeMTL($_POST['level_name'] ?? '');
    $location_layer_id = !empty($_POST['location_layer_id']) ? (int)$_POST['location_layer_id'] : 0;

    if (!$update_id || !$level_rank || $level_rank < 1 || $level_rank > 100 || empty($level_name)) {
        redirectMTL('manage-marketing-team-levels', 'invalidparameters');
    }

    $stmt_u = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM marketing_team_levels WHERE level_rank = ? AND id != ?");
    $stmt_u->bind_param("ii", $level_rank, $update_id);
    $stmt_u->execute();
    $dup = (int)$stmt_u->get_result()->fetch_assoc()['cnt'];
    $stmt_u->close();

    if ($dup > 0) {
        $encoded_prid = base64_encode((string)$update_id);
        redirectMTL("edit-marketing-team-level?prid=$encoded_prid", 'alreadyexists&rank=' . $level_rank);
    }

    $stmt_upd = $db_conn->prepare("UPDATE marketing_team_levels SET level_rank = ?, level_name = ?, location_layer_id = ? WHERE id = ?");
    $location_layer_id_param = $location_layer_id > 0 ? $location_layer_id : null;
    $stmt_upd->bind_param("isii", $level_rank, $level_name, $location_layer_id_param, $update_id);

    if ($stmt_upd->execute()) {
        $stmt_upd->close();
        redirectMTL('manage-marketing-team-levels', 'updatedSuccess');
    } else {
        $stmt_upd->close();
        $encoded_prid = base64_encode((string)$update_id);
        redirectMTL("edit-marketing-team-level?prid=$encoded_prid", 'error');
    }
}

// ── Fallback ──────────────────────────────────────────────────────────────────
redirectMTL('manage-marketing-team-levels', 'invalid_action');
