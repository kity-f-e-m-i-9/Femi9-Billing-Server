<?php
// ASM -> District Manager order performance -- one sheet per ASM, one row
// per DM under them: this month's Get Orders vs No Orders taken, how many
// of this month's Get Orders have already been converted to an invoice
// (via user_invoice.source_ms_order_id, same conversion link ms-order-
// invoice-add.php writes), and last month's converted amount vs how much
// of it has since been returned. Deleted/voided invoices are excluded
// entirely from both the converted amount and its returns -- a removed
// invoice shouldn't contribute to either side.
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
function excelSafeSheetName(string $name, array &$usedNames): string {
    $clean = trim(preg_replace('/[:\\\\\/\?\*\[\]]/', ' ', $name));
    if ($clean === '') { $clean = 'Sheet'; }
    $base = mb_substr($clean, 0, 31);
    $final = $base;
    $n = 2;
    while (isset($usedNames[mb_strtolower($final)])) {
        $suffix = ' (' . $n . ')';
        $final = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
        $n++;
    }
    $usedNames[mb_strtolower($final)] = true;
    return $final;
}

$thisMonthStart = date('Y-m-01');
$thisMonthEnd   = date('Y-m-t');
$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
$lastMonthEnd   = date('Y-m-t', strtotime('last day of last month'));

// ---- Staff hierarchy: DM (level_rank=3) -> ASM (manager_id) ------------
$staffById = [];
$staffRes = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.manager_id, tl.level_rank
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
");
while ($row = $staffRes->fetch_assoc()) {
    $staffById[(int) $row['id']] = [
        'name'       => trim($row['ms_name']),
        'manager_id' => $row['manager_id'] !== null ? (int) $row['manager_id'] : null,
        'level_rank' => $row['level_rank'] !== null ? (int) $row['level_rank'] : null,
    ];
}

function resolveAsmFor(int $dmId, array $staffById): string {
    $cur = $staffById[$dmId]['manager_id'] ?? null;
    $depth = 0;
    while ($cur !== null && isset($staffById[$cur]) && $depth < 10) {
        if (($staffById[$cur]['level_rank'] ?? null) === 2) { return $staffById[$cur]['name']; }
        $cur = $staffById[$cur]['manager_id'];
        $depth++;
    }
    return 'Unassigned';
}

$dmIds = [];
foreach ($staffById as $id => $s) {
    if (($s['level_rank'] ?? null) === 3) { $dmIds[] = $id; }
}

// ---- This month's Get/No orders per DM ----------------------------------
$getOrderCount  = array_fill_keys($dmIds, 0);
$noOrderCount   = array_fill_keys($dmIds, 0);
$getOrderIdsByDm = array_fill_keys($dmIds, []); // for the conversion check below

$ordRes = $db_conn->query(
    "SELECT ms_id, order_id, new_order
     FROM ms_orders
     WHERE order_date BETWEEN '$thisMonthStart' AND '$thisMonthEnd'
     GROUP BY ms_id, order_id, new_order"
);
while ($row = $ordRes->fetch_assoc()) {
    $id = (int) $row['ms_id'];
    if (!isset($getOrderCount[$id])) continue; // not a DM (e.g. an SM/ASM own visit) -- skip
    if ($row['new_order'] === 'yes') {
        $getOrderCount[$id]++;
        $getOrderIdsByDm[$id][] = $row['order_id'];
    } else {
        $noOrderCount[$id]++;
    }
}

// ---- Conversion: how many of this month's Get Orders are invoiced ------
$convertedThisMonth = array_fill_keys($dmIds, 0);
$allThisMonthOrderIds = [];
foreach ($getOrderIdsByDm as $id => $ids) { foreach ($ids as $oid) { $allThisMonthOrderIds[$oid] = $id; } }
if (!empty($allThisMonthOrderIds)) {
    $placeholders = implode(',', array_fill(0, count($allThisMonthOrderIds), '?'));
    $types = str_repeat('s', count($allThisMonthOrderIds));
    $ids = array_keys($allThisMonthOrderIds);
    $stmt = $db_conn->prepare(
        "SELECT DISTINCT source_ms_order_id FROM user_invoice
         WHERE source_ms_order_id IN ($placeholders) AND deleted_at IS NULL AND voided_at IS NULL"
    );
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dmId = $allThisMonthOrderIds[$row['source_ms_order_id']] ?? null;
        if ($dmId !== null) { $convertedThisMonth[$dmId]++; }
    }
    $stmt->close();
}

