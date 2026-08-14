<?php
// AJAX backend for the "Filled Firkas" hover-modal on dashboard.php — lists
// the Territory Partners (active or inactive tab) assigned within this
// Sales BDM's own districts, with their Napkin-only target amount and
// current weekly-target completion status.
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once("include/TpWeeklyTarget.php");
error_reporting(0);
header('Content-Type: application/json');

// Same "view as" resolution used by dashboard.php — a manager viewing a
// team member's own dashboard sees that member's Filled Firkas data too.
$effectiveBdmId = (int)$salesBdmID;
if (!empty($_GET['view_bdm_id'])) {
    $requestedId = (int)$_GET['view_bdm_id'];
    if ($requestedId > 0 && $requestedId !== (int)$salesBdmID) {
        require_once("include/TeamSubtree.php");
        $mySubtree = getBdmSubtreeIds($db_conn, (int)$salesBdmID);
        if (in_array($requestedId, $mySubtree, true)) {
            $effectiveBdmId = $requestedId;
        }
    }
}

$tab     = ($_GET['tab'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Weekly-target month follows the dashboard's own date filter (?month=Y-m,
// derived there from its existing "from" date) — falls back to the current
// month if missing/malformed.
$month = $_GET['month'] ?? '';
$month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');

$tpIds = getBdmAssignedTpIds($db_conn, $effectiveBdmId, true);
if (empty($tpIds)) {
    echo json_encode(['total' => 0, 'page' => $page, 'per_page' => $perPage, 'rows' => []]);
    exit;
}
$tpIdList  = implode(',', array_map('intval', $tpIds));
$activeVal = $tab === 'active' ? 1 : 0;

$countRow = $db_conn->query("SELECT COUNT(*) c FROM territory_partners WHERE id IN ($tpIdList) AND is_active=$activeVal")->fetch_assoc();
$total = (int)($countRow['c'] ?? 0);

$offset = ($page - 1) * $perPage;
$tpRows = $db_conn->query("
    SELECT id, tp_id, name, mobile, branch_district
    FROM territory_partners
    WHERE id IN ($tpIdList) AND is_active=$activeVal
    ORDER BY name ASC
    LIMIT $perPage OFFSET $offset
")->fetch_all(MYSQLI_ASSOC);

$rows = [];
foreach ($tpRows as $tp) {
    $locRows = $db_conn->query("
        SELECT pln.name AS firka_name, pln.target_amount
        FROM territory_partner_locations tpl
        JOIN partner_location_nodes pln ON pln.id = tpl.location_id
        WHERE tpl.territory_partner_id = " . (int)$tp['id'] . "
        ORDER BY pln.name ASC
    ")->fetch_all(MYSQLI_ASSOC);

    $firkaNames   = array_map(fn($r) => $r['firka_name'], $locRows);
    $targetAmount = array_sum(array_map(fn($r) => (float)$r['target_amount'], $locRows));

    $weekly = getTpWeeklyCompletion($db_conn, (string)$tp['tp_id'], $targetAmount, $month);

    $rows[] = [
        'tp_id'         => $tp['tp_id'],
        'name'          => $tp['name'],
        'mobile'        => $tp['mobile'],
        'district'      => $tp['branch_district'],
        'firkas'        => implode(', ', $firkaNames),
        'target_amount' => $targetAmount,
        'weekly'        => $weekly,
    ];
}

echo json_encode(['total' => $total, 'page' => $page, 'per_page' => $perPage, 'rows' => $rows]);
