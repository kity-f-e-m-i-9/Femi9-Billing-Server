<?php
/**
 * Excel Export for Company (LLP) MIS Report
 *
 * Reuses mis-report.php's own calculation logic (via include, output
 * discarded) so every figure in this workbook is guaranteed to match the
 * on-screen report exactly, including every reconciliation fix already
 * applied there (TP discount netting, full return netting, etc.) — no
 * duplicated query logic to drift out of sync over time.
 *
 * Sheet 1: Amount view — Sales/Return/Turnover (overall + Napkin/Diaper
 *   split), Channel Breakdown split by Napkin/Diaper per channel (OT
 *   further split into its own sub-channels — Amazon, Flipkart, Website,
 *   etc.), and Gross Profit with a full calculation breakdown (Sold Value,
 *   Cost of Goods, GST backed out, Output GST) for Napkin, Diaper, and
 *   Combined, plus Expense and Net Profit.
 * Sheet 2: Quantity view — same structure, in units instead of ₹.
 */

declare(strict_types=1);

ob_start();
error_reporting(0);
ini_set('display_errors', '0');
ini_set('memory_limit', '256M');
set_time_limit(300);

// ── Reuse mis-report.php's own calculation logic ────────────────────────────
// Everything up to its first HTML output (its own <!DOCTYPE html> — see
// $is_neksomo_view guard) is pure PHP: session/permission check, date-range
// resolution from $_GET, and every $variable this export reads below. LLP
// logins never hit the neksomo branch, so mis-report.php never emits HTML or
// exits before returning control here.
chdir(__DIR__);
require_once __DIR__ . '/mis-report.php';
ob_end_clean();

if ($is_neksomo_view) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "This export is for the company (LLP) MIS report only.";
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

// ── Palette (matches the report's own KPI card colors) ──────────────────────
const CLR_TITLE_BG   = '1F3B57'; // dark navy
const CLR_SUB_BG     = 'E8F0FE'; // pale blue
const CLR_SECTION_BG = '2A5C8A'; // section header blue
const CLR_HEADER_BG  = '4472C4'; // table header blue
const CLR_TOTAL_BG   = '2E7D32'; // green (turnover / totals)
const CLR_RETURN_BG  = 'C62828'; // red (returns)
const CLR_GP_BG      = '6A1B9a'; // purple (gross profit)
const CLR_ALT_ROW    = 'F5F7FA';
const CLR_WHITE      = 'FFFFFF';
const CLR_TEXT       = '212529';
const CLR_MUTED      = '6C757D';

/** Write to a cell using 1-based column index (A=1, B=2, …). String values
 * are written as explicit TYPE_STRING2, not TYPE_STRING (Excel's normal
 * default) — several row labels in this file legitimately start with
 * "= " or "(+) "/"(−) " (e.g. "= Net Profit"), and setCellValue()'s
 * auto-detection reads a leading "=" as a formula, producing #NAME? in the
 * cell instead of the label text. TYPE_STRING2 skips formula detection
 * entirely, so this is safe for every string this file ever writes. */
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
/** Merge + style a full-width title/section banner row spanning $cols columns. */
function banner(Worksheet $sheet, int $row, int $cols, string $text, string $bg, int $size = 13, string $fg = CLR_WHITE, int $height = 26): void {
    $sheet->setCellValueExplicit('A' . $row, $text, DataType::TYPE_STRING2);
    $sheet->mergeCells(xrange($sheet, 1, $row, $cols, $row));
    $style = $sheet->getStyle('A' . $row);
    $style->getFont()->setBold(true)->setSize($size)->getColor()->setRGB($fg);
    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
    $sheet->getRowDimension($row)->setRowHeight($height);
}
/** Style a header row (column captions) across $cols columns. */
function headerRow(Worksheet $sheet, int $row, int $cols, string $bg = CLR_HEADER_BG): void {
    $range = xrange($sheet, 1, $row, $cols, $row);
    $style = $sheet->getStyle($range);
    $style->getFont()->setBold(true)->setSize(10.5)->getColor()->setRGB(CLR_WHITE);
    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
    $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('FFFFFF');
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet->getRowDimension($row)->setRowHeight(22);
}
/** Style a plain data row, optional zebra striping and bold-first-col label row. */
function dataRow(Worksheet $sheet, int $row, int $cols, bool $zebra = false, bool $boldLabel = true): void {
    $range = xrange($sheet, 1, $row, $cols, $row);
    $style = $sheet->getStyle($range);
    $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DDDDDD');
    if ($zebra) $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(CLR_ALT_ROW);
    if ($boldLabel) $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getRowDimension($row)->setRowHeight(18);
}
/** Style a totals/emphasis row. */
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
function pctFmt(Worksheet $sheet, string $cell): void {
    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('0.0"%"');
}

// ── Re-derive the extra data this export needs that the on-screen report ────
// doesn't already expose as a variable: a per-channel Napkin/Diaper sold+
// returned split (Channel Breakdown itself carries no category dimension —
// it's built from invoice/tp_invoice HEADER totals, not line items), OT
// sales split by its own sub-channel (cat), and a fresh Sold/Cost/GST
// breakdown for Napkin & Diaper Gross Profit (the on-screen LLP cards only
// ever show the single final GP number — see $gross_profit /
// $grand_diaper_gross_profit_llp above).

