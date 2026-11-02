<?php
// One Excel sheet per Sales BDM — their assigned District(s), every Firka
// under those districts with its Target Amount, and whichever Territory
// Partner (if any) covers that Firka (name/phone/address). Mirrors
// district-dm-asm-firka-export.php's hierarchy-walk logic, swapped to
// salesbdm_locations/sales_bdm_staff instead of marketing_staff, and
// reshaped to one sheet per person instead of one flat company-wide sheet.
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

// ---- Load every active BDM -------------------------------------------
$bdms = [];
$res = $db_conn->query("
    SELECT id, bdm_name, bdm_mobile
    FROM sales_bdm_staff
    WHERE account_status = 'active'
    ORDER BY bdm_name ASC
");
while ($row = $res->fetch_assoc()) {
    $bdms[(int)$row['id']] = [
        'name'   => trim($row['bdm_name']),
        'mobile' => $row['bdm_mobile'],
    ];
}

// ---- Load the whole active location tree once -------------------------
$allNodes = [];
$locRes = $db_conn->query("SELECT id, parent_id, depth, name FROM partner_location_nodes WHERE is_active = 1");
while ($row = $locRes->fetch_assoc()) {
    $allNodes[(int)$row['id']] = [
        'id'        => (int)$row['id'],
        'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
        'depth'     => (int)$row['depth'],
        'name'      => $row['name'],
    ];
}

// Collapse any assigned node (State/District/Division/Taluk) down to the
// District(s) it covers — same convention
// marketing/include/AssignedLocations.php's getMsAssignedDistricts() uses.
function districtsForAssignment(array $allNodes, int $locId): array {
    if (!isset($allNodes[$locId])) return [];
    $node = $allNodes[$locId];
    if ($node['depth'] === 3) return [$locId];
    if ($node['depth'] === 2) {
        $out = [];
        foreach ($allNodes as $nId => $n) {
            if ($n['depth'] === 3 && $n['parent_id'] === $locId) $out[] = $nId;
        }
        return $out;
    }
    // DIVISION/TALUK/FIRKA or anything deeper — walk up to the nearest DISTRICT.
    $cur = $node;
    while ($cur !== null && $cur['depth'] !== 3) {
        $cur = $cur['parent_id'] !== null ? ($allNodes[$cur['parent_id']] ?? null) : null;
    }
    return $cur !== null ? [$cur['id']] : [];
}

// ---- Assigned districts per BDM ----------------------------------------
$districtsByBdm = []; // bdm_id => [district_id => true]
$assignRes = $db_conn->query("SELECT bdm_id, location_id FROM salesbdm_locations");
while ($row = $assignRes->fetch_assoc()) {
    $bdmId = (int)$row['bdm_id'];
    if (!isset($bdms[$bdmId])) continue;
    foreach (districtsForAssignment($allNodes, (int)$row['location_id']) as $distId) {
        $districtsByBdm[$bdmId][$distId] = true;
    }
}

// ---- Firka rows (District -> Division -> Taluk -> Firka), with TP ------
$firkaRows = []; // district_id => [ [firka_name, target, tp_name, tp_mobile, tp_address], ... ]
$frRes = $db_conn->query("
    SELECT d.id AS district_id, d.name AS district_name, f.name AS firka_name,
           COALESCE(f.target_amount, 0) + COALESCE(f.diaper_target_amount, 0) AS firka_target,
           tp.name AS tp_name, tp.mobile AS tp_mobile,
           tp.branch_line1, tp.branch_line2, tp.branch_city, tp.branch_state, tp.branch_pincode
    FROM partner_location_nodes d
    LEFT JOIN partner_location_nodes dv ON dv.parent_id = d.id AND dv.depth = 4
    LEFT JOIN partner_location_nodes t  ON t.parent_id  = dv.id AND t.depth  = 5
    LEFT JOIN partner_location_nodes f  ON f.parent_id  = t.id  AND f.depth  = 6
    LEFT JOIN territory_partner_locations tpl ON tpl.location_id = f.id
    LEFT JOIN territory_partners tp ON tp.id = tpl.territory_partner_id AND tp.is_active = 1 AND tp.deleted_at IS NULL
    WHERE d.depth = 3
    ORDER BY d.name, f.name
");
while ($row = $frRes->fetch_assoc()) {
    if ($row['firka_name'] === null) continue;
    $addrParts = array_filter([$row['branch_line1'], $row['branch_line2'], $row['branch_city'], $row['branch_state'], $row['branch_pincode']]);
    $firkaRows[(int)$row['district_id']][] = [
        'district'   => $row['district_name'],
        'firka'      => $row['firka_name'],
        'target'     => (float)$row['firka_target'],
        'tp_name'    => $row['tp_name'] ?? 'Vacant Area',
        'tp_mobile'  => $row['tp_mobile'] ?? '',
        'tp_address' => $addrParts ? implode(', ', $addrParts) : '',
    ];
}

// ============================================================================
// SPREADSHEET — one sheet per BDM
// ============================================================================
require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php'; \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$headers = ['S.No', 'District', 'Firka', 'Target Amount', 'TP Name', 'TP Phone Number', 'TP Address'];
$sheetIndex = 0;
$usedTitles = [];

foreach ($bdms as $bdmId => $bdm) {
    $districts = array_keys($districtsByBdm[$bdmId] ?? []);
    if (empty($districts)) continue; // skip BDMs with nothing assigned

    $title = $bdm['name'] !== '' ? $bdm['name'] : ('BDM ' . $bdmId);
    $title = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $title);
    $title = mb_substr($title, 0, 28);
    $baseTitle = $title;
    $n = 2;
    while (isset($usedTitles[$title])) { $title = mb_substr($baseTitle, 0, 25) . ' (' . $n . ')'; $n++; }
    $usedTitles[$title] = true;

    $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $title);
    $spreadsheet->addSheet($sheet, $sheetIndex++);

    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', $bdm['name'] . ' (' . $bdm['mobile'] . ') — Assigned Territory | ' . date('d M Y'));
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF667EEA']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(28);

    $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
    foreach ($headers as $i => $label) {
        $sheet->setCellValue($cols[$i] . '2', $label);
    }
    $sheet->getStyle('A2:G2')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
    ]);

    // Collect this BDM's rows across all their districts, then sort so
    // filled (has a TP) rows come first, grouped by district/firka name,
    // with every Vacant Area row pushed to the bottom of the sheet instead
    // of interspersed district-by-district — easier to scan at a glance.
    $bdmRows = [];
    foreach ($districts as $distId) {
        foreach (($firkaRows[$distId] ?? []) as $r) { $bdmRows[] = $r; }
    }
    usort($bdmRows, function ($a, $b) {
        $aVacant = $a['tp_name'] === 'Vacant Area' ? 1 : 0;
        $bVacant = $b['tp_name'] === 'Vacant Area' ? 1 : 0;
        if ($aVacant !== $bVacant) return $aVacant <=> $bVacant;
        $cmp = strcmp($a['district'], $b['district']);
        return $cmp !== 0 ? $cmp : strcmp($a['firka'], $b['firka']);
    });

    $rowNum = 3;
    $sno = 1;
    foreach ($bdmRows as $r) {
        $sheet->setCellValue("A{$rowNum}", $sno++);
        $sheet->setCellValue("B{$rowNum}", $r['district']);
        $sheet->setCellValue("C{$rowNum}", $r['firka']);
        $sheet->setCellValue("D{$rowNum}", $r['target']);
        $sheet->setCellValue("E{$rowNum}", $r['tp_name']);
        // Phone number as an explicit text cell -- otherwise Excel's General
        // format renders a 10-digit number in scientific notation (9.84E+09).
        $sheet->setCellValueExplicit("F{$rowNum}", $r['tp_mobile'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue("G{$rowNum}", $r['tp_address']);

        $bgColor = (($rowNum - 2) % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
        $sheet->getStyle("A{$rowNum}:G{$rowNum}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("D{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("G{$rowNum}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($rowNum)->setRowHeight(32);
        if ($r['tp_name'] === 'Vacant Area') {
            $sheet->getStyle("E{$rowNum}")->getFont()->setItalic(true)->getColor()->setARGB('FFB45309');
        }
        $rowNum++;
    }

    if ($rowNum === 3) {
        $sheet->setCellValue('A3', 'No Firkas found under this BDM\'s assigned district(s).');
        $sheet->mergeCells('A3:G3');
    }

    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(24);
    $sheet->getColumnDimension('D')->setWidth(16);
    $sheet->getColumnDimension('E')->setWidth(24);
    $sheet->getColumnDimension('F')->setWidth(16);
    $sheet->getColumnDimension('G')->setWidth(55);
    $sheet->getStyle('A3:G' . ($rowNum - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("A3:A" . ($rowNum - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("D3:D" . ($rowNum - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("F3:F" . ($rowNum - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->freezePane('A3');
    $sheet->setAutoFilter('A2:G2');
}

if ($sheetIndex === 0) {
    $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'No Data');
    $spreadsheet->addSheet($sheet, 0);
    $sheet->setCellValue('A1', 'No BDMs with assigned districts found.');
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'salesbdm_tp_coverage_' . date('Y-m-d') . '.xlsx';

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
