<?php
// SM / ASM / District Manager — Firka Coverage (Tamil Nadu) — one row per
// district: who owns it (District Manager, walked up via manager_id to
// ASM and SM -- marketing_staff's own hierarchy, see include/
// MsShopCoverage.php's getMsDistrictMap()/getMsShopCoverageReport() for
// the same rollup pattern used elsewhere), how many of its firkas (leaf
// location nodes) are Vacant/Filled/Active/Inactive, each bucket's own
// Target Amount (Napkin target_amount + Diaper diaper_target_amount,
// summed across that bucket's firkas), and the actual firka names in
// each bucket.
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

@include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
@include("config.php");

if (!isset($_SESSION) || !isset($db_conn)) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Session or database error. Please try again.";
    exit;
}

try {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Excel library not found. Please install PhpSpreadsheet.";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function xlsx_set(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colIndex, int $row, $value): void {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . $row, $value);
}

// Matches the reference file's own convention: an empty bucket (e.g. no
// Inactive firkas in this district) shows "—", not a blank cell.
function namesOrDash(array $names): string {
    return empty($names) ? '—' : implode(', ', $names);
}

// ---- Firka-level coverage data (same source query as before) ----------
$sql = "
    SELECT
        d.name             AS district_name,
        f.name             AS firka_name,
        COALESCE(f.target_amount, 0) + COALESCE(f.diaper_target_amount, 0) AS firka_target,
        tp.is_active       AS tp_is_active,
        tp.deleted_at      AS tp_deleted_at,
        (tpl.location_id IS NOT NULL) AS is_filled
    FROM partner_location_nodes d
    LEFT JOIN partner_location_nodes dv ON dv.parent_id = d.id AND dv.depth = 4
    LEFT JOIN partner_location_nodes t  ON t.parent_id  = dv.id AND t.depth  = 5
    LEFT JOIN partner_location_nodes f  ON f.parent_id  = t.id  AND f.depth  = 6
    LEFT JOIN territory_partner_locations tpl ON tpl.location_id = f.id
    LEFT JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
    WHERE d.depth = 3
      AND d.parent_id = (SELECT id FROM partner_location_nodes WHERE depth = 2 AND name = 'Tamilnadu' LIMIT 1)
    ORDER BY district_name, firka_name
";
$firkaRows = $db_conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// ---- SM / ASM / District Manager resolution ----------------------------
// Same hierarchy marketing_staff already encodes: manager_id walks up,
// marketing_team_levels.level_rank numbers the tiers (1=SM, 2=ASM,
// 3=District Manager as currently configured -- resolved by rank number,
// not hardcoded level text, so a future re-numbering still works).
$staffById = [];
$staffRes = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id, tl.level_rank
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
");
while ($row = $staffRes->fetch_assoc()) {
    $staffById[(int) $row['id']] = [
        'name'       => trim($row['ms_name']),
        'manager_id' => $row['manager_id'] !== null ? (int) $row['manager_id'] : null,
        'level_rank' => $row['level_rank'] !== null ? (int) $row['level_rank'] : null,
    ];
}

// Every location node, to resolve an assignment (which can point at a
// STATE/DISTRICT/TALUK/FIRKA node) up to its owning district -- same
// resolution rule as getMsDistrictMap()/AssignedLocations.php.
$allLocNodes = [];
$locRes = $db_conn->query("SELECT id, parent_id, depth, name FROM partner_location_nodes WHERE is_active = 1");
while ($row = $locRes->fetch_assoc()) {
    $allLocNodes[(int) $row['id']] = [
        'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
        'depth'     => (int) $row['depth'],
    ];
}
$districtForNode = function (int $locId) use ($allLocNodes): ?int {
    if (!isset($allLocNodes[$locId])) return null;
    $cur = $allLocNodes[$locId];
    $curId = $locId;
    if ($cur['depth'] === 2) return null; // STATE-level -- no single district
    while ($cur !== null && $cur['depth'] !== 3) {
        if ($cur['parent_id'] === null) return null;
        $curId = $cur['parent_id'];
        $cur = $allLocNodes[$curId] ?? null;
    }
    return $cur !== null ? $curId : null;
};

// district node id -> [ms_id, ...] of everyone with any assignment
// resolving to that district (could be SM/ASM/DM level directly).
$assignedByDistrictId = [];
$assignRes = $db_conn->query("SELECT ms_id, location_id FROM marketing_staff_locations");
while ($row = $assignRes->fetch_assoc()) {
    $distId = $districtForNode((int) $row['location_id']);
    if ($distId !== null) {
        $assignedByDistrictId[$distId][] = (int) $row['ms_id'];
    }
}

