<?php
include("checksession.php");
error_reporting(0);

$prid     = isset($_GET['prid']) ? trim($_GET['prid']) : '';
$level_id = $prid ? (int) base64_decode($prid) : 0;

if (!$level_id) {
    header("Location: manage-marketing-team-levels");
    exit;
}

$stmt_l = $db_conn->prepare("SELECT id, level_name FROM marketing_team_levels WHERE id = ?");
$stmt_l->bind_param("i", $level_id);
$stmt_l->execute();
$level = $stmt_l->get_result()->fetch_assoc();
$stmt_l->close();

if (!$level) {
    header("Location: manage-marketing-team-levels");
    exit;
}

$_chkTL = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'team_level_id'");
if ($_chkTL && $_chkTL->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
}

// Guard: block if any marketing staff are assigned to this level
$stmt_s = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM marketing_staff WHERE team_level_id = ?");
$stmt_s->bind_param("i", $level_id);
$stmt_s->execute();
$staff_cnt = (int)$stmt_s->get_result()->fetch_assoc()['cnt'];
$stmt_s->close();

if ($staff_cnt > 0) {
    header("Location: manage-marketing-team-levels?hasStaff=1");
    exit;
}

$stmt_d = $db_conn->prepare("DELETE FROM marketing_team_levels WHERE id = ?");
$stmt_d->bind_param("i", $level_id);
$stmt_d->execute();
$stmt_d->close();

header("Location: manage-marketing-team-levels?deletedDone=1");
exit;
