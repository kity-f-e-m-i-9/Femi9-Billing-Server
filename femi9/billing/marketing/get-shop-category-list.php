<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/AssignedLocations.php';
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

// Same dual-source + dedupe logic as dashboard.php's New Shop
// Onboarding/Shop Category Split (2026-12-01, user-reported this endpoint
// was silently missing every TP-onboarded shop, same as that modal's pills
// summary was) — DM-added ms_shop rows for this team, plus TP-added shop
// rows across every district this team's own district-level members cover,
// excluding a DM shop that's really the same real shop a TP already has
// (matched by normalised name + 10-digit mobile).
function gscDedupeKey($name, $mobile): ?string {
    $digits = preg_replace('/\D+/', '', (string)$mobile);
    $nm = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string)$name)));
    if (strlen($digits) === 10 && $nm !== '') { return $nm . '|' . $digits; }
    return null;
}

$scopeIds = [];
foreach ($teamMsIds as $_tmid) {
    foreach (getMsAssignedTargetScopeNodes($db_conn, $_tmid) as $_sn) { $scopeIds[(int)$_sn['id']] = true; }
}
$scopeIds = array_keys($scopeIds);

$idList = implode(',', array_map('intval', $teamMsIds));
$fromEsc = $db_conn->real_escape_string($from);
$toEsc   = $db_conn->real_escape_string($to);

$dmRows = $db_conn->query("
    SELECT s.name, s.mobile_number, COALESCE(dn.name, s.district_name, 'Unassigned') AS district_name,
           s.taluk_name, s.created_at, s.shop_cat
    FROM ms_shop s
    LEFT JOIN partner_location_nodes dn ON dn.id = s.district_node_id
    WHERE s.ms_id IN ($idList) AND DATE(s.created_at) BETWEEN '$fromEsc' AND '$toEsc'
")->fetch_all(MYSQLI_ASSOC);

$tpRows = [];
$tpShopKeys = [];
if (!empty($scopeIds)) {
    $scopeIdList = implode(',', array_map('intval', $scopeIds));
    $tpRows = $db_conn->query("
        SELECT s.name, s.mobile_number, COALESCE(dn.name, 'Unassigned') AS district_name,
               tn.name AS taluk_name, s.created_at, s.shop_cat
        FROM shop s
        LEFT JOIN partner_location_nodes dn ON dn.id = s.district_id
        LEFT JOIN partner_location_nodes tn ON tn.id = s.taluk_id
        WHERE s.district_id IN ($scopeIdList) AND s.source_ms_shop_id IS NULL
          AND s.deleted_at IS NULL AND s.created_at IS NOT NULL
          AND DATE(s.created_at) BETWEEN '$fromEsc' AND '$toEsc'
    ")->fetch_all(MYSQLI_ASSOC);

    $resAllTp = $db_conn->query("
        SELECT name, mobile_number FROM shop
        WHERE district_id IN ($scopeIdList) AND source_ms_shop_id IS NULL AND deleted_at IS NULL
    ");
    while ($r = $resAllTp->fetch_assoc()) {
        $k = gscDedupeKey($r['name'], $r['mobile_number']);
        if ($k !== null) { $tpShopKeys[$k] = true; }
    }
}

$catIdsSeen = array_values(array_unique(array_filter(array_merge(
    array_column($dmRows, 'shop_cat'), array_column($tpRows, 'shop_cat')
), fn($v) => $v !== null && $v !== '')));
$catLabelById = [];
if (!empty($catIdsSeen)) {
    $catIdList = implode(',', array_map('intval', $catIdsSeen));
    $resCat = $db_conn->query("SELECT id, catlable FROM shop_category WHERE id IN ($catIdList)");
    while ($r = $resCat->fetch_assoc()) { $catLabelById[(int)$r['id']] = $r['catlable']; }
}
function gscLabel(array $catLabelById, $catId): string {
    return ($catId !== null && $catId !== '' && isset($catLabelById[(int)$catId])) ? $catLabelById[(int)$catId] : 'Uncategorized';
}

$allRows = [];
foreach ($tpRows as $r) { $allRows[] = $r; }
foreach ($dmRows as $r) {
    $k = gscDedupeKey($r['name'], $r['mobile_number']);
    if ($k !== null && isset($tpShopKeys[$k])) { continue; } // already with a TP, not a genuinely new/separate shop
    $allRows[] = $r;
}

usort($allRows, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

$out = [];
foreach ($allRows as $r) {
    $label = gscLabel($catLabelById, $r['shop_cat']);
    if ($category !== '' && $label !== $category) { continue; }
    $out[] = [
        'name'      => $r['name'],
        'mobile'    => $r['mobile_number'],
        'district'  => $r['district_name'],
        'taluk'     => $r['taluk_name'],
        'date'      => date('d-m-Y', strtotime($r['created_at'])),
        'category'  => $label,
    ];
}
echo json_encode($out);