// 0) Channel Breakdown split by Napkin / Diaper — line-item basis (same
// product population + category-mapping rule as the existing Sales/Return/
// Turnover Napkin/Diaper split above: an unmapped product has no category
// and is folded into Napkin). Each channel is queried from the exact same
// source table + filter mis-report.php's own Channel Breakdown uses for
// that channel (invoice for Customer, user_invoice grouped by to_user_type
// for Shop/SS/Stockist/Distributor/SD, tp_invoice_items for Territory
// Partner, ot_sales/ot_sales_return for OT) — courier is not part of any
// line item, so unlike the whole-channel row, these per-category figures
// are not netted against it (same convention the Napkin/Diaper Sales/
// Return/Turnover section above already has, for the same reason).
function channel_category_split(mysqli $db, string $source, string $utype, string $from, string $to, int $filter_tp, string $tc_ii, string $tc_uii, string $tc_tpi, string $gp_tp_net_line_amt, ?string $to_user_type = null): array {
    $cat_join = "LEFT JOIN neksomo_product_mapping m ON m.company_product_id = p.id
                 LEFT JOIN products np ON np.id = m.neksomo_product_id";
    switch ($source) {
        case 'invoice':
            $sold_sql = "SELECT ii.pr_id, ii.qty, ii.total amt FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
                         WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_ii}";
            $sold_types = 'sss'; $sold_params = [$utype, $from, $to];
            $ret_sql = "SELECT ri.prid pr_id, ri.qty, ri.total amt FROM user_return_stock_items ri
                        WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
                        AND ri.from_usertype='customer'";
            $ret_types = 'sss'; $ret_params = [$utype, $from, $to];
            break;
        case 'user_invoice':
            $sold_sql = "SELECT uii.pr_id, uii.qty, uii.total amt FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
                         WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_uii} AND ui.to_user_type=?";
            $sold_types = 'ssss'; $sold_params = [$utype, $from, $to, $to_user_type];
            $ret_sql = "SELECT ri.prid pr_id, ri.qty, ri.total amt FROM user_return_stock_items ri
                        WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
                        AND ri.from_usertype=?";
            $ret_types = 'ssss'; $ret_params = [$utype, $from, $to, $to_user_type];
            break;
        case 'tp':
            $sold_sql = "SELECT tpii.product_id pr_id, tpii.quantity qty, {$gp_tp_net_line_amt} amt
                         FROM tp_invoice_items tpii JOIN tp_invoices tpi ON tpi.id=tpii.tp_invoice_id
                         WHERE (tpi.created_by_user_type != 'super_stockiest') AND tpi.invoice_date BETWEEN ? AND ?{$tc_tpi}";
            $sold_types = 'ss'; $sold_params = [$from, $to];
            $ret_sql = "SELECT ri.prid pr_id, ri.qty, ri.total amt FROM user_return_stock_items ri
                        WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
                        AND ri.from_usertype='territory_partner'";
            $ret_types = 'sss'; $ret_params = [$utype, $from, $to];
            break;
        case 'ot':
            $sold_sql = "SELECT os.prid pr_id, os.qty, os.total amt FROM ot_sales os WHERE os.date BETWEEN ? AND ?";
            $sold_types = 'ss'; $sold_params = [$from, $to];
            $ret_sql = "SELECT osr.prid pr_id, osr.qty, osr.total amt FROM ot_sales_return osr WHERE osr.return_date BETWEEN ? AND ?";
            $ret_types = 'ss'; $ret_params = [$from, $to];
            break;
        default:
            return ['napkin' => ['sold_amt'=>0,'sold_qty'=>0,'ret_amt'=>0,'ret_qty'=>0], 'diaper' => ['sold_amt'=>0,'sold_qty'=>0,'ret_amt'=>0,'ret_qty'=>0]];
    }
    // Sold and returns are two independently-summed passes (not the same
    // pop-driven-union pattern the main report's return-netting fixes use)
    // — acceptable here since this export section is presentational detail,
    // not a reconciliation target itself, and each pass already sums every
    // row for its own side correctly (no per-product join to miss a row).
    $sold_by_cat = call_rows($db, "SELECT COALESCE(np.category,'') != 'diaper' is_napkin,
            COALESCE(SUM(sold.amt),0) sold_amt, COALESCE(SUM(sold.qty),0) sold_qty
        FROM ({$sold_sql}) sold JOIN products p ON p.id = sold.pr_id {$cat_join} GROUP BY is_napkin",
        $sold_types, $sold_params);
    $ret_by_cat = call_rows($db, "SELECT COALESCE(np.category,'') != 'diaper' is_napkin,
            COALESCE(SUM(ret.amt),0) ret_amt, COALESCE(SUM(ret.qty),0) ret_qty
        FROM ({$ret_sql}) ret JOIN products p ON p.id = ret.pr_id {$cat_join} GROUP BY is_napkin",
        $ret_types, $ret_params);
    $out = ['napkin' => ['sold_amt'=>0.0,'sold_qty'=>0.0,'ret_amt'=>0.0,'ret_qty'=>0.0], 'diaper' => ['sold_amt'=>0.0,'sold_qty'=>0.0,'ret_amt'=>0.0,'ret_qty'=>0.0]];
    foreach ($sold_by_cat as $r) {
        $key = ((int)$r['is_napkin'] === 1) ? 'napkin' : 'diaper';
        $out[$key]['sold_amt'] = (float)$r['sold_amt'];
        $out[$key]['sold_qty'] = (float)$r['sold_qty'];
    }
    foreach ($ret_by_cat as $r) {
        $key = ((int)$r['is_napkin'] === 1) ? 'napkin' : 'diaper';
        $out[$key]['ret_amt'] = (float)$r['ret_amt'];
        $out[$key]['ret_qty'] = (float)$r['ret_qty'];
    }
    return $out;
}

