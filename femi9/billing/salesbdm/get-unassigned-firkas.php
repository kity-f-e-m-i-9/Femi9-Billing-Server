<?php
// AJAX backend for the "Unassigned Firkas" modal on dashboard.php — lists
// every Firka within this Sales BDM's own districts that currently has NO
// Territory Partner assigned at all.
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
error_reporting(0);
header('Content-Type: application/json');

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

$districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
$districtDepth = (int)($districtDepthRow['depth'] ?? 0);
$districtNames = getBdmAssignedDistrictNames($db_conn, $effectiveBdmId);

if (!$districtDepth || empty($districtNames)) {
    echo json_encode(['total' => 0, 'page' => $page, 'per_page' => $perPage, 'rows' => []]);
    exit;
}

$dn = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);
$ph = implode(',', array_fill(0, count($dn), '?'));
$types = 'i' . str_repeat('s', count($dn));
$params = array_merge([$districtDepth], $dn);

$searchWhere = '';
if ($search !== '') {
    $searchWhere = " AND (pln.name LIKE ? OR dt.district_name LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$sql = "
    WITH RECURSIVE district_tree AS (
        SELECT id, id AS district_id, name AS district_name
        FROM partner_location_nodes
        WHERE depth = ? AND LOWER(TRIM(name)) IN ($ph)
        UNION ALL
        SELECT n.id, dt.district_id, dt.district_name
        FROM partner_location_nodes n
        JOIN district_tree dt ON n.parent_id = dt.id
    )
    SELECT dt.district_name, pln.name AS firka_name, pln.target_amount
    FROM partner_location_nodes pln
    JOIN district_tree dt ON dt.id = pln.id
    JOIN partner_location_layers pll ON pll.depth = pln.depth
    LEFT JOIN territory_partner_locations tpl ON tpl.location_id = pln.id
    WHERE pll.is_tp_filter_enabled = 1 AND pln.is_active = 1$searchWhere
    GROUP BY pln.id, pln.name, dt.district_name, pln.target_amount
    HAVING COUNT(tpl.location_id) = 0
    ORDER BY dt.district_name ASC, pln.name ASC
";

$stmt = $db_conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$allRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total  = count($allRows);
$offset = ($page - 1) * $perPage;
$rows   = array_slice($allRows, $offset, $perPage);

echo json_encode(['total' => $total, 'page' => $page, 'per_page' => $perPage, 'rows' => $rows]);
