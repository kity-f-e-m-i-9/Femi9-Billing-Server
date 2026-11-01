<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

$msId = (int)$markeingSTFID;
$category = trim($_GET['category'] ?? '');
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = date('Y-m-d'); }

// Same "this team" scope as dashboard.php's own Shop Category Split card —
// this person's own ms_id plus every District-level staffer anywhere below
// them in the reporting chain.
$_staffRows = $db_conn->query("
    SELECT ms.id, ms.manager_id, tl.level_name
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    WHERE ms.deleted_at IS NULL
")->fetch_all(MYSQLI_ASSOC);
$_byManager = [];
foreach ($_staffRows as $row) {
    $_byManager[$row['manager_id'] ? (int)$row['manager_id'] : 0][] = $row;
}
$teamMsIds = [$msId];
$_queue = [$msId];
while (!empty($_queue)) {
    $_cur = array_shift($_queue);
    foreach ($_byManager[$_cur] ?? [] as $_child) {
        if (stripos($_child['level_name'] ?? '', 'District') !== false) { $teamMsIds[] = (int)$_child['id']; }
        $_queue[] = (int)$_child['id'];
    }
}

$idList = implode(',', array_map('intval', $teamMsIds));
$params = [$from, $to];
$types = 'ss';
$catSql = '';
if ($category !== '' && $category !== 'Uncategorized') {
    $catSql = 'AND sc.catlable = ?';
    $types .= 's';
    $params[] = $category;
} elseif ($category === 'Uncategorized') {
    $catSql = 'AND sc.catlable IS NULL';
}

$stmt = $db_conn->prepare("
    SELECT s.name, s.mobile_number, s.district_name, s.taluk_name, s.created_at,
           COALESCE(sc.catlable, 'Uncategorized') AS category
    FROM ms_shop s
    LEFT JOIN shop_category sc ON sc.id = s.shop_cat
    WHERE s.ms_id IN ($idList) AND DATE(s.created_at) BETWEEN ? AND ? $catSql
    ORDER BY s.created_at DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'name'      => $r['name'],
        'mobile'    => $r['mobile_number'],
        'district'  => $r['district_name'],
        'taluk'     => $r['taluk_name'],
        'date'      => date('d-m-Y', strtotime($r['created_at'])),
        'category'  => $r['category'],
    ];
}
echo json_encode($out);