// Maps a district's own node id -> ['sm' => name|null, 'asm' => ..., 'dm' => ...].
// Picks the most specific (highest level_rank) assigned staff as the
// district's direct owner, then walks manager_id up for the other tiers
// -- so a district assigned straight to an ASM (no DM underneath) shows
// District Manager "—", matching real data (e.g. KANCHIPURAM below).
function resolveHierarchy(array $msIds, array $staffById): array {
    $result = ['sm' => null, 'asm' => null, 'dm' => null];
    $best = null;
    foreach ($msIds as $id) {
        if (!isset($staffById[$id]) || $staffById[$id]['level_rank'] === null) continue;
        if ($best === null || $staffById[$id]['level_rank'] > $staffById[$best]['level_rank']) {
            $best = $id;
        }
    }
    if ($best === null) return $result;

    $rank = $staffById[$best]['level_rank'];
    $chain = []; // rank => name, walking up from $best
    $cur = $best;
    $depth = 0;
    while ($cur !== null && isset($staffById[$cur]) && $depth < 10) {
        $r = $staffById[$cur]['level_rank'];
        if ($r !== null) { $chain[$r] = $staffById[$cur]['name']; }
        $cur = $staffById[$cur]['manager_id'];
        $depth++;
    }
    $result['dm']  = $chain[3] ?? null;
    $result['asm'] = $chain[2] ?? null;
    $result['sm']  = $chain[1] ?? null;
    return $result;
}

ob_end_clean();

