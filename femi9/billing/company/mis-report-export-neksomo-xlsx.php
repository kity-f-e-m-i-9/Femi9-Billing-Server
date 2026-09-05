<?php
/**
 * Excel Export for Neksomo (Pieces Sold Report) MIS View
 *
 * Same concept as mis-report-export-xlsx.php (the LLP/company-scope
 * export): reuses mis-report.php's own calculation logic directly (via
 * include, HTML output discarded), so every figure here matches the
 * on-screen Pieces Sold Report exactly. Structured the same way — full
 * NAPKIN block, full DIAPER block, then COMBINED (Net Profit, GST
 * Payable, partner profit share) — but without a Channel Breakdown /
 * Returns-by-Channel section, since the Neksomo view has no channel
 * dimension at all (it's a single entity-scoped report, not a
 * per-channel one like the LLP view).
 *
 * Sheet 1: Amount view — Sold/Return/Turnover (pack qty + pieces + value
 *   for Napkin, pack qty + value for Diaper), Gross Profit with full
 *   calculation, Diaper's Output/Input/Net GST, Combined GP/Expense/Net
 *   Profit, Overall GST Payable, and the partner profit-share split.
 * Sheet 2: Quantity view — same structure, in units instead of ₹.
 */

declare(strict_types=1);

ob_start();
error_reporting(0);
ini_set('display_errors', '0');
ini_set('memory_limit', '256M');
set_time_limit(300);

// ── Reuse mis-report.php's own calculation logic ────────────────────────────
// A neksomo login's own $is_neksomo_view branch inside mis-report.php
// renders its full HTML page and then calls exit() (see that file's line
// ~2571) — so a plain include/require would terminate this whole request
// right there, before control ever returned here. MIS_REPORT_SUPPRESS_HTML
// is checked by mis-report.php's own neksomo branch (added alongside this
// export) to skip both the HTML output and the exit() when set, since every
// PHP variable this export needs is already computed by that point.
define('MIS_REPORT_SUPPRESS_HTML', true);
chdir(__DIR__);
require_once __DIR__ . '/mis-report.php';
ob_end_clean();

if (!$is_neksomo_view) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "This export is for the Neksomo (Pieces Sold) view only.";
    exit;
}