$channel_category_breakdown = [];
if ($scope === 'company') {
    $channel_category_breakdown['company'] = channel_category_split($db_conn, 'invoice', $utype, $from, $to, $filter_tp, $tc_ii, $tc_uii, $tc_tpi, $gp_tp_net_line_amt);
    foreach (['super_stockiest', 'stockiest', 'super_distributor', 'distributor', 'shop'] as $tut) {
        $channel_category_breakdown[$tut] = channel_category_split($db_conn, 'user_invoice', $utype, $from, $to, $filter_tp, $tc_ii, $tc_uii, $tc_tpi, $gp_tp_net_line_amt, $tut);
    }
    $channel_category_breakdown['territory_partner'] = channel_category_split($db_conn, 'tp', $utype, $from, $to, $filter_tp, $tc_ii, $tc_uii, $tc_tpi, $gp_tp_net_line_amt);
    $channel_category_breakdown['ot'] = channel_category_split($db_conn, 'ot', $utype, $from, $to, $filter_tp, $tc_ii, $tc_uii, $tc_tpi, $gp_tp_net_line_amt);
}

// 1) OT channel split by sub-channel (Amazon, Flipkart, Website, etc.)
$ot_by_subchannel = [];
if ($scope === 'company') {
    $ot_sold_sub = call_rows($db_conn,
        "SELECT cat, COUNT(DISTINCT tempid) cnt, COALESCE(SUM(total),0) amt, COALESCE(SUM(qty),0) qty
         FROM ot_sales WHERE `date` BETWEEN ? AND ? GROUP BY cat ORDER BY amt DESC",
        'ss', [$from, $to]);
    $ot_ret_sub = call_rows($db_conn,
        "SELECT os.cat, COALESCE(SUM(osr.total),0) amt, COALESCE(SUM(osr.qty),0) qty
         FROM ot_sales_return osr JOIN ot_sales os ON os.tempid=osr.tempid AND os.prid=osr.prid
         WHERE osr.return_date BETWEEN ? AND ? GROUP BY os.cat",
        'ss', [$from, $to]);
    $ot_ret_map = [];
    foreach ($ot_ret_sub as $r) $ot_ret_map[$r['cat']] = $r;
    foreach ($ot_sold_sub as $r) {
        $cat = $r['cat'];
        $ret = $ot_ret_map[$cat] ?? ['amt' => 0, 'qty' => 0];
        $ot_by_subchannel[] = [
            'name' => $cat, 'cnt' => (int)$r['cnt'],
            'sold_amt' => (float)$r['amt'], 'sold_qty' => (int)$r['qty'],
            'ret_amt' => (float)$ret['amt'], 'ret_qty' => (int)$ret['qty'],
        ];
    }
}

// 2) Napkin / Diaper Gross Profit — full calculation breakdown for LLP scope.
// Same product population, same cost-rate sourcing, same GST de-tax method
// as $gross_profit / $grand_diaper_gross_profit_llp above (see their
// comments) — this just also returns Sold Value, Cost Value, and the GST
// amount backed out of each side as their own totals, instead of only the
// final margin, so the export can show the calculation, not just the result.
// $cost_rate_subq / $category_filter_sql / $params / $types must be the
// EXACT same set mis-report.php uses for that bucket — napkin and diaper use
// genuinely different cost bases ($gp_cost_rate_subq vs
// $gp_diaper_cost_rate_subq, see that variable's own comment) and therefore
// different param counts/bindings ($gp_all_params vs $gp_diaper_all_params),
// not just a different WHERE filter.
function llp_gp_breakdown(mysqli $db, string $cost_rate_subq, string $category_filter_sql, array $params, string $types): array {
    $sql = "
        SELECT
            COALESCE(SUM(sold.qty_sold - COALESCE(ret.qty_returned,0)),0) net_qty,
            COALESCE(SUM((sold.sold_rate / (1+COALESCE(p.gst,0)/100)) * (sold.qty_sold - COALESCE(ret.qty_returned,0))),0) sold_value_extax,
            COALESCE(SUM((sold.sold_rate - sold.sold_rate/(1+COALESCE(p.gst,0)/100)) * (sold.qty_sold - COALESCE(ret.qty_returned,0))),0) output_gst,
            COALESCE(SUM({$cost_rate_subq} * (sold.qty_sold - COALESCE(ret.qty_returned,0))),0) cost_value
        FROM (
            SELECT s.pr_id, SUM(s.qty) qty_sold, SUM(s.line_total)/NULLIF(SUM(s.qty),0) sold_rate
            FROM (
                SELECT ii.pr_id, ii.qty, ii.total AS line_total
                FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
                WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$GLOBALS['tc_ii']}
                UNION ALL
                SELECT uii.pr_id, uii.qty, uii.total AS line_total
                FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
                WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$GLOBALS['tc_uii']}
                {$GLOBALS['gp_ot_sold_union']}
                {$GLOBALS['gp_tp_union']}
            ) s
            GROUP BY s.pr_id
        ) sold
        JOIN products p ON p.id = sold.pr_id
        {$GLOBALS['gp_cat_join']}
        LEFT JOIN (
            SELECT r.pr_id, SUM(r.qty) qty_returned
            FROM (
                SELECT ri.prid pr_id, ri.qty
                FROM user_return_stock_items ri
                WHERE ri.to_usertype=?" . ($GLOBALS['filter_tp'] > 0 ? " AND ri.to_userid={$GLOBALS['filter_tp']}" : "") . " AND ri.date BETWEEN ? AND ?
                {$GLOBALS['gp_ot_ret_union']}
            ) r
            GROUP BY r.pr_id
        ) ret ON ret.pr_id = sold.pr_id
        WHERE {$cost_rate_subq} IS NOT NULL AND {$category_filter_sql}";
    $row = crow($db, $sql, $types, $params);
    return [
        'net_qty'         => (float)($row['net_qty'] ?? 0),
        'sold_value'      => (float)($row['sold_value_extax'] ?? 0),
        'output_gst'      => (float)($row['output_gst'] ?? 0),
        'cost_value'      => (float)($row['cost_value'] ?? 0),
        'gross_profit'    => (float)($row['sold_value_extax'] ?? 0) - (float)($row['cost_value'] ?? 0),
    ];
}

