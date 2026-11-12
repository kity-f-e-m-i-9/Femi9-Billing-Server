<?php
/**
 * One-time audit export (2026-11-11): before today's fix, "Daily Assign
 * District TPs" wrote BOTH tp_daily_assignments (the intended day-by-day
 * log) AND territory_partner_locations (that TP's PERMANENT Firka
 * ownership) on every save — so a Firka's current permanent owner may
 * actually be whoever a daily roster action last happened to name, not a
 * deliberate "Edit Territory Partner" change.
 *
 * This lists every currently-owned Firka whose owner matches the latest
 * tp_daily_assignments entry for that same Firka — i.e. every row that
 * COULD have been set by the old buggy Daily Assign write path — grouped
 * by TP, so the company can manually review each one and decide whether
 * it's actually correct or needs reverting via Edit Territory Partner.
 * Nothing here is changed automatically; this is read-only.
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
require_once("include/PermissionCheck.php"); requirePermission('ms_edit');
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

$sql = "
    SELECT tp.id AS tp_id, tp.name AS tp_name, tp.tp_id AS tp_code, tp.is_active AS tp_is_active,
           COALESCE(NULLIF(tp.assigned_district,''), tp.branch_district) AS tp_own_district,
           n.name AS firka_name, d.name AS firka_district,
           tda.assign_date, tda.assigned_by
    FROM territory_partner_locations tpl
    JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
    JOIN partner_location_nodes n ON n.id = tpl.location_id
    JOIN partner_location_nodes taluk ON taluk.id = n.parent_id
    JOIN partner_location_nodes division ON division.id = taluk.parent_id
    JOIN partner_location_nodes d ON d.id = division.parent_id
    JOIN (
        SELECT t1.location_id, t1.territory_partner_id, t1.assign_date, t1.assigned_by
        FROM tp_daily_assignments t1
        INNER JOIN (
            SELECT location_id, MAX(assign_date) AS max_date
            FROM tp_daily_assignments
            GROUP BY location_id
        ) t2 ON t2.location_id = t1.location_id AND t2.max_date = t1.assign_date
    ) tda ON tda.location_id = tpl.location_id AND tda.territory_partner_id = tpl.territory_partner_id
    ORDER BY tp.is_active ASC, tp.name ASC, n.name ASC
";
$rows = $db_conn->query($sql)->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Assign Audit');

$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', 'Firkas whose current permanent owner matches a Daily Assign entry — needs manual review');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(26);

$headers = ['TP Name', 'TP Code', 'TP Status', "TP's Own District", 'Firka Name', 'Firka District', 'District Match?', 'Last Daily-Assign Date', 'Assigned By'];
foreach ($headers as $i => $h) { $sheet->setCellValue(chr(ord('A') + $i) . '3', $h); }
$sheet->getStyle('A3:I3')->applyFromArray([
    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$row = 4;
$lastTpId = null;
$tpBandColor = 'FFFFFFFF';
foreach ($rows as $r) {
    if ($r['tp_id'] !== $lastTpId) {
        $tpBandColor = ($tpBandColor === 'FFFFFFFF') ? 'FFF0F4FF' : 'FFFFFFFF';
        $lastTpId = $r['tp_id'];
    }
    // Loose match — the TP's own registered district is free text and
    // sometimes differs slightly in case/spacing from the location tree's
    // district name, so this is a helper flag to scan quickly, not a
    // guaranteed verdict; always eyeball a "No" before fixing anything.
    $tpDist = mb_strtolower(trim((string)($r['tp_own_district'] ?? '')));
    $firkaDist = mb_strtolower(trim((string)($r['firka_district'] ?? '')));
    $isMatch = ($tpDist !== '' && $tpDist === $firkaDist);
    $matchLabel = $isMatch ? 'Yes' : 'No';

    $statusLabel = $r['tp_is_active'] ? 'Active' : 'Inactive';

    $sheet->setCellValue("A{$row}", $r['tp_name']);
    $sheet->setCellValue("B{$row}", $r['tp_code']);
    $sheet->setCellValue("C{$row}", $statusLabel);
    $sheet->getStyle("C{$row}")->applyFromArray(['font' => ['bold' => true, 'color' => ['argb' => $r['tp_is_active'] ? 'FF0CA30C' : 'FFDC2626']]]);
    $sheet->setCellValue("D{$row}", $r['tp_own_district']);
    $sheet->setCellValue("E{$row}", $r['firka_name']);
    $sheet->setCellValue("F{$row}", $r['firka_district']);
    $sheet->setCellValue("G{$row}", $matchLabel);
    $sheet->getStyle("G{$row}")->applyFromArray(['font' => ['bold' => true, 'color' => ['argb' => $isMatch ? 'FF0CA30C' : 'FFDC2626']]]);
    $sheet->setCellValue("H{$row}", date('d-m-Y', strtotime($r['assign_date'])));
    $sheet->setCellValue("I{$row}", $r['assigned_by']);
    $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $tpBandColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);
    $row++;
}

$sheet->getColumnDimension('A')->setWidth(26);
$sheet->getColumnDimension('B')->setWidth(14);
$sheet->getColumnDimension('C')->setWidth(12);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(28);
$sheet->getColumnDimension('F')->setWidth(20);
$sheet->getColumnDimension('G')->setWidth(16);
$sheet->getColumnDimension('H')->setWidth(20);
$sheet->getColumnDimension('I')->setWidth(20);
$sheet->freezePane('A4');
$sheet->setAutoFilter('A3:I3');

$filename = 'TP_Firka_DailyAssign_Audit_' . date('Y-m-d') . '.xlsx';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;
