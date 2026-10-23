<?php
// Territory Partners under a given Sales BDM (optionally narrowed to one of
// their own districts) — for MIS Report's "Sales BDM" tab (mis-report.php),
// so the Territory Partner filter there can sit next to District instead of
// duplicating the one inside the embedded dashboard itself.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
header('Content-Type: application/json');
error_reporting(0);

$bdm_id = (int)($_GET['bdm_id'] ?? 0);
if (!$bdm_id) { echo json_encode([]); exit; }

$district = trim($_GET['district'] ?? '');
$districtNamesOverride = null;
if ($district !== '') {
    $allNames = getBdmAssignedDistrictNames($db_conn, $bdm_id);
    foreach ($allNames as $n) {
        if (mb_strtolower(trim($n)) === mb_strtolower($district)) { $districtNamesOverride = [$n]; break; }
    }
    if ($districtNamesOverride === null) { echo json_encode([]); exit; } // not actually one of this BDM's districts
}

$tpIds = getBdmAssignedTpIds($db_conn, $bdm_id, false, $districtNamesOverride);
if (empty($tpIds)) { echo json_encode([]); exit; }

$idList = implode(',', array_map('intval', $tpIds));
$rows = $db_conn->query("SELECT id, name FROM territory_partners WHERE id IN ($idList) ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
echo json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $rows));