$llp_napkin_gp = ['net_qty'=>0,'sold_value'=>0,'output_gst'=>0,'cost_value'=>0,'gross_profit'=>0];
$llp_diaper_gp = ['net_qty'=>0,'sold_value'=>0,'output_gst'=>0,'cost_value'=>0,'gross_profit'=>0];
if ($scope === 'company' && !$is_neksomo_view) {
    // Napkin: $gp_cost_rate_subq (1 placeholder, effective_date<=?, appearing
    // twice — SELECT + WHERE) with $gp_all_params, same as $grand_gross_profit_llp.
    $llp_napkin_gp = llp_gp_breakdown(
        $db_conn, $gp_cost_rate_subq, "COALESCE(np.category,'') != 'diaper'",
        $gp_all_params, str_repeat('s', count($gp_all_params))
    );
    // Diaper: $gp_diaper_cost_rate_subq (also 1 placeholder, same shape) with
    // $gp_diaper_all_params, same as $grand_diaper_gross_profit_llp — a
    // genuinely different cost basis (neksomo_llp_piece_rates, no
    // pieces_per_pack scaling), not just a different category filter.
    $llp_diaper_gp = llp_gp_breakdown(
        $db_conn, $gp_diaper_cost_rate_subq, "np.category = 'diaper'",
        $gp_diaper_all_params, str_repeat('s', count($gp_diaper_all_params))
    );
}
$llp_combined_gp = [
    'net_qty'      => $llp_napkin_gp['net_qty'] + $llp_diaper_gp['net_qty'],
    'sold_value'   => $llp_napkin_gp['sold_value'] + $llp_diaper_gp['sold_value'],
    'output_gst'   => $llp_napkin_gp['output_gst'] + $llp_diaper_gp['output_gst'],
    'cost_value'   => $llp_napkin_gp['cost_value'] + $llp_diaper_gp['cost_value'],
    'gross_profit' => $llp_napkin_gp['gross_profit'] + $llp_diaper_gp['gross_profit'],
];

// ── Build the workbook ───────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator($business_name ?? 'Femi9')
    ->setTitle('MIS Report Export')
    ->setSubject('Sales / Returns / Turnover / Gross Profit');

$periodLabel = 'Period: ' . date('d M Y', strtotime($from)) . '  to  ' . date('d M Y', strtotime($to));
$scopeLabel  = 'Scope: Income to Company (LLP)' . ($filter_tp > 0 ? ' — TP filtered' : '');

/**
 * Renders the "Sales / Return / Turnover" equation block (Overall + Napkin
 * + Diaper + Combined), Channel Breakdown (with OT split by sub-channel),
 * and Gross Profit with calculation, for one metric basis — amount (₹) or
 * quantity (units). $isQty toggles number formatting and which value keys
 * are read off each figure.
 */
