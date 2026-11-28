<?php
// Excel export for tp-target-achievers-report.php — one sheet per calendar
// month in the selected From/To range, each listing TPs whose Shop Invoice
// amount that month was >= their own target amount.
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
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

function validateMonth_taax(?string $m, string $default): string {
    if (empty($m)) return $default;
    return preg_match('/^\d{4}-\d{2}$/', $m) ? $m : $default;
}

$from_month = validateMonth_taax($_GET['from_month'] ?? null, date('Y-m'));
$to_month   = validateMonth_taax($_GET['to_month'] ?? null, date('Y-m'));
if (strtotime($from_month . '-01') > strtotime($to_month . '-01')) {
    [$from_month, $to_month] = [$to_month, $from_month];
}

$months = [];
$cursor = strtotime($from_month . '-01');
$endTs  = strtotime($to_month . '-01');
while ($cursor <= $endTs) {
    $months[] = date('Y-m', $cursor);
    $cursor = strtotime('+1 month', $cursor);
}

$tpTargets = [];
$res = $db_conn->query("
    SELECT tp.id, tp.tp_id AS tp_code, tp.name, tp.mobile,
           GROUP_CONCAT(DISTINCT district.name ORDER BY district.name SEPARATOR ', ') AS district_names,
           COALESCE(SUM(leaf.target_amount), 0) AS target_amount
    FROM territory_partners tp
    LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
    LEFT JOIN partner_location_nodes leaf     ON leaf.id = tpl.location_id
    LEFT JOIN partner_location_nodes taluk    ON taluk.id = leaf.parent_id
    LEFT JOIN partner_location_nodes division ON division.id = taluk.parent_id
    LEFT JOIN partner_location_nodes district ON district.id = division.parent_id
    WHERE tp.is_active = 1 AND tp.deleted_at IS NULL
    GROUP BY tp.id
    HAVING target_amount > 0
");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $tpTargets[(int)$r['id']] = [
            'name'           => $r['name'],
            'tp_code'        => $r['tp_code'],
            'mobile'         => $r['mobile'],
            'district_names' => $r['district_names'] ?: '',
            'target_amount'  => (float)$r['target_amount'],
        ];
    }
}

function tpShopInvoiceByMonthXlsx(mysqli $db_conn, string $monthStart, string $monthEnd): array {
    $stmt = $db_conn->prepare("
        SELECT ui.from_user_id AS tp_id, COALESCE(SUM(ui.total), 0) AS achieved
        FROM user_invoice ui
        WHERE ui.from_user_type = 'territory_partner' AND ui.to_user_type = 'shop'
          AND ui.sub_total > 0 AND ui.`date` BETWEEN ? AND ?
        GROUP BY ui.from_user_id
    ");
    $stmt->bind_param('ss', $monthStart, $monthEnd);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $out = [];
    foreach ($rows as $r) { $out[(int)$r['tp_id']] = (float)$r['achieved']; }
    return $out;
}

function writeMonthSheet(Spreadsheet $spreadsheet, string $label, array $rows, int $sheetIdx): void {
    $sheet = $spreadsheet->createSheet($sheetIdx);
    $safeName = substr(preg_replace('/[\[\]\*\/\\\\\?\:]/', '', $label), 0, 31) ?: ('Month' . $sheetIdx);
    $sheet->setTitle($safeName);

    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', $label . ' — TP Target Achievers');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $headers = ['TP Name', 'TP Code', 'Mobile', 'District(s)', 'Target Amount', 'Invoiced (Shop)', '% Achieved'];
    $col = 'A';
    foreach ($headers as $h) { $sheet->setCellValue($col . '2', $h); $col++; }
    $sheet->getStyle('A2:G2')->getFont()->setBold(true);
    $sheet->getStyle('A2:G2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E2F3');
    $sheet->getStyle('A2:G2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

    $r = 3;
    $lastDataRow = $r - 1;
    foreach ($rows as $row) {
        $sheet->setCellValue("A$r", $row['name']);
        $sheet->setCellValue("B$r", $row['tp_code']);
        $sheet->setCellValue("C$r", $row['mobile']);
        $sheet->setCellValue("D$r", $row['district_names']);
        $sheet->setCellValue("E$r", $row['target_amount']);
        $sheet->setCellValue("F$r", $row['achieved']);
        $sheet->setCellValue("G$r", $row['pct'] . '%');
        $lastDataRow = $r;
        $r++;
    }

    if (empty($rows)) {
        $sheet->mergeCells("A3:G3");
        $sheet->setCellValue('A3', 'No TP met their target this month.');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $lastDataRow = 3;
    }

    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $c) { $sheet->getColumnDimension($c)->setWidth(22); }
    if (!empty($rows)) {
        $sheet->getStyle("E3:F$lastDataRow")->getNumberFormat()->setFormatCode('#,##0.00');
    }
    $sheet->getStyle("A2:G$lastDataRow")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->freezePane('A3');
    if (!empty($rows)) {
        $sheet->setAutoFilter('A2:G' . $lastDataRow);
    }
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$sheetIdx = 0;
foreach ($months as $ym) {
    $monthStart = $ym . '-01';
    $monthEnd   = date('Y-m-t', strtotime($monthStart));
    $label      = date('F Y', strtotime($monthStart));
    $achievedByTp = tpShopInvoiceByMonthXlsx($db_conn, $monthStart, $monthEnd);

    $rows = [];
    foreach ($tpTargets as $tpId => $t) {
        $achieved = $achievedByTp[$tpId] ?? 0.0;
        if ($achieved >= $t['target_amount']) {
            $rows[] = array_merge($t, ['achieved' => $achieved, 'pct' => round($achieved / $t['target_amount'] * 100, 1)]);
        }
    }
    usort($rows, fn($a, $b) => $b['achieved'] <=> $a['achieved']);

    writeMonthSheet($spreadsheet, $label, $rows, $sheetIdx);
    $sheetIdx++;
}

if ($sheetIdx === 0) {
    $spreadsheet->createSheet(0)->setCellValue('A1', 'No months in range.');
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'TP_Target_Achievers_' . $from_month . '_to_' . $to_month . '.xlsx';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;
