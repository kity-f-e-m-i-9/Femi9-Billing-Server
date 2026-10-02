<?php
// ASM -> District Manager order performance -- one sheet per ASM, one row
// per DM under them.
//
// Data source: Total/Get/No Order counts come from ms_orders (every visit
// the DM themself logged) -- same table marketing/manage_order_product.php
// itself reads for its own "Get Order / No Order" card, so the two stay
// consistent. Conversion tracking (which Get Orders turned into a real
// invoice) instead needs tp_orders: a DM's Get Order only gets a tp_orders
// row once it's actually forwarded to a Territory Partner (tagged
// assigned_by_ms_id = that DM -- see territory-partner/manage-orders.php's
// "From DM: <name>" badge, which reads this exact column), and
// tp_orders.invoiced_inv_id (once set) is a real join key into
// user_invoice.inv_id. A "No Order" visit has nothing to forward, so it
// never gets a tp_orders row at all -- sourcing Get/No Order counts from
// tp_orders (an earlier version of this file did) silently produced 0 for
// every single DM's No Order Count, and undercounted Get Orders too (only
// ones already forwarded showed up).
//
// Primary period: 01-09-2026 to 30-09-2026 (September) -- Total/Get/No
// Order counts, how many of that month's Get Orders are already converted
// to an invoice, and the live (non-deleted/non-voided) invoiced amount
// for those.
//
// Returns/Deletions window: 01-08-2026 to 30-09-2026 (August + September)
// -- wider on purpose, since a Get Order placed in August can convert to
// an invoice later in September; this catches that invoice's returns/
// deletion regardless of which of the two months it actually converted
// in. Returned Amount and Deleted Invoice Amount are reported as two
// separate columns (a deleted/voided invoice is excluded from the
// converted total, but its own amount is surfaced here instead of just
// silently dropped).
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

// Fixed per request -- not derived from today's date.
$periodStart        = '2026-09-01';
$periodEnd          = '2026-09-30';
$returnsWindowStart = '2026-08-01';
$returnsWindowEnd   = '2026-09-30';

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

// ---- September Get/No orders per DM -------------------------------------
// Counts come from ms_orders (every DM visit the DM themself logged,
// get-order or no-order) -- NOT tp_orders, which only ever gets a row once
// a Get Order is actually forwarded to a TP. A "No Order" visit has nothing
// to forward, so it never gets a tp_orders row at all -- sourcing No Order
// Count from tp_orders silently produced 0 for every DM (caught by
// comparing against marketing/manage_order_product.php's own "Get Order /
// No Order" card, which reads ms_orders directly and showed real values).
// invoiced_inv_id (for the conversion step below) is then looked up via the
// same ms_orders -> tp_orders join manage_order_product.php itself uses.
$dmIdListSql = implode(',', $dmIds);
$getOrderCount    = array_fill_keys($dmIds, 0);
$noOrderCount     = array_fill_keys($dmIds, 0);
$getOrderInvIdsByDm = array_fill_keys($dmIds, []); // invoiced_inv_id values, one per distinct order

if (!empty($dmIds)) {
    $ordRes = $db_conn->query(
        "SELECT ms_id, new_order, COUNT(DISTINCT order_id) c
         FROM ms_orders
         WHERE ms_id IN ($dmIdListSql) AND order_date BETWEEN '$periodStart' AND '$periodEnd'
         GROUP BY ms_id, new_order"
    );
    while ($row = $ordRes->fetch_assoc()) {
        $id = (int) $row['ms_id'];
        if (!isset($getOrderCount[$id])) continue; // not a DM -- skip
        if ($row['new_order'] === 'yes') { $getOrderCount[$id] = (int) $row['c']; }
        else { $noOrderCount[$id] = (int) $row['c']; }
    }

    $invRes = $db_conn->query(
        "SELECT DISTINCT o.ms_id, t.invoiced_inv_id
         FROM ms_orders o JOIN tp_orders t ON t.order_id = o.order_id
         WHERE o.ms_id IN ($dmIdListSql) AND o.new_order = 'yes'
           AND o.order_date BETWEEN '$periodStart' AND '$periodEnd'
           AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''"
    );
    while ($row = $invRes->fetch_assoc()) {
        $id = (int) $row['ms_id'];
        if (!isset($getOrderInvIdsByDm[$id])) continue;
        $getOrderInvIdsByDm[$id][] = $row['invoiced_inv_id'];
    }
}