function render_sheet(
    Worksheet $sheet, bool $isQty, string $business_name, string $periodLabel, string $scopeLabel,
    float $gross_revenue, float $total_return_amt, float $total_revenue,
    float $total_units, float $total_return_qty, float $net_units,
    float $grand_napkin_sold_amt_llp, float $grand_napkin_sold_qty_llp,
    float $grand_napkin_return_amt_llp, float $grand_napkin_return_qty_llp,
    float $grand_napkin_turnover_amt_llp, float $grand_napkin_turnover_qty_llp,
    float $grand_diaper_sold_amt_llp, float $grand_diaper_sold_qty_llp,
    float $grand_diaper_return_amt_llp, float $grand_diaper_return_qty_llp,
    float $grand_diaper_turnover_amt_llp, float $grand_diaper_turnover_qty_llp,
    float $grand_combined_sold_amt_llp, float $grand_combined_sold_qty_llp,
    float $grand_combined_return_amt_llp, float $grand_combined_return_qty_llp,
    float $grand_combined_turnover_amt_llp, float $grand_combined_turnover_qty_llp,
    array $channel_labels, array $channel_breakdown, float $channel_total_rev,
    array $ot_by_subchannel, array $channel_category_breakdown,
    array $llp_napkin_gp, array $llp_diaper_gp, array $llp_combined_gp,
    // $_reportNapkinOnlyNetProfit intentionally unused inside — it's the
    // report's own $net_profit (Napkin gross profit − Expense, see
    // mis-report.php), which is NOT the Combined Net Profit this sheet
    // shows; that's re-derived fresh from $llp_combined_gp below instead.
    // Kept as a parameter (rather than dropped from both call sites) so its
    // scope is documented here rather than silently discarded upstream.
    float $total_expenses, ?float $_reportNapkinOnlyNetProfit
): void {
    $COLS = 5; // widest table below uses 5 columns
    $fmt = fn(Worksheet $s, string $cell) => $isQty ? qtyFmt($s, $cell) : moneyFmt($s, $cell);
    $unitWord = $isQty ? 'Qty' : 'Amount';

    $row = 1;
    banner($sheet, $row, $COLS, strtoupper($business_name) . ' — MIS REPORT (' . ($isQty ? 'QUANTITY VIEW' : 'AMOUNT VIEW') . ')', CLR_TITLE_BG, 15, CLR_WHITE, 32);
    $row++;
    banner($sheet, $row, $COLS, $periodLabel . '   |   ' . $scopeLabel, CLR_SUB_BG, 11, CLR_TEXT, 22);
    $row += 2;

    /**
     * Renders one category's full self-contained block: Sales/Return/
     * Turnover, Channel Breakdown (with OT further split into its own
     * sub-channels), and — amount sheet only — Gross Profit with the full
     * calculation. $catKey is 'napkin' or 'diaper', used to pull the right
     * side out of $channel_category_breakdown's per-channel rows.
     */
    $renderCategoryBlock = function (
        string $bannerLabel, string $catKey, string $catBg,
        float $sold_amt, float $sold_qty, float $ret_amt, float $ret_qty, float $turn_amt, float $turn_qty,
        array $gp
    ) use ($sheet, $isQty, $fmt, $unitWord, $COLS, $channel_labels, $channel_breakdown, $ot_by_subchannel, $channel_category_breakdown, $total_expenses, &$row) {
        banner($sheet, $row, $COLS, $bannerLabel, $catBg, 14, CLR_WHITE, 28); $row++;
        $row++;

        // Sales / Return / Turnover
        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, $unitWord);
        headerRow($sheet, $row, 2); $row++;
        $srtRows = [
            ['Sales (gross, pre-return)', $isQty ? $sold_qty : $sold_amt],
            ['Returns', $isQty ? $ret_qty : $ret_amt],
            ['Total Turnover (Sales − Returns)', $isQty ? $turn_qty : $turn_amt],
        ];
        foreach ($srtRows as $i => [$label, $val]) {
            xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $val);
            $fmt($sheet, 'B' . $row);
            dataRow($sheet, $row, 2, $i % 2 === 1);
            $row++;
        }
        $row++;

        // Channel Breakdown for this category — re-derived from each
        // channel's own line items (see channel_category_split()'s own
        // comment), so not netted against courier the way a header total is.
        xset($sheet, 1, $row, 'Channel'); xset($sheet, 2, $row, 'Invoices');
        xset($sheet, 3, $row, $unitWord); xset($sheet, 4, $row, 'Share of ' . $bannerLabel);
        headerRow($sheet, $row, 4); $row++;
        $i = 0;
        $catTotal = ($isQty ? $turn_qty : $turn_amt) ?: 1;
        foreach ($channel_labels as $key => $label) {
            if ($key === 'ot') continue;
            $r = $channel_breakdown[$key] ?? ['cnt' => 0, 'rev' => 0.0];
            $cat = $channel_category_breakdown[$key][$catKey] ?? ['sold_amt'=>0,'sold_qty'=>0,'ret_amt'=>0,'ret_qty'=>0];
            $val = $isQty ? ($cat['sold_qty'] - $cat['ret_qty']) : ($cat['sold_amt'] - $cat['ret_amt']);
            xset($sheet, 1, $row, $label);
            xset($sheet, 2, $row, (int)$r['cnt']);
            xset($sheet, 3, $row, $val); $fmt($sheet, 'C'.$row);
            $pct = $catTotal != 0 ? round($val / $catTotal * 100, 1) : 0;
            xset($sheet, 4, $row, $pct); pctFmt($sheet, 'D'.$row);
            dataRow($sheet, $row, 4, $i % 2 === 1);
            $i++; $row++;
        }
        $ot_row = $channel_breakdown['ot'] ?? ['cnt' => 0, 'rev' => 0.0];
        $ot_cat = $channel_category_breakdown['ot'][$catKey] ?? ['sold_amt'=>0,'sold_qty'=>0,'ret_amt'=>0,'ret_qty'=>0];
        $ot_val = $isQty ? ($ot_cat['sold_qty'] - $ot_cat['ret_qty']) : ($ot_cat['sold_amt'] - $ot_cat['ret_amt']);
        xset($sheet, 1, $row, 'OT Channel (combined)');
        xset($sheet, 2, $row, (int)$ot_row['cnt']);
        xset($sheet, 3, $row, $ot_val); $fmt($sheet, 'C'.$row);
        $pct = $catTotal != 0 ? round($ot_val / $catTotal * 100, 1) : 0;
        xset($sheet, 4, $row, $pct); pctFmt($sheet, 'D'.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setItalic(true);
        dataRow($sheet, $row, 4, $i % 2 === 1);
        $row++;
        // OT sub-channels have no category split of their own (see note
        // below) — shown once, combined, under Napkin only, to avoid
        // implying a real split that doesn't exist.
        if ($catKey === 'napkin') {
            foreach ($ot_by_subchannel as $sub) {
                $net_amt = $sub['sold_amt'] - $sub['ret_amt'];
                $net_qty = $sub['sold_qty'] - $sub['ret_qty'];
                xset($sheet, 1, $row, '   ↳ ' . $sub['name'] . ' (all categories)');
                xset($sheet, 2, $row, $sub['cnt']);
                xset($sheet, 3, $row, $isQty ? $net_qty : $net_amt); $fmt($sheet, 'C'.$row);
                xset($sheet, 4, $row, '—');
                $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->getColor()->setRGB(CLR_MUTED);
                dataRow($sheet, $row, 4, false, false);
                $row++;
            }
        }
        $row++;
        xset($sheet, 1, $row, 'Note: per-channel figures above are re-derived from ' . strtolower($bannerLabel) . ' line items only, so they are not netted against courier charges the way a whole-channel header total is. OT sub-channels (Amazon, Flipkart, Website, etc.) have no per-category split of their own — shown combined, once, under Napkin.');
        $sheet->mergeCells(xrange($sheet, 1, $row, $COLS, $row));
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(CLR_MUTED);
        $row += 2;

        // Returns by Channel — which channel the returns in this category's
        // Returns figure (above) actually came from. Same source/scope as
        // the Channel Breakdown table just above (channel_category_split()),
        // just reading ret_amt/ret_qty instead of netting them against sold.
        xset($sheet, 1, $row, 'Channel'); xset($sheet, 2, $row, 'Return ' . $unitWord); xset($sheet, 3, $row, 'Share of ' . $bannerLabel . ' Returns');
        headerRow($sheet, $row, 3); $row++;
        $i = 0;
        $catRetTotal = ($isQty ? $ret_qty : $ret_amt) ?: 1;
        foreach ($channel_labels as $key => $label) {
            if ($key === 'ot') continue;
            $cat = $channel_category_breakdown[$key][$catKey] ?? ['sold_amt'=>0,'sold_qty'=>0,'ret_amt'=>0,'ret_qty'=>0];
            $val = $isQty ? $cat['ret_qty'] : $cat['ret_amt'];
            if ($val == 0) continue; // skip channels with zero returns this period, for a shorter table
            xset($sheet, 1, $row, $label);
            xset($sheet, 2, $row, $val); $fmt($sheet, 'B'.$row);
            $pct = $catRetTotal != 0 ? round($val / $catRetTotal * 100, 1) : 0;
            xset($sheet, 3, $row, $pct); pctFmt($sheet, 'C'.$row);
            dataRow($sheet, $row, 3, $i % 2 === 1);
            $i++; $row++;
        }
        $ot_cat = $channel_category_breakdown['ot'][$catKey] ?? ['sold_amt'=>0,'sold_qty'=>0,'ret_amt'=>0,'ret_qty'=>0];
        $ot_ret_val = $isQty ? $ot_cat['ret_qty'] : $ot_cat['ret_amt'];
        if ($ot_ret_val != 0) {
            xset($sheet, 1, $row, 'OT Channel');
            xset($sheet, 2, $row, $ot_ret_val); $fmt($sheet, 'B'.$row);
            $pct = $catRetTotal != 0 ? round($ot_ret_val / $catRetTotal * 100, 1) : 0;
            xset($sheet, 3, $row, $pct); pctFmt($sheet, 'C'.$row);
            $sheet->getStyle('A'.$row)->getFont()->setItalic(true);
            dataRow($sheet, $row, 3, $i % 2 === 1);
            $i++; $row++;
        }
        if ($i === 0) {
            xset($sheet, 1, $row, 'No returns this period');
            $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->getColor()->setRGB(CLR_MUTED);
            dataRow($sheet, $row, 3, false, false);
            $row++;
        }
        $row++;

        // Gross Profit with full calculation — amount sheet only
        if (!$isQty) {
            xset($sheet, 1, $row, 'Component'); xset($sheet, 2, $row, $bannerLabel);
            headerRow($sheet, $row, 2); $row++;
            $gpRows = [
                ['Net Qty Sold (sold − returned)', $gp['net_qty'], 'qty'],
                ['Sold Value (ex-GST)', $gp['sold_value'], 'money'],
                ['(+) Output GST (collected on sales)', $gp['output_gst'], 'money'],
                ['Sold Value (incl. GST)', $gp['sold_value'] + $gp['output_gst'], 'money'],
                ['(−) Cost of Goods (ex-GST)', $gp['cost_value'], 'money'],
                ['= Gross Profit (ex-GST)', $gp['gross_profit'], 'money_bold'],
            ];
            foreach ($gpRows as $i => [$label, $val, $type]) {
                xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $val);
                if ($type === 'qty') qtyFmt($sheet, 'B'.$row); else moneyFmt($sheet, 'B'.$row);
                if ($type === 'money_bold') {
                    $sheet->getStyle(xrange($sheet,1,$row,2,$row))->getFont()->setBold(true);
                    $sheet->getStyle(xrange($sheet,1,$row,2,$row))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3E5F5');
                }
                dataRow($sheet, $row, 2, $i % 2 === 1 && $type !== 'money_bold');
                $row++;
            }
            $row++;
            xset($sheet, 1, $row, 'GST is a pass-through tax collected on behalf of the government, not company revenue — Gross Profit above is always computed on the pre-tax (ex-GST) sold value and cost. "Sold Value (incl. GST)" is shown only for reference.');
            $sheet->mergeCells(xrange($sheet, 1, $row, $COLS, $row));
            $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(CLR_MUTED);
            $row += 2;
        }
    };

    // ── NAPKIN — full block ──────────────────────────────────────────────────
    $renderCategoryBlock(
        'NAPKIN', 'napkin', CLR_SECTION_BG,
        $grand_napkin_sold_amt_llp, $grand_napkin_sold_qty_llp,
        $grand_napkin_return_amt_llp, $grand_napkin_return_qty_llp,
        $grand_napkin_turnover_amt_llp, $grand_napkin_turnover_qty_llp,
        $llp_napkin_gp
    );

    // ── DIAPER — full block ──────────────────────────────────────────────────
    $renderCategoryBlock(
        'DIAPER', 'diaper', CLR_GP_BG,
        $grand_diaper_sold_amt_llp, $grand_diaper_sold_qty_llp,
        $grand_diaper_return_amt_llp, $grand_diaper_return_qty_llp,
        $grand_diaper_turnover_amt_llp, $grand_diaper_turnover_qty_llp,
        $llp_diaper_gp
    );

    // ── COMBINED — Overall Sales/Return/Turnover + Net Profit only ──────────
    banner($sheet, $row, $COLS, 'COMBINED (NAPKIN + DIAPER)', CLR_TOTAL_BG, 14, CLR_WHITE, 28); $row++;
    $row++;
    xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, $unitWord);
    headerRow($sheet, $row, 2); $row++;
    $combinedRows = [
        ['Sales (gross, pre-return)', $isQty ? $total_units : $gross_revenue],
        ['Returns', $isQty ? $total_return_qty : $total_return_amt],
        ['Total Turnover (Sales − Returns)', $isQty ? $net_units : $total_revenue],
    ];
    foreach ($combinedRows as $i => [$label, $val]) {
        xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $val);
        $fmt($sheet, 'B' . $row);
        dataRow($sheet, $row, 2, $i % 2 === 1);
        $row++;
    }
    $row++;
    xset($sheet, 1, $row, 'Note: only products mapped to a Neksomo product (Napkin/Diaper Product Mapping) are classified into the NAPKIN/DIAPER blocks above — an unmapped product has no category and is folded into Napkin, though it still counts in the Combined figures here.');
    $sheet->mergeCells(xrange($sheet, 1, $row, $COLS, $row));
    $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(CLR_MUTED);
    $row += 2;

    // Combined Returns by Channel — Napkin + Diaper returns summed per
    // channel, so it's clear which channel returned how much overall (each
    // category block above already shows this split for its own category).
    xset($sheet, 1, $row, 'Channel'); xset($sheet, 2, $row, 'Return ' . $unitWord); xset($sheet, 3, $row, 'Share of Total Returns');
    headerRow($sheet, $row, 3); $row++;
    $i = 0;
    $combRetTotal = ($isQty ? $total_return_qty : $total_return_amt) ?: 1;
    foreach ($channel_labels as $key => $label) {
        if ($key === 'ot') continue;
        $nap = $channel_category_breakdown[$key]['napkin'] ?? ['ret_amt'=>0,'ret_qty'=>0];
        $dia = $channel_category_breakdown[$key]['diaper'] ?? ['ret_amt'=>0,'ret_qty'=>0];
        $val = $isQty ? ($nap['ret_qty'] + $dia['ret_qty']) : ($nap['ret_amt'] + $dia['ret_amt']);
        if ($val == 0) continue;
        xset($sheet, 1, $row, $label);
        xset($sheet, 2, $row, $val); $fmt($sheet, 'B'.$row);
        $pct = $combRetTotal != 0 ? round($val / $combRetTotal * 100, 1) : 0;
        xset($sheet, 3, $row, $pct); pctFmt($sheet, 'C'.$row);
        dataRow($sheet, $row, 3, $i % 2 === 1);
        $i++; $row++;
    }
    $otNap = $channel_category_breakdown['ot']['napkin'] ?? ['ret_amt'=>0,'ret_qty'=>0];
    $otDia = $channel_category_breakdown['ot']['diaper'] ?? ['ret_amt'=>0,'ret_qty'=>0];
    $otVal = $isQty ? ($otNap['ret_qty'] + $otDia['ret_qty']) : ($otNap['ret_amt'] + $otDia['ret_amt']);
    if ($otVal != 0) {
        xset($sheet, 1, $row, 'OT Channel');
        xset($sheet, 2, $row, $otVal); $fmt($sheet, 'B'.$row);
        $pct = $combRetTotal != 0 ? round($otVal / $combRetTotal * 100, 1) : 0;
        xset($sheet, 3, $row, $pct); pctFmt($sheet, 'C'.$row);
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true);
        dataRow($sheet, $row, 3, $i % 2 === 1);
        $i++; $row++;
    }
    if ($i === 0) {
        xset($sheet, 1, $row, 'No returns this period');
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->getColor()->setRGB(CLR_MUTED);
        dataRow($sheet, $row, 3, false, false);
        $row++;
    }
    $row++;

    if (!$isQty) {
        xset($sheet, 1, $row, 'Metric'); xset($sheet, 2, $row, 'Amount');
        headerRow($sheet, $row, 2); $row++;
        // Net Profit here is deliberately re-derived from $llp_combined_gp
        // (Napkin + Diaper), NOT the report's own $net_profit variable —
        // that variable is $gross_profit - $total_expenses, and
        // $gross_profit is the Napkin-only figure (see mis-report.php's own
        // "1b-2. GROSS PROFIT split by Napkin / Diaper" comment) — reusing
        // it here would silently drop Diaper's gross profit from Net Profit
        // whenever Diaper had any.
        $combined_net_profit = $llp_combined_gp['gross_profit'] - $total_expenses;
        $npRows = [
            ['Napkin Gross Profit', $llp_napkin_gp['gross_profit'], false],
            ['(+) Diaper Gross Profit', $llp_diaper_gp['gross_profit'], false],
            ['= Combined Gross Profit', $llp_combined_gp['gross_profit'], false],
            ['(−) Expense (Expense Tracker, this period)', $total_expenses, false],
            ['= Net Profit', $combined_net_profit, true],
        ];
        foreach ($npRows as $i => [$label, $val, $bold]) {
            xset($sheet, 1, $row, $label); xset($sheet, 2, $row, $val); moneyFmt($sheet, 'B'.$row);
            if ($bold) totalRow($sheet, $row, 2, CLR_GP_BG);
            else dataRow($sheet, $row, 2, $i % 2 === 1);
            $row++;
        }
        $row++;
    }

    // ── Column widths & cosmetics ────────────────────────────────────────────
    $sheet->getColumnDimension('A')->setWidth(42);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(18);
    $sheet->getColumnDimension('E')->setWidth(16);
    $sheet->freezePane('A5');
    $sheet->getSheetView()->setZoomScale(100);
    $sheet->setShowGridlines(false);
}

