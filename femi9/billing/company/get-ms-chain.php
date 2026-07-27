<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$ms_id = (int)($_GET['ms_id'] ?? 0);

if (!$ms_id) {
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

$chain = [];
$current_id = $ms_id;
$visited = [];

$stmt = $db_conn->prepare("
    SELECT ms.id, ms.ms_name, ms.manager_id, l.level_name
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels l ON l.id = ms.team_level_id
    WHERE ms.id = ?
");

while ($current_id && !isset($visited[$current_id]) && count($chain) < 20) {
    $visited[$current_id] = true;
    $stmt->bind_param("i", $current_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) break;

    $chain[] = [
        'id'         => (int)$row['id'],
        'name'       => $row['ms_name'],
        'level_name' => $row['level_name'],
    ];

    $current_id = $row['manager_id'] ? (int)$row['manager_id'] : 0;
}
$stmt->close();

echo json_encode($chain);
