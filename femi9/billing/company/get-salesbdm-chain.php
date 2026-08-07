<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$bdm_id = (int)($_GET['bdm_id'] ?? 0);

if (!$bdm_id) {
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

$chain = [];
$current_id = $bdm_id;
$visited = [];

$stmt = $db_conn->prepare("
    SELECT bs.id, bs.bdm_name, bs.manager_id, l.level_name
    FROM sales_bdm_staff bs
    LEFT JOIN salesbdm_team_levels l ON l.id = bs.team_level_id
    WHERE bs.id = ?
");

while ($current_id && !isset($visited[$current_id]) && count($chain) < 20) {
    $visited[$current_id] = true;
    $stmt->bind_param("i", $current_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) break;

    $chain[] = [
        'id'         => (int)$row['id'],
        'name'       => $row['bdm_name'],
        'level_name' => $row['level_name'],
    ];

    $current_id = $row['manager_id'] ? (int)$row['manager_id'] : 0;
}
$stmt->close();

echo json_encode($chain);