// ---- September Get Order Value (estimated) ------------------------------
// qty * outlet_price * (1 - discount%) per line, same formula marketing/
// manage_order_product.php's own "Get Order Value" card uses -- what the DM
// asked for, regardless of whether it's been forwarded/invoiced yet.
$getOrderValue = array_fill_keys($dmIds, 0.0);
if (!empty($dmIds)) {
    $productPriceMap = [];
    $resAllProd = $db_conn->query("SELECT id, outlet_price FROM products");
    while ($pr = $resAllProd->fetch_assoc()) { $productPriceMap[(int) $pr['id']] = (float) $pr['outlet_price']; }

    $valRes = $db_conn->query(
        "SELECT ms_id, pr_id, qty, discount_percentage
         FROM ms_orders
         WHERE ms_id IN ($dmIdListSql) AND new_order = 'yes'
           AND order_date BETWEEN '$periodStart' AND '$periodEnd'"
    );
    while ($row = $valRes->fetch_assoc()) {
        $id = (int) $row['ms_id'];
        if (!isset($getOrderValue[$id])) continue;
        $price = $productPriceMap[(int) $row['pr_id']] ?? 0;
        $discount = (float) ($row['discount_percentage'] ?? 0);
        $getOrderValue[$id] += (int) $row['qty'] * $price * (1 - $discount / 100);
    }
}

// ---- September Get Orders -> converted count + live invoiced amount ----
$convertedCount = array_fill_keys($dmIds, 0);
$convertedAmt   = array_fill_keys($dmIds, 0.0);

$allSeptInvIds = [];
foreach ($getOrderInvIdsByDm as $id => $invIds) { foreach ($invIds as $invId) { $allSeptInvIds[$invId] = $id; } }
if (!empty($allSeptInvIds)) {
    $placeholders = implode(',', array_fill(0, count($allSeptInvIds), '?'));
    $types = str_repeat('s', count($allSeptInvIds));
    $ids = array_keys($allSeptInvIds);
    $stmt = $db_conn->prepare(
        "SELECT inv_id, total FROM user_invoice
         WHERE inv_id IN ($placeholders) AND deleted_at IS NULL AND voided_at IS NULL"
    );
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dmId = $allSeptInvIds[$row['inv_id']] ?? null;
        if ($dmId === null) continue;
        $convertedCount[$dmId]++;
        $convertedAmt[$dmId] += (float) $row['total'];
    }
    $stmt->close();
}

// ---- Aug+Sep Get Orders -> their invoices -> returns & deletions -------
$returnedAmt = array_fill_keys($dmIds, 0.0);
$deletedAmt  = array_fill_keys($dmIds, 0.0);

$winInvIdsByDm = array_fill_keys($dmIds, []);
if (!empty($dmIds)) {
    $winOrdRes = $db_conn->query(
        "SELECT DISTINCT o.ms_id, t.invoiced_inv_id
         FROM ms_orders o JOIN tp_orders t ON t.order_id = o.order_id
         WHERE o.ms_id IN ($dmIdListSql) AND o.new_order = 'yes'
           AND o.order_date BETWEEN '$returnsWindowStart' AND '$returnsWindowEnd'
           AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''"
    );
    while ($row = $winOrdRes->fetch_assoc()) {
        $id = (int) $row['ms_id'];
        if (isset($winInvIdsByDm[$id])) { $winInvIdsByDm[$id][] = $row['invoiced_inv_id']; }
    }
}
$allWinInvIds = [];
foreach ($winInvIdsByDm as $id => $invIds) { foreach ($invIds as $invId) { $allWinInvIds[$invId] = $id; } }

