<?php
// Channel-wise Sales from Company — Sales/Returns/Net per calendar month in
// the picked range, one column-group per month + a Total group, same layout
// as the earlier one-off reports (Jul-Sep 2026 / Aug-Oct 2026). Date range
// comes from channel-sales-report.php's picker.
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

require_once("checksession.php");
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;

function validateDate_csx(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

$defaultFrom = date('Y-m-01', strtotime('-2 months'));
$defaultTo   = date('Y-m-t');
$from_date = validateDate_csx($_GET['frdate'] ?? null, $defaultFrom);
$to_date   = validateDate_csx($_GET['todate'] ?? null, $defaultTo);
if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }

// Split the range into calendar months (a partial first/last month is
// clipped to the picked range, same way a date-range report normally works).
$months = [];
$cursor = new DateTime(date('Y-m-01', strtotime($from_date)));
$rangeEnd = new DateTime($to_date);
while ($cursor <= $rangeEnd) {
    $monthStart = (clone $cursor);
    $monthEnd = (clone $cursor)->modify('last day of this month');
    $segFrom = max($monthStart, new DateTime($from_date));
    $segTo = min($monthEnd, $rangeEnd);
    $months[] = [
        'label' => $cursor->format('M Y'),
        'from'  => $segFrom->format('Y-m-d'),
        'to'    => $segTo->format('Y-m-d'),
    ];
    $cursor->modify('first day of next month');
}
if (empty($months)) {
    die("Invalid date range.");
}
if (count($months) > 12) {
    die("Date range too wide — pick 12 months or fewer.");
}

$channel_labels = [
    'territory_partner' => 'Territory Partner',
    'super_stockiest'   => 'Super Stockist',
    'stockiest'         => 'Stockist',
    'super_distributor' => 'Super Distributor',
    'distributor'       => 'Distributor',
    'shop'              => 'Shop',
    'company'           => 'Customer (Direct)',
];

