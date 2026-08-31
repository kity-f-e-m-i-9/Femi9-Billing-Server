<?php
// AJAX backend for the "Active TPs" modal on dashboard.php (View TPs button
// on the Active TPs coverage card) — lists this Sales BDM's active TPs with
// their district, assigned Firka count (hover for the full list), Target
// Amount (scoped to Firkas inside this BDM's own district tree — same rule
// dashboard.php's own Active TPs card total uses, so the two always agree),
// and how much Napkin advance they've paid within the caller's own From/To
// date filter.
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);
header('Content-Type: application/json');

try {

tpEnsureAdvanceWalletColumns($db_conn);

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

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$search  = trim($_GET['q'] ?? '');
$from    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to      = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']   ?? '') ? $_GET['to']   : date('Y-m-d');

$tpIds = getBdmAssignedTpIds($db_conn, $effectiveBdmId); // active-only default
if (empty($tpIds)) {
    echo json_encode(['total' => 0, 'total_advance_paid' => 0, 'page' => $page, 'per_page' => $perPage, 'rows' => []]);
    exit;
}
$tpIdList = implode(',', array_map('intval', $tpIds));

// Same district-tree scoping dashboard.php's own Active TPs Target Amount
// total uses — a Firka outside this BDM's own districts never counts toward
// a TP's target here either, so this modal's rows always sum to that card's
// total.
$districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
$districtDepth = (int)($districtDepthRow['depth'] ?? 0);
$districtNames = getBdmAssignedDistrictNames($db_conn, $effectiveBdmId);
$districtTreeSql = '';
$dtTypes = ''; $dtParams = [];
if ($districtDepth && !empty($districtNames)) {
    $dn = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);
    $ph = implode(',', array_fill(0, count($dn), '?'));
    $dtTypes = 'i' . str_repeat('s', count($dn));
    $dtParams = array_merge([$districtDepth], $dn);
    $districtTreeSql = "WITH RECURSIVE district_tree AS (
                SELECT id FROM partner_location_nodes WHERE depth = ? AND LOWER(TRIM(name)) IN ($ph)
                UNION ALL
                SELECT n.id FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
             ) ";
}

$searchWhere = '';
$searchParams = [];
$searchTypes = '';
if ($search !== '') {
    $searchWhere = " AND (tp.name LIKE ? OR tp.tp_id LIKE ? OR tp.mobile LIKE ?)";
    $like = '%' . $search . '%';
    $searchParams = [$like, $like, $like];
    $searchTypes = 'sss';
}

if ($districtTreeSql) {
    $sql = $districtTreeSql . "
        SELECT tp.id, tp.tp_id, tp.name, tp.mobile,
               COALESCE(NULLIF(tp.assigned_district,''), tp.branch_district) AS district,
               COUNT(DISTINCT CASE WHEN pln.id IN (SELECT id FROM district_tree) THEN pln.id END) AS firka_count,
               GROUP_CONCAT(DISTINCT CASE WHEN pln.id IN (SELECT id FROM district_tree) THEN pln.name END ORDER BY pln.name SEPARATOR ', ') AS firka_names,
               COALESCE(SUM(CASE WHEN pln.id IN (SELECT id FROM district_tree) THEN pln.target_amount END), 0) AS target
        FROM territory_partners tp
        LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
        LEFT JOIN partner_location_nodes pln ON pln.id = tpl.location_id
        WHERE tp.id IN ($tpIdList) AND tp.deleted_at IS NULL$searchWhere
        GROUP BY tp.id
        ORDER BY tp.name ASC
    ";
    $types  = $dtTypes . $searchTypes;
    $params = array_merge($dtParams, $searchParams);
} else {
    $sql = "
        SELECT tp.id, tp.tp_id, tp.name, tp.mobile,
               COALESCE(NULLIF(tp.assigned_district,''), tp.branch_district) AS district,
               0 AS firka_count, '' AS firka_names, 0 AS target
        FROM territory_partners tp
        WHERE tp.id IN ($tpIdList) AND tp.deleted_at IS NULL$searchWhere
        ORDER BY tp.name ASC
    ";
    $types  = $searchTypes;
    $params = $searchParams;
}

if ($types) {
    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $allRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $allRows = $db_conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Napkin advance paid per TP, within the caller's From/To — same figure
// basis as the dashboard's own Payment card / Advance-by-week modal.
$advanceByTp = [];
$stmtA = $db_conn->prepare("
    SELECT territory_partner_id, COALESCE(SUM(amount),0) AS paid
    FROM tp_advance_payments
    WHERE territory_partner_id IN ($tpIdList) AND product_type = 'napkin' AND deleted_at IS NULL
      AND payment_date BETWEEN ? AND ?
    GROUP BY territory_partner_id
");
$stmtA->bind_param('ss', $from, $to);
$stmtA->execute();
$advRows = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtA->close();
foreach ($advRows as $r) { $advanceByTp[(int)$r['territory_partner_id']] = (float)$r['paid']; }

foreach ($allRows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['firka_count'] = (int)$r['firka_count'];
    $r['target'] = (float)$r['target'];
    $r['advance_paid'] = $advanceByTp[$r['id']] ?? 0.0;
}
unset($r);

$total  = count($allRows);
$totalAdvancePaid = array_sum(array_column($allRows, 'advance_paid'));
$offset = ($page - 1) * $perPage;
$rows   = array_slice($allRows, $offset, $perPage);

echo json_encode(['total' => $total, 'total_advance_paid' => $totalAdvancePaid, 'page' => $page, 'per_page' => $perPage, 'from' => $from, 'to' => $to, 'rows' => $rows]);

} catch (\Throwable $e) {
    echo json_encode(['total' => 0, 'page' => 1, 'per_page' => 15, 'rows' => [], 'error' => $e->getMessage()]);
}