if (!empty($allWinInvIds)) {
    $placeholders = implode(',', array_fill(0, count($allWinInvIds), '?'));
    $types = str_repeat('s', count($allWinInvIds));
    $ids = array_keys($allWinInvIds);

    // Every invoice converted from this window's Get Orders, live or not --
    // deleted/voided ones go straight into Deleted Invoice Amount instead
    // of Returned Amount's lookup.
    $stmt = $db_conn->prepare(
        "SELECT inv_id, total, deleted_at, voided_at FROM user_invoice
         WHERE inv_id IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    $liveInvIdsByDm = array_fill_keys($dmIds, []);
    while ($row = $res->fetch_assoc()) {
        $dmId = $allWinInvIds[$row['inv_id']] ?? null;
        if ($dmId === null) continue;
        if ($row['deleted_at'] !== null || $row['voided_at'] !== null) {
            $deletedAmt[$dmId] += (float) $row['total'];
        } else {
            $liveInvIdsByDm[$dmId][] = $row['inv_id'];
        }
    }
    $stmt->close();

    // Returns against the still-live invoices -- user_return_stock.invnumber
    // is confirmed (against live data) to actually hold user_invoice.inv_id,
    // not inv_number despite the column name. Repeats the whole return's
    // total on every line item, so dedupe by returnid before summing (same
    // convention mis-report.php already uses).
    $allLiveInvIds = [];
    foreach ($liveInvIdsByDm as $id => $invIds) { foreach ($invIds as $invId) { $allLiveInvIds[$invId][] = $id; } }
    if (!empty($allLiveInvIds)) {
        $placeholders2 = implode(',', array_fill(0, count($allLiveInvIds), '?'));
        $types2 = str_repeat('s', count($allLiveInvIds));
        $invIds2 = array_keys($allLiveInvIds);
        $stmt2 = $db_conn->prepare(
            "SELECT invnumber, returnid, MAX(total) AS total
             FROM user_return_stock
             WHERE invnumber IN ($placeholders2) AND deleted_at IS NULL
             GROUP BY invnumber, returnid"
        );
        $stmt2->bind_param($types2, ...$invIds2);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
            $dmIdsForInv = $allLiveInvIds[$row['invnumber']] ?? [];
            foreach ($dmIdsForInv as $dmId) {
                $returnedAmt[$dmId] += (float) $row['total'];
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
        'dm_name'         => $staffById[$id]['name'],
        'total_orders'    => $getOrderCount[$id] + $noOrderCount[$id],
        'get_orders'      => $getOrderCount[$id],
        'get_order_value' => $getOrderValue[$id],
        'no_orders'       => $noOrderCount[$id],
        'converted_cnt'   => $convertedCount[$id],
        'converted_amt'   => $convertedAmt[$id],
        'returned_amt'    => $returnedAmt[$id],
        'deleted_amt'     => $deletedAmt[$id],
    ];
}
sort($asmOrder, SORT_STRING | SORT_FLAG_CASE);
$asmOrder = array_values(array_filter($asmOrder, fn($k) => $k !== 'Unassigned'));
$asmOrder[] = 'Unassigned';
$asmOrder = array_values(array_filter($asmOrder, fn($k) => isset($rowsByAsm[$k]) && !empty($rowsByAsm[$k])));

function writeOrderSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $heading, array $rows): void {
    $sheet->setCellValue('A1', $heading);

    $headerRow = 3;
    $columns = [
        'District Manager',
        'Total Order Count', 'Get Order Count', 'Get Order Value (Est.)', 'No Order Count',
        'Get Orders Converted to Invoice', 'Converted Amount',
        'Returned Amount', 'Deleted Invoice Amount',
    ];
    $col = 1;
    foreach ($columns as $c) { xlsx_set($sheet, $col, $headerRow, $c); $col++; }
    $lastCol = $col - 1;
    $sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex($lastCol) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

    $headerRange = Coordinate::stringFromColumnIndex(1) . $headerRow . ':' . Coordinate::stringFromColumnIndex($lastCol) . $headerRow;
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    $row = $headerRow + 1;
    foreach ($rows as $r) {
        xlsx_set($sheet, 1, $row, $r['dm_name']);
        xlsx_set($sheet, 2, $row, $r['total_orders']);
        xlsx_set($sheet, 3, $row, $r['get_orders']);
        xlsx_set($sheet, 4, $row, $r['get_order_value']);
        xlsx_set($sheet, 5, $row, $r['no_orders']);
        xlsx_set($sheet, 6, $row, $r['converted_cnt']);
        xlsx_set($sheet, 7, $row, $r['converted_amt']);
        xlsx_set($sheet, 8, $row, $r['returned_amt']);
        xlsx_set($sheet, 9, $row, $r['deleted_amt']);
        $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('G' . $row . ':I' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9'); // Total - grey
        $sheet->getStyle('C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE'); // Get Order - green
        $sheet->getStyle('D' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Get Order Value - blue
        $sheet->getStyle('E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE'); // No Order - red
        $sheet->getStyle('F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Converted count - blue
        $sheet->getStyle('H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEB9C'); // Returned - amber
        $sheet->getStyle('I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F4CCCC'); // Deleted - dusty red
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

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    $usedSheetNames = [];
    foreach ($asmOrder as $asmName) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(excelSafeSheetName($asmName, $usedSheetNames));
        $heading = ($asmName === 'Unassigned' ? 'DM Order Performance (No ASM Assigned)' : 'DM Order Performance — ASM: ' . $asmName)
            . ' | Orders: 01-09-2026 to 30-09-2026 | Returns/Deletions window: 01-08-2026 to 30-09-2026';
        writeOrderSheet($sheet, $heading, $rowsByAsm[$asmName]);
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
