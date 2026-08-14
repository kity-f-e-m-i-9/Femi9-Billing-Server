<?php
// "Total Flow" panel data for the company Dashboard (mis-report.php).
//
// Zone-wise BDM performance: Territory Partner firka coverage and Channel
// Partner division coverage, per zone (sales_bdm_staff.zone / the 6
// Business Development Team rows), plus company-wide roll-ups.
//
// PERFORMANCE: this is fetched lazily by JS only on first click of the
// "Total Flow" tab — never on normal dashboard page load (see the
// mis-report.php incident where a recursive-CTE query on this same
// dashboard hung every admin login for 50+ minutes). Everything here is a
// small, indexed, non-recursive query; the full location-node tree
// (~1,500 rows) is loaded once and walked in PHP memory instead of doing
// per-firka/per-zone DB round-trips.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
header('Content-Type: application/json');
error_reporting(0);

function tf_ancestor_district_id(int $nodeId, array &$nodesById, int $districtDepth): ?int {
    $currentId = $nodeId;
    $guard = 0;
    while ($currentId > 0 && $guard < 10) {
        if (!isset($nodesById[$currentId])) return null;
        $node = $nodesById[$currentId];
        if ((int)$node['depth'] === $districtDepth) return $currentId;
        if ((int)$node['depth'] < $districtDepth) return null; // walked past district level, no match
        $currentId = (int)$node['parent_id'];
        $guard++;
    }
    return null;
}

// ── 1. Load the full location-node tree once (small: ~1,500 rows) ──────────
$nodesById = [];
$districtNameToId = [];
$res = $db_conn->query("SELECT id, parent_id, depth, name, target_amount FROM partner_location_nodes");
while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id'];
    $nodesById[$id] = [
        'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : 0,
        'depth' => (int)$row['depth'],
        'name' => $row['name'],
        'target_amount' => $row['target_amount'] !== null ? (float)$row['target_amount'] : 0.0,
    ];
}

$districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
$districtDepth = $districtDepthRow ? (int)$districtDepthRow['depth'] : 3;

foreach ($nodesById as $id => $n) {
    if ($n['depth'] === $districtDepth) {
        $districtNameToId[strtoupper(trim($n['name']))] = $id;
    }
}

// ── 2. The 6 zone-owning BDMs ────────────────────────────────────────────
$zoneBdms = [];
$res = $db_conn->query("SELECT id, bdm_name, zone FROM sales_bdm_staff WHERE zone IS NOT NULL AND zone <> '' ORDER BY id ASC");
while ($row = $res->fetch_assoc()) {
    $zoneBdms[(int)$row['id']] = [
        'bdm_id' => (int)$row['id'],
        'bdm_name' => trim($row['bdm_name']),
        'zone' => $row['zone'],
    ];
}

// ── 3. Resolve each zone's assigned district ids, reusing the same
//      resolution logic used everywhere else in this app
//      (getBdmAssignedDistrictNames — handles state/taluk-level edge cases).
$zoneDistrictIds = [];   // bdm_id => [district_id, ...]
$districtIdToZone = [];  // district_id => bdm_id (first zone claiming it wins)
foreach ($zoneBdms as $bdmId => $z) {
    $names = getBdmAssignedDistrictNames($db_conn, $bdmId);
    $ids = [];
    foreach ($names as $name) {
        $key = strtoupper(trim($name));
        if (isset($districtNameToId[$key])) {
            $did = $districtNameToId[$key];
            $ids[] = $did;
            if (!isset($districtIdToZone[$did])) $districtIdToZone[$did] = $bdmId;
        }
    }
    $zoneDistrictIds[$bdmId] = array_values(array_unique($ids));
}