// ---- Last month's Get Orders -> their converted amount + returns -------
$lastMonthConvertedAmt = array_fill_keys($dmIds, 0.0);
$lastMonthReturnedAmt  = array_fill_keys($dmIds, 0.0);

$lmOrdRes = $db_conn->query(
    "SELECT ms_id, order_id FROM ms_orders
     WHERE new_order = 'yes' AND order_date BETWEEN '$lastMonthStart' AND '$lastMonthEnd'
     GROUP BY ms_id, order_id"
);
$lastMonthOrderIdsByDm = array_fill_keys($dmIds, []);
while ($row = $lmOrdRes->fetch_assoc()) {
    $id = (int) $row['ms_id'];
    if (isset($lastMonthOrderIdsByDm[$id])) { $lastMonthOrderIdsByDm[$id][] = $row['order_id']; }
}

$allLastMonthOrderIds = [];
foreach ($lastMonthOrderIdsByDm as $id => $ids) { foreach ($ids as $oid) { $allLastMonthOrderIds[$oid] = $id; } }

if (!empty($allLastMonthOrderIds)) {
    $placeholders = implode(',', array_fill(0, count($allLastMonthOrderIds), '?'));
    $types = str_repeat('s', count($allLastMonthOrderIds));
    $ids = array_keys($allLastMonthOrderIds);

    // Invoices converted from last month's Get Orders -- excludes deleted/
    // voided invoices entirely, both from the converted total and from
    // being eligible for a returns lookup below.
    $stmt = $db_conn->prepare(
        "SELECT source_ms_order_id, inv_number, total FROM user_invoice
         WHERE source_ms_order_id IN ($placeholders) AND deleted_at IS NULL AND voided_at IS NULL"
    );
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    $invNumbersByDm = array_fill_keys($dmIds, []);
    while ($row = $res->fetch_assoc()) {
        $dmId = $allLastMonthOrderIds[$row['source_ms_order_id']] ?? null;
        if ($dmId === null) continue;
        $lastMonthConvertedAmt[$dmId] += (float) $row['total'];
        $invNumbersByDm[$dmId][] = $row['inv_number'];
    }
    $stmt->close();

    // Returns against those same invoices -- user_return_stock repeats the
    // whole return's total on every line item, so dedupe by returnid
    // before summing (same convention mis-report.php already uses).
    $allInvNumbers = [];
    foreach ($invNumbersByDm as $id => $nums) { foreach ($nums as $n) { $allInvNumbers[$n][] = $id; } }
    if (!empty($allInvNumbers)) {
        $placeholders2 = implode(',', array_fill(0, count($allInvNumbers), '?'));
        $types2 = str_repeat('s', count($allInvNumbers));
        $invNums = array_keys($allInvNumbers);
        $stmt2 = $db_conn->prepare(
            "SELECT invnumber, returnid, MAX(total) AS total
             FROM user_return_stock
             WHERE invnumber IN ($placeholders2) AND deleted_at IS NULL
             GROUP BY invnumber, returnid"
        );
        $stmt2->bind_param($types2, ...$invNums);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
            $dmIdsForInv = $allInvNumbers[$row['invnumber']] ?? [];
            foreach ($dmIdsForInv as $dmId) {
                $lastMonthReturnedAmt[$dmId] += (float) $row['total'];
            }
        }
        $stmt2->close();
    }
}

ob_end_clean();

