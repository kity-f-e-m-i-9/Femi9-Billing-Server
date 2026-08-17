<?php
// AJAX backend for the "Filled Firkas" hover-modal on dashboard.php — lists
// the Territory Partners (active or inactive tab) assigned within this
// Sales BDM's own districts, with their Napkin-only target amount, current
// weekly-target completion status, and a promptness rank for the current
// week (earliest Napkin sale this week = best rank).
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
$status  = $_GET['status'] ?? 'all';
$status  = in_array($status, ['on_track', 'behind'], true) ? $status : 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$search  = trim($_GET['q'] ?? '');

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

if ($search !== '') {
    $stmt = $db_conn->prepare("
        SELECT id, tp_id, name, mobile, branch_district
        FROM territory_partners
        WHERE id IN ($tpIdList) AND is_active=$activeVal
          AND (name LIKE ? OR mobile LIKE ? OR tp_id LIKE ?)
        ORDER BY name ASC
    ");
    $like = '%' . $search . '%';
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $tpRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $tpRows = $db_conn->query("
        SELECT id, tp_id, name, mobile, branch_district
        FROM territory_partners
        WHERE id IN ($tpIdList) AND is_active=$activeVal
        ORDER BY name ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

// Weekly status/rank has to be computed for the WHOLE tab (not just one
// page) before filtering by On Track/Behind and ranking — otherwise
// pagination would show fewer than a page's worth of matching rows, or
// rank TPs only against whichever 15 happened to load on that page.
$all = [];
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

    $weekly = getTpWeeklyCompletion($db_conn, (int)$tp['id'], $targetAmount, $month);

    if ($status === 'on_track' && (!empty($weekly['is_future']) || empty($weekly['on_track']))) continue;
    if ($status === 'behind' && (!empty($weekly['is_future']) || !empty($weekly['on_track']))) continue;

    $all[] = [
        'tp_id'         => $tp['tp_id'],
        'name'          => $tp['name'],
        'mobile'        => $tp['mobile'],
        'district'      => $tp['branch_district'],
        'firkas'        => implode(', ', $firkaNames),
        'target_amount' => $targetAmount,
        'weekly'        => $weekly,
    ];
}

// Rank by promptness this week — earliest first Napkin sale (smallest
// rank_day_offset) wins; TPs with no sale yet this week (null offset) sort
// last, below every TP who has sold something. Ties broken by name.
usort($all, function ($a, $b) {
    $ao = $a['weekly']['rank_day_offset'] ?? null;
    $bo = $b['weekly']['rank_day_offset'] ?? null;
    if ($ao === null && $bo === null) return strcmp($a['name'], $b['name']);
    if ($ao === null) return 1;
    if ($bo === null) return -1;
    if ($ao !== $bo) return $ao <=> $bo;
    return strcmp($a['name'], $b['name']);
});
foreach ($all as $i => &$row) { $row['rank'] = $i + 1; }
unset($row);

$total  = count($all);
$offset = ($page - 1) * $perPage;
$rows   = array_slice($all, $offset, $perPage);

echo json_encode(['total' => $total, 'page' => $page, 'per_page' => $perPage, 'rows' => $rows]);