// ── 4. Assign every firka (depth 6) and division (depth 4) to a zone by
//      walking up its parent chain (in memory) to its enclosing district.
$firkasByZone = [];    // bdm_id => [firka_id, ...]
$divisionsByZone = [];
foreach ($nodesById as $id => $n) {
    if ($n['depth'] === 6) {
        $districtId = tf_ancestor_district_id($id, $nodesById, $districtDepth);
        if ($districtId !== null && isset($districtIdToZone[$districtId])) {
            $firkasByZone[$districtIdToZone[$districtId]][] = $id;
        }
    } elseif ($n['depth'] === 4) {
        $districtId = tf_ancestor_district_id($id, $nodesById, $districtDepth);
        if ($districtId !== null && isset($districtIdToZone[$districtId])) {
            $divisionsByZone[$districtIdToZone[$districtId]][] = $id;
        }
    }
}

// ── 5. Filled-location sets (single batched query each) ────────────────────
// A Firka counts as "filled" the moment ANY TP is assigned to it, active or
// not — matching the salesbdm dashboard's own Filled/Unassigned Firka cards.
// Within "filled", we additionally split Active (has at least one active TP)
// vs Inactive (has a TP, but every one of them is inactive).
$filledFirkaTp = [];       // location_id => territory_partner_id (any TP, active or not — first one wins)
$filledFirkaHasActive = []; // location_id => true if at least one assigned TP is active
$res = $db_conn->query("
    SELECT tpl.location_id, tpl.territory_partner_id, tp.is_active
    FROM territory_partner_locations tpl
    JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
");
while ($row = $res->fetch_assoc()) {
    $locId = (int)$row['location_id'];
    if (!isset($filledFirkaTp[$locId])) { $filledFirkaTp[$locId] = (int)$row['territory_partner_id']; }
    if ((int)$row['is_active'] === 1) { $filledFirkaHasActive[$locId] = true; }
}

$filledDivisionCp = []; // location_id => channel_partner_id
$res = $db_conn->query("
    SELECT cpl.location_id, cpl.channel_partner_id
    FROM channel_partner_locations cpl
    JOIN channel_partners cp ON cp.id = cpl.channel_partner_id
    WHERE cp.is_active = 1
");
while ($row = $res->fetch_assoc()) {
    $filledDivisionCp[(int)$row['location_id']] = (int)$row['channel_partner_id'];
}

// ── 6. Per-zone metrics ─────────────────────────────────────────────────
$tpRows = [];
$cpRows = [];
$totalVacantValue = 0.0;
$maxVacantZone = null;
$maxVacantValue = -1.0;

foreach ($zoneBdms as $bdmId => $z) {
    $firkaIds = $firkasByZone[$bdmId] ?? [];
    $totalFirkas = count($firkaIds);
    $totalFirkaValue = 0.0;
    $filledFirkas = 0;
    $filledFirkaValue = 0.0;
    $activeFirkas = 0;
    $inactiveFirkas = 0;
    $activeFirkaValue = 0.0;
    $inactiveFirkaValue = 0.0;
    $activeTpIds = [];
    foreach ($firkaIds as $fid) {
        $val = $nodesById[$fid]['target_amount'];
        $totalFirkaValue += $val;
        if (isset($filledFirkaTp[$fid])) {
            $filledFirkas++;
            $filledFirkaValue += $val;
            $activeTpIds[$filledFirkaTp[$fid]] = true;
            if (!empty($filledFirkaHasActive[$fid])) { $activeFirkas++; $activeFirkaValue += $val; }
            else { $inactiveFirkas++; $inactiveFirkaValue += $val; }
        }
    }
    $vacantFirkas = $totalFirkas - $filledFirkas;
    $vacantFirkaValue = $totalFirkaValue - $filledFirkaValue;

    $divisionIds = $divisionsByZone[$bdmId] ?? [];
    $totalDivisions = count($divisionIds);
    $totalDivisionValue = 0.0;
    $filledDivisions = 0;
    $filledDivisionValue = 0.0;
    $activeCpIds = [];
    foreach ($divisionIds as $did) {
        $val = $nodesById[$did]['target_amount'];
        $totalDivisionValue += $val;
        if (isset($filledDivisionCp[$did])) {
            $filledDivisions++;
            $filledDivisionValue += $val;
            $activeCpIds[$filledDivisionCp[$did]] = true;
        }
    }
    $vacantDivisions = $totalDivisions - $filledDivisions;
    $vacantDivisionValue = $totalDivisionValue - $filledDivisionValue;

    $totalDistricts = count($zoneDistrictIds[$bdmId] ?? []);

    $tpRows[] = [
        'zone' => $z['zone'],
        'bdm_name' => $z['bdm_name'],
        'total_firkas' => $totalFirkas,
        'total_firkas_value' => $totalFirkaValue,
        'filled_firkas' => $filledFirkas,
        'filled_firkas_value' => $filledFirkaValue,
        'active_firkas' => $activeFirkas,
        'active_firkas_value' => $activeFirkaValue,
        'inactive_firkas' => $inactiveFirkas,
        'inactive_firkas_value' => $inactiveFirkaValue,
        'active_tps' => count($activeTpIds),
        'vacant_firkas' => $vacantFirkas,
        'vacant_firkas_value' => $vacantFirkaValue,
    ];
    $cpRows[] = [
        'zone' => $z['zone'],
        'bdm_name' => $z['bdm_name'],
        'total_districts' => $totalDistricts,
        'total_divisions' => $totalDivisions,
        'filled_divisions' => $filledDivisions,
        'filled_divisions_value' => $filledDivisionValue,
        'vacant_divisions' => $vacantDivisions,
        'vacant_divisions_value' => $vacantDivisionValue,
        'active_cps' => count($activeCpIds),
    ];

    $totalVacantValue += $vacantFirkaValue;
    if ($vacantFirkaValue > $maxVacantValue) {
        $maxVacantValue = $vacantFirkaValue;
        $maxVacantZone = $z['zone'];
    }
}

// ── 7. Company-wide roll-ups (sum of the 6 zone rows) ───────────────────
$sum = function(array $rows, string $key) {
    return array_sum(array_column($rows, $key));
};
$totalFirkas = $sum($tpRows, 'total_firkas');
$totalFirkaValue = $sum($tpRows, 'total_firkas_value');
$filledFirkas = $sum($tpRows, 'filled_firkas');
$filledFirkaValue = $sum($tpRows, 'filled_firkas_value');
$activeFirkas = $sum($tpRows, 'active_firkas');
$activeFirkaValue = $sum($tpRows, 'active_firkas_value');
$inactiveFirkas = $sum($tpRows, 'inactive_firkas');
$inactiveFirkaValue = $sum($tpRows, 'inactive_firkas_value');
$vacantFirkas = $sum($tpRows, 'vacant_firkas');
$vacantFirkaValue = $sum($tpRows, 'vacant_firkas_value');
$activeTps = $sum($tpRows, 'active_tps');

$totalDivisions = $sum($cpRows, 'total_divisions');
$totalDistricts = $sum($cpRows, 'total_districts');
$filledDivisions = $sum($cpRows, 'filled_divisions');
$filledDivisionValue = $sum($cpRows, 'filled_divisions_value');
$vacantDivisions = $sum($cpRows, 'vacant_divisions');
$vacantDivisionValue = $sum($cpRows, 'vacant_divisions_value');
$activeCps = $sum($cpRows, 'active_cps');

$pct = function($part, $total) { return $total > 0 ? round($part / $total * 100, 1) : 0; };

$tpTotals = [
    'total_firkas' => $totalFirkas, 'total_firkas_value' => $totalFirkaValue,
    'filled_firkas' => $filledFirkas, 'filled_firkas_value' => $filledFirkaValue,
    'active_firkas' => $activeFirkas, 'active_firkas_value' => $activeFirkaValue,
    'inactive_firkas' => $inactiveFirkas, 'inactive_firkas_value' => $inactiveFirkaValue,
    'active_tps' => $activeTps,
    'vacant_firkas' => $vacantFirkas, 'vacant_firkas_value' => $vacantFirkaValue,
];
$cpTotals = [
    'total_districts' => $totalDistricts, 'total_divisions' => $totalDivisions,
    'filled_divisions' => $filledDivisions, 'filled_divisions_value' => $filledDivisionValue,
    'vacant_divisions' => $vacantDivisions, 'vacant_divisions_value' => $vacantDivisionValue,
    'active_cps' => $activeCps,
];

// ── 8. Chart data ────────────────────────────────────────────────────────
$vacantChartRows = $tpRows;
usort($vacantChartRows, function($a, $b) { return $b['vacant_firkas_value'] <=> $a['vacant_firkas_value']; });

// ── 9. Insights (numbers computed from real data; two closing lines are
//      static encouragement text per spec) ─────────────────────────────
$insights = [];
$insights[] = "Total {$vacantFirkas} Firkas are vacant with a business potential of ₹" . number_format($vacantFirkaValue, 0) . ".";
$insights[] = "{$vacantDivisions} Channel Partner Divisions are vacant with a potential of ₹" . number_format($vacantDivisionValue, 0) . ".";
if ($maxVacantZone !== null && $maxVacantValue > 0) {
    $insights[] = "{$maxVacantZone} has the highest vacant business potential of ₹" . number_format($maxVacantValue, 0) . ".";
}
$insights[] = "Focus on improving Territory Partner onboarding in all zones.";
$insights[] = "Strengthen Channel Partner network to unlock full business potential.";

echo json_encode([
    'date_label' => date('M-d,Y'),
    'kpis' => [
        'total_firkas' => $totalFirkas, 'total_firkas_value' => $totalFirkaValue,
        'filled_firkas' => $filledFirkas, 'filled_firkas_pct' => $pct($filledFirkas, $totalFirkas), 'filled_firkas_value' => $filledFirkaValue,
        'active_firkas' => $activeFirkas, 'active_firkas_value' => $activeFirkaValue,
        'inactive_firkas' => $inactiveFirkas, 'inactive_firkas_value' => $inactiveFirkaValue,
        'vacant_firkas' => $vacantFirkas, 'vacant_firkas_pct' => $pct($vacantFirkas, $totalFirkas), 'vacant_firkas_value' => $vacantFirkaValue,
        'active_tps' => $activeTps,
        'total_divisions' => $totalDivisions, 'total_districts' => $totalDistricts,
        'filled_divisions' => $filledDivisions, 'filled_divisions_pct' => $pct($filledDivisions, $totalDivisions), 'filled_divisions_value' => $filledDivisionValue,
        'vacant_divisions' => $vacantDivisions, 'vacant_divisions_pct' => $pct($vacantDivisions, $totalDivisions), 'vacant_divisions_value' => $vacantDivisionValue,
        'active_cps' => $activeCps,
    ],
    'team' => array_values($zoneBdms),
    'team_overview' => [
        'zones' => count($zoneBdms),
        'members' => count($zoneBdms),
        'total_firkas' => $totalFirkas,
        'active_tps' => $activeTps,
        'total_divisions' => $totalDivisions,
        'active_cps' => $activeCps,
    ],
    'tp_table' => $tpRows,
    'tp_totals' => $tpTotals,
    'cp_table' => $cpRows,
    'cp_totals' => $cpTotals,
    'chart_vacant_business' => [
        'labels' => array_column($vacantChartRows, 'zone'),
        'data' => array_map(fn($r) => round($r['vacant_firkas_value'], 2), $vacantChartRows),
        'total' => $totalVacantValue,
    ],
    'chart_firka_status' => ['filled' => $filledFirkas, 'vacant' => $vacantFirkas],
    'chart_division_status' => ['filled' => $filledDivisions, 'vacant' => $vacantDivisions],
    'insights' => $insights,
]);