// ── Load PhpSpreadsheet ──────────────────────────────────────────────────────
try {
    $vendor_paths = [
        __DIR__ . '/../../../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
    ];
    $loaded = false;
    foreach ($vendor_paths as $path) {
        if (file_exists($path)) { require_once $path; $loaded = true; break; }
    }
    if (!$loaded) throw new Exception("Composer autoload not found");
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Excel library not found. Please install PhpSpreadsheet: composer require phpoffice/phpspreadsheet";
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ── Palette (same as the LLP export, for a consistent look) ─────────────────
const CLR_TITLE_BG   = '1F3B57';
const CLR_SUB_BG     = 'E8F0FE';
const CLR_SECTION_BG = '2A5C8A';
const CLR_TOTAL_BG   = '2E7D32';
const CLR_GP_BG      = '6A1B9a';
const CLR_ALT_ROW    = 'F5F7FA';
const CLR_WHITE      = 'FFFFFF';
const CLR_TEXT       = '212529';
const CLR_MUTED      = '6C757D';

/** Write to a cell using 1-based column index (A=1, B=2, …). String values
 * are written as explicit TYPE_STRING2 so a label starting with "=", "+",
 * "-", or "@" (e.g. "= Net Profit") is never misread as a formula. */
function xset(Worksheet $sheet, int $col, int $row, $value): void {
    $cell = Coordinate::stringFromColumnIndex($col) . $row;
    if (is_string($value)) {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING2);
    } else {
        $sheet->setCellValue($cell, $value);
    }
}
function xrange(Worksheet $sheet, int $c1, int $r1, int $c2, int $r2): string {
    return Coordinate::stringFromColumnIndex($c1) . $r1 . ':' . Coordinate::stringFromColumnIndex($c2) . $r2;
}
function banner(Worksheet $sheet, int $row, int $cols, string $text, string $bg, int $size = 13, string $fg = CLR_WHITE, int $height = 26): void {
    $sheet->setCellValueExplicit('A' . $row, $text, DataType::TYPE_STRING2);
    $sheet->mergeCells(xrange($sheet, 1, $row, $cols, $row));
    $style = $sheet->getStyle('A' . $row);
    $style->getFont()->setBold(true)->setSize($size)->getColor()->setRGB($fg);
    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
    $sheet->getRowDimension($row)->setRowHeight($height);
}
function headerRow(Worksheet $sheet, int $row, int $cols, string $bg = CLR_SECTION_BG): void {
    $range = xrange($sheet, 1, $row, $cols, $row);
    $style = $sheet->getStyle($range);
    $style->getFont()->setBold(true)->setSize(10.5)->getColor()->setRGB(CLR_WHITE);
    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
    $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('FFFFFF');
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet->getRowDimension($row)->setRowHeight(22);
}
function dataRow(Worksheet $sheet, int $row, int $cols, bool $zebra = false, bool $boldLabel = true): void {
    $range = xrange($sheet, 1, $row, $cols, $row);
    $style = $sheet->getStyle($range);
    $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DDDDDD');
    if ($zebra) $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(CLR_ALT_ROW);
    if ($boldLabel) $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getRowDimension($row)->setRowHeight(18);
}
function totalRow(Worksheet $sheet, int $row, int $cols, string $bg): void {
    $range = xrange($sheet, 1, $row, $cols, $row);
    $style = $sheet->getStyle($range);
    $style->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(CLR_WHITE);
    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
    $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('FFFFFF');
    $sheet->getRowDimension($row)->setRowHeight(22);
}
function moneyFmt(Worksheet $sheet, string $cell): void {
    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('₹#,##,##0.00;[RED]-₹#,##,##0.00');
}
function qtyFmt(Worksheet $sheet, string $cell): void {
    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##,##0;[RED]-#,##,##0');
}

// ── Build the workbook ───────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator($business_name ?? 'Femi9')
    ->setTitle('Neksomo Pieces Sold Report Export')
    ->setSubject('Sold / Return / Turnover / Gross Profit');

$periodLabel = 'Period: ' . date('d M Y', strtotime($from)) . '  to  ' . date('d M Y', strtotime($to));
$scopeLabel  = 'Scope: ' . ($selected_entity_name ?? 'Neksomo Hygiene Industries');

// Fixed partner split of Combined Net Profit — same as the on-screen report.
$profit_shares = [
    'Anand'            => 0.50,
    'Saravana Shankar' => 0.40,
    'Tamil Selvan'     => 0.10,
];

/**
 * Renders one sheet (Amount or Quantity). $isQty toggles which value keys
 * are read (pack qty vs pieces vs ₹ value) and number formatting.
 */
