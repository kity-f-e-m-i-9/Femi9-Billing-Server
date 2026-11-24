<?php
// Excel export for "Manage Demo/Free/Damage" (demofree_manage.php) — same
// date range, same usertype scope and same one-row-per-entry (tempid) layout
// as the on-screen table: Company Profile, Category, Date, Remarks, then one
// qty column per product, plus a TOTAL row at the bottom.
declare(strict_types=1);
ini_set('display_errors', '0');
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

require_once __DIR__ . '/../shared/db-helpers.php';
require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('demo_free');
require_once("config.php");
require_once("include/GodownAccess.php");

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found"); }
require_once $autoloadPath;

$from_date = trim((string)($_GET['frdate'] ?? ''));
$to_date   = trim((string)($_GET['todate'] ?? ''));
$re = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($re, $from_date) || !preg_match($re, $to_date)) {
    $to_date   = date("Y-m-d");
    $from_date = date("Y-m-d", strtotime("-2 days", strtotime($to_date)));
}

// Products (column order = id asc, same as the page)
$products = [];
$rp = mysqli_query($db_conn, "SELECT id, productName FROM products ORDER BY id ASC");
while ($p = mysqli_fetch_assoc($rp)) { $products[(int)$p['id']] = (string)$p['productName']; }

// Godown names (same finance filter the page applies)
$godowns = [];
$rg = mysqli_query($db_conn, "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn));
while ($g = mysqli_fetch_assoc($rg)) { $godowns[(int)$g['id']] = (string)$g['gname']; }

// All lines in range, grouped by tempid. The page shows header fields from
// the first line (lowest id) of each tempid.
$res = db_q($db_conn,
    "SELECT * FROM demofreedamage WHERE usertype = ? AND date BETWEEN ? AND ? ORDER BY tempid, id ASC",
    [$Login_user_TYPEvl, $from_date, $to_date]);
$entries = []; // tempid => ['head'=>row, 'qty'=>[pid=>qty]]
while ($r = mysqli_fetch_assoc($res)) {
    $t = $r['tempid'];
    if (!isset($entries[$t])) { $entries[$t] = ['head' => $r, 'qty' => []]; }
    $pid = (int)$r['product_id'];
    if (!isset($entries[$t]['qty'][$pid])) { $entries[$t]['qty'][$pid] = (float)$r['qty']; }
}

$ss = new Spreadsheet();
$sh = $ss->getActiveSheet();
$sh->setTitle('Demo-Free-Damage');

$headers = array_merge(['S.No', 'Company Profile', 'Category', 'Date', 'Remarks'], array_values($products));
foreach ($headers as $i => $h) { $sh->setCellValue([$i + 1, 1], $h); }
$lastCol = Coordinate::stringFromColumnIndex(count($headers));
$sh->getStyle("A1:{$lastCol}1")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
]);

$row = 2; $n = 0; $totals = array_fill_keys(array_keys($products), 0.0);
foreach ($entries as $e) {
    $h = $e['head'];
    $c = 1;
    $sh->setCellValue([$c++, $row], ++$n);
    $sh->setCellValue([$c++, $row], $godowns[(int)$h['userid']] ?? '');
    $sh->setCellValue([$c++, $row], (string)$h['category']);
    $sh->setCellValue([$c++, $row], date("d/M/Y", strtotime((string)$h['date'])));
    $sh->setCellValue([$c++, $row], (string)$h['remarks']);
    foreach ($products as $pid => $_) {
        $q = $e['qty'][$pid] ?? 0;
        $totals[$pid] += $q;
        $sh->setCellValue([$c++, $row], $q);
    }
    $row++;
}
// TOTAL row
$sh->setCellValue([2, $row], 'TOTAL');
$c = 6;
foreach ($products as $pid => $_) { $sh->setCellValue([$c++, $row], $totals[$pid]); }
$sh->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);

foreach (range(1, count($headers)) as $i) {
    $sh->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}
$sh->freezePane('F2');

while (ob_get_level()) { ob_end_clean(); }
$fname = "demo_free_damage_{$from_date}_to_{$to_date}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');
(new XlsxWriter($ss))->save('php://output');
exit;