// ── Sheet 1: Amount view ─────────────────────────────────────────────────────
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Amount');
render_sheet(
    $sheet1, false, $business_name ?? 'Femi9', $periodLabel, $scopeLabel,
    $gross_revenue, $total_return_amt, $total_revenue,
    $total_units, $total_return_qty, $net_units,
    $grand_napkin_sold_amt_llp, $grand_napkin_sold_qty_llp,
    $grand_napkin_return_amt_llp, $grand_napkin_return_qty_llp,
    $grand_napkin_turnover_amt_llp, $grand_napkin_turnover_qty_llp,
    $grand_diaper_sold_amt_llp, $grand_diaper_sold_qty_llp,
    $grand_diaper_return_amt_llp, $grand_diaper_return_qty_llp,
    $grand_diaper_turnover_amt_llp, $grand_diaper_turnover_qty_llp,
    $grand_combined_sold_amt_llp, $grand_combined_sold_qty_llp,
    $grand_combined_return_amt_llp, $grand_combined_return_qty_llp,
    $grand_combined_turnover_amt_llp, $grand_combined_turnover_qty_llp,
    $channel_labels, $channel_breakdown, $channel_total_rev,
    $ot_by_subchannel, $channel_category_breakdown,
    $llp_napkin_gp, $llp_diaper_gp, $llp_combined_gp,
    $total_expenses, $net_profit
);