function render_sheet(
    Worksheet $sheet, bool $isQty, string $business_name, string $periodLabel, string $scopeLabel,
    int $grand_total_pack_qty, int $grand_total_pieces, float $grand_total_value,
    int $grand_total_return_qty, int $grand_total_return_pieces, float $grand_total_return_value,
    int $grand_total_net_qty, int $grand_total_net_pieces,
    float $grand_gross_profit,
    int $grand_diaper_pack_qty, float $grand_diaper_value,
    int $grand_diaper_return_qty, float $grand_diaper_return_value,
    int $grand_diaper_net_qty,
    float $grand_diaper_output_gst, float $grand_diaper_input_gst, float $grand_diaper_net_gst,
    float $grand_diaper_gross_profit,
    float $grand_combined_gross_profit, float $grand_combined_expense, float $grand_combined_net_profit,
    float $grand_combined_net_gst,
    array $profit_shares,
    array $pieces_sold, array $diaper_sold
): void {
    $COLS = 5; // widest table (product-wise, Quantity sheet) uses 5 columns
    $fmt = fn(Worksheet $s, string $cell) => $isQty ? qtyFmt($s, $cell) : moneyFmt($s, $cell);
    $unitWord = $isQty ? 'Qty' : 'Amount';

    $row = 1;
    banner($sheet, $row, $COLS, strtoupper($business_name) . ' — NEKSOMO PIECES SOLD REPORT (' . ($isQty ? 'QUANTITY VIEW' : 'AMOUNT VIEW') . ')', CLR_TITLE_BG, 15, CLR_WHITE, 32);
    $row++;
    banner($sheet, $row, $COLS, $periodLabel . '   |   ' . $scopeLabel, CLR_SUB_BG, 11, CLR_TEXT, 22);
    $row += 2;

    // ── NAPKIN ────────────────────────────────────────────────────────────────
    banner($sheet, $row, $COLS, 'NAPKIN', CLR_SECTION_BG, 14, CLR_WHITE, 28); $row++;
    $row++;
    if ($isQty) {
        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, 'Pack Qty'); xset($sheet, 3, $row, 'Pieces');
        headerRow($sheet, $row, 3); $row++;
        $napkinRows = [
            ['Sold', $grand_total_pack_qty, $grand_total_pieces],
            ['Return', $grand_total_return_qty, $grand_total_return_pieces],
            ['Overall Turnover (Sold − Return)', $grand_total_net_qty, $grand_total_net_pieces],
        ];
        foreach ($napkinRows as $i => [$label, $qty, $pcs]) {
            xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $qty); xset($sheet, 3, $row, $pcs);
            qtyFmt($sheet, 'B'.$row); qtyFmt($sheet, 'C'.$row);
            dataRow($sheet, $row, 3, $i % 2 === 1);
            $row++;
        }
    } else {
        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, 'Amount');
        headerRow($sheet, $row, 2); $row++;
        $napkinRows = [
            ['Sold Value', $grand_total_value],
            ['Return Value', $grand_total_return_value],
            ['Overall Turnover (Sold − Return)', $grand_total_value - $grand_total_return_value],
        ];
        foreach ($napkinRows as $i => [$label, $val]) {
            xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $val); moneyFmt($sheet, 'B'.$row);
            dataRow($sheet, $row, 2, $i % 2 === 1);
            $row++;
        }
    }
    $row++;

    if (!$isQty) {
        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, 'Amount');
        headerRow($sheet, $row, 2); $row++;
        xset($sheet, 1, $row, 'Napkin Gross Profit'); xset($sheet, 2, $row, $grand_gross_profit);
        moneyFmt($sheet, 'B'.$row);
        $sheet->getStyle(xrange($sheet,1,$row,2,$row))->getFont()->setBold(true);
        $sheet->getStyle(xrange($sheet,1,$row,2,$row))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3E5F5');
        dataRow($sheet, $row, 2, false, true);
        $row += 2;
        xset($sheet, 1, $row, 'Gross Profit already nets out Purchase Value against Return Purchase Value on top of the Overall Turnover above (see Product-wise Pieces Sold on the report page for the full per-product detail this export summarizes).');
        $sheet->mergeCells(xrange($sheet, 1, $row, $COLS, $row));
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(CLR_MUTED);
        $row += 2;
    }

    // ── DIAPER ────────────────────────────────────────────────────────────────
    banner($sheet, $row, $COLS, 'DIAPER', CLR_GP_BG, 14, CLR_WHITE, 28); $row++;
    $row++;
    xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, $unitWord);
    headerRow($sheet, $row, 2); $row++;
    $diaperRows = [
        ['Sold', $isQty ? $grand_diaper_pack_qty : $grand_diaper_value],
        ['Return', $isQty ? $grand_diaper_return_qty : $grand_diaper_return_value],
        ['Overall Turnover (Sold − Return)', $isQty ? $grand_diaper_net_qty : ($grand_diaper_value - $grand_diaper_return_value)],
    ];
    foreach ($diaperRows as $i => [$label, $val]) {
        xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $val);
        $fmt($sheet, 'B'.$row);
        dataRow($sheet, $row, 2, $i % 2 === 1);
        $row++;
    }
    $row++;
    xset($sheet, 1, $row, 'Note: Diaper is pack-based and mapped 1:1 to its company SKU — no piece conversion, unlike Napkin above.');
    $sheet->mergeCells(xrange($sheet, 1, $row, $COLS, $row));
    $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(CLR_MUTED);
    $row += 2;

    if (!$isQty) {
        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, 'Amount');
        headerRow($sheet, $row, 2); $row++;
        $diaperGstRows = [
            ['Output GST (Sales)', $grand_diaper_output_gst, false],
            ['(−) Input GST (Purchases)', $grand_diaper_input_gst, false],
            ['= Net GST Payable', $grand_diaper_net_gst, true],
        ];
        foreach ($diaperGstRows as $i => [$label, $val, $bold]) {
            xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $val); moneyFmt($sheet, 'B'.$row);
            if ($bold) { $sheet->getStyle(xrange($sheet,1,$row,2,$row))->getFont()->setBold(true); dataRow($sheet, $row, 2, false, true); }
            else dataRow($sheet, $row, 2, $i % 2 === 1);
            $row++;
        }
        $row++;
        xset($sheet, 1, $row, 'GST shown separately — never included in Diaper Gross Profit below. Net of returns.');
        $sheet->mergeCells(xrange($sheet, 1, $row, $COLS, $row));
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(CLR_MUTED);
        $row += 2;

        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, 'Amount');
        headerRow($sheet, $row, 2); $row++;
        xset($sheet, 1, $row, 'Diaper Gross Profit'); xset($sheet, 2, $row, $grand_diaper_gross_profit);
        moneyFmt($sheet, 'B'.$row);
        $sheet->getStyle(xrange($sheet,1,$row,2,$row))->getFont()->setBold(true);
        $sheet->getStyle(xrange($sheet,1,$row,2,$row))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3E5F5');
        dataRow($sheet, $row, 2, false, true);
        $row += 2;
    }

    // ── COMBINED ─────────────────────────────────────────────────────────────
    banner($sheet, $row, $COLS, 'COMBINED (NAPKIN + DIAPER)', CLR_TOTAL_BG, 14, CLR_WHITE, 28); $row++;
    $row++;

    if (!$isQty) {
        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, 'Amount');
        headerRow($sheet, $row, 2); $row++;
        $npRows = [
            ['Napkin Gross Profit', $grand_gross_profit, false],
            ['(+) Diaper Gross Profit', $grand_diaper_gross_profit, false],
            ['= Combined Gross Profit', $grand_combined_gross_profit, false],
            ['(−) Expense (Neksomo shared pool, this period)', $grand_combined_expense, false],
            ['= Net Profit', $grand_combined_net_profit, true],
        ];
        foreach ($npRows as $i => [$label, $val, $bold]) {
            xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $val); moneyFmt($sheet, 'B'.$row);
            if ($bold) totalRow($sheet, $row, 2, CLR_GP_BG);
            else dataRow($sheet, $row, 2, $i % 2 === 1);
            $row++;
        }
        $row++;
        xset($sheet, 1, $row, 'Expense is Neksomo\'s single shared expense pool, not split between Napkin/Diaper.');
        $sheet->mergeCells(xrange($sheet, 1, $row, $COLS, $row));
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(CLR_MUTED);
        $row += 2;

        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, 'Amount');
        headerRow($sheet, $row, 2); $row++;
        xset($sheet, 1, $row, 'Overall GST Payable'); xset($sheet, 2, $row, $grand_combined_net_gst);
        moneyFmt($sheet, 'B'.$row);
        dataRow($sheet, $row, 2, false, true);
        $row++;
        $row++;
        xset($sheet, 1, $row, 'GST payable is separate from profit — collected on behalf of the government, not earnings. Napkin is always 0% GST, so this is effectively Diaper\'s Net GST.');
        $sheet->mergeCells(xrange($sheet, 1, $row, $COLS, $row));
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(CLR_MUTED);
        $row += 2;

        xset($sheet, 1, $row, 'Partner'); xset($sheet, 2, $row, 'Share %'); xset($sheet, 3, $row, 'Net Profit Share');
        headerRow($sheet, $row, 3); $row++;
        $i = 0;
        foreach ($profit_shares as $name => $pct) {
            xset($sheet, 1, $row, $name);
            xset($sheet, 2, $row, $pct * 100);
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode('0"%"');
            xset($sheet, 3, $row, $grand_combined_net_profit * $pct);
            moneyFmt($sheet, 'C'.$row);
            dataRow($sheet, $row, 3, $i % 2 === 1);
            $i++; $row++;
        }
        $row++;
    } else {
        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, $unitWord);
        headerRow($sheet, $row, 2); $row++;
        $combinedRows = [
            ['Napkin Pack Qty (Sold)', $grand_total_pack_qty],
            ['Napkin Pieces (Sold)', $grand_total_pieces],
            ['Diaper Pack Qty (Sold)', $grand_diaper_pack_qty],
        ];
        foreach ($combinedRows as $i => [$label, $val]) {
            xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $val); qtyFmt($sheet, 'B'.$row);
            dataRow($sheet, $row, 2, $i % 2 === 1);
            $row++;
        }
        $row++;

        // Product-wise Sale / Return — Napkin (pack qty, from $pieces_sold)
        // and Diaper (pack qty, from $diaper_sold) side by side, keyed by
        // product name — the two arrays cover different product sets
        // (Napkin-mapped vs Diaper-mapped), so this merges them into one
        // row per product name rather than assuming they line up by index.
        $prodRows = [];
        foreach ($pieces_sold as $r) {
            $name = $r['productName'];
            if (!isset($prodRows[$name])) $prodRows[$name] = ['nap_sale'=>0,'nap_ret'=>0,'dia_sale'=>0,'dia_ret'=>0];
            $prodRows[$name]['nap_sale'] = (int)$r['total_qty'];
            $prodRows[$name]['nap_ret']  = (int)($r['return_qty'] ?? 0);
        }
        foreach ($diaper_sold as $r) {
            $name = $r['productName'];
            if (!isset($prodRows[$name])) $prodRows[$name] = ['nap_sale'=>0,'nap_ret'=>0,'dia_sale'=>0,'dia_ret'=>0];
            $prodRows[$name]['dia_sale'] = (int)$r['total_qty'];
            $prodRows[$name]['dia_ret']  = (int)($r['return_qty'] ?? 0);
        }
        // Sort by total (napkin+diaper) sale qty, descending.
        uasort($prodRows, fn($a, $b) => ($b['nap_sale']+$b['dia_sale']) <=> ($a['nap_sale']+$a['dia_sale']));

        xset($sheet, 1, $row, 'Product');
        xset($sheet, 2, $row, 'Napkin Sale (Pack Qty)'); xset($sheet, 3, $row, 'Napkin Return (Pack Qty)');
        xset($sheet, 4, $row, 'Diaper Sale (Pack Qty)'); xset($sheet, 5, $row, 'Diaper Return (Pack Qty)');
        headerRow($sheet, $row, 5); $row++;
        $i = 0;
        foreach ($prodRows as $name => $r) {
            if ($r['nap_sale'] == 0 && $r['nap_ret'] == 0 && $r['dia_sale'] == 0 && $r['dia_ret'] == 0) continue;
            xset($sheet, 1, $row, $name);
            xset($sheet, 2, $row, $r['nap_sale']); qtyFmt($sheet, 'B'.$row);
            xset($sheet, 3, $row, $r['nap_ret']);  qtyFmt($sheet, 'C'.$row);
            xset($sheet, 4, $row, $r['dia_sale']); qtyFmt($sheet, 'D'.$row);
            xset($sheet, 5, $row, $r['dia_ret']);  qtyFmt($sheet, 'E'.$row);
            dataRow($sheet, $row, 5, $i % 2 === 1);
            $i++; $row++;
        }
        if ($i === 0) {
            xset($sheet, 1, $row, 'No sales this period');
            $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->getColor()->setRGB(CLR_MUTED);
            dataRow($sheet, $row, 5, false, false);
            $row++;
        }
        $row++;
        xset($sheet, 1, $row, 'Note: Napkin figures here are Pack Qty (see the NAPKIN block above for the Pieces conversion). Diaper is already pack-based with no piece conversion.');
        $sheet->mergeCells(xrange($sheet, 1, $row, $COLS, $row));
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(CLR_MUTED);
        $row += 2;
    }

    // ── Column widths & cosmetics ────────────────────────────────────────────
    $sheet->getColumnDimension('A')->setWidth(46);
    $sheet->getColumnDimension('B')->setWidth(22);
    $sheet->getColumnDimension('C')->setWidth(22);
    $sheet->getColumnDimension('D')->setWidth(22);
    $sheet->getColumnDimension('E')->setWidth(22);
    $sheet->freezePane('A5');
    $sheet->getSheetView()->setZoomScale(100);
    $sheet->setShowGridlines(false);
}

