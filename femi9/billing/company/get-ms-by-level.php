<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$level_id      = (int)($_GET['level_id'] ?? 0);
$exclude_ms_id = (int)($_GET['exclude_ms_id'] ?? 0);

if (!$level_id) {
    echo json_encode([]);
    exit;
}

$_chkTL = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'team_level_id'");
if ($_chkTL && $_chkTL->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
}
$_chkMgr = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'manager_id'");
if ($_chkMgr && $_chkMgr->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
}

$stmt = $db_conn->prepare("SELECT id, ms_name FROM marketing_staff WHERE team_level_id = ? AND id != ? ORDER BY ms_name ASC");
$stmt->bind_param("ii", $level_id, $exclude_ms_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$result = [];
foreach ($rows as $r) {
    $result[] = ['id' => (int)$r['id'], 'name' => $r['ms_name']];
}

echo json_encode($result);
