<?php
// Flat, single-sheet District x DM/ASM x Firka coverage export — one row
// per Tamil Nadu district with its Target Amount, DM, ASM, and
// Total/Filled/Vacant firka counts + values. Same underlying district/
// firka/hierarchy resolution as sm-asm-dm-firka-coverage-export.php (see
// that file's own comments for the full rationale), just reshaped into the
// simpler flat column layout the user asked for instead of per-ASM sheets.
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_once __DIR__ . "/checksession.php";
require_once __DIR__ . "/config.php";

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;

// ---- Firka-level coverage data ------------------------------------------
$sql = "
    SELECT
        d.name             AS district_name,
        f.name             AS firka_name,
        COALESCE(f.target_amount, 0) + COALESCE(f.diaper_target_amount, 0) AS firka_target,
        (tpl.location_id IS NOT NULL) AS is_filled,
        tp.id              AS tp_id,
        tp.is_active       AS tp_is_active,
        tp.deleted_at      AS tp_deleted_at
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

// ---- DM / ASM resolution (same hierarchy walk as the per-ASM export) ---
$staffById = [];
$staffRes = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id, tl.level_rank
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
");
while ($row = $staffRes->fetch_assoc()) {
    $staffById[(int)$row['id']] = [
        'name'       => trim($row['ms_name']),
        'manager_id' => $row['manager_id'] !== null ? (int)$row['manager_id'] : null,
        'level_rank' => $row['level_rank'] !== null ? (int)$row['level_rank'] : null,
    ];
}

$allLocNodes = [];
$locRes = $db_conn->query("SELECT id, parent_id, depth FROM partner_location_nodes WHERE is_active = 1");
while ($row = $locRes->fetch_assoc()) {
    $allLocNodes[(int)$row['id']] = [
        'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
        'depth'     => (int)$row['depth'],
    ];
}
$districtForNode = function (int $locId) use ($allLocNodes): ?int {
    if (!isset($allLocNodes[$locId])) return null;
    $cur = $allLocNodes[$locId];
    $curId = $locId;
    if ($cur['depth'] === 2) return null;
    while ($cur !== null && $cur['depth'] !== 3) {
        if ($cur['parent_id'] === null) return null;
        $curId = $cur['parent_id'];
        $cur = $allLocNodes[$curId] ?? null;
    }
    return $cur !== null ? $curId : null;
};

$assignedByDistrictId = [];
$assignRes = $db_conn->query("SELECT ms_id, location_id FROM marketing_staff_locations");
while ($row = $assignRes->fetch_assoc()) {
    $distId = $districtForNode((int)$row['location_id']);
    if ($distId !== null) {
        $assignedByDistrictId[$distId][] = (int)$row['ms_id'];
    }
}

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
    $chain = [];
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

$districtNodeIds = [];
$distNodeRes = $db_conn->query("
    SELECT id, name FROM partner_location_nodes
    WHERE depth = 3 AND parent_id = (SELECT id FROM partner_location_nodes WHERE depth = 2 AND name = 'Tamilnadu' LIMIT 1)
");
while ($row = $distNodeRes->fetch_assoc()) { $districtNodeIds[$row['name']] = (int)$row['id']; }

// ---- Group firka rows by district, compute Total/Filled/Vacant ---------
$districts = [];
$districtOrder = [];
foreach ($firkaRows as $r) {
    $key = $r['district_name'];
    if (!isset($districts[$key])) {
        $districts[$key] = [
            'district_name' => $key,
            'total' => 0, 'total_amt' => 0.0,
            'filled' => 0, 'filled_amt' => 0.0,
            'vacant' => 0, 'vacant_amt' => 0.0,
            'active_tp_ids' => [], 'inactive_tp_ids' => [],
        ];
        $districtOrder[] = $key;
    }

    // TP active/inactive counts — tracked as id sets (not a running count)
    // so a TP assigned to 2+ firkas within the same district is still
    // counted once, same as a real headcount would read.
    if ($r['tp_id'] !== null) {
        $isActiveTp = ((int)$r['tp_is_active'] === 1 && $r['tp_deleted_at'] === null);
        if ($isActiveTp) {
            $districts[$key]['active_tp_ids'][(int)$r['tp_id']] = true;
        } else {
            $districts[$key]['inactive_tp_ids'][(int)$r['tp_id']] = true;
        }
    }

    if ($r['firka_name'] === null) continue;

    $target = (float)$r['firka_target'];
    $districts[$key]['total']++;
    $districts[$key]['total_amt'] += $target;

    if ($r['is_filled']) {
        $districts[$key]['filled']++;
        $districts[$key]['filled_amt'] += $target;
    } else {
        $districts[$key]['vacant']++;
        $districts[$key]['vacant_amt'] += $target;
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
        'dm'  => $hierarchy['dm']  ?? '—',
        'asm' => $hierarchy['asm'] ?? '—',
        'active_tp_count'   => count($d['active_tp_ids']),
        'inactive_tp_count' => count($d['inactive_tp_ids']),
    ]);
}

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('DM Areas');

