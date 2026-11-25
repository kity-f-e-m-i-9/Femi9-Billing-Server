<?php
// TP Firka→District coverage report (company-only) — 2026-12-01, user-requested
// follow-up to the multi-district TP work: a simple Firka-count-per-TP view
// is misleading, since a TP can hold several Firkas that all sit under the
// SAME district (normal) vs several Firkas spread across 2+ DIFFERENT
// districts (the case that needs extra attention — see
// company/include/MultiDistrictTpMismatch.php for why). This report lists
// every active TP split into those two buckets.
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

require_once("checksession.php");
require_once("config.php");

$autoloadPath = '../../vendor/autoload.php';
if (!file_exists($autoloadPath)) { die("Composer autoload not found: " . $autoloadPath); }
require_once $autoloadPath;
require_once __DIR__ . '/../shared/SafeSpreadsheetBinder.php';
\PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new FemiSafeValueBinder());

// Every active TP's Firka assignments, each resolved up to its owning
// District (same partner_location_nodes depth chain
// MultiDistrictTpMismatch.php's getMultiDistrictTps() uses: Firka -> Division
// -> Division-group -> District).
function getTpFirkaDistrictRows($db_conn): array {
    $res = $db_conn->query("
        SELECT tpl.territory_partner_id AS tp_id,
               f.id AS firka_id, f.name AS firka_name,
               d.id AS district_id, d.name AS district_name
        FROM territory_partner_locations tpl
        INNER JOIN partner_location_nodes f  ON f.id  = tpl.location_id
        INNER JOIN partner_location_nodes t  ON t.id  = f.parent_id
        INNER JOIN partner_location_nodes dv ON dv.id = t.parent_id
        INNER JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        INNER JOIN territory_partners tp ON tp.id = tpl.territory_partner_id AND tp.deleted_at IS NULL
        ORDER BY tp.tp_id ASC, d.name ASC, f.name ASC
    ");
    $byTp = [];
    while ($r = $res->fetch_assoc()) {
        $tpId = (int)$r['tp_id'];
        if (!isset($byTp[$tpId])) { $byTp[$tpId] = ['firkas' => [], 'districts' => []]; }
        $byTp[$tpId]['firkas'][] = ['firka_id' => (int)$r['firka_id'], 'firka_name' => $r['firka_name'], 'district_id' => (int)$r['district_id'], 'district_name' => $r['district_name']];
        $byTp[$tpId]['districts'][(int)$r['district_id']] = $r['district_name'];
    }
    return $byTp;
}

$tpDetails = [];
$res = $db_conn->query("SELECT id, tp_id, name, mobile FROM territory_partners WHERE deleted_at IS NULL");
while ($r = $res->fetch_assoc()) { $tpDetails[(int)$r['id']] = $r; }

$byTp = getTpFirkaDistrictRows($db_conn);

$singleRows = [];
$multiRows = [];
foreach ($byTp as $tpId => $info) {
    $tp = $tpDetails[$tpId] ?? null;
    if (!$tp) { continue; }
    $firkaNames = array_column($info['firkas'], 'firka_name');
    $firkaCount = count($info['firkas']);
    $districtCount = count($info['districts']);

    if ($districtCount <= 1) {
        $singleRows[] = [
            $tp['tp_id'], $tp['name'], $tp['mobile'],
            $firkaCount, implode(', ', $firkaNames),
            $districtCount === 1 ? implode('', array_values($info['districts'])) : '-',
        ];
    } else {
        // Group the Firka names under each of this TP's districts for readability.
        $byDistrictFirkas = [];
        foreach ($info['firkas'] as $f) {
            $byDistrictFirkas[$f['district_name']][] = $f['firka_name'];
        }
        $districtBreakdown = [];
        foreach ($byDistrictFirkas as $distName => $firkas) {
            $districtBreakdown[] = $distName . ' (' . implode(', ', $firkas) . ')';
        }
        $multiRows[] = [
            $tp['tp_id'], $tp['name'], $tp['mobile'],
            $firkaCount, $districtCount,
            implode(', ', array_values($info['districts'])),
            implode(' | ', $districtBreakdown),
        ];
    }
}

usort($singleRows, fn($a, $b) => strcmp((string)$a[0], (string)$b[0]));
usort($multiRows, fn($a, $b) => $b[4] <=> $a[4]);

function writeTpCoverageSheet($sheet, string $title, array $headers, array $rows, string $HEADER_FILL) {
    $sheet->setTitle($title);
    foreach ($headers as $i => $label) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue("{$col}1", $label);
    }
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $HEADER_FILL]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(26);

    $r = 2;
    foreach ($rows as $row) {
        foreach ($row as $i => $val) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$r}", $val);
        }
        $r++;
    }
    $lastRow = $r - 1;
    if ($lastRow >= 1) { $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}"); }
    $sheet->freezePane('A2');
    foreach (range('A', $lastCol) as $col) { $sheet->getColumnDimension($col)->setWidth(24); }
}

$spreadsheet = new Spreadsheet();
$HEADER_FILL = '2563EB';

$sheet1 = $spreadsheet->getActiveSheet();
writeTpCoverageSheet(
    $sheet1,
    'Single District TPs',
    ['TP Code', 'TP Name', 'Mobile', 'Firka Count', 'Firka Names', 'District'],
    $singleRows,
    $HEADER_FILL
);

$sheet2 = $spreadsheet->createSheet();
writeTpCoverageSheet(
    $sheet2,
    'Multi District TPs',
    ['TP Code', 'TP Name', 'Mobile', 'Firka Count', 'District Count', 'Districts', 'District-wise Firkas'],
    $multiRows,
    $HEADER_FILL
);

$spreadsheet->setActiveSheetIndex(0);

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="tp-district-coverage-' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new XlsxWriter($spreadsheet);
$writer->save('php://output');
exit;
