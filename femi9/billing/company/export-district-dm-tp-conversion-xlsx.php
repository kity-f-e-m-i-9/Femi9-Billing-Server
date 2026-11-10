<?php
// Excel twin of district-dm-tp-conversion-report.php — same fixed period,
// same DM Get-Order-converted-value / TP shop-invoice-value math, one sheet
// with a District / DM / DM Value / TP / TP Value row layout (one row per
// District+DM+TP combination so every pairing is visible; blank DM/TP cells
// mean "none assigned").
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

require_once __DIR__ . '/../shared/db-helpers.php';
require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("config.php");
require_once __DIR__ . '/../marketing/include/MsDistrictScope.php';

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

$periodFrom = '2026-11-01';
$periodTo   = '2026-11-08';

$districtDepthRow = $db_conn->query(
    "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1"
)->fetch_assoc();
$districtDepth = (int)($districtDepthRow['depth'] ?? 3);

$districts = [];
$res = $db_conn->query("SELECT id, name FROM partner_location_nodes WHERE depth = $districtDepth AND is_active = 1 ORDER BY name ASC");
while ($row = $res->fetch_assoc()) { $districts[] = ['id' => (int)$row['id'], 'name' => $row['name']]; }

$dmsByDistrictId = [];
$resDm = $db_conn->query("
    SELECT d.id AS district_id, ms.id AS ms_id, ms.ms_name, msl.id AS assign_order
    FROM partner_location_nodes d
    JOIN marketing_staff_locations msl ON msl.location_id = d.id
    JOIN marketing_staff ms ON ms.id = msl.ms_id AND ms.deleted_at IS NULL
    JOIN marketing_team_levels tl ON tl.id = ms.team_level_id AND tl.level_name LIKE '%District%'
    WHERE d.depth = $districtDepth AND d.is_active = 1
");
while ($row = $resDm->fetch_assoc()) {
    $dmsByDistrictId[(int)$row['district_id']][] = ['id' => (int)$row['ms_id'], 'name' => $row['ms_name'], 'order' => (int)$row['assign_order']];
}

$allDmIds = [];
foreach ($dmsByDistrictId as $list) { foreach ($list as $dm) { $allDmIds[] = $dm['id']; } }
$allDmIds = array_values(array_unique($allDmIds));

// Re-order: grouped by DM, districts within one DM's group in the order
// that DM was actually assigned them (marketing_staff_locations.id), not
// alphabetically — per explicit request. No-DM districts go last, alpha.
$dmNameById = [];
foreach ($dmsByDistrictId as $list) { foreach ($list as $dm) { $dmNameById[$dm['id']] = $dm['name']; } }
usort($districts, function ($a, $b) use ($dmsByDistrictId, $dmNameById) {
    $dmA = $dmsByDistrictId[$a['id']][0] ?? null;
    $dmB = $dmsByDistrictId[$b['id']][0] ?? null;
    if ($dmA === null && $dmB === null) { return strcmp($a['name'], $b['name']); }
    if ($dmA === null) { return 1; }
    if ($dmB === null) { return -1; }
    $nameCmp = strcmp($dmNameById[$dmA['id']], $dmNameById[$dmB['id']]);
    if ($nameCmp !== 0) { return $nameCmp; }
    return $dmA['order'] <=> $dmB['order'];
});

$convertedAmtByDm = array_fill_keys($allDmIds, 0.0);
if (!empty($allDmIds)) {
    $dmIdListSql = implode(',', $allDmIds);
    $res2 = $db_conn->query("
        SELECT DISTINCT o.ms_id, t.invoiced_inv_id
        FROM ms_orders o JOIN tp_orders t ON t.order_id = o.order_id
        WHERE o.ms_id IN ($dmIdListSql) AND o.new_order = 'yes'
          AND o.order_date BETWEEN '$periodFrom' AND '$periodTo'
          AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
    ");
    $invToDm = [];
    while ($row = $res2->fetch_assoc()) { $invToDm[$row['invoiced_inv_id']] = (int)$row['ms_id']; }
    if (!empty($invToDm)) {
        $placeholders = implode(',', array_fill(0, count($invToDm), '?'));
        $types = str_repeat('s', count($invToDm));
        $ids = array_keys($invToDm);
        $stmt = $db_conn->prepare("SELECT inv_id, total FROM user_invoice WHERE inv_id IN ($placeholders) AND deleted_at IS NULL AND voided_at IS NULL");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $dmId = $invToDm[$row['inv_id']] ?? null;
            if ($dmId === null) continue;
            $convertedAmtByDm[$dmId] += (float)$row['total'];
        }
        $stmt->close();
    }
}

$tpIdsByDistrictId = [];
foreach ($districts as $d) { $tpIdsByDistrictId[$d['id']] = getMsNodeTpIds($db_conn, [$d['id']], true); }
$allTpIds = [];
foreach ($tpIdsByDistrictId as $ids) { $allTpIds = array_merge($allTpIds, $ids); }
$allTpIds = array_values(array_unique($allTpIds));

$tpDetails = [];
if (!empty($allTpIds)) {
    $idList = implode(',', array_map('intval', $allTpIds));
    $res3 = $db_conn->query("SELECT id, tp_id, name FROM territory_partners WHERE id IN ($idList)");
    while ($row = $res3->fetch_assoc()) { $tpDetails[(int)$row['id']] = $row; }
}

$invoiceAmtByTp = array_fill_keys($allTpIds, 0.0);
if (!empty($allTpIds)) {
    $idList = implode(',', array_map('intval', $allTpIds));
    $res4 = $db_conn->query("
        SELECT from_user_id, SUM(total) AS amt
        FROM user_invoice
        WHERE from_user_type = 'territory_partner' AND to_user_type = 'shop'
          AND from_user_id IN ($idList)
          AND `date` BETWEEN '$periodFrom' AND '$periodTo'
          AND deleted_at IS NULL AND voided_at IS NULL
        GROUP BY from_user_id
    ");
    while ($row = $res4->fetch_assoc()) { $invoiceAmtByTp[(int)$row['from_user_id']] = (float)$row['amt']; }
}

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('District DM-TP Conversion');

$sheet->setCellValue('A1', 'District-wise DM / TP Order Conversion | Period: ' . date('d-m-Y', strtotime($periodFrom)) . ' to ' . date('d-m-Y', strtotime($periodTo)));
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

$headerRow = 3;
$headers = ['District', 'District Manager', 'DM Get Order Converted Value', 'Territory Partner', 'TP Invoice Value to Shops'];
foreach ($headers as $i => $h) { $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $headerRow, $h); }
$headerRange = 'A' . $headerRow . ':E' . $headerRow;
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
$sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

// One row per District — DM name(s), TP name(s) and TP value(s) are each
// newline-joined into their own single cell (same line order across the TP
// Name / TP Value pair of columns, so line N in one lines up with line N in
// the other) instead of repeating the district over many rows.
$row = $headerRow + 1;
foreach ($districts as $d) {
    $dms = $dmsByDistrictId[$d['id']] ?? [];
    $tpIds = $tpIdsByDistrictId[$d['id']] ?? [];
    $tps = array_values(array_filter(array_map(function ($tpId) use ($tpDetails, $invoiceAmtByTp) {
        $tp = $tpDetails[$tpId] ?? null;
        if (!$tp) return null;
        return ['name' => $tp['name'] . ' (' . $tp['tp_id'] . ')', 'amt' => $invoiceAmtByTp[$tpId] ?? 0.0];
    }, $tpIds)));

    if (empty($dms) && empty($tps)) { continue; }

    $dmNameLines = !empty($dms) ? implode("\n", array_column($dms, 'name')) : '';
    $dmAmtTotal = array_sum(array_map(fn($dm) => $convertedAmtByDm[$dm['id']] ?? 0.0, $dms));

    // Rich text so the trailing "TOTAL" line can be bold while the TP lines
    // above it stay normal weight, all inside the one wrapped cell.
    $tpNameRich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
    $tpAmtRich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
    if (!empty($tps)) {
        $tpAmtTotal = array_sum(array_column($tps, 'amt'));
        $tpNameRich->createText(implode("\n", array_column($tps, 'name')) . "\n");
        $tpNameRich->createTextRun('TOTAL')->getFont()->setBold(true);
        $tpAmtRich->createText(implode("\n", array_map(fn($tp) => number_format($tp['amt'], 2), $tps)) . "\n");
        $tpAmtRich->createTextRun(number_format($tpAmtTotal, 2))->getFont()->setBold(true);
    }

    $sheet->setCellValue('A' . $row, $d['name']);
    $sheet->setCellValue('B' . $row, $dmNameLines);
    $sheet->setCellValue('C' . $row, !empty($dms) ? $dmAmtTotal : null);
    $sheet->getCell('D' . $row)->setValue(!empty($tps) ? $tpNameRich : '');
    $sheet->getCell('E' . $row)->setValue(!empty($tps) ? $tpAmtRich : '');
    $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('B' . $row . ':E' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    $row++;
}

if ($row > $headerRow + 1) {
    $sheet->getStyle('A' . ($headerRow + 1) . ':E' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}
foreach (range(1, 5) as $i) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(28);
}
$sheet->freezePane('A' . ($headerRow + 1));

$filename = 'District_DM_TP_Conversion_' . $periodFrom . '_to_' . $periodTo . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;
