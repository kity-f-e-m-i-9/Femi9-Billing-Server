<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$level_id       = (int)($_GET['level_id'] ?? 0);
$exclude_bdm_id = (int)($_GET['exclude_bdm_id'] ?? 0);

if (!$level_id) {
    echo json_encode([]);
    exit;
}

$_chkTL = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'team_level_id'");
if ($_chkTL && $_chkTL->num_rows === 0) {
    $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN team_level_id INT NULL DEFAULT NULL AFTER user_position");
}
$_chkMgr = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'manager_id'");
if ($_chkMgr && $_chkMgr->num_rows === 0) {
    $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN manager_id INT NULL DEFAULT NULL AFTER team_level_id");
}

$stmt = $db_conn->prepare("SELECT id, bdm_name FROM sales_bdm_staff WHERE team_level_id = ? AND id != ? ORDER BY bdm_name ASC");
$stmt->bind_param("ii", $level_id, $exclude_bdm_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$result = [];
foreach ($rows as $r) {
    $result[] = ['id' => (int)$r['id'], 'name' => $r['bdm_name']];
}

echo json_encode($result);