$headers = [
    'A' => 'S.No', 'B' => 'District', 'C' => 'District Target Amount',
    'D' => 'DM', 'E' => 'ASM', 'F' => 'Active TPs', 'G' => 'Inactive TPs', 'H' => 'Total TPs',
    'I' => 'Total Firka',
    'J' => 'Filled Firkas', 'K' => 'Filled Value',
    'L' => 'Vacant Firkas', 'M' => 'Vacant Firkas Value',
];
$lastCol = 'M';

$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'District / DM / ASM / Firka Coverage — Tamil Nadu | ' . date('d M Y'));
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

foreach ($headers as $col => $label) {
    $sheet->setCellValue("{$col}2", $label);
}
$sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$rowNum = 3;
foreach ($rows as $i => $r) {
    $sheet->setCellValue("A{$rowNum}", $i + 1);
    $sheet->setCellValue("B{$rowNum}", $r['district_name']);
    $sheet->setCellValue("C{$rowNum}", $r['total_amt']);
    $sheet->setCellValue("D{$rowNum}", $r['dm']);
    $sheet->setCellValue("E{$rowNum}", $r['asm']);
    $sheet->setCellValue("F{$rowNum}", $r['active_tp_count']);
    $sheet->setCellValue("G{$rowNum}", $r['inactive_tp_count']);
    $sheet->setCellValue("H{$rowNum}", $r['active_tp_count'] + $r['inactive_tp_count']);
    $sheet->setCellValue("I{$rowNum}", $r['total']);
    $sheet->setCellValue("J{$rowNum}", $r['filled']);
    $sheet->setCellValue("K{$rowNum}", $r['filled_amt']);
    $sheet->setCellValue("L{$rowNum}", $r['vacant']);
    $sheet->setCellValue("M{$rowNum}", $r['vacant_amt']);

    $bgColor = (($rowNum - 2) % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);
    $sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("K{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("M{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("C{$rowNum}:M{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("F{$rowNum}")->getFont()->getColor()->setARGB('FF166534');
    $sheet->getStyle("G{$rowNum}")->getFont()->getColor()->setARGB('FF991B1B');
    $sheet->getStyle("H{$rowNum}")->getFont()->setBold(true);

    $rowNum++;
}

$totalsRow = $rowNum;
$dataStart = 3;
$dataEnd   = $rowNum - 1;
$sheet->setCellValue("A{$totalsRow}", 'TOTAL');
$sheet->mergeCells("A{$totalsRow}:B{$totalsRow}");
if ($dataEnd >= $dataStart) {
    $sheet->setCellValue("C{$totalsRow}", "=SUM(C{$dataStart}:C{$dataEnd})");
    $sheet->setCellValue("F{$totalsRow}", "=SUM(F{$dataStart}:F{$dataEnd})");
    $sheet->setCellValue("G{$totalsRow}", "=SUM(G{$dataStart}:G{$dataEnd})");
    $sheet->setCellValue("H{$totalsRow}", "=SUM(H{$dataStart}:H{$dataEnd})");
    $sheet->setCellValue("I{$totalsRow}", "=SUM(I{$dataStart}:I{$dataEnd})");
    $sheet->setCellValue("J{$totalsRow}", "=SUM(J{$dataStart}:J{$dataEnd})");
    $sheet->setCellValue("K{$totalsRow}", "=SUM(K{$dataStart}:K{$dataEnd})");
    $sheet->setCellValue("L{$totalsRow}", "=SUM(L{$dataStart}:L{$dataEnd})");
    $sheet->setCellValue("M{$totalsRow}", "=SUM(M{$dataStart}:M{$dataEnd})");
}
$sheet->getStyle("C{$totalsRow}:M{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle("C{$totalsRow}:M{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(16);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(12);
$sheet->getColumnDimension('G')->setWidth(12);
$sheet->getColumnDimension('H')->setWidth(12);
$sheet->getColumnDimension('I')->setWidth(12);
$sheet->getColumnDimension('J')->setWidth(14);
$sheet->getColumnDimension('K')->setWidth(14);
$sheet->getColumnDimension('L')->setWidth(14);
$sheet->getColumnDimension('M')->setWidth(16);

$sheet->freezePane('A3');
$sheet->setAutoFilter("A2:{$lastCol}2");

$filename = 'district_dm_asm_firka_coverage_' . date('Y-m-d') . '.xlsx';

if (ob_get_length()) { ob_end_clean(); }

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');
exit;
