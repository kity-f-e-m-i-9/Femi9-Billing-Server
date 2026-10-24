<?php
/**
 * Exports the complete TP Reward Points table (reward_points_tp.php) for a
 * given date range — same columns shown on-screen, including District,
 * Monthly Target, and Sales Points (which stays out of the Total Points sum
 * on-screen too, and is called out as such here).
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
use PhpOffice\PhpSpreadsheet\Style\Color;

require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('reward_points');
require_once("config.php");
require_once("include/TpRewardPointsData.php");
require_once(__DIR__ . "/../territory-partner/include/LegacyRolePoints.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;

function validateDate_rtpExport(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

$daysInMonth     = (int) date('t');
$currentMonth    = date('m');
$defaultFromDate = date("Y-{$currentMonth}-01");
$defaultToDate   = date("Y-{$currentMonth}-{$daysInMonth}");

$current_from_date = validateDate_rtpExport($_GET['frdate'] ?? null, $defaultFromDate);
$current_to_date   = validateDate_rtpExport($_GET['todate'] ?? null, $defaultToDate);

if (strtotime($current_from_date) > strtotime($current_to_date)) {
    [$current_from_date, $current_to_date] = [$current_to_date, $current_from_date];
}

$target_ranges = getTpTargetAmountRanges();
$current_target_range = $_GET['target_range'] ?? '';
if (!array_key_exists($current_target_range, $target_ranges)) $current_target_range = '';

try {
    $combined_users = getTpRewardPointsData($current_from_date, $current_to_date, $current_target_range);
} catch (PDOException $e) {
    error_log("reward_points_tp_export_xlsx error: " . $e->getMessage());
    die("Failed to load reward points data.");
}

// June 2026 legacy role points (Stockist/Super Stockist/Distributor/Super
// Distributor), matched by phone number — same as the on-screen table.
foreach ($combined_users as &$u) {
    $mobile = $u['details']['mobile'] ?? '';
    $u['legacy_all'] = $mobile ? getLegacyRolePointsAll($db_conn, $mobile) : [];
    $u['legacy_total'] = array_sum(array_map(fn($l) => $l['purchase'] + $l['daily_login'], $u['legacy_all']));
    $u['grand_total'] = $u['total_points'] + $u['legacy_total'];
    $u['legacy_sales_total'] = array_sum(array_map(fn($l) => $l['sales'], $u['legacy_all']));
    $u['sales_grand_total'] = $u['sales_points'] + $u['legacy_sales_total'];
}
unset($u);

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('TP Reward Points');

$headers = [
    'A' => 'S.No',
    'B' => 'TP ID',
    'C' => 'Name',
    'D' => 'Mobile',
    'E' => 'District',
    'F' => 'Monthly Target (Rs)',
    'G' => 'Purchase Points',
    'H' => 'Login Points',
    'I' => 'Team Points',
    'J' => 'Advance Bonus Points',
    'K' => 'Returns (-)',
    'L' => 'Total Points',
    'M' => 'Sales Points (not in total)',
    'N' => 'Old Role(s) (before TP)',
    'O' => 'Old Role ID(s)',
    'P' => 'Old Role Purchase (Jun)',
    'Q' => 'Old Role Login (Jun)',
    'R' => 'Old Role Sales (Jun)',
    'S' => 'Grand Total (Total Points + Old Role)',
    'T' => 'Sales Grand Total (Sales + Old Role)',
];
$lastCol = 'T';

// ── Title ──
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', sprintf(
    'TP Reward Points | %s - %s%s',
    date('d M Y', strtotime($current_from_date)),
    date('d M Y', strtotime($current_to_date)),
    $current_target_range !== '' ? ' | Monthly Target: ' . $target_ranges[$current_target_range] : ''
));
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
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
$numericCols = ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'P', 'Q', 'R', 'S', 'T'];

foreach ($combined_users as $i => $u) {
    $d = $u['details'];
    $sn = $i + 1;

    $sheet->setCellValue("A{$rowNum}", $sn);
    $sheet->setCellValue("B{$rowNum}", $d['tp_id'] ?? '');
    $sheet->setCellValue("C{$rowNum}", $d['name'] ?? '');
    $sheet->setCellValue("D{$rowNum}", $d['mobile'] ?? '');
    $sheet->setCellValue("E{$rowNum}", $u['district_names'] ?? '');
    $sheet->setCellValue("F{$rowNum}", (float)$u['target_amount']);
    $sheet->setCellValue("G{$rowNum}", (float)$u['purchase_points']);
    $sheet->setCellValue("H{$rowNum}", (float)$u['daily_points']);
    $sheet->setCellValue("I{$rowNum}", (float)$u['team_points']);
    $sheet->setCellValue("J{$rowNum}", (float)$u['advance_points']);
    $sheet->setCellValue("K{$rowNum}", (float)$u['return_points']);
    $sheet->setCellValue("L{$rowNum}", (float)$u['total_points']);
    $sheet->setCellValue("M{$rowNum}", (float)$u['sales_points']);

    $sheet->setCellValue("N{$rowNum}", implode("\n", array_column($u['legacy_all'], 'role_label')));
    $sheet->setCellValue("O{$rowNum}", implode("\n", array_column($u['legacy_all'], 'temp_id')));
    $sheet->setCellValue("P{$rowNum}", (float)array_sum(array_column($u['legacy_all'], 'purchase')));
    $sheet->setCellValue("Q{$rowNum}", (float)array_sum(array_column($u['legacy_all'], 'daily_login')));
    $sheet->setCellValue("R{$rowNum}", (float)$u['legacy_sales_total']);
    $sheet->setCellValue("S{$rowNum}", (float)$u['grand_total']);
    $sheet->setCellValue("T{$rowNum}", (float)$u['sales_grand_total']);
    if (!empty($u['legacy_all'])) {
        $sheet->getStyle("N{$rowNum}:O{$rowNum}")->getAlignment()->setWrapText(true);
    }

    $bgColor = ($sn % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);

    foreach ($numericCols as $nc) {
        $sheet->getStyle("{$nc}{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("{$nc}{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    $sheet->getStyle("L{$rowNum}")->getFont()->setBold(true)->setColor(new Color('FF4F46E5'));
    $sheet->getStyle("M{$rowNum}")->getFont()->setColor(new Color('FF0EA5E9'));
    $sheet->getStyle("S{$rowNum}")->getFont()->setBold(true)->setColor(new Color('FF111827'));
    $sheet->getStyle("T{$rowNum}")->getFont()->setBold(true)->setColor(new Color('FF111827'));

    $rowNum++;
}

// ── Totals row ──
$totalsRow = $rowNum;
$dataStart = 3;
$dataEnd   = $rowNum - 1;

$sheet->setCellValue("A{$totalsRow}", 'TOTAL');
$sheet->mergeCells("A{$totalsRow}:E{$totalsRow}");

if ($dataEnd >= $dataStart) {
    foreach (['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'P', 'Q', 'R', 'S', 'T'] as $nc) {
        $sheet->setCellValue("{$nc}{$totalsRow}", "=SUM({$nc}{$dataStart}:{$nc}{$dataEnd})");
    }
}
foreach ($numericCols as $nc) {
    $sheet->getStyle("{$nc}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("{$nc}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
$sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFAAAAAA']]],
]);

// ── Column widths ──
$sheet->getColumnDimension('A')->setWidth(7);
$sheet->getColumnDimension('B')->setWidth(14);
$sheet->getColumnDimension('C')->setWidth(24);
$sheet->getColumnDimension('D')->setWidth(14);
$sheet->getColumnDimension('E')->setWidth(20);
foreach (['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'] as $nc) $sheet->getColumnDimension($nc)->setWidth(16);
$sheet->getColumnDimension('N')->setWidth(20);
$sheet->getColumnDimension('O')->setWidth(24);
foreach (['P', 'Q', 'R', 'S', 'T'] as $nc) $sheet->getColumnDimension($nc)->setWidth(18);

// ── Freeze + filter ──
$sheet->freezePane('A3');
$sheet->setAutoFilter("A2:{$lastCol}2");

// ── Output ──
$rangeSuffix = $current_target_range !== '' ? '_target_' . str_replace('-', '_', rtrim($current_target_range, '-')) : '';
$filename = "tp_reward_points_{$current_from_date}_to_{$current_to_date}{$rangeSuffix}.xlsx";

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