// ── Sheet 2: Quantity view ───────────────────────────────────────────────────
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Quantity');
render_sheet(
    $sheet2, true, $business_name ?? 'Femi9', $periodLabel, $scopeLabel,
    $gross_revenue, $total_return_amt, $total_revenue,
    $total_units, $total_return_qty, $net_units,
    $grand_napkin_sold_amt_llp, $grand_napkin_sold_qty_llp,
    $grand_napkin_return_amt_llp, $grand_napkin_return_qty_llp,
    $grand_napkin_turnover_amt_llp, $grand_napkin_turnover_qty_llp,
    $grand_diaper_sold_amt_llp, $grand_diaper_sold_qty_llp,
    $grand_diaper_return_amt_llp, $grand_diaper_return_qty_llp,
    $grand_diaper_turnover_amt_llp, $grand_diaper_turnover_qty_llp,
    $grand_combined_sold_amt_llp, $grand_combined_sold_qty_llp,
    $grand_combined_return_amt_llp, $grand_combined_return_qty_llp,
    $grand_combined_turnover_amt_llp, $grand_combined_turnover_qty_llp,
    $channel_labels, $channel_breakdown, $channel_total_rev,
    $ot_by_subchannel, $channel_category_breakdown,
    $llp_napkin_gp, $llp_diaper_gp, $llp_combined_gp,
    $total_expenses, $net_profit
);

$spreadsheet->setActiveSheetIndex(0);

// ── Output ────────────────────────────────────────────────────────────────
$filename = 'MIS_Report_' . date('d-M-Y', strtotime($from)) . '_to_' . date('d-M-Y', strtotime($to)) . '.xlsx';
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