function monthChannelStats(mysqli $db_conn, string $from, string $to): array {
    $stats = [];
    foreach (['territory_partner','super_stockiest','stockiest','super_distributor','distributor','shop','company'] as $k) {
        $stats[$k] = ['sales' => 0.0, 'returns' => 0.0];
    }

    $s = $db_conn->prepare("SELECT COALESCE(SUM(total_amount-courier_charges),0) rev FROM tp_invoices
        WHERE (created_by_user_type != 'super_stockiest') AND invoice_date BETWEEN ? AND ?");
    $s->bind_param('ss', $from, $to); $s->execute();
    $stats['territory_partner']['sales'] = (float)($s->get_result()->fetch_assoc()['rev'] ?? 0);

    $s = $db_conn->prepare("SELECT to_user_type ch, COALESCE(SUM(total-courier_charges),0) rev FROM user_invoice
        WHERE from_user_type='company' AND sub_total>0 AND `date` BETWEEN ? AND ? GROUP BY to_user_type");
    $s->bind_param('ss', $from, $to); $s->execute();
    $res = $s->get_result();
    while ($r = $res->fetch_assoc()) {
        if (isset($stats[$r['ch']])) $stats[$r['ch']]['sales'] = (float)$r['rev'];
    }

    $s = $db_conn->prepare("SELECT COALESCE(SUM(total-courier_charges),0) rev FROM invoice
        WHERE user_type='company' AND sub_total>0 AND `date` BETWEEN ? AND ?");
    $s->bind_param('ss', $from, $to); $s->execute();
    $stats['company']['sales'] = (float)($s->get_result()->fetch_assoc()['rev'] ?? 0);

    $s = $db_conn->prepare("SELECT from_usertype ch, COALESCE(SUM(total),0) amount FROM (
            SELECT returnid, from_usertype, MAX(total) total FROM user_return_stock
            WHERE to_usertype='company' AND deleted_at IS NULL AND `date` BETWEEN ? AND ?
            GROUP BY returnid, from_usertype
        ) x GROUP BY from_usertype");
    $s->bind_param('ss', $from, $to); $s->execute();
    $res = $s->get_result();
    while ($r = $res->fetch_assoc()) {
        $key = $r['ch'] === 'customer' ? 'company' : $r['ch'];
        if (isset($stats[$key])) $stats[$key]['returns'] = (float)$r['amount'];
    }

    return $stats;
}

function monthOtStats(mysqli $db_conn, string $from, string $to): array {
    $stats = [];
    $s = $db_conn->prepare("SELECT cat, COALESCE(SUM(CAST(total AS DECIMAL(12,2))),0) amt FROM ot_sales
        WHERE `date` BETWEEN ? AND ? AND cat IS NOT NULL AND cat != '' GROUP BY cat");
    $s->bind_param('ss', $from, $to); $s->execute();
    $res = $s->get_result();
    while ($r = $res->fetch_assoc()) {
        $stats[$r['cat']]['sales'] = (float)$r['amt'];
        $stats[$r['cat']]['returns'] = $stats[$r['cat']]['returns'] ?? 0.0;
    }

    $s = $db_conn->prepare("SELECT os.cat cat, COALESCE(SUM(CAST(osr.total AS DECIMAL(12,2))),0) amt
        FROM ot_sales_return osr
        INNER JOIN ot_sales os ON osr.tempid = os.tempid AND osr.prid = os.prid
        WHERE osr.return_date BETWEEN ? AND ? AND os.cat IS NOT NULL AND os.cat != ''
        GROUP BY os.cat");
    $s->bind_param('ss', $from, $to); $s->execute();
    $res = $s->get_result();
    while ($r = $res->fetch_assoc()) {
        $stats[$r['cat']]['sales'] = $stats[$r['cat']]['sales'] ?? 0.0;
        $stats[$r['cat']]['returns'] = (float)$r['amt'];
    }

    ksort($stats);
    return $stats;
}

$byMonth = [];
$otByMonth = [];
$allOtCats = [];
foreach ($months as $i => $m) {
    $byMonth[$i] = monthChannelStats($db_conn, $m['from'], $m['to']);
    $otByMonth[$i] = monthOtStats($db_conn, $m['from'], $m['to']);
    foreach ($otByMonth[$i] as $cat => $v) $allOtCats[$cat] = true;
}
ksort($allOtCats);
$otCats = array_keys($allOtCats);

require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Channel Sales');

$NUMFMT = '#,##0';
$TITLE_FILL = '667EEA';
$HEADER_FILL = '2563EB';
$RETURNS_COLOR = '991B1B';
$numGroups = count($months) + 1; // + Total
$lastCol = $numGroups * 3;
$colLetter = function (int $idx): string { return Coordinate::stringFromColumnIndex($idx + 1); };

$periodLabel = (count($months) === 1) ? $months[0]['label'] : ($months[0]['label'] . ' - ' . $months[count($months) - 1]['label']);

$sheet->setCellValue('A1', "Channel-wise Sales from Company — {$periodLabel} (Sales / Returns / Net)");
$sheet->mergeCells('A1:' . $colLetter($lastCol) . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($TITLE_FILL);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(28);

$sheet->setCellValue('A2', 'Channel');
$sheet->mergeCells('A2:A3');
$groupStartCol = 1;
foreach (array_merge(array_column($months, 'label'), ['Total']) as $label) {
    $c1 = $colLetter($groupStartCol);
    $c2 = $colLetter($groupStartCol + 2);
    $sheet->setCellValue($c1 . '2', $label);
    $sheet->mergeCells($c1 . '2:' . $c2 . '2');
    $groupStartCol += 3;
}
$headerRange = 'A2:' . $colLetter($lastCol) . '3';
$sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($HEADER_FILL);
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

$groupStartCol = 1;
for ($g = 0; $g < $numGroups; $g++) {
    $sheet->setCellValue($colLetter($groupStartCol) . '3', 'Sales');
    $sheet->setCellValue($colLetter($groupStartCol + 1) . '3', 'Returns');
    $sheet->setCellValue($colLetter($groupStartCol + 2) . '3', 'Net');
    $groupStartCol += 3;
}

$row = 4;

function writeDataRow($sheet, int &$row, string $label, array $monthVals, string $numfmt, string $returnsColor, bool $indent = false): array {
    $colLetter = function (int $idx): string { return Coordinate::stringFromColumnIndex($idx + 1); };
    $sheet->setCellValue('A' . $row, ($indent ? '   ' : '') . $label);

    $col = 1;
    $totSales = 0.0; $totReturns = 0.0;
    foreach ($monthVals as $mv) {
        $sales = $mv['sales']; $returns = $mv['returns']; $net = $sales - $returns;
        $totSales += $sales; $totReturns += $returns;
        $sheet->setCellValue($colLetter($col) . $row, $sales);
        $sheet->setCellValue($colLetter($col + 1) . $row, $returns);
        $sheet->setCellValue($colLetter($col + 2) . $row, $net);
        $sheet->getStyle($colLetter($col) . $row)->getNumberFormat()->setFormatCode($numfmt);
        $sheet->getStyle($colLetter($col + 1) . $row)->getNumberFormat()->setFormatCode($numfmt);
        $sheet->getStyle($colLetter($col + 1) . $row)->getFont()->getColor()->setRGB($returnsColor);
        $sheet->getStyle($colLetter($col + 2) . $row)->getNumberFormat()->setFormatCode($numfmt);
        $sheet->getStyle($colLetter($col + 2) . $row)->getFont()->setBold(true);
        $col += 3;
    }
    $totNet = $totSales - $totReturns;
    $sheet->setCellValue($colLetter($col) . $row, $totSales);
    $sheet->setCellValue($colLetter($col + 1) . $row, $totReturns);
    $sheet->setCellValue($colLetter($col + 2) . $row, $totNet);
    $sheet->getStyle($colLetter($col) . $row)->getNumberFormat()->setFormatCode($numfmt);
    $sheet->getStyle($colLetter($col + 1) . $row)->getNumberFormat()->setFormatCode($numfmt);
    $sheet->getStyle($colLetter($col + 1) . $row)->getFont()->getColor()->setRGB($returnsColor);
    $sheet->getStyle($colLetter($col + 2) . $row)->getNumberFormat()->setFormatCode($numfmt);
    $sheet->getStyle($colLetter($col + 2) . $row)->getFont()->setBold(true);

    $thisRow = $row;
    $row++;
    return ['sales' => $totSales, 'returns' => $totReturns, 'row' => $thisRow];
}

foreach ($channel_labels as $key => $label) {
    $monthVals = [];
    foreach ($months as $i => $m) {
        $monthVals[] = $byMonth[$i][$key] ?? ['sales' => 0.0, 'returns' => 0.0];
    }
    writeDataRow($sheet, $row, $label, $monthVals, $NUMFMT, $RETURNS_COLOR);
}
$lastChannelRow = $row - 1;

$sheet->setCellValue('A' . $row, 'OT Channel (by category)');
$sheet->getStyle('A' . $row)->getFont()->setBold(true);
$row++;

$otFirstRow = $row;
foreach ($otCats as $cat) {
    $monthVals = [];
    foreach ($months as $i => $m) {
        $monthVals[] = $otByMonth[$i][$cat] ?? ['sales' => 0.0, 'returns' => 0.0];
    }
    writeDataRow($sheet, $row, $cat, $monthVals, $NUMFMT, $RETURNS_COLOR, true);
}
$otLastRow = $row - 1;

$sheet->setCellValue('A' . $row, '   OT Channel Total');
$sheet->getStyle('A' . $row)->getFont()->setBold(true);
$col = 1;
for ($g = 0; $g < $numGroups; $g++) {
    foreach ([0, 1, 2] as $off) {
        $c = $colLetter($col + $off);
        if ($otLastRow >= $otFirstRow) {
            $sheet->setCellValue($c . $row, "=SUM({$c}{$otFirstRow}:{$c}{$otLastRow})");
        } else {
            $sheet->setCellValue($c . $row, 0);
        }
        $sheet->getStyle($c . $row)->getNumberFormat()->setFormatCode($NUMFMT);
        $sheet->getStyle($c . $row)->getFont()->setBold(true);
        if ($off === 1) $sheet->getStyle($c . $row)->getFont()->getColor()->setRGB($RETURNS_COLOR);
    }
    $col += 3;
}
$otTotalRow = $row;
$row++;

$sheet->setCellValue('A' . $row, 'GRAND TOTAL (from Company)');
$col = 1;
for ($g = 0; $g < $numGroups; $g++) {
    foreach ([0, 1, 2] as $off) {
        $c = $colLetter($col + $off);
        $sheet->setCellValue($c . $row, "=SUM({$c}4:{$c}{$lastChannelRow})+{$c}{$otTotalRow}");
        $sheet->getStyle($c . $row)->getNumberFormat()->setFormatCode($NUMFMT);
    }
    $col += 3;
}
$grandRow = $row;
$grandRange = 'A' . $grandRow . ':' . $colLetter($lastCol) . $grandRow;
$sheet->getStyle($grandRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle($grandRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($TITLE_FILL);
$col = 1;
for ($g = 0; $g < $numGroups; $g++) {
    $sheet->getStyle($colLetter($col + 1) . $grandRow)->getFont()->setBold(true)->getColor()->setRGB($RETURNS_COLOR);
    $col += 3;
}

$sheet->getColumnDimension('A')->setWidth(28);
for ($c = 1; $c <= $lastCol; $c++) {
    $sheet->getColumnDimension($colLetter($c))->setWidth(12);
}
$sheet->freezePane('B4');

$filename = 'channel_sales_' . preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower($periodLabel)) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new XlsxWriter($spreadsheet);
$writer->setPreCalculateFormulas(true);
$writer->save('php://output');
exit;
