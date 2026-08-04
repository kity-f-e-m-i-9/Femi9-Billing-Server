<?php
/**
 * Exports the Territory Partner accounts processed by a specific TP Bonus
 * Points Calculator *execution* (not a dry run — dry runs are never
 * persisted, so there is nothing to export until Execute has been run).
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
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}
require_once $autoloadPath;

$dbConn = $db_conn;

$executionId = trim((string)($_GET['execution_id'] ?? ''));
if ($executionId === '' || strpos($executionId, 'TPBNS-') !== 0) {
    die("Invalid or missing execution ID.");
}

// Only executions that were actually run (not previewed) are persisted —
// this is the "executed accounts" list.
$execStmt = $dbConn->prepare(
    "SELECT execution_id, month_year, total_users_processed, total_eligible_users,
            total_ineligible_users, total_bonus_points_awarded, total_accounts_deactivated,
            executed_by_user_name, executed_at, is_rolled_back
     FROM bonus_execution_log
     WHERE execution_id = ? AND execution_mode = 'execute'"
);
$execStmt->bind_param("s", $executionId);
$execStmt->execute();
$exec = $execStmt->get_result()->fetch_assoc();
$execStmt->close();

if (!$exec) {
    die("Execution not found.");
}

// Per-TP snapshot rows saved at execution time.
$rowsStmt = $dbConn->prepare(
    "SELECT user_id, user_name, monthly_target, total_advance_paid,
            week1_cumulative, week1_status, week2_cumulative, week2_status,
            week3_cumulative, week3_status, week4_cumulative, week4_status,
            eligibility_status, bonus_points_awarded, bonus_calculation
     FROM bonus_points_history
     WHERE execution_id = ? AND user_type = 'territory_partner'
     ORDER BY user_name ASC"
);
$rowsStmt->bind_param("s", $executionId);
$rowsStmt->execute();
$rows = $rowsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rowsStmt->close();

// Deactivations triggered by this specific execution — bonus_points_history
// itself doesn't record account status, only bonus_deactivation_log does.
$deactByUser = [];
$deactStmt = $dbConn->prepare(
    "SELECT user_id, deactivation_reason, restored_at
     FROM bonus_deactivation_log
     WHERE execution_id = ? AND user_type = 'territory_partner'"
);
$deactStmt->bind_param("s", $executionId);
$deactStmt->execute();
foreach ($deactStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $d) {
    $deactByUser[$d['user_id']] = $d;
}
$deactStmt->close();

// ============================================================================
// SPREADSHEET
// ============================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('TP Bonus - Executed Accounts');

$headers = [
    'A' => 'S.No',
    'B' => 'TP ID',
    'C' => 'TP Name',
    'D' => 'Monthly Target (Rs)',
    'E' => 'Total Paid (Rs)',
    'F' => 'Week 1 Cumulative (Rs)',
    'G' => 'Week 1 Status',
    'H' => 'Week 2 Cumulative (Rs)',
    'I' => 'Week 2 Status',
    'J' => 'Week 3 Cumulative (Rs)',
    'K' => 'Week 3 Status',
    'L' => 'Week 4 Cumulative (Rs)',
    'M' => 'Week 4 Status',
    'N' => 'Eligibility',
    'O' => 'Bonus Points Awarded',
    'P' => 'Bonus Calculation',
    'Q' => 'Account Status',
    'R' => 'Deactivation Reason',
];
$lastCol = 'R';

// ── Title ──
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', sprintf(
    'TP Bonus Points - Executed Accounts (%s) | Execution: %s | Run by %s on %s%s',
    $exec['month_year'],
    $exec['execution_id'],
    $exec['executed_by_user_name'],
    date('d M Y H:i', strtotime($exec['executed_at'])),
    (int)$exec['is_rolled_back'] === 1 ? ' | ROLLED BACK' : ''
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
$numericCols = ['D', 'E', 'F', 'H', 'J', 'L', 'O'];

foreach ($rows as $i => $r) {
    $sn = $i + 1;
    $deact = $deactByUser[$r['user_id']] ?? null;
    $accountStatus = $deact
        ? (!empty($deact['restored_at']) ? 'Deactivated (Restored)' : 'Deactivated')
        : '—';

    $sheet->setCellValue("A{$rowNum}", $sn);
    $sheet->setCellValue("B{$rowNum}", $r['user_id']);
    $sheet->setCellValue("C{$rowNum}", $r['user_name']);
    $sheet->setCellValue("D{$rowNum}", (float)$r['monthly_target']);
    $sheet->setCellValue("E{$rowNum}", (float)$r['total_advance_paid']);
    $sheet->setCellValue("F{$rowNum}", (float)$r['week1_cumulative']);
    $sheet->setCellValue("G{$rowNum}", ucfirst($r['week1_status']));
    $sheet->setCellValue("H{$rowNum}", (float)$r['week2_cumulative']);
    $sheet->setCellValue("I{$rowNum}", ucfirst($r['week2_status']));
    $sheet->setCellValue("J{$rowNum}", (float)$r['week3_cumulative']);
    $sheet->setCellValue("K{$rowNum}", ucfirst($r['week3_status']));
    $sheet->setCellValue("L{$rowNum}", (float)$r['week4_cumulative']);
    $sheet->setCellValue("M{$rowNum}", ucfirst($r['week4_status']));
    $sheet->setCellValue("N{$rowNum}", $r['eligibility_status'] === 'eligible' ? 'Eligible' : 'Not Eligible');
    $sheet->setCellValue("O{$rowNum}", (float)$r['bonus_points_awarded']);
    $sheet->setCellValue("P{$rowNum}", $r['bonus_calculation']);
    $sheet->setCellValue("Q{$rowNum}", $accountStatus);
    $sheet->setCellValue("R{$rowNum}", $deact['deactivation_reason'] ?? '');

    $bgColor = ($sn % 2 === 0) ? 'FFF0F4FF' : 'FFFFFFFF';
    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
    ]);

    foreach ($numericCols as $nc) {
        $sheet->getStyle("{$nc}{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("{$nc}{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    $sheet->getStyle("O{$rowNum}")->getFont()->setBold(true)->setColor(new Color('FFB45309'));
    if ($accountStatus !== '—') {
        $sheet->getStyle("Q{$rowNum}")->getFont()->setColor(new Color('FFDC2626'));
    }

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
    $sheet->setCellValue("E{$totalsRow}", "=SUM(E{$dataStart}:E{$dataEnd})");
    $sheet->setCellValue("O{$totalsRow}", "=SUM(O{$dataStart}:O{$dataEnd})");
}
foreach (['D', 'E', 'O'] as $nc) {
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
foreach (['D', 'E', 'F', 'H', 'J', 'L', 'O'] as $nc) $sheet->getColumnDimension($nc)->setWidth(16);
foreach (['G', 'I', 'K', 'M', 'N', 'Q'] as $nc) $sheet->getColumnDimension($nc)->setWidth(13);
$sheet->getColumnDimension('P')->setWidth(30);
$sheet->getColumnDimension('R')->setWidth(28);

// ── Freeze + filter ──
$sheet->freezePane('A3');
$sheet->setAutoFilter("A2:{$lastCol}2");

// ── Output ──
$filename = "tp_bonus_executed_accounts_{$exec['month_year']}_{$executionId}.xlsx";

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
