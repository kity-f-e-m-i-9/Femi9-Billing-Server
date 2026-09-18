<?php
/**
 * Exports the "Unassigned Firkas" list (dashboard.php's card/modal) for
 * whatever search text is currently typed there — same query as
 * get-unassigned-firkas.php, minus pagination, so every matching row lands
 * in the sheet. Mirrors company/export-tp-invoices-xlsx.php's pattern
 * rather than sharing a function, matching this codebase's existing
 * per-export-file convention.
 */

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

require_once("checksession.php");
require_once("config.php");
require_once("include/BdmTpScope.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;

// Same "am I allowed to view this BDM's dashboard" check as
// get-unassigned-firkas.php — a manager viewing a subordinate's dashboard
// can export that subordinate's list; anyone else falls back to their own.
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

$search = trim($_GET['q'] ?? '');

$districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
$districtDepth = (int)($districtDepthRow['depth'] ?? 0);
$districtNames = getBdmAssignedDistrictNames($db_conn, $effectiveBdmId);

$rows = [];
if ($districtDepth && !empty($districtNames)) {
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
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Unassigned Firkas');

$headers = [
    'A' => 'S.No',
    'B' => 'District',
    'C' => 'Firka',
    'D' => 'Target Amount',
];
$lastCol = 'D';

// ── Title ──
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'Unassigned Firkas' . ($search !== '' ? ' | Search: "' . $search . '"' : ''));
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDC2626']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(30);

// ── Headers ──
foreach ($headers as $col => $label) {
    $sheet->setCellValue("{$col}2", $label);
}
$sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

// ── Data ──
$rowNum = 3;
foreach ($rows as $i => $r) {
    $sn = $i + 1;
    $sheet->setCellValue("A{$rowNum}", $sn);
    $sheet->setCellValue("B{$rowNum}", $r['district_name']);
    $sheet->setCellValue("C{$rowNum}", $r['firka_name']);
    $sheet->setCellValue("D{$rowNum}", (float)($r['target_amount'] ?? 0));

    $bgColor = ($sn % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);
    $sheet->getStyle("D{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $rowNum++;
}

// ── Totals row ──
$totalsRow = $rowNum;
$dataStart = 3;
$dataEnd   = $rowNum - 1;

$sheet->setCellValue("A{$totalsRow}", 'TOTAL');
$sheet->mergeCells("A{$totalsRow}:C{$totalsRow}");
if ($dataEnd >= $dataStart) {
    $sheet->setCellValue("D{$totalsRow}", "=SUM(D{$dataStart}:D{$dataEnd})");
}
$sheet->getStyle("D{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle("D{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDC2626']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

// ── Column widths ──
$sheet->getColumnDimension('A')->setWidth(7);
$sheet->getColumnDimension('B')->setWidth(24);
$sheet->getColumnDimension('C')->setWidth(24);
$sheet->getColumnDimension('D')->setWidth(18);

// ── Freeze + filter ──
$sheet->freezePane('A3');
$sheet->setAutoFilter("A2:{$lastCol}2");

// ── Output ──
$filename = 'unassigned_firkas_' . date('Y-m-d') . '.xlsx';

if (ob_get_length()) { ob_end_clean(); }

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');
exit;