// ---- Group firka rows by district, compute buckets + amounts ----------
$districtNodeIds = [];
$distNodeRes = $db_conn->query("
    SELECT id, name FROM partner_location_nodes
    WHERE depth = 3 AND parent_id = (SELECT id FROM partner_location_nodes WHERE depth = 2 AND name = 'Tamilnadu' LIMIT 1)
");
while ($row = $distNodeRes->fetch_assoc()) { $districtNodeIds[$row['name']] = (int) $row['id']; }

$districts = [];
$districtOrder = [];
foreach ($firkaRows as $r) {
    $key = $r['district_name'];
    if (!isset($districts[$key])) {
        $districts[$key] = [
            'district_name' => $key,
            'total' => 0, 'total_amt' => 0.0, 'total_names' => [],
            'vacant' => 0, 'vacant_amt' => 0.0, 'vacant_names' => [],
            'filled' => 0, 'filled_amt' => 0.0, 'filled_names' => [],
            'active' => 0, 'active_amt' => 0.0, 'active_names' => [],
            'inactive' => 0, 'inactive_amt' => 0.0, 'inactive_names' => [],
        ];
        $districtOrder[] = $key;
    }
    if ($r['firka_name'] === null) continue; // district with no firka structure at all

    $target = (float) $r['firka_target'];
    $isActive = ((int) $r['tp_is_active'] === 1 && $r['tp_deleted_at'] === null);

    $districts[$key]['total']++;
    $districts[$key]['total_amt'] += $target;
    $districts[$key]['total_names'][] = $r['firka_name'];

    if (!$r['is_filled']) {
        $districts[$key]['vacant']++;
        $districts[$key]['vacant_amt'] += $target;
        $districts[$key]['vacant_names'][] = $r['firka_name'];
    } else {
        $districts[$key]['filled']++;
        $districts[$key]['filled_amt'] += $target;
        $districts[$key]['filled_names'][] = $r['firka_name'];
        if ($isActive) {
            $districts[$key]['active']++;
            $districts[$key]['active_amt'] += $target;
            $districts[$key]['active_names'][] = $r['firka_name'];
        } else {
            $districts[$key]['inactive']++;
            $districts[$key]['inactive_amt'] += $target;
            $districts[$key]['inactive_names'][] = $r['firka_name'];
        }
    }
}

$rows = [];
foreach ($districtOrder as $key) {
    $d = $districts[$key];
    $nodeId = $districtNodeIds[$key] ?? null;
    $hierarchy = $nodeId !== null && isset($assignedByDistrictId[$nodeId])
        ? resolveHierarchy($assignedByDistrictId[$nodeId], $staffById)
        : ['sm' => null, 'asm' => null, 'dm' => null];

    $rows[] = array_merge($d, [
        'sm'  => $hierarchy['sm']  ?? '—',
        'asm' => $hierarchy['asm'] ?? '—',
        'dm'  => $hierarchy['dm']  ?? '—',
    ]);
}

// One sheet per ASM -- districts with no ASM resolved (e.g. "Modern
// Trade", or a district assigned straight to an SM with no ASM beneath
// them) go into a trailing "Unassigned" sheet instead of being dropped.
$rowsByAsm = [];
$asmOrder = [];
foreach ($rows as $r) {
    $key = ($r['asm'] !== '—' && $r['asm'] !== null && $r['asm'] !== '') ? $r['asm'] : 'Unassigned';
    if (!isset($rowsByAsm[$key])) { $rowsByAsm[$key] = []; $asmOrder[] = $key; }
    $rowsByAsm[$key][] = $r;
}
sort($asmOrder, SORT_STRING | SORT_FLAG_CASE);
$asmOrder = array_values(array_filter($asmOrder, fn($k) => $k !== 'Unassigned'));
$asmOrder[] = 'Unassigned'; // always last, if present

// Excel sheet names: max 31 chars, no : \ / ? * [ ], and must be unique
// (two ASMs sharing a name after truncation get a numeric suffix).
function excelSafeSheetName(string $name, array &$usedNames): string {
    $clean = preg_replace('/[:\\\\\/\?\*\[\]]/', ' ', $name);
    $clean = trim($clean);
    if ($clean === '') { $clean = 'Sheet'; }
    $base = mb_substr($clean, 0, 31);
    $final = $base;
    $n = 2;
    while (isset($usedNames[mb_strtolower($final)])) {
        $suffix = ' (' . $n . ')';
        $final = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
        $n++;
    }
    $usedNames[mb_strtolower($final)] = true;
    return $final;
}

function writeCoverageSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $sheetHeading, array $rows, string $today): void {
    $sheet->setCellValue('A1', $sheetHeading . ' — Tamil Nadu (as of ' . $today . ')');

    $headerRow = 3;
    $columns = [
        'SM', 'ASM', 'District Manager', 'District',
        'Total Firkas', 'Total Amount', 'Total Firka Names',
        'Vacant Firkas', 'Vacant Amount', 'Vacant Firka Names',
        'Filled Firkas', 'Filled Amount', 'Filled Firka Names',
        'Active Firkas', 'Active Amount', 'Active Firka Names',
        'Inactive Firkas', 'Inactive Amount', 'Inactive Firka Names',
    ];
    $col = 1;
    foreach ($columns as $c) { xlsx_set($sheet, $col, $headerRow, $c); $col++; }
    $lastCol = $col - 1;
    $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex($lastCol) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $headerRange = Coordinate::stringFromColumnIndex(1) . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    $countCols  = [5, 8, 11, 14, 17];  // Total/Vacant/Filled/Active/Inactive Firkas
    $amountCols = [6, 9, 12, 15, 18];  // their matching Amount columns
    $colors = ['D9D9D9', 'FFC7CE', 'BDD7EE', 'C6EFCE', 'FFEB9C']; // grey/red/blue/green/amber

    $row = $headerRow + 1;
    foreach ($rows as $r) {
        xlsx_set($sheet, 1, $row, $r['sm']);
        xlsx_set($sheet, 2, $row, $r['asm']);
        xlsx_set($sheet, 3, $row, $r['dm']);
        xlsx_set($sheet, 4, $row, $r['district_name']);

        xlsx_set($sheet, 5,  $row, $r['total']);
        xlsx_set($sheet, 6,  $row, $r['total_amt']);
        xlsx_set($sheet, 7,  $row, namesOrDash($r['total_names']));
        xlsx_set($sheet, 8,  $row, $r['vacant']);
        xlsx_set($sheet, 9,  $row, $r['vacant_amt']);
        xlsx_set($sheet, 10, $row, namesOrDash($r['vacant_names']));
        xlsx_set($sheet, 11, $row, $r['filled']);
        xlsx_set($sheet, 12, $row, $r['filled_amt']);
        xlsx_set($sheet, 13, $row, namesOrDash($r['filled_names']));
        xlsx_set($sheet, 14, $row, $r['active']);
        xlsx_set($sheet, 15, $row, $r['active_amt']);
        xlsx_set($sheet, 16, $row, namesOrDash($r['active_names']));
        xlsx_set($sheet, 17, $row, $r['inactive']);
        xlsx_set($sheet, 18, $row, $r['inactive_amt']);
        xlsx_set($sheet, 19, $row, namesOrDash($r['inactive_names']));

        foreach ($countCols as $i => $ci) {
            $colLetter = Coordinate::stringFromColumnIndex($ci);
            $sheet->getStyle($colLetter . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colors[$i]);
        }
        foreach ($amountCols as $ci) {
            $sheet->getStyle(Coordinate::stringFromColumnIndex($ci) . $row)->getNumberFormat()->setFormatCode('#,##0');
        }
        $sheet->getStyle('A' . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $row++;
    }

    if ($row > $headerRow + 1) {
        $dataRange = 'A' . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    foreach (range(1, $lastCol) as $ci) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
    }
    // Name-list columns can get very wide with autosize -- cap them.
    foreach ([7, 10, 13, 16, 19] as $ci) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setAutoSize(false)->setWidth(60);
    }

    $sheet->freezePane('A' . ($headerRow + 1));
}

try {
    $today = date('Y-m-d');
    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0); // start empty, one sheet added per ASM below

    $usedSheetNames = [];
    foreach ($asmOrder as $asmName) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(excelSafeSheetName($asmName, $usedSheetNames));
        $heading = $asmName === 'Unassigned'
            ? 'SM / ASM / District Manager — Firka Coverage (No ASM Assigned)'
            : 'SM / ASM / District Manager — Firka Coverage — ASM: ' . $asmName;
        writeCoverageSheet($sheet, $heading, $rowsByAsm[$asmName], $today);
    }
    $spreadsheet->setActiveSheetIndex(0);

    $filename = "SM_ASM_DM_Firka_Coverage_{$today}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save('php://output');
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error generating Excel file: " . $e->getMessage();
    error_log("Excel generation error: " . $e->getMessage());
}

exit;
