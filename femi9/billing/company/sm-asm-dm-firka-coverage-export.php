<?php
// SM → ASM → District Manager → District firka-coverage report — for
// EVERY district (regardless of whether it has an ASM, a District
// Manager, both, or neither assigned), how many of that district's firkas
// (leaf location nodes) have a Territory Partner assigned ("filled") vs
// none at all ("vacant"), and among filled firkas, whether that TP is
// active or inactive/deleted. One row per district.
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

// One row per FIRKA (partner_location_nodes depth=6) — not per district —
// so every firka's own name is visible, whichever of the three buckets it
// falls in: filled by an active TP, filled by an inactive/deleted TP, or
// vacant. Rebuilt 2026-09-25 from the earlier per-district totals-only
// version per request: counts alone didn't say WHICH firkas were vacant or
// which TP had gone inactive, only how many.
//
// EVERY district still shows up (even ones with no ASM/DM/firka structure
// at all — confirmed 2026-09-25 that the earlier DM-anchored version
// silently dropped 24 such districts) via the same LEFT JOIN chain; a
// district with genuinely no firka nodes gets one placeholder row instead
// of vanishing. ASM and District Manager are each resolved independently
// by looking up who (if anyone) is directly assigned to this district at
// that level via marketing_staff_locations — NOT by walking a DM's own
// manager_id, since that chain can be broken (e.g. a DM whose manager_id
// points at a deleted/non-existent staff row, as found for CHENNAI's
// Karthiban) and would otherwise silently hide a real ASM assignment or
// fabricate a wrong one. SM is only resolved when this district's own ASM
// exists (SM = that ASM's manager) — with no ASM directly on this
// district, there's no reliable chain to a SM, so it's left blank rather
// than guessed.
//
// Every district's location tree reaches a uniform District(3) ->
// Division(4) -> Taluk(5) -> Firka(6) depth, confirmed against live data —
// no district stops short at Taluk, so depth=6 is safe to treat as "the
// firka level" everywhere, not just the Erode-style clusters this concept
// originated in. territory_partner_locations.location_id is always a
// depth-6 firka node, and is UNIQUE per firka (one TP slot per firka) — so
// a firka is "filled" exactly when a row exists there, "vacant" when it
// doesn't.
$sql = "
    SELECT
        sm.ms_name         AS sm_name,
        asm_direct.ms_name AS asm_name,
        dm_direct.ms_name  AS dm_name,
        d.name             AS district_name,
        dv.name            AS division_name,
        t.name             AS taluk_name,
        f.name             AS firka_name,
        tp.name            AS tp_name,
        tp.is_active       AS tp_is_active,
        tp.deleted_at      AS tp_deleted_at,
        (tpl.location_id IS NOT NULL) AS is_filled
    FROM partner_location_nodes d
    LEFT JOIN partner_location_nodes dv ON dv.parent_id = d.id AND dv.depth = 4
    LEFT JOIN partner_location_nodes t  ON t.parent_id  = dv.id AND t.depth  = 5
    LEFT JOIN partner_location_nodes f  ON f.parent_id  = t.id  AND f.depth  = 6
    LEFT JOIN territory_partner_locations tpl ON tpl.location_id = f.id
    LEFT JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
    LEFT JOIN (
        SELECT msl.location_id, ms.id, ms.ms_name, ms.manager_id
        FROM marketing_staff_locations msl
        INNER JOIN marketing_staff ms ON ms.id = msl.ms_id
        INNER JOIN marketing_team_levels mtl ON mtl.id = ms.team_level_id AND mtl.level_name = 'Assistant Sales Manager (ASM)'
        WHERE ms.deleted_at IS NULL
    ) asm_direct ON asm_direct.location_id = d.id
    LEFT JOIN (
        SELECT msl.location_id, ms.id, ms.ms_name
        FROM marketing_staff_locations msl
        INNER JOIN marketing_staff ms ON ms.id = msl.ms_id
        INNER JOIN marketing_team_levels mtl ON mtl.id = ms.team_level_id AND mtl.level_name = 'District Manager'
        WHERE ms.deleted_at IS NULL
    ) dm_direct ON dm_direct.location_id = d.id
    LEFT JOIN marketing_staff sm ON sm.id = asm_direct.manager_id AND sm.deleted_at IS NULL
    WHERE d.depth = 3
      AND d.parent_id = (SELECT id FROM partner_location_nodes WHERE depth = 2 AND name = 'Tamilnadu' LIMIT 1)
    ORDER BY sm_name, asm_name, dm_name, district_name, division_name, taluk_name, firka_name
";
$firkaRows = $db_conn->query($sql)->fetch_all(MYSQLI_ASSOC);

ob_end_clean();