// ---- Group DMs by ASM ----------------------------------------------------
$rowsByAsm = [];
$asmOrder = [];
foreach ($dmIds as $id) {
    $asmName = resolveAsmFor($id, $staffById);
    if (!isset($rowsByAsm[$asmName])) { $rowsByAsm[$asmName] = []; $asmOrder[] = $asmName; }
    $rowsByAsm[$asmName][] = [
        'dm_name'            => $staffById[$id]['name'],
        'get_orders'         => $getOrderCount[$id],
        'no_orders'          => $noOrderCount[$id],
        'total_orders'       => $getOrderCount[$id] + $noOrderCount[$id],
        'converted'          => $convertedThisMonth[$id],
        'lm_converted_amt'   => $lastMonthConvertedAmt[$id],
        'lm_returned_amt'    => $lastMonthReturnedAmt[$id],
    ];
}
sort($asmOrder, SORT_STRING | SORT_FLAG_CASE);
$asmOrder = array_values(array_filter($asmOrder, fn($k) => $k !== 'Unassigned'));
$asmOrder[] = 'Unassigned';
$asmOrder = array_values(array_filter($asmOrder, fn($k) => isset($rowsByAsm[$k]) && !empty($rowsByAsm[$k])));

function writeOrderSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $heading, array $rows, string $thisMonthLabel, string $lastMonthLabel): void {
    $sheet->setCellValue('A1', $heading);

    $headerRow = 3;
    $columns = [
        'District Manager',
        "Get Orders ({$thisMonthLabel})", "No Orders ({$thisMonthLabel})", "Total Orders ({$thisMonthLabel})",
        "Get Orders Converted ({$thisMonthLabel})",
        "Converted Amount ({$lastMonthLabel})", "Returned Amount ({$lastMonthLabel})",
    ];
    $col = 1;
    foreach ($columns as $c) { xlsx_set($sheet, $col, $headerRow, $c); $col++; }
    $lastCol = $col - 1;
    $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex($lastCol) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

    $headerRange = Coordinate::stringFromColumnIndex(1) . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    $row = $headerRow + 1;
    foreach ($rows as $r) {
        xlsx_set($sheet, 1, $row, $r['dm_name']);
        xlsx_set($sheet, 2, $row, $r['get_orders']);
        xlsx_set($sheet, 3, $row, $r['no_orders']);
        xlsx_set($sheet, 4, $row, $r['total_orders']);
        xlsx_set($sheet, 5, $row, $r['converted']);
        xlsx_set($sheet, 6, $row, $r['lm_converted_amt']);
        xlsx_set($sheet, 7, $row, $r['lm_returned_amt']);
        $sheet->getStyle('F' . $row . ':G' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE'); // Get Orders - green
        $sheet->getStyle('C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE'); // No Orders - red
        $sheet->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Converted - blue
        $sheet->getStyle('G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEB9C'); // Returned - amber
        $sheet->getStyle('A' . $row . ':' . Coordinate::stringFromColumnIndex($lastCol) . $row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $row++;
    }

    if ($row > $headerRow + 1) {
        $dataRange = 'A' . ($headerRow + 1) . ':' . Coordinate::stringFromColumnIndex($lastCol) . ($row - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
    foreach (range(1, $lastCol) as $ci) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
    }
    $sheet->freezePane('A' . ($headerRow + 1));
}

try {
    $today = date('Y-m-d');
    $thisMonthLabel = date('M Y');
    $lastMonthLabel = date('M Y', strtotime('first day of last month'));

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    $usedSheetNames = [];
    foreach ($asmOrder as $asmName) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(excelSafeSheetName($asmName, $usedSheetNames));
        $heading = ($asmName === 'Unassigned' ? 'DM Order Performance (No ASM Assigned)' : 'DM Order Performance — ASM: ' . $asmName)
            . ' (as of ' . $today . ')';
        writeOrderSheet($sheet, $heading, $rowsByAsm[$asmName], $thisMonthLabel, $lastMonthLabel);
    }
    if ($spreadsheet->getSheetCount() === 0) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('No Data');
        $sheet->setCellValue('A1', 'No District Managers found.');
    }
    $spreadsheet->setActiveSheetIndex(0);

    $filename = "ASM_DM_Order_Performance_{$today}.xlsx";
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