// ── Sheet 1: Amount view ─────────────────────────────────────────────────────
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Amount');
render_sheet(
    $sheet1, false, $business_name ?? 'Femi9', $periodLabel, $scopeLabel,
    $grand_total_pack_qty, $grand_total_pieces, $grand_total_value,
    $grand_total_return_qty, $grand_total_return_pieces, $grand_total_return_value,
    $grand_total_net_qty, $grand_total_net_pieces,
    $grand_gross_profit,
    $grand_diaper_pack_qty, $grand_diaper_value,
    $grand_diaper_return_qty, $grand_diaper_return_value,
    $grand_diaper_net_qty,
    $grand_diaper_output_gst, $grand_diaper_input_gst, $grand_diaper_net_gst,
    $grand_diaper_gross_profit,
    $grand_combined_gross_profit, $grand_combined_expense, $grand_combined_net_profit,
    $grand_combined_net_gst,
    $profit_shares,
    $pieces_sold, $diaper_sold
);

// ── Sheet 2: Quantity view ───────────────────────────────────────────────────
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Quantity');
render_sheet(
    $sheet2, true, $business_name ?? 'Femi9', $periodLabel, $scopeLabel,
    $grand_total_pack_qty, $grand_total_pieces, $grand_total_value,
    $grand_total_return_qty, $grand_total_return_pieces, $grand_total_return_value,
    $grand_total_net_qty, $grand_total_net_pieces,
    $grand_gross_profit,
    $grand_diaper_pack_qty, $grand_diaper_value,
    $grand_diaper_return_qty, $grand_diaper_return_value,
    $grand_diaper_net_qty,
    $grand_diaper_output_gst, $grand_diaper_input_gst, $grand_diaper_net_gst,
    $grand_diaper_gross_profit,
    $grand_combined_gross_profit, $grand_combined_expense, $grand_combined_net_profit,
    $grand_combined_net_gst,
    $profit_shares,
    $pieces_sold, $diaper_sold
);

$spreadsheet->setActiveSheetIndex(0);

// ── Output ────────────────────────────────────────────────────────────────
$filename = 'Neksomo_Pieces_Sold_Report_' . date('d-M-Y', strtotime($from)) . '_to_' . date('d-M-Y', strtotime($to)) . '.xlsx';
$filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');

$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
exit;