// Re-grouped back to one row per district (SM/ASM/DM/District), but unlike
// the original counts-only version, each bucket now carries its own firka
// NAME list, not just a count — rebuilt 2026-09-25 per request: separate,
// named Vacant / Active / Inactive columns instead of one combined
// per-firka Status column (tried first, but wanted split back out).
// Grouped in PHP (not SQL GROUP_CONCAT) so a large district's name list
// (e.g. Chennai's 115 firkas) is never silently truncated by
// group_concat_max_len.
$districts = [];
$districtOrder = [];
foreach ($firkaRows as $r) {
    $key = ($r['sm_name'] ?? '') . '|' . ($r['asm_name'] ?? '') . '|' . ($r['dm_name'] ?? '') . '|' . $r['district_name'];
    if (!isset($districts[$key])) {
        $districts[$key] = [
            'sm_name' => $r['sm_name'], 'asm_name' => $r['asm_name'], 'dm_name' => $r['dm_name'],
            'district_name' => $r['district_name'],
            'vacant_names' => [], 'active_names' => [], 'inactive_names' => [],
        ];
        $districtOrder[] = $key;
    }
    if ($r['firka_name'] === null) continue; // district with no firka structure at all
    if (!$r['is_filled']) {
        $districts[$key]['vacant_names'][] = $r['firka_name'];
    } elseif ((int) $r['tp_is_active'] === 1 && $r['tp_deleted_at'] === null) {
        $districts[$key]['active_names'][] = $r['firka_name'];
    } else {
        $districts[$key]['inactive_names'][] = $r['firka_name'];
    }
}
$rows = array_map(fn($key) => $districts[$key], $districtOrder);

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('SM-ASM-DM Firka Coverage');

    $today = date('Y-m-d');
    $sheet->setCellValue('A1', 'SM / ASM / District Manager — Firka Coverage — Tamil Nadu (as of ' . $today . ')');
    $sheet->mergeCells('A1:N1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $headerRow = 3;
    $columns = [
        'SM', 'ASM', 'District Manager', 'District',
        'Vacant Firkas', 'Vacant Firka Names',
        'Filled Firkas', 'Filled Firka Names',
        'Active Firkas', 'Active Firka Names',
        'Inactive Firkas', 'Inactive Firka Names',
    ];
    $col = 1;
    foreach ($columns as $c) { xlsx_set($sheet, $col, $headerRow, $c); $col++; }
    $lastCol = $col - 1;
    $headerRange = Coordinate::stringFromColumnIndex(1) . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    // Vacant/Filled/Active/Inactive name-list columns hold a long
    // comma-joined string — wrap so a big district (e.g. Chennai's 51
    // vacant firkas) doesn't produce one unreadably wide cell.
    foreach (['F', 'H', 'J', 'L'] as $wrapCol) {
        $sheet->getStyle($wrapCol . ':' . $wrapCol)->getAlignment()->setWrapText(true);
    }

    $row = $headerRow + 1;
    foreach ($rows as $r) {
        // "Filled" = Active + Inactive together — every firka with a TP
        // assigned to it, regardless of that TP's current status. Added
        // 2026-09-25 alongside the existing Active/Inactive split, which on
        // its own didn't answer "how many firkas are filled overall."
        $filledNames = array_merge($r['active_names'], $r['inactive_names']);

        xlsx_set($sheet, 1, $row, $r['sm_name'] ?: '—');
        xlsx_set($sheet, 2, $row, $r['asm_name'] ?: '—');
        xlsx_set($sheet, 3, $row, $r['dm_name'] ?: '—');
        xlsx_set($sheet, 4, $row, $r['district_name']);
        xlsx_set($sheet, 5, $row, count($r['vacant_names']));
        xlsx_set($sheet, 6, $row, implode(', ', $r['vacant_names']) ?: '—');
        xlsx_set($sheet, 7, $row, count($filledNames));
        xlsx_set($sheet, 8, $row, implode(', ', $filledNames) ?: '—');
        xlsx_set($sheet, 9, $row, count($r['active_names']));
        xlsx_set($sheet, 10, $row, implode(', ', $r['active_names']) ?: '—');
        xlsx_set($sheet, 11, $row, count($r['inactive_names']));
        xlsx_set($sheet, 12, $row, implode(', ', $r['inactive_names']) ?: '—');
        $sheet->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE'); // Vacant count - red
        $sheet->getStyle('G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Filled count - blue
        $sheet->getStyle('I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE'); // Active count - green
        $sheet->getStyle('K' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEB9C'); // Inactive count - amber
        $row++;
    }

    if ($row > $headerRow + 1) {
        $dataRange = Coordinate::stringFromColumnIndex(1) . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    }

    foreach ([1, 2, 3, 4, 5, 7, 9, 11] as $c) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
    }
    foreach ([6, 8, 10, 12] as $c) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(60);
    }

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
